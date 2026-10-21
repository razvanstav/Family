<?php

namespace App\Modules\Calendar\Application;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class CalendarAccess
{
    public function visible(string $family, int $actor): Builder
    {
        return DB::table('calendar_items')->where('calendar_items.family_id', $family)
            ->where(function (Builder $query) use ($actor) {
                $query->where('calendar_items.owner_id', $actor)->orWhere('calendar_items.visibility', 'family');
            });
    }
}
