<?php

namespace App\Modules\Memory\Application;

use App\Modules\Calendar\Contracts\AutomaticReminders;
use App\Modules\Calendar\Contracts\SourceItems;
use App\Platform\Contracts\NotificationPreferences;

final class MemoryAnniversaryReminders implements AutomaticReminders
{
    public function __construct(private readonly MemoryAnniversarySource $source, private readonly SourceItems $calendar, private readonly NotificationPreferences $preferences) {}

    public function syncFamily(string $family): void
    {
        $today = now('Europe/Bucharest');
        $year = $today->year;
        $date = 'make_date('.$year.', extract(month from memory_moments.occurred_on)::int, 1) + (extract(day from memory_moments.occurred_on)::int - 1)';
        $rows = $this->source->moments($family)->where('memory_moments.occurred_on', '<', $year.'-01-01')
            ->whereRaw("to_char(($date), 'MM-DD') = to_char(memory_moments.occurred_on, 'MM-DD')")
            ->whereRaw("($date) >= ?", [$today->toDateString()])
            ->select('memory_moments.id', 'memory_moments.title', 'memory_moments.version', 'reader.id as membership_id', 'reader.user_id')
            ->selectRaw("($date)::text as anniversary_on, md5('memory-anniversary:' || memory_moments.id::text || ':' || reader.id::text || ':' || ?::text)::uuid as source_id", [$year])
            ->orderBy('memory_moments.id')->orderBy('reader.id')->get();
        $eligible = [];
        $sources = [];
        $reminders = [];
        foreach ($rows as $row) {
            if (! array_key_exists($row->user_id, $eligible)) {
                $settings = $this->preferences->forUser($row->user_id)['memories'];
                $eligible[$row->user_id] = $settings['in_app'] || $settings['email'];
            }
            if (! $eligible[$row->user_id]) {
                continue;
            }
            $sources[] = ['type' => $this->source->type(), 'id' => $row->source_id, 'parent_id' => $row->id,
                'version' => (int) $row->version, 'owner_id' => $row->user_id, 'title' => $row->title,
                'date' => $row->anniversary_on, 'visibility' => 'private', 'active' => true];
            $reminders[$row->source_id] = ['offset' => 0, 'recipients' => [$row->membership_id]];
        }
        if ($sources !== []) {
            $this->calendar->syncMany($family, $this->source->type(), $sources, $reminders);
        }
    }
}
