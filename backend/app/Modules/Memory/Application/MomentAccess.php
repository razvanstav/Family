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
            ->where(fn (Builder $scope) => $scope->where('memory_moments.owner_id', $actor)->orWhere('memory_moments.visibility', 'family'));
    }
}
