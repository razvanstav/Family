<?php

namespace App\Modules\Kitchen\Application;

use App\Modules\Calendar\Contracts\DatedRecords;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class MealDatedRecords implements DatedRecords
{
    public function records(string $family, int $actor, string $membership): Builder
    {
        return DB::table('kitchen_meals as m')->join('users as author', 'author.id', '=', 'm.owner_id')
            ->where('m.family_id', $family)->whereNull('m.archived_at')
            ->where(fn (Builder $access) => $access->where('m.owner_id', $actor)->orWhere('m.visibility', 'family'))
            ->where(fn (Builder $access) => $access->whereNull('m.visit_id')
                ->orWhereExists(fn (Builder $visit) => $visit->selectRaw('1')->from('kitchen_dining_visits as meal_visit')
                    ->whereColumn('meal_visit.family_id', 'm.family_id')->whereColumn('meal_visit.id', 'm.visit_id')
                    ->where(fn (Builder $visible) => $visible->where('meal_visit.owner_id', $actor)->orWhere('meal_visit.visibility', 'family'))))
            ->select('m.id as source_id', 'm.id as source_parent_id', 'm.meal_date as event_date', 'm.dish_title as title', 'm.visibility', 'm.owner_id', 'author.name as owner_name', 'm.version')
            ->selectRaw("md5('meal:' || m.id::text)::uuid as id, 'meal'::text as source_type, m.meal_type::text as source_kind");
    }
}
