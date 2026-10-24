<?php

namespace App\Modules\Pets\Application;

use App\Modules\Calendar\Contracts\SourceItems;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class PetCalendar
{
    public function __construct(private readonly SourceItems $calendar, private readonly PetDeadlineSource $source) {}

    public function settings(string $family): Builder
    {
        return $this->calendar->settings($family, $this->source->type());
    }

    /** @param array{offset:?int,recipients:list<string>}|null $reminder */
    public function syncEntry(string $family, string $entry, ?array $reminder = null): void
    {
        $row = $this->source->entries($family)->where('e.id', $entry)->firstOrFail(['e.*', 'p.name as pet_name', 'p.archived_at as pet_archived_at']);
        $this->calendar->sync($family, ['type' => $this->source->type(), 'id' => $row->id, 'parent_id' => $row->pet_id,
            'version' => (int) $row->version, 'owner_id' => $row->author_id, 'title' => mb_substr($row->title.' · '.$row->pet_name, 0, 160),
            'date' => $row->next_due_on ?? $row->event_date,
            'active' => $row->next_due_on !== null && $row->next_due_completed_at === null && $row->archived_at === null && $row->pet_archived_at === null], $reminder);
    }

    public function syncPet(string $family, string $pet): void
    {
        DB::table('pet_entries')->where('family_id', $family)->where('pet_id', $pet)->orderBy('id')->select('id')->chunk(100, function ($entries) use ($family): void {
            foreach ($entries as $entry) {
                $this->syncEntry($family, $entry->id);
            }
        });
    }
}
