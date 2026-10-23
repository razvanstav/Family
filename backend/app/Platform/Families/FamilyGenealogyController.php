<?php

namespace App\Platform\Families;

use App\Platform\Audit\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class FamilyGenealogyController
{
    public function __construct(private MemberProfileAccess $access) {}

    private function nodes(string $family): Collection
    {
        $members = DB::table('family_memberships as m')->leftJoin('users as u', 'u.id', '=', 'm.user_id')
            ->where('m.family_id', $family)->whereNull('m.revoked_at')->select('m.id', 'm.id as membership_id')
            ->selectRaw('coalesce(u.name, m.child_name) as name, 0 as version')->get();

        return $members->concat(DB::table('genealogy_people')->where('family_id', $family)->whereNull('membership_id')->get(['id', 'membership_id', 'name', 'version']))->sortBy('name')->values();
    }

    public function index(Request $request, string $family, string $member): JsonResponse
    {
        $this->access->subject($family, $member);
        $nodes = $this->nodes($family);
        $ids = $nodes->pluck('id');
        $edges = DB::table('genealogy_relations')->where('family_id', $family)->whereNull('removed_at')
            ->whereIn('from_id', $ids)->whereIn('to_id', $ids)->orderBy('id')->get(['id', 'from_id', 'to_id', 'kind', 'version']);

        return response()->json(['nodes' => $nodes, 'edges' => $edges, 'can_manage' => $request->attributes->get('membership')->role === 'owner']);
    }

    public function store(Request $request, string $family, string $member): JsonResponse
    {
        abort_unless($request->attributes->get('membership')->role === 'owner', 403);
        $this->access->subject($family, $member);
        $data = $request->validate(['from_id' => 'required|uuid', 'relation' => 'required|in:parent,child,partner,sibling',
            'target_id' => 'required_without:name|prohibits:name|uuid', 'name' => 'required_without:target_id|prohibits:target_id|string|max:100',
            'email' => 'prohibited', 'user_id' => 'prohibited', 'role' => 'prohibited', 'parent_ids' => 'prohibited']);
        $nodes = $this->nodes($family)->keyBy('id');
        abort_unless($nodes->has($data['from_id']), 404);
        if (isset($data['target_id'])) {
            abort_unless($nodes->has($data['target_id']), 404);
        }

        return DB::transaction(function () use ($request, $family, $member, $data, $nodes): JsonResponse {
            $target = $data['target_id'] ?? (string) Str::uuid();
            if (! isset($data['target_id'])) {
                $name = trim($data['name']);
                abort_if($name === '', 422, 'Completează numele rudei.');
                DB::table('genealogy_people')->insert(['id' => $target, 'family_id' => $family, 'name' => $name, 'created_at' => now(), 'updated_at' => now()]);
            }
            $from = $data['relation'] === 'parent' ? $target : $data['from_id'];
            $to = $data['relation'] === 'parent' ? $data['from_id'] : $target;
            $kind = in_array($data['relation'], ['parent', 'child'], true) ? 'parent' : $data['relation'];
            abort_if($from === $to, 422, 'Alege două persoane diferite.');
            if ($kind !== 'parent' && strcmp($from, $to) > 0) {
                [$from, $to] = [$to, $from];
            }
            if ($kind === 'parent') {
                $children = DB::table('genealogy_relations')->where('family_id', $family)->where('kind', 'parent')->whereNull('removed_at')->get(['from_id', 'to_id'])->groupBy('from_id');
                $pending = [$to];
                $seen = [];
                while ($pending !== []) {
                    $id = array_pop($pending);
                    abort_if($id === $from, 422, 'Această legătură ar forma un cerc între părinți și copii.');
                    if (isset($seen[$id])) {
                        continue;
                    }
                    $seen[$id] = true;
                    foreach ($children->get($id, collect()) as $edge) {
                        $pending[] = $edge->to_id;
                    }
                }
            }
            foreach ([$from, $to] as $id) {
                if ($nodes->has($id) && $nodes[$id]->membership_id !== null) {
                    DB::table('genealogy_people')->insertOrIgnore(['id' => $id, 'family_id' => $family, 'membership_id' => $id, 'created_at' => now(), 'updated_at' => now()]);
                }
            }
            $existing = DB::table('genealogy_relations')->where('family_id', $family)->where('kind', $kind)->where('from_id', $from)->where('to_id', $to)->first();
            abort_if($existing && $existing->removed_at === null, 409, 'Legătura există deja.');
            if ($existing) {
                DB::table('genealogy_relations')->where('id', $existing->id)->update(['removed_at' => null, 'version' => $existing->version + 1, 'updated_at' => now()]);
            } else {
                DB::table('genealogy_relations')->insert(['id' => (string) Str::uuid(), 'family_id' => $family, 'from_id' => $from, 'to_id' => $to, 'kind' => $kind, 'created_at' => now(), 'updated_at' => now()]);
            }
            Audit::record($family, $request->user()->id, 'genealogy.relation_added', $member);

            return $this->index($request, $family, $member)->setStatusCode(201);
        });
    }

    public function rename(Request $request, string $family, string $member, string $person): JsonResponse
    {
        abort_unless($request->attributes->get('membership')->role === 'owner', 403);
        $this->access->subject($family, $member);
        $row = DB::table('genealogy_people')->where('family_id', $family)->where('id', $person)->whereNull('membership_id')->firstOrFail();
        $this->access->version($request, (int) $row->version);
        $data = $request->validate(['name' => 'required|string|max:100']);
        DB::table('genealogy_people')->where('family_id', $family)->where('id', $person)->update(['name' => $data['name'], 'version' => $row->version + 1, 'updated_at' => now()]);
        Audit::record($family, $request->user()->id, 'genealogy.person_renamed', $person);

        return $this->index($request, $family, $member);
    }

    public function remove(Request $request, string $family, string $member, string $relation): JsonResponse
    {
        abort_unless($request->attributes->get('membership')->role === 'owner', 403);
        $this->access->subject($family, $member);
        $row = DB::table('genealogy_relations')->where('family_id', $family)->where('id', $relation)->whereNull('removed_at')->firstOrFail();
        $this->access->version($request, (int) $row->version);
        DB::table('genealogy_relations')->where('family_id', $family)->where('id', $relation)->update(['removed_at' => now(), 'version' => $row->version + 1, 'updated_at' => now()]);
        Audit::record($family, $request->user()->id, 'genealogy.relation_removed', $relation);

        return $this->index($request, $family, $member);
    }
}
