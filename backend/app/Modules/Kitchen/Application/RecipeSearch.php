<?php

namespace App\Modules\Kitchen\Application;

use App\Platform\Contracts\SearchProvider;
use App\Platform\Search\SearchTerm;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class RecipeSearch implements SearchProvider
{
    public function type(): string
    {
        return 'recipe';
    }

    public function query(string $family, int $actor, string $membership, SearchTerm $term): Builder
    {
        $query = DB::table('recipes as r')->join('recipe_versions as v', function ($join) {
            $join->on('v.family_id', '=', 'r.family_id')->on('v.recipe_id', '=', 'r.id')->on('v.version_no', '=', 'r.version');
        })->where('r.family_id', $family)->whereNull('r.archived_at');
        RecipeAccess::constrain($query, $actor);

        return $term->constrain($query, ['v.title', 'v.instructions', 'v.ingredients::text'])
            ->selectRaw("'recipe'::text as type, r.id, v.title, left(v.instructions, 140) as excerpt, v.category, r.visibility, NULL::uuid as member_id, r.created_at::date as date");
    }
}
