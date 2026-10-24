<?php

namespace App\Modules\Documents\Application;

use App\Modules\Calendar\Contracts\AutomaticReminders;
use App\Modules\Calendar\Contracts\SourceItems;
use App\Platform\Contracts\NotificationPreferences;

final class DocumentExpiryReminders implements AutomaticReminders
{
    public function __construct(private readonly DocumentExpirySource $source, private readonly SourceItems $calendar, private readonly NotificationPreferences $preferences) {}

    public function syncFamily(string $family): void
    {
        $rows = $this->source->documents($family)->where('d.expires_on', '>=', now('Europe/Bucharest')->toDateString())
            ->select('d.id', 'd.title', 'd.expires_on', 'd.version', 'reader.id as membership_id', 'reader.user_id')
            ->selectRaw("md5('document-expiry:' || d.id::text || ':' || reader.id::text)::uuid as source_id")
            ->orderBy('d.id')->orderBy('reader.id')->get();
        $eligible = [];
        $sources = [];
        $reminders = [];
        foreach ($rows as $row) {
            if (! array_key_exists($row->user_id, $eligible)) {
                $settings = $this->preferences->forUser($row->user_id)['deadlines'];
                $eligible[$row->user_id] = $this->preferences->configured($row->user_id) && ($settings['in_app'] || $settings['email']);
            }
            if (! $eligible[$row->user_id]) {
                continue;
            }
            $sources[] = ['type' => $this->source->type(), 'id' => $row->source_id, 'parent_id' => $row->id,
                'version' => (int) $row->version, 'owner_id' => $row->user_id, 'title' => $row->title,
                'date' => $row->expires_on, 'visibility' => 'private', 'active' => true];
            $reminders[$row->source_id] = ['offset' => 10080, 'recipients' => [$row->membership_id]];
        }
        if ($sources !== []) {
            $this->calendar->syncMany($family, $this->source->type(), $sources, $reminders);
        }
    }
}
