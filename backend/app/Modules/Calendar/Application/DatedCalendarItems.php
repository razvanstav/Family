<?php

namespace App\Modules\Calendar\Application;

use App\Modules\Calendar\Contracts\DatedRecords;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class DatedCalendarItems
{
    /** @param iterable<DatedRecords> $sources */
    public function __construct(private readonly iterable $sources) {}

    /** @return Collection<int, array<string, mixed>> */
    public function month(string $family, int $actor, string $membership, string $first, string $last): Collection
    {
        $items = collect();
        foreach ($this->sources as $source) {
            $rows = DB::query()->fromSub($source->records($family, $actor, $membership), 'dated_record')
                ->whereBetween('event_date', [$first, $last])->get();
            foreach ($rows as $row) {
                $items->push($this->payload($row));
            }
        }

        return $items;
    }

    /** @return array<string, mixed>|null */
    public function find(string $family, int $actor, string $membership, string $id): ?array
    {
        if (! Str::isUuid($id)) {
            return null;
        }
        foreach ($this->sources as $source) {
            $row = DB::query()->fromSub($source->records($family, $actor, $membership), 'dated_record')->where('id', $id)->first();
            if ($row !== null) {
                return $this->payload($row);
            }
        }

        return null;
    }

    /** Stored reminder projections take precedence over a mirror of the same canonical date. */
    public function merge(Collection $stored, Collection $dated): Collection
    {
        $seen = [];
        $items = collect();
        foreach ($stored->concat($dated) as $item) {
            $key = $this->canonicalKey($item);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $items->push($item);
        }

        return $items->sort(fn (array $left, array $right): int => [$left['event_date'], $left['all_day'] ? 0 : 1, $left['starts_at'] ?? '', $left['id']]
            <=> [$right['event_date'], $right['all_day'] ? 0 : 1, $right['starts_at'] ?? '', $right['id']])->values();
    }

    private function canonicalKey(array $event): string
    {
        $source = $event['source'];
        if ($source === null) {
            return 'manual:'.$event['id'];
        }
        $type = match ($source['type']) {
            'document_expiry' => 'document_date', 'task' => 'task_date', 'vehicle_entry' => 'vehicle_date',
            'pet_entry', 'pet_record', 'pet_due' => 'pet_date', 'birthday' => 'member_birth', default => $source['type'],
        };
        $id = in_array($source['type'], ['document_expiry', 'birthday'], true) ? $source['parent_id'] : $source['id'];

        return $type.':'.$id.':'.$event['event_date'];
    }

    /** @return array<string, mixed> */
    private function payload(object $record): array
    {
        $source = ['type' => $record->source_type, 'id' => $record->source_id, 'parent_id' => $record->source_parent_id, 'virtual' => true];
        if ($record->source_kind !== null) {
            $source['kind'] = $record->source_kind;
        }

        return ['id' => $record->id, 'title' => $record->title, 'notes' => null, 'event_date' => $record->event_date,
            'all_day' => true, 'time' => null, 'starts_at' => null, 'timezone' => 'Europe/Bucharest',
            'visibility' => $record->visibility, 'owner_name' => $record->owner_name, 'owner_id' => (int) $record->owner_id,
            'can_edit' => false, 'version' => (int) $record->version, 'archived_at' => null, 'source' => $source,
            'my_reminder_offset_minutes' => null, 'my_reminder_version' => 0, 'reminder' => null];
    }
}
