<?php

namespace App\Modules\Vehicles\Application;

use App\Platform\Audit\ActivityEvents;
use App\Platform\Contracts\ActivitySource;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class VehicleActivitySource implements ActivitySource
{
    public function __construct(private readonly VehicleDeadlineSource $source) {}

    public function forDay(string $family, int $actor, CarbonImmutable $from, CarbonImmutable $until, int $limit): array
    {
        $vehicles = DB::table('vehicles as v')->where('v.family_id', $family)->whereNull('v.archived_at')
            ->select('v.id as resource_id', 'v.name as title')
            ->selectRaw("'vehicle.created'::text as source_action, 'vehicle'::text as source_kind, NULL::uuid as parent_id");
        $entries = $this->source->entries($family)->whereNull('e.archived_at')->whereNull('v.archived_at')
            ->select('e.id as resource_id', 'e.title')
            ->selectRaw("'vehicle.entry_created'::text as source_action, 'vehicle_entry'::text as source_kind")
            ->addSelect('e.vehicle_id as parent_id');
        $query = ActivityEvents::query($family, ['vehicle.created', 'vehicle.entry_created'], $from, $until)
            ->joinSub($vehicles->unionAll($entries), 'visible', function ($join) {
                $join->on('visible.resource_id', '=', 'activity.resource_id')->on('visible.source_action', '=', 'activity.action');
            })->select('visible.title', 'visible.source_kind', 'visible.parent_id');

        return ActivityEvents::read($query, $limit, fn (object $row): array => $row->source_kind === 'vehicle_entry'
            ? ['kind' => 'vehicle_entry', 'id' => $row->resource_id, 'parent_id' => $row->parent_id]
            : ['kind' => 'vehicle', 'id' => $row->resource_id]);
    }
}
