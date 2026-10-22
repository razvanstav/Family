<?php

namespace App\Modules\Memory\Application;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class MomentAccess
{
    public function visible(string $family, int $actor): Builder
    {
        return DB::table('memory_moments')->where('memory_moments.family_id', $family)
            ->whereExists(fn (Builder $member) => $member->selectRaw('1')->from('family_memberships as reader')
                ->whereColumn('reader.family_id', 'memory_moments.family_id')->where('reader.user_id', $actor)->whereNull('reader.revoked_at'))
            ->where(fn (Builder $access) => $access->where('memory_moments.owner_id', $actor)
                ->orWhere(fn (Builder $shared) => $shared->whereNull('memory_moments.archived_at')
                    ->where(fn (Builder $scope) => $scope->where('memory_moments.visibility', 'family')
                        ->orWhere(fn (Builder $selected) => $selected->where('memory_moments.visibility', 'selected')
                            ->whereExists(fn (Builder $grant) => $grant->selectRaw('1')->from('memory_moment_grants as g')
                                ->join('family_memberships as recipient', function ($join) {
                                    $join->on('recipient.family_id', '=', 'g.family_id')->on('recipient.id', '=', 'g.membership_id');
                                })->whereColumn('g.family_id', 'memory_moments.family_id')->whereColumn('g.moment_id', 'memory_moments.id')
                                ->where('recipient.user_id', $actor)->whereNull('recipient.revoked_at'))))));
    }

    public function revokeMembership(string $family, string $membership): void
    {
        DB::table('memory_moment_grants')->where('family_id', $family)->where('membership_id', $membership)->delete();
    }
}
