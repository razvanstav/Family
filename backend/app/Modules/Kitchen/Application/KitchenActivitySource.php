<?php

namespace App\Modules\Kitchen\Application;

use App\Platform\Audit\ActivityEvents;
use App\Platform\Contracts\ActivitySource;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;

final class KitchenActivitySource implements ActivitySource
{
    public function forDay(string $family, int $actor, CarbonImmutable $from, CarbonImmutable $until, int $limit): array
    {
        $meals = ActivityEvents::query($family, ['meal.created'], $from, $until)
            ->join('kitchen_meals as m', function ($join) {
                $join->on('m.family_id', '=', 'activity.family_id')->on('m.id', '=', 'activity.resource_id');
            })->whereNull('m.archived_at')
            ->where(fn (Builder $access) => $access->where('m.owner_id', $actor)->orWhere('m.visibility', 'family'))
            ->whereExists(fn (Builder $member) => $member->selectRaw('1')->from('family_memberships as reader')
                ->whereColumn('reader.family_id', 'm.family_id')->where('reader.user_id', $actor)->whereNull('reader.revoked_at'))
            ->addSelect('m.dish_title as title', 'm.meal_date');
        $recipes = ActivityEvents::query($family, ['recipe.created'], $from, $until)
            ->join('recipes as r', function ($join) {
                $join->on('r.family_id', '=', 'activity.family_id')->on('r.id', '=', 'activity.resource_id');
            })->join('recipe_versions as v', function ($join) {
                $join->on('v.family_id', '=', 'r.family_id')->on('v.recipe_id', '=', 'r.id')->on('v.version_no', '=', 'r.version');
            })->whereNull('r.archived_at')->where('r.is_example', false)->addSelect('v.title');
        RecipeAccess::constrain($recipes, $actor);

        $items = array_merge(
            ActivityEvents::read($meals, $limit, fn (object $row): array => ['kind' => 'meal', 'id' => $row->resource_id, 'date' => $row->meal_date]),
            ActivityEvents::read($recipes, $limit, fn (object $row): array => ['kind' => 'recipe', 'id' => $row->resource_id]),
        );
        usort($items, fn (array $left, array $right): int => strcmp($right['occurred_at'], $left['occurred_at']) ?: strcmp($right['id'], $left['id']));

        return array_slice($items, 0, $limit);
    }
}
