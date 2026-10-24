<?php

namespace App\Modules\Memory\Application;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\DB;

final class MomentAccess
{
    public function visible(string $family, int $actor): Builder
    {
        $query = DB::table('memory_moments')->where('memory_moments.family_id', $family)
            ->whereExists(fn (Builder $member) => $member->selectRaw('1')->from('family_memberships as reader')
                ->whereColumn('reader.family_id', 'memory_moments.family_id')->where('reader.user_id', $actor)->whereNull('reader.revoked_at'));

        return $this->authorize($query, $actor);
    }

    public function recipients(string $family): Builder
    {
        $query = DB::table('memory_moments')->join('family_memberships as reader', function ($join) {
            $join->on('reader.family_id', '=', 'memory_moments.family_id')->whereNull('reader.revoked_at')->whereNotNull('reader.user_id');
        })->where('memory_moments.family_id', $family);

        return $this->authorize($query, DB::raw('reader.user_id'));
    }

    private function authorize(Builder $query, int|Expression $actor): Builder
    {
        return $query->where(fn (Builder $access) => $access->where('memory_moments.owner_id', $actor)
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
