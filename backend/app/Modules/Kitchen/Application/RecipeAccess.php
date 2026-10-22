<?php

namespace App\Modules\Kitchen\Application;

use Illuminate\Database\Query\Builder;

final class RecipeAccess
{
    public static function constrain(Builder $query, int $actorId): void
    {
        $query->whereExists(fn (Builder $member) => $member->selectRaw('1')->from('family_memberships as reader')
            ->whereColumn('reader.family_id', 'r.family_id')->where('reader.user_id', $actorId)->whereNull('reader.revoked_at'))
            ->where(fn (Builder $access) => $access->where('r.owner_id', $actorId)
                ->orWhere(fn (Builder $shared) => $shared->whereNull('r.archived_at')->where(fn (Builder $scope) => $scope
                    ->where('r.visibility', 'family')->orWhere(fn (Builder $selected) => $selected->where('r.visibility', 'selected')
                    ->whereExists(fn (Builder $grant) => $grant->selectRaw('1')->from('recipe_grants as g')
                        ->join('family_memberships as recipient', function ($join) {
                            $join->on('recipient.family_id', '=', 'g.family_id')->on('recipient.id', '=', 'g.membership_id');
                        })->whereColumn('g.family_id', 'r.family_id')->whereColumn('g.recipe_id', 'r.id')
                        ->where('recipient.user_id', $actorId)->whereNull('recipient.revoked_at'))))));
    }
}
