<?php

namespace App\Modules\Calendar\Application;

use App\Platform\Contracts\NotificationPreferences;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class CalendarReminders
{
    public function __construct(private readonly CalendarSources $sources, private readonly NotificationPreferences $preferences) {}

    /** @return array{due_at: CarbonImmutable, expires_at: CarbonImmutable} */
    public function schedule(object $event, ?int $offset = null): array
    {
        $offset ??= $event->reminder_offset_minutes;
        $start = $event->all_day
            ? CarbonImmutable::parse($event->event_date.' 09:00', 'Europe/Bucharest')
            : CarbonImmutable::parse($event->starts_at)->utc();
        $due = $event->all_day
            ? $start->subDays(intdiv($offset, 1440))
            : $start->subMinutes($offset);

        return ['due_at' => $due->utc(), 'expires_at' => $event->all_day
            ? $start->addDay()->startOfDay()->utc()
            : $start->addMinute()->utc()];
    }

    /** @param list<string> $recipients */
    public function configure(string $family, object $event, ?int $offset, array $recipients, array $recipientOffsets = []): void
    {
        if ($recipientOffsets !== [] && (($event->source_type ?? null) !== 'birthday' || array_diff(array_keys($recipientOffsets), $recipients) !== []
            || array_diff(array_values($recipientOffsets), [0, 1440, 10080]) !== [])) {
            throw ValidationException::withMessages(['reminder_offset_minutes' => 'Momentul reminderului nu este valid.']);
        }
        if ($offset !== null) {
            $allowed = DB::table('family_memberships')->where('family_id', $family)->whereNull('revoked_at')->whereNotNull('user_id')->whereIn('id', $recipients);
            if ($event->visibility === 'private') {
                $allowed->where('user_id', $event->owner_id);
            }
            if (count($recipients) === 0 || $allowed->count() !== count($recipients)) {
                throw ValidationException::withMessages(['reminder_recipients' => 'Alege membri activi care au acces la eveniment. Pentru un eveniment privat te poți alege doar pe tine.']);
            }
            $allDayOffsets = match ($event->source_type ?? null) {
                'vehicle_entry' => [0, 1440, 10080, 43200],
                'document_expiry' => [10080],
                default => [0, 1440],
            };
            if ($event->all_day && ! in_array($offset, $allDayOffsets, true)) {
                throw ValidationException::withMessages(['reminder_offset_minutes' => 'Pentru un eveniment fără oră, alege ziua evenimentului sau ziua precedentă.']);
            }
        } elseif (count($recipients) > 0) {
            throw ValidationException::withMessages(['reminder_recipients' => 'Alege momentul reminderului.']);
        }
        DB::table('calendar_reminder_recipients')->where('calendar_item_id', $event->id)->delete();
        foreach ($recipients as $membership) {
            DB::table('calendar_reminder_recipients')->insert(['family_id' => $family, 'calendar_item_id' => $event->id, 'membership_id' => $membership, 'offset_minutes' => $recipientOffsets[$membership] ?? null]);
        }
    }

    /** The event and its durable delivery intents share the caller's tenant transaction. */
    public function replan(string $family, object $event, ?array $memberships = null): void
    {
        DB::table('calendar_reminder_deliveries')->where('calendar_item_id', $event->id)
            ->when($memberships !== null, fn (Builder $query) => $query->whereIn('membership_id', $memberships))
            ->whereIn('state', ['pending', 'delivered'])->update(['state' => 'cancelled']);
        if ($event->archived_at !== null || $event->reminder_offset_minutes === null) {
            return;
        }
        $recipients = DB::table('calendar_reminder_recipients as r')->join('family_memberships as m', 'm.id', '=', 'r.membership_id')
            ->where('r.calendar_item_id', $event->id)->whereNull('m.revoked_at')->whereNotNull('m.user_id')->get(['r.membership_id', 'r.offset_minutes']);
        foreach ($recipients as $recipient) {
            if ($memberships !== null && ! in_array($recipient->membership_id, $memberships, true)) {
                continue;
            }
            $schedule = $this->schedule($event, $recipient->offset_minutes);
            DB::table('calendar_reminder_deliveries')->upsert([
                'id' => (string) Str::uuid(), 'family_id' => $family, 'calendar_item_id' => $event->id,
                'membership_id' => $recipient->membership_id, 'source_version' => $event->version,
                'due_at' => $schedule['due_at']->toIso8601String(), 'expires_at' => $schedule['expires_at']->toIso8601String(),
                'state' => $schedule['due_at']->lt(now()) ? 'expired' : 'pending',
                'delivered_at' => null, 'read_at' => null,
                'in_app_enabled' => true, 'email_state' => null, 'email_attempts' => 0,
                'email_claimed_at' => null, 'email_sent_at' => null, 'email_next_attempt_at' => null, 'email_error_code' => null,
            ], ['calendar_item_id', 'source_version', 'membership_id'], ['due_at', 'expires_at', 'state', 'delivered_at', 'read_at',
                'in_app_enabled', 'email_state', 'email_attempts', 'email_claimed_at', 'email_sent_at', 'email_next_attempt_at', 'email_error_code']);
        }
    }

    public function cancelMembership(string $family, string $membership): void
    {
        $owner = DB::table('family_memberships')->where('family_id', $family)->where('id', $membership)->value('user_id');
        $ownedEvents = DB::table('calendar_items')->where('family_id', $family)->where('owner_id', $owner)->whereNull('source_type')->select('id');
        $birthdays = DB::table('calendar_items')->where('family_id', $family)->where('source_type', 'birthday')->where('source_parent_id', $membership)->select('id');
        DB::table('calendar_reminder_deliveries')->where('family_id', $family)
            ->where(function (Builder $query) use ($membership, $ownedEvents, $birthdays) {
                $query->where('membership_id', $membership)->orWhereIn('calendar_item_id', $ownedEvents)->orWhereIn('calendar_item_id', $birthdays);
            })->whereIn('state', ['pending', 'delivered'])->update(['state' => 'cancelled']);
        DB::table('calendar_reminder_recipients')->where('family_id', $family)->where('membership_id', $membership)->delete();
        DB::table('birthday_reminder_preferences')->where('family_id', $family)->where(fn (Builder $query) => $query->where('recipient_membership_id', $membership)->orWhere('subject_membership_id', $membership))->delete();
    }

    public function authorized(string $family): Builder
    {
        return $this->sources->restrict(DB::table('calendar_reminder_deliveries as d'), $family, 'e')
            ->join('calendar_items as e', function ($join) {
                $join->on('e.id', '=', 'd.calendar_item_id')->on('e.family_id', '=', 'd.family_id')->on('e.version', '=', 'd.source_version');
            })
            ->join('family_memberships as recipient', function ($join) {
                $join->on('recipient.id', '=', 'd.membership_id')->on('recipient.family_id', '=', 'd.family_id');
            })
            ->join('family_memberships as owner', function ($join) {
                $join->on('owner.user_id', '=', 'e.owner_id')->on('owner.family_id', '=', 'e.family_id');
            })
            ->where('d.family_id', $family)->whereNull('recipient.revoked_at')->whereNull('e.archived_at')
            ->where(function (Builder $query) {
                $query->whereNotNull('e.source_type')->orWhereNull('owner.revoked_at');
            })
            ->whereNotNull('e.reminder_offset_minutes')
            ->where(function (Builder $query) {
                $query->where('e.visibility', 'family')->orWhereColumn('e.owner_id', 'recipient.user_id');
            });
    }

    public function notifications(string $family, int $user): Builder
    {
        return $this->authorized($family)->where('d.state', 'delivered')->where('d.in_app_enabled', true)->where('recipient.user_id', $user);
    }

    /** @return Collection<int, object> Pending reminders addressed to this actor, never other recipients. */
    public function upcoming(string $family, int $actor): Collection
    {
        $now = CarbonImmutable::now();

        return $this->filterEnabledChannels($this->authorized($family), $actor)->where('recipient.user_id', $actor)->where('d.state', 'pending')
            ->where('d.expires_at', '>=', $now->toIso8601String())
            ->where('d.due_at', '<=', $now->addDays(30)->toIso8601String())
            ->orderBy('d.due_at')->orderBy('d.id')->limit(5)
            ->get(['d.id', 'd.calendar_item_id as event_id', 'd.due_at', 'e.title', 'e.event_date'])
            ->map(function (object $reminder): object {
                $reminder->due_at = CarbonImmutable::parse($reminder->due_at)->utc()->toIso8601String();

                return $reminder;
            });
    }

    private function filterEnabledChannels(Builder $query, int $user): Builder
    {
        $preferences = $this->preferences->forUser($user);

        return $query->where(function (Builder $enabled) use ($preferences) {
            foreach ([null, 'birthday', 'task', 'memory_anniversary', 'vehicle_entry', 'pet_entry', 'document_expiry'] as $sourceType) {
                $channels = $preferences[$this->preferences->category($sourceType)];
                if ($channels['in_app'] || $channels['email']) {
                    $sourceType === null ? $enabled->orWhereNull('e.source_type') : $enabled->orWhere('e.source_type', $sourceType);
                }
            }
            $enabled->orWhereRaw('false');
        });
    }

    public function deliverFamily(string $family, ?BirthdayCalendar $birthdays = null): int
    {
        return DB::transaction(function () use ($family, $birthdays): int {
            if (! DB::table('families')->where('id', $family)->lockForUpdate()->first()) {
                return 0;
            }
            DB::select("SELECT set_config('app.family_id', ?, true)", [$family]);
            $birthdays?->syncFamily($family);
            $now = now();
            $due = DB::table('calendar_reminder_deliveries')->where('family_id', $family)->where('state', 'pending')
                ->where('due_at', '<=', $now->toIso8601String())->orderBy('due_at')->orderBy('id')->limit(100)->get();
            if ($due->isEmpty()) {
                return 0;
            }
            $authorized = $this->authorized($family)->whereIn('d.id', $due->pluck('id'))
                ->get(['d.id', 'recipient.user_id', 'e.source_type'])->keyBy('id');
            $preferences = [];
            $delivered = 0;
            foreach ($due as $delivery) {
                $recipient = $authorized->get($delivery->id);
                $state = ! $recipient ? 'cancelled' : (CarbonImmutable::parse($delivery->expires_at)->lt($now) ? 'expired' : 'delivered');
                if ($recipient && ! isset($preferences[$recipient->user_id])) {
                    $preferences[$recipient->user_id] = $this->preferences->forUser($recipient->user_id);
                }
                $channels = $recipient ? $preferences[$recipient->user_id][$this->preferences->category($recipient->source_type)] : ['in_app' => false, 'email' => false];
                DB::table('calendar_reminder_deliveries')->where('id', $delivery->id)->where('state', 'pending')->update([
                    'state' => $state, 'delivered_at' => $state === 'delivered' ? $now->toIso8601String() : null,
                    'in_app_enabled' => $state === 'delivered' && $channels['in_app'],
                    'email_state' => $state === 'delivered' && $channels['email'] ? 'pending' : null,
                ]);
                if ($state === 'delivered' && $channels['in_app']) {
                    $delivered++;
                }
            }

            return $delivered;
        });
    }
}
