<?php

namespace App\Modules\Memory\Http;

use App\Modules\Memory\Application\MomentAccess;
use App\Platform\Audit\Audit;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class MomentController
{
    public function __construct(private readonly MomentAccess $access) {}

    private function visible(Request $request, string $family): Builder
    {
        $people = DB::table('memory_moment_people as p')
            ->join('family_memberships as member', function ($join) {
                $join->on('member.family_id', '=', 'p.family_id')->on('member.id', '=', 'p.membership_id');
            })->join('users as person', 'person.id', '=', 'member.user_id')
            ->where('p.family_id', $family)->whereNull('member.revoked_at')
            ->selectRaw("p.moment_id, json_agg(json_build_object('id', member.id, 'name', person.name) ORDER BY person.name, member.id) as people_json")
            ->groupBy('p.moment_id');

        return $this->access->visible($family, $request->user()->id)
            ->join('users as author', 'author.id', '=', 'memory_moments.owner_id')
            ->leftJoinSub($people, 'people', 'people.moment_id', '=', 'memory_moments.id')
            ->select('memory_moments.*', 'author.name as owner_name', 'people.people_json');
    }

    private function payload(Request $request, object $moment): array
    {
        $data = (array) $moment;
        unset($data['family_id'], $data['people_json']);
        $data['people'] = json_decode($moment->people_json ?? '[]', true);
        $data['can_edit'] = (int) $moment->owner_id === $request->user()->id && $request->attributes->get('membership')->role !== 'limited';

        return $data;
    }

    public function index(Request $request, string $family): JsonResponse
    {
        $filters = $request->validate([
            'q' => 'sometimes|nullable|string|max:120',
            'kind' => ['sometimes', Rule::in(['moment', 'note'])],
            'visibility' => ['sometimes', Rule::in(['private', 'family'])],
            'page' => 'sometimes|integer|min:1|max:10000',
        ]);
        $query = $this->visible($request, $family);
        $search = trim($filters['q'] ?? '');
        if ($search !== '') {
            $pattern = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';
            $query->where(fn (Builder $text) => $text->where('memory_moments.title', 'ilike', $pattern)->orWhere('memory_moments.body', 'ilike', $pattern));
        }
        foreach (['kind', 'visibility'] as $filter) {
            if (isset($filters[$filter])) {
                $query->where('memory_moments.'.$filter, $filters[$filter]);
            }
        }
        $page = (int) ($filters['page'] ?? 1);
        $items = $query->orderByDesc('memory_moments.occurred_on')->orderByDesc('memory_moments.id')->offset(($page - 1) * 12)->limit(13)->get();

        return response()->json([
            'items' => $items->take(12)->map(fn (object $moment): array => $this->payload($request, $moment))->values(),
            'page' => $page, 'has_more' => $items->count() > 12,
        ]);
    }

    public function show(Request $request, string $family, string $moment): JsonResponse
    {
        $item = $this->visible($request, $family)->where('memory_moments.id', $moment)->firstOrFail();

        return response()->json($this->payload($request, $item))->header('ETag', '"'.$item->version.'"');
    }

    private function content(Request $request, string $family): array
    {
        $data = $request->validate([
            'kind' => ['required', Rule::in(['moment', 'note'])],
            'title' => 'required|string|max:160', 'body' => 'nullable|required_if:kind,note|string|max:8000',
            'occurred_on' => 'required|date_format:Y-m-d|after_or_equal:1900-01-01|before_or_equal:2100-12-31',
            'visibility' => ['required', Rule::in(['private', 'family'])],
            'membership_ids' => 'sometimes|array|max:100', 'membership_ids.*' => 'required|uuid|distinct',
            'owner_id' => 'prohibited', 'family_id' => 'prohibited', 'version' => 'prohibited',
        ], [
            'title.required' => 'Completează titlul.', 'body.required_if' => 'Completează textul notei.',
            'occurred_on.date_format' => 'Alege o dată validă.',
        ]);
        $memberships = $data['membership_ids'] ?? [];
        $active = DB::table('family_memberships')->where('family_id', $family)->whereNull('revoked_at')->whereIn('id', $memberships)->count();
        if ($active !== count($memberships)) {
            throw ValidationException::withMessages(['membership_ids' => 'Alege numai membri activi ai acestei familii.']);
        }

        return [
            'kind' => $data['kind'], 'title' => $data['title'], 'body' => $data['body'] ?? null,
            'occurred_on' => $data['occurred_on'], 'visibility' => $data['visibility'], 'membership_ids' => $memberships,
        ];
    }

    private function syncPeople(string $family, string $moment, array $memberships): void
    {
        DB::table('memory_moment_people')->where('family_id', $family)->where('moment_id', $moment)->delete();
        if ($memberships !== []) {
            DB::table('memory_moment_people')->insert(array_map(fn (string $membership): array => [
                'family_id' => $family, 'moment_id' => $moment, 'membership_id' => $membership,
            ], $memberships));
        }
    }

    public function store(Request $request, string $family): JsonResponse
    {
        abort_if($request->attributes->get('membership')->role === 'limited', 403, 'Rolul tău permite doar consultarea amintirilor.');
        $data = $this->content($request, $family);

        return DB::transaction(function () use ($request, $family, $data): JsonResponse {
            $memberships = $data['membership_ids'];
            unset($data['membership_ids']);
            $id = (string) Str::uuid();
            DB::table('memory_moments')->insert($data + [
                'id' => $id, 'family_id' => $family, 'owner_id' => $request->user()->id,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->syncPeople($family, $id, $memberships);
            Audit::record($family, $request->user()->id, 'memory.created', $id);

            return $this->show($request, $family, $id)->setStatusCode(201);
        });
    }

    public function update(Request $request, string $family, string $moment): JsonResponse
    {
        $item = $this->visible($request, $family)->where('memory_moments.id', $moment)->firstOrFail();
        abort_unless((int) $item->owner_id === $request->user()->id && $request->attributes->get('membership')->role !== 'limited', 403, 'Doar autorul poate modifica această amintire.');
        abort_unless($request->header('If-Match'), 428, 'Versiunea amintirii este necesară.');
        abort_unless($request->header('If-Match') === '"'.$item->version.'"', 412, 'Amintirea s-a schimbat. Reîncarc-o înainte de a salva.');
        $data = $this->content($request, $family);

        return DB::transaction(function () use ($request, $family, $moment, $item, $data): JsonResponse {
            $memberships = $data['membership_ids'];
            unset($data['membership_ids']);
            $updated = DB::table('memory_moments')->where('family_id', $family)->where('id', $moment)->where('version', $item->version)
                ->update($data + ['version' => $item->version + 1, 'updated_at' => now()]);
            abort_unless($updated === 1, 412, 'Amintirea s-a schimbat. Reîncarc-o înainte de a salva.');
            $this->syncPeople($family, $moment, $memberships);
            Audit::record($family, $request->user()->id, 'memory.updated', $moment);

            return $this->show($request, $family, $moment);
        });
    }
}
