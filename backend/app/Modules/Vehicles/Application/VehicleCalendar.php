<?php

namespace App\Modules\Vehicles\Application;

use App\Modules\Calendar\Contracts\SourceItems;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class VehicleCalendar
{
    public function __construct(private readonly SourceItems $calendar, private readonly VehicleDeadlineSource $source) {}

    public function settings(string $family): Builder
    {
        return $this->calendar->settings($family, $this->source->type());
    }

    /** @param array{offset: ?int, recipients: list<string>}|null $reminder */
    public function syncEntry(string $family, string $entry, ?array $reminder = null): void
    {
        $row = $this->source->entries($family)->where('e.id', $entry)->firstOrFail(['e.*', 'v.name as vehicle_name', 'v.archived_at as vehicle_archived_at']);
        $this->calendar->sync($family, ['type' => $this->source->type(), 'id' => $row->id, 'parent_id' => $row->vehicle_id,
            'version' => (int) $row->version, 'owner_id' => $row->author_id, 'title' => mb_substr($row->title.' · '.$row->vehicle_name, 0, 160),
            'date' => $row->event_date, 'active' => $row->state === 'planned' && $row->archived_at === null && $row->vehicle_archived_at === null], $reminder);
    }

    public function syncVehicle(string $family, string $vehicle): void
    {
        DB::table('vehicle_entries')->where('family_id', $family)->where('vehicle_id', $vehicle)->orderBy('id')->select('id')->chunk(100, function ($entries) use ($family) {
            foreach ($entries as $entry) {
                $this->syncEntry($family, $entry->id);
            }
        });
    }
}
