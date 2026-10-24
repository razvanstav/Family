<?php

namespace App\Modules\Vehicles\Application;

use App\Modules\Calendar\Contracts\DatedRecords;
use Illuminate\Database\Query\Builder;

final class VehicleDatedRecords implements DatedRecords
{
    public function __construct(private readonly VehicleDeadlineSource $source) {}

    public function records(string $family, int $actor, string $membership): Builder
    {
        return $this->source->entries($family)->join('users as author', 'author.id', '=', 'e.author_id')
            ->whereNull('e.archived_at')->whereNull('v.archived_at')
            ->select('e.id as source_id', 'e.vehicle_id as source_parent_id', 'e.event_date', 'e.author_id as owner_id', 'author.name as owner_name', 'e.version')
            ->selectRaw("md5('vehicle_date:' || e.id::text)::uuid as id, 'vehicle_date'::text as source_type, e.kind::text as source_kind, 'family'::text as visibility, left(e.title || ' · ' || v.name, 160)::text as title");
    }
}
