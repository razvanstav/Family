<?php

namespace App\Modules\Vehicles\Application;

use App\Modules\Calendar\Contracts\DeadlineSource;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class VehicleDeadlineSource implements DeadlineSource
{
    public function type(): string
    {
        return 'vehicle_entry';
    }

    public function references(string $family): Builder
    {
        return $this->entries($family)->select('e.id', 'e.vehicle_id as parent_id', 'e.version')
            ->selectRaw("(e.state = 'planned' AND e.archived_at IS NULL AND v.archived_at IS NULL) as active");
    }

    public function entries(string $family): Builder
    {
        return DB::table('vehicle_entries as e')->join('vehicles as v', function ($join) {
            $join->on('v.id', '=', 'e.vehicle_id')->on('v.family_id', '=', 'e.family_id');
        })->where('e.family_id', $family);
    }
}
