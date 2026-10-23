<?php

namespace App\Modules\Calendar\Application;

use App\Modules\Calendar\Contracts\SourceItems;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class SourceCalendarItems implements SourceItems
{
    public function __construct(private readonly CalendarReminders $reminders) {}

    public function settings(string $family, string $type): Builder
    {
        return DB::table('calendar_items as c')
            ->leftJoinSub(DB::table('calendar_reminder_recipients')->where('family_id', $family)
                ->selectRaw('calendar_item_id, json_agg(membership_id ORDER BY membership_id) as recipients_json')->groupBy('calendar_item_id'),
                'recipients', 'recipients.calendar_item_id', '=', 'c.id')
            ->where('c.family_id', $family)->where('c.source_type', $type)
            ->select('c.source_id', 'c.id as calendar_event_id', 'c.reminder_offset_minutes', 'recipients.recipients_json');
    }

    /**
     * Called inside the source's tenant transaction; this projection cannot be edited independently.
     *
     * @param  array{type: string, id: string, parent_id: string, version: int, owner_id: int, title: string, date: string, active: bool, visibility?: string}  $source
     * @param  array{offset: ?int, recipients: list<string>, offsets?: array<string, int>}|null  $reminder  Null preserves existing settings.
     */
    public function sync(string $family, array $source, ?array $reminder = null): void
    {
        $current = DB::table('calendar_items')->where('family_id', $family)->where('source_type', $source['type'])->where('source_id', $source['id'])->first();
        $recipients = $current && $reminder !== null ? DB::table('calendar_reminder_recipients')->where('calendar_item_id', $current->id)->get()->all() : [];
        $this->apply($family, $source, $reminder, $current, $recipients);
    }

    /** Batch projection reads keep Calendar and Home lists independent of the number of birthdays. */
    public function syncMany(string $family, string $type, array $sources, array $reminders): void
    {
        $current = DB::table('calendar_items')->where('family_id', $family)->where('source_type', $type)
            ->whereIn('source_id', array_column($sources, 'id'))->get()->keyBy('source_id');
        $recipients = DB::table('calendar_reminder_recipients')->where('family_id', $family)->whereIn('calendar_item_id', $current->pluck('id'))->get()->groupBy('calendar_item_id');
        foreach ($sources as $source) {
            $event = $current[$source['id']] ?? null;
            $this->apply($family, $source, $reminders[$source['id']], $event, $event ? ($recipients[$event->id] ?? collect())->all() : []);
        }
    }

    private function apply(string $family, array $source, ?array $reminder, ?object $current, array $currentRecipients): void
    {
        if (! $current && ! $source['active']) {
            return;
        }
        $values = ['source_type' => $source['type'], 'source_id' => $source['id'], 'source_parent_id' => $source['parent_id'],
            'source_version' => $source['version'], 'title' => $source['title'], 'event_date' => $source['date'],
            'visibility' => $source['visibility'] ?? 'family'];
        $sameReminder = $reminder === null;
        $changedRecipients = [];
        if ($current && $reminder !== null) {
            $before = [];
            foreach ($currentRecipients as $recipient) {
                $before[$recipient->membership_id] = $recipient->offset_minutes;
            }
            $after = array_fill_keys($reminder['recipients'], null);
            foreach ($reminder['offsets'] ?? [] as $membership => $offset) {
                $after[$membership] = $offset;
            }
            ksort($before);
            ksort($after);
            $sameReminder = $current->reminder_offset_minutes === $reminder['offset'] && $before === $after;
            $changedRecipients = array_values(array_filter(array_unique(array_merge(array_keys($before), array_keys($after))),
                fn (string $id) => ! array_key_exists($id, $before) || ! array_key_exists($id, $after) || $before[$id] !== $after[$id]));
        }
        $sameSource = $current && collect($values)->every(fn ($value, $key) => $current->{$key} === $value)
            && ($current->archived_at === null) === $source['active'];
        if ($sameSource && $sameReminder) {
            return;
        }
        $preferenceOnly = $current && $source['type'] === 'birthday' && $current->source_version === $source['version']
            && $current->event_date === $source['date'] && ($current->archived_at === null) === $source['active'];
        $id = $current?->id ?? (string) Str::uuid();
        $values += ['version' => ($current?->version ?? 0) + ($preferenceOnly ? 0 : 1), 'archived_at' => $source['active'] ? null : ($current?->archived_at ?? now()), 'updated_at' => now()];
        if ($reminder !== null) {
            $values['reminder_offset_minutes'] = $reminder['offset'];
        }
        if ($current) {
            DB::table('calendar_items')->where('family_id', $family)->where('id', $id)->update($values);
        } else {
            DB::table('calendar_items')->insert($values + ['id' => $id, 'family_id' => $family, 'owner_id' => $source['owner_id'],
                'all_day' => true, 'starts_at' => null, 'notes' => null, 'created_at' => now()]);
        }
        $event = DB::table('calendar_items')->where('id', $id)->firstOrFail();
        if ($reminder !== null) {
            $this->reminders->configure($family, $event, $reminder['offset'], $reminder['recipients'], $reminder['offsets'] ?? []);
        }
        $this->reminders->replan($family, $event, $preferenceOnly ? $changedRecipients : null);
    }

    public function deactivateParent(string $family, string $type, string $parent): void
    {
        $ids = DB::table('calendar_items')->where('family_id', $family)->where('source_type', $type)->where('source_parent_id', $parent)->whereNull('archived_at')->pluck('id');
        DB::table('calendar_items')->whereIn('id', $ids)->update(['archived_at' => now(), 'version' => DB::raw('version + 1'), 'updated_at' => now()]);
        DB::table('calendar_reminder_deliveries')->whereIn('calendar_item_id', $ids)->whereIn('state', ['pending', 'delivered'])->update(['state' => 'cancelled']);
    }
}
