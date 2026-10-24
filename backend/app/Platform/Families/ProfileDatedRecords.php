<?php

namespace App\Platform\Families;

use App\Modules\Calendar\Contracts\DatedRecords;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class ProfileDatedRecords implements DatedRecords
{
    public function records(string $family, int $actor, string $membership): Builder
    {
        $birth = DB::table('member_profiles as p')->join('family_memberships as m', function ($join) {
            $join->on('m.family_id', '=', 'p.family_id')->on('m.id', '=', 'p.membership_id');
        })->leftJoin('users as subject', 'subject.id', '=', 'm.user_id')->join('users as viewer', fn ($join) => $join->where('viewer.id', $actor))
            ->where('p.family_id', $family)->whereNull('m.revoked_at')->whereNotNull('p.birth_date')
            ->where(fn (Builder $access) => $access->where('m.user_id', $actor)->orWhereExists(fn (Builder $admin) => $admin->selectRaw('1')->from('family_memberships as admin')
                ->whereColumn('admin.family_id', 'p.family_id')->where('admin.id', $membership)->where('admin.user_id', $actor)->where('admin.role', 'owner')->whereNull('admin.revoked_at')))
            ->select('m.id as source_id', 'm.id as source_parent_id', 'p.birth_date as event_date', 'viewer.id as owner_id', 'viewer.name as owner_name', 'p.version')
            ->selectRaw("md5('member_birth:' || m.id::text)::uuid as id, 'member_birth'::text as source_type, NULL::text as source_kind, 'private'::text as visibility, left('Naștere · ' || coalesce(subject.name, m.child_name), 160)::text as title");
        $entries = DB::table('member_profile_entries as e')->join('family_memberships as m', function ($join) {
            $join->on('m.family_id', '=', 'e.family_id')->on('m.id', '=', 'e.membership_id');
        })->leftJoin('users as subject', 'subject.id', '=', 'm.user_id')->join('users as author', 'author.id', '=', 'e.author_id')
            ->where('e.family_id', $family)->whereNull('m.revoked_at')->whereNull('e.archived_at')
            ->where(fn (Builder $access) => $access->where('e.author_id', $actor)->orWhere('e.visibility', 'family'))
            ->select('e.id as source_id', 'e.membership_id as source_parent_id', 'e.occurred_on as event_date', 'e.author_id as owner_id', 'author.name as owner_name', 'e.version')
            ->selectRaw("md5('profile_entry:' || e.id::text)::uuid as id, 'profile_entry'::text as source_type, e.kind::text as source_kind, e.visibility::text as visibility, left(e.title || ' · ' || coalesce(subject.name, m.child_name), 160)::text as title");

        return $birth->unionAll($entries);
    }
}
