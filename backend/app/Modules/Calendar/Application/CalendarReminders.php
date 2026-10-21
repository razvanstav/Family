<?php

namespace App\Modules\Calendar\Application;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class CalendarReminders
{
    /** @return array{due_at: CarbonImmutable, expires_at: CarbonImmutable} */
    public function schedule(object $event): array
    {
        $start = $event->all_day
            ? CarbonImmutable::parse($event->event_date.' 09:00', 'Europe/Bucharest')
            : CarbonImmutable::parse($event->starts_at)->utc();
        $due = $event->all_day && $event->reminder_offset_minutes === 1440
            ? $start->subDay()
            : $start->subMinutes($event->reminder_offset_minutes);

        return ['due_at' => $due->utc(), 'expires_at' => $event->all_day
            ? $start->addDay()->startOfDay()->utc()
            : $start->addMinute()->utc()];
    }

    /** @param list<string> $recipients */
    public function configure(string $family, object $event, ?int $offset, array $recipients): void
    {
        if ($offset !== null) {
            $allowed = DB::table('family_memberships')->where('family_id', $family)->whereNull('revoked_at')->whereIn('id', $recipients);
            if ($event->visibility === 'private') {
                $allowed->where('user_id', $event->owner_id);
            }
            if (count($recipients) === 0 || $allowed->count() !== count($recipients)) {
                throw ValidationException::withMessages(['reminder_recipients' => 'Alege membri activi care au acces la eveniment. Pentru un eveniment privat te poți alege doar pe tine.']);
            }
            if ($event->all_day && ! in_array($offset, [0, 1440], true)) {
                throw ValidationException::withMessages(['reminder_offset_minutes' => 'Pentru un eveniment fără oră, alege ziua evenimentului sau ziua precedentă.']);
            }
        } elseif (count($recipients) > 0) {
            throw ValidationException::withMessages(['reminder_recipients' => 'Alege momentul reminderului.']);
        }
        DB::table('calendar_reminder_recipients')->where('calendar_item_id', $event->id)->delete();
        foreach ($recipients as $membership) {
            DB::table('calendar_reminder_recipients')->insert(['family_id' => $family, 'calendar_item_id' => $event->id, 'membership_id' => $membership]);
        }
    }

    /** The event and its durable delivery intents share the caller's tenant transaction. */
    public function replan(string $family, object $event): void
    {
        DB::table('calendar_reminder_deliveries')->where('calendar_item_id', $event->id)
            ->whereIn('state', ['pending', 'delivered'])->update(['state' => 'cancelled']);
        if ($event->archived_at !== null || $event->reminder_offset_minutes === null) {
            return;
        }
        $schedule = $this->schedule($event);
        $recipients = DB::table('calendar_reminder_recipients as r')->join('family_memberships as m', 'm.id', '=', 'r.membership_id')
            ->where('r.calendar_item_id', $event->id)->whereNull('m.revoked_at')->pluck('r.membership_id');
        foreach ($recipients as $membership) {
            DB::table('calendar_reminder_deliveries')->insert([
                'id' => (string) Str::uuid(), 'family_id' => $family, 'calendar_item_id' => $event->id,
                'membership_id' => $membership, 'source_version' => $event->version,
                'due_at' => $schedule['due_at']->toIso8601String(), 'expires_at' => $schedule['expires_at']->toIso8601String(),
                'state' => $schedule['due_at']->lt(now()) ? 'expired' : 'pending',
            ]);
        }
    }

    public function cancelMembership(string $family, string $membership): void
    {
        $owner = DB::table('family_memberships')->where('family_id', $family)->where('id', $membership)->value('user_id');
        $ownedEvents = DB::table('calendar_items')->where('family_id', $family)->where('owner_id', $owner)->select('id');
        DB::table('calendar_reminder_deliveries')->where('family_id', $family)
            ->where(function (Builder $query) use ($membership, $ownedEvents) {
                $query->where('membership_id', $membership)->orWhereIn('calendar_item_id', $ownedEvents);
            })->whereIn('state', ['pending', 'delivered'])->update(['state' => 'cancelled']);
        DB::table('calendar_reminder_recipients')->where('family_id', $family)->where('membership_id', $membership)->delete();
    }

    private function authorized(string $family): Builder
    {
        return DB::table('calendar_reminder_deliveries as d')
            ->join('calendar_items as e', function ($join) {
                $join->on('e.id', '=', 'd.calendar_item_id')->on('e.family_id', '=', 'd.family_id')->on('e.version', '=', 'd.source_version');
            })
            ->join('family_memberships as recipient', function ($join) {
                $join->on('recipient.id', '=', 'd.membership_id')->on('recipient.family_id', '=', 'd.family_id');
            })
            ->join('family_memberships as owner', function ($join) {
                $join->on('owner.user_id', '=', 'e.owner_id')->on('owner.family_id', '=', 'e.family_id');
            })
            ->where('d.family_id', $family)->whereNull('recipient.revoked_at')->whereNull('owner.revoked_at')->whereNull('e.archived_at')
            ->whereNotNull('e.reminder_offset_minutes')
            ->where(function (Builder $query) {
                $query->where('e.visibility', 'family')->orWhereColumn('e.owner_id', 'recipient.user_id');
            });
    }

    public function notifications(string $family, int $user): Builder
    {
        return $this->authorized($family)->where('d.state', 'delivered')->where('recipient.user_id', $user);
    }

    /** @return Collection<int, object> Pending reminders addressed to this actor, never other recipients. */
    public function upcoming(string $family, int $actor): Collection
    {
        $now = CarbonImmutable::now();

        return $this->authorized($family)->where('recipient.user_id', $actor)->where('d.state', 'pending')
            ->where('d.expires_at', '>=', $now->toIso8601String())
            ->where('d.due_at', '<=', $now->addDays(30)->toIso8601String())
            ->orderBy('d.due_at')->orderBy('d.id')->limit(5)
            ->get(['d.id', 'd.calendar_item_id as event_id', 'd.due_at', 'e.title', 'e.event_date'])
            ->map(function (object $reminder): object {
                $reminder->due_at = CarbonImmutable::parse($reminder->due_at)->utc()->toIso8601String();

                return $reminder;
            });
    }

    public function deliverFamily(string $family): int
    {
        return DB::transaction(function () use ($family): int {
            if (! DB::table('families')->where('id', $family)->lockForUpdate()->first()) {
                return 0;
            }
            DB::select("SELECT set_config('app.family_id', ?, true)", [$family]);
            $now = now();
            $due = DB::table('calendar_reminder_deliveries')->where('family_id', $family)->where('state', 'pending')
                ->where('due_at', '<=', $now->toIso8601String())->orderBy('due_at')->orderBy('id')->limit(100)->get();
            $delivered = 0;
            foreach ($due as $delivery) {
                $state = ! $this->authorized($family)->where('d.id', $delivery->id)->exists()
                    ? 'cancelled' : (CarbonImmutable::parse($delivery->expires_at)->lt($now) ? 'expired' : 'delivered');
                DB::table('calendar_reminder_deliveries')->where('id', $delivery->id)->where('state', 'pending')->update([
                    'state' => $state, 'delivered_at' => $state === 'delivered' ? $now->toIso8601String() : null,
                ]);
                if ($state === 'delivered') {
                    $delivered++;
                }
            }

            return $delivered;
        });
    }
}
