<?php

namespace App\Modules\Calendar\Application;

use App\Modules\Calendar\Contracts\AutomaticReminders;
use App\Modules\Calendar\Contracts\ReminderReconciliation;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class ReminderReconciler implements ReminderReconciliation
{
    /** @param iterable<AutomaticReminders> $sources */
    public function __construct(private readonly BirthdayCalendar $birthdays, private readonly iterable $sources) {}

    public function invalidateFamily(string $family): void
    {
        DB::table('calendar_reminder_syncs')->upsert([
            'family_id' => $family, 'dirty' => true, 'reconciled_on' => null,
        ], ['family_id'], ['dirty']);
    }

    public function invalidateUser(int $user): void
    {
        $families = DB::table('family_memberships')->where('user_id', $user)->whereNull('revoked_at')->orderBy('family_id')->pluck('family_id');
        DB::transaction(function () use ($families): void {
            $previous = DB::selectOne("SELECT current_setting('app.family_id', true) AS family_id")->family_id ?? '';
            try {
                foreach ($families as $family) {
                    DB::select("SELECT set_config('app.family_id', ?, true)", [$family]);
                    $this->invalidateFamily($family);
                }
            } finally {
                DB::select("SELECT set_config('app.family_id', ?, true)", [$previous]);
            }
        });
    }

    public function synchronizeFamily(string $family): bool
    {
        return $this->inFamily($family, fn (): bool => $this->reconcile($family)) ?? false;
    }

    /** @return array{reminders: bool, email: bool} */
    public function prepareFamily(string $family): array
    {
        return $this->inFamily($family, function () use ($family): array {
            $this->reconcile($family);
            $now = CarbonImmutable::now();
            $reminders = DB::table('calendar_reminder_deliveries')->where('family_id', $family)->where('state', 'pending')
                ->where('due_at', '<=', $now->toIso8601String())->exists();
            $email = DB::table('calendar_reminder_deliveries')->where('family_id', $family)
                ->where(function (Builder $work) use ($now): void {
                    $work->where(function (Builder $pending) use ($now): void {
                        $pending->where('email_state', 'pending')->where(fn (Builder $attempt) => $attempt->whereNull('email_next_attempt_at')->orWhere('email_next_attempt_at', '<=', $now->toIso8601String()));
                    })->orWhere(fn (Builder $claim) => $claim->where('email_state', 'sending')->where('email_claimed_at', '<=', $now->subMinutes(5)->toIso8601String()));
                })->exists();

            return ['reminders' => $reminders, 'email' => $email];
        }) ?? ['reminders' => false, 'email' => false];
    }

    private function reconcile(string $family): bool
    {
        $today = now('Europe/Bucharest')->toDateString();
        $checkpoint = DB::table('calendar_reminder_syncs')->where('family_id', $family)->lockForUpdate()->first();
        if ($checkpoint && ! $checkpoint->dirty && $checkpoint->reconciled_on === $today) {
            return false;
        }
        foreach ($this->sources as $source) {
            $source->syncFamily($family);
        }
        $this->birthdays->syncFamily($family);
        DB::table('calendar_reminder_syncs')->upsert([
            'family_id' => $family, 'dirty' => false, 'reconciled_on' => $today,
        ], ['family_id'], ['dirty', 'reconciled_on']);

        return true;
    }

    private function inFamily(string $family, Closure $callback): mixed
    {
        return DB::transaction(function () use ($family, $callback): mixed {
            if (! DB::table('families')->where('id', $family)->lockForUpdate()->first()) {
                return null;
            }
            DB::select("SELECT set_config('app.family_id', ?, true)", [$family]);

            return $callback();
        });
    }
}
