<?php

namespace App\Modules\Memory\Http;

use App\Modules\Memory\Application\MomentAccess;
use App\Platform\Audit\Audit;
use App\Platform\Contracts\PrivateMedia;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class MomentController
{
    public function __construct(private readonly MomentAccess $access, private readonly PrivateMedia $media) {}

    private function visible(Request $request, string $family): Builder
    {
        $people = DB::table('memory_moment_people as p')
            ->join('family_memberships as member', function ($join) {
                $join->on('member.family_id', '=', 'p.family_id')->on('member.id', '=', 'p.membership_id');
            })->join('users as person', 'person.id', '=', 'member.user_id')
            ->where('p.family_id', $family)->whereNull('member.revoked_at')
            ->selectRaw("p.moment_id, json_agg(json_build_object('id', member.id, 'name', person.name) ORDER BY person.name, member.id) as people_json")
            ->groupBy('p.moment_id');

        $grants = DB::table('memory_moment_grants as g')->join('family_memberships as recipient', function ($join) {
            $join->on('recipient.family_id', '=', 'g.family_id')->on('recipient.id', '=', 'g.membership_id');
        })->where('g.family_id', $family)->whereNull('recipient.revoked_at')
            ->selectRaw('g.moment_id, json_agg(g.membership_id ORDER BY g.membership_id) as grants_json')->groupBy('g.moment_id');

        return $this->access->visible($family, $request->user()->id)
            ->join('users as author', 'author.id', '=', 'memory_moments.owner_id')
            ->leftJoinSub($people, 'people', 'people.moment_id', '=', 'memory_moments.id')
            ->leftJoinSub($grants, 'grants', 'grants.moment_id', '=', 'memory_moments.id')
            ->leftJoin('media_assets as photo', function ($join) {
                $join->on('photo.family_id', '=', 'memory_moments.family_id')->on('photo.id', '=', 'memory_moments.photo_asset_id');
            })->select('memory_moments.*', 'author.name as owner_name', 'people.people_json', 'photo.status as photo_status')
            ->selectRaw('CASE WHEN memory_moments.owner_id = ? THEN grants.grants_json ELSE NULL END as grants_json', [$request->user()->id]);
    }

    private function payload(Request $request, object $moment): array
    {
        $data = (array) $moment;
        unset($data['family_id'], $data['people_json'], $data['grants_json'], $data['photo_asset_id']);
        $data['people'] = json_decode($moment->people_json ?? '[]', true);
        $data['grants'] = json_decode($moment->grants_json ?? '[]', true);
        $data['can_manage'] = (int) $moment->owner_id === $request->user()->id && $request->attributes->get('membership')->role !== 'limited';
        $data['can_edit'] = $data['can_manage'] && ! $moment->archived_at;

        return $data;
    }

    public function index(Request $request, string $family): JsonResponse
    {
        $filters = $request->validate([
            'q' => 'sometimes|nullable|string|max:120',
            'kind' => ['sometimes', Rule::in(['moment', 'note'])],
            'visibility' => ['sometimes', Rule::in(['private', 'family', 'selected'])],
            'archived' => 'sometimes|boolean',
            'page' => 'sometimes|integer|min:1|max:10000',
        ]);
        $query = $this->visible($request, $family);
        ($filters['archived'] ?? false) ? $query->whereNotNull('memory_moments.archived_at') : $query->whereNull('memory_moments.archived_at');
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
            'visibility' => ['required', Rule::in(['private', 'family', 'selected'])],
            'membership_ids' => 'sometimes|array|max:100', 'membership_ids.*' => 'required|uuid|distinct',
            'grant_membership_ids' => 'sometimes|array|max:100', 'grant_membership_ids.*' => 'required|uuid|distinct',
            'photo_action' => ['sometimes', Rule::in(['keep', 'replace', 'remove'])],
            'photo' => 'sometimes|file|max:10240',
            'photo_asset_id' => 'prohibited', 'archived_at' => 'prohibited',
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
        $grants = $data['grant_membership_ids'] ?? [];
        if (($data['visibility'] === 'selected') !== ($grants !== [])) {
            throw ValidationException::withMessages(['grant_membership_ids' => 'Alege persoane numai pentru accesul „Persoane alese”.']);
        }
        $recipients = DB::table('family_memberships')->where('family_id', $family)->whereNull('revoked_at')
            ->where('user_id', '!=', $request->user()->id)->whereIn('id', $grants)->count();
        if ($recipients !== count($grants)) {
            throw ValidationException::withMessages(['grant_membership_ids' => 'Alege numai alți membri activi ai acestei familii.']);
        }
        $photoAction = $data['photo_action'] ?? ($request->hasFile('photo') ? 'replace' : 'keep');
        if (($photoAction === 'replace') !== $request->hasFile('photo')) {
            throw ValidationException::withMessages(['photo' => 'Alege o fotografie numai pentru înlocuire.']);
        }

        return [
            'kind' => $data['kind'], 'title' => $data['title'], 'body' => $data['body'] ?? null,
            'occurred_on' => $data['occurred_on'], 'visibility' => $data['visibility'], 'membership_ids' => $memberships,
            'grant_membership_ids' => $grants, 'photo_action' => $photoAction,
        ];
    }

    private function syncMemberships(string $table, string $family, string $moment, array $memberships): void
    {
        DB::table($table)->where('family_id', $family)->where('moment_id', $moment)->delete();
        if ($memberships !== []) {
            DB::table($table)->insert(array_map(fn (string $membership): array => [
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
            $grants = $data['grant_membership_ids'];
            unset($data['membership_ids'], $data['grant_membership_ids'], $data['photo_action']);
            $asset = $request->hasFile('photo') ? $this->media->receiveImage($family, $request->user()->id, $request->file('photo')) : null;
            $id = (string) Str::uuid();
            DB::table('memory_moments')->insert($data + [
                'id' => $id, 'family_id' => $family, 'owner_id' => $request->user()->id,
                'photo_asset_id' => $asset['id'] ?? null,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->syncMemberships('memory_moment_people', $family, $id, $memberships);
            $this->syncMemberships('memory_moment_grants', $family, $id, $grants);
            Audit::record($family, $request->user()->id, 'memory.created', $id);

            return $this->show($request, $family, $id)->setStatusCode(201);
        });
    }

    public function update(Request $request, string $family, string $moment): JsonResponse
    {
        $item = $this->mutable($request, $family, $moment);
        abort_if($item->archived_at, 409, 'Restaurează amintirea înainte de a o edita.');
        $data = $this->content($request, $family);

        return DB::transaction(function () use ($request, $family, $moment, $item, $data): JsonResponse {
            $memberships = $data['membership_ids'];
            $grants = $data['grant_membership_ids'];
            $assetId = match ($data['photo_action']) {
                'replace' => $this->media->receiveImage($family, $request->user()->id, $request->file('photo'))['id'],
                'remove' => null,
                default => $item->photo_asset_id,
            };
            unset($data['membership_ids'], $data['grant_membership_ids'], $data['photo_action']);
            $updated = DB::table('memory_moments')->where('family_id', $family)->where('id', $moment)->where('version', $item->version)
                ->update($data + ['photo_asset_id' => $assetId, 'version' => $item->version + 1, 'updated_at' => now()]);
            abort_unless($updated === 1, 412, 'Amintirea s-a schimbat. Reîncarc-o înainte de a salva.');
            $this->syncMemberships('memory_moment_people', $family, $moment, $memberships);
            $this->syncMemberships('memory_moment_grants', $family, $moment, $grants);
            DB::table('families')->where('id', $family)->increment('authz_revision');
            Audit::record($family, $request->user()->id, 'memory.updated', $moment);

            return $this->show($request, $family, $moment);
        });
    }

    private function mutable(Request $request, string $family, string $moment): object
    {
        $item = $this->visible($request, $family)->where('memory_moments.id', $moment)->firstOrFail();
        abort_unless((int) $item->owner_id === $request->user()->id && $request->attributes->get('membership')->role !== 'limited', 403, 'Doar autorul poate modifica această amintire.');
        abort_unless($request->header('If-Match'), 428, 'Versiunea amintirii este necesară.');
        abort_unless($request->header('If-Match') === '"'.$item->version.'"', 412, 'Amintirea s-a schimbat. Reîncarc-o înainte de a salva.');

        return $item;
    }

    public function archive(Request $request, string $family, string $moment): JsonResponse
    {
        return $this->changeArchive($request, $family, $moment, false);
    }

    public function restore(Request $request, string $family, string $moment): JsonResponse
    {
        return $this->changeArchive($request, $family, $moment, true);
    }

    private function changeArchive(Request $request, string $family, string $moment, bool $restore): JsonResponse
    {
        $item = $this->mutable($request, $family, $moment);
        abort_if($restore ? ! $item->archived_at : (bool) $item->archived_at, 409, $restore ? 'Amintirea este deja activă.' : 'Amintirea este deja arhivată.');

        return DB::transaction(function () use ($request, $family, $moment, $item, $restore): JsonResponse {
            DB::table('memory_moments')->where('family_id', $family)->where('id', $moment)->update([
                'archived_at' => $restore ? null : now(), 'visibility' => 'private', 'version' => $item->version + 1, 'updated_at' => now(),
            ]);
            $this->syncMemberships('memory_moment_grants', $family, $moment, []);
            DB::table('families')->where('id', $family)->increment('authz_revision');
            Audit::record($family, $request->user()->id, $restore ? 'memory.restored' : 'memory.archived', $moment);

            return $this->show($request, $family, $moment);
        });
    }

    public function photo(Request $request, string $family, string $moment): StreamedResponse
    {
        $item = $this->access->visible($family, $request->user()->id)->where('memory_moments.id', $moment)->firstOrFail();
        abort_unless($item->photo_asset_id, 404);

        return $this->media->image($family, $item->photo_asset_id);
    }

    public function retryPhoto(Request $request, string $family, string $moment): JsonResponse
    {
        $item = $this->mutable($request, $family, $moment);
        abort_if($item->archived_at, 409, 'Restaurează amintirea înainte de a verifica fotografia.');
        abort_unless($item->photo_asset_id, 404);
        $this->media->retry($family, $item->photo_asset_id);
        Audit::record($family, $request->user()->id, 'memory.photo_checked', $moment);

        return $this->show($request, $family, $moment);
    }
}
