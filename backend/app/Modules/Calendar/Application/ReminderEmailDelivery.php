<?php

namespace App\Modules\Calendar\Application;

use App\Platform\Contracts\NotificationPreferences;
use App\Platform\Contracts\ReminderEmailSender;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use LogicException;
use Throwable;

final class ReminderEmailDelivery
{
    public function __construct(private readonly CalendarReminders $reminders, private readonly NotificationPreferences $preferences, private readonly ReminderEmailSender $sender) {}

    /** SMTP acceptance is recorded independently from the in-app delivery and never retried after an uncertain result. */
    public function deliverFamily(string $family): int
    {
        if (DB::transactionLevel() !== 0) {
            throw new LogicException('Reminder emails require an independent delivery boundary.');
        }
        $ids = $this->inFamily($family, function () use ($family): array {
            DB::table('calendar_reminder_deliveries')->where('family_id', $family)->where('email_state', 'sending')
                ->where('email_claimed_at', '<=', now()->subMinutes(5)->toIso8601String())
                ->update(['email_state' => 'uncertain', 'email_error_code' => 'interrupted_delivery']);

            return DB::table('calendar_reminder_deliveries')->where('family_id', $family)->where('email_state', 'pending')
                ->where(fn (Builder $query) => $query->whereNull('email_next_attempt_at')->orWhere('email_next_attempt_at', '<=', now()->toIso8601String()))
                ->orderBy('due_at')->orderBy('id')->limit(100)->pluck('id')->all();
        }) ?? [];
        $accepted = 0;
        foreach ($ids as $id) {
            if (! $this->claim($family, $id)) {
                continue;
            }
            try {
                // The earlier claim stays committed even if this authorization lock or SMTP fails.
                $sent = $this->inFamily($family, function () use ($family, $id): bool {
                    $payload = $this->eligible($family, $id, 'sending');
                    if ($payload === null) {
                        return false;
                    }
                    $title = preg_replace('/[\r\n]+/u', ' ', $payload->title);
                    $date = CarbonImmutable::parse($payload->event_date)->locale('ro')->translatedFormat('j F Y');
                    if (! $payload->all_day) {
                        $date .= ', '.CarbonImmutable::parse($payload->starts_at)->setTimezone('Europe/Bucharest')->format('H:i').' (ora României)';
                    }
                    $body = $title."\n".$payload->family_name."\n".$date."\n\nDeschide Amora: ".rtrim(config('app.url'), '/')."/app/\n\nPreferințele pentru notificări sunt în Contul meu → Setări.";
                    $this->sender->send($payload->email, 'Amora — '.$title, $body);

                    return true;
                });
            } catch (Throwable $error) {
                $this->finish($family, $id, ['email_state' => 'uncertain', 'email_error_code' => 'transport_uncertain']);
                report($error);

                continue;
            }
            if (! $sent) {
                continue;
            }
            $this->finish($family, $id, ['email_state' => 'sent', 'email_sent_at' => now()->toIso8601String(), 'email_error_code' => null]);
            $accepted++;
        }

        return $accepted;
    }

    private function claim(string $family, string $id): bool
    {
        return $this->inFamily($family, function () use ($family, $id): bool {
            if ($this->eligible($family, $id, 'pending') === null) {
                return false;
            }

            return DB::table('calendar_reminder_deliveries')->where('family_id', $family)->where('id', $id)->where('email_state', 'pending')
                ->update(['email_state' => 'sending', 'email_claimed_at' => now()->toIso8601String(), 'email_next_attempt_at' => null,
                    'email_attempts' => DB::raw('email_attempts + 1'), 'email_error_code' => null]) === 1;
        }) ?? false;
    }

    private function eligible(string $family, string $id, string $state): ?object
    {
        $delivery = DB::table('calendar_reminder_deliveries')->where('family_id', $family)->where('id', $id)->where('email_state', $state)->first();
        if ($delivery === null) {
            return null;
        }
        $payload = $this->reminders->authorized($family)->join('users as user', 'user.id', '=', 'recipient.user_id')
            ->join('families as family', 'family.id', '=', 'd.family_id')->where('d.id', $id)->where('d.state', 'delivered')
            ->when($state === 'sending', fn (Builder $query) => $query->lock('FOR UPDATE OF "user"'))
            ->first(['recipient.user_id', 'user.email', 'family.name as family_name', 'e.title', 'e.event_date', 'e.starts_at', 'e.all_day', 'e.source_type']);
        if ($payload === null || ! $this->preferences->forUser($payload->user_id)[$this->preferences->category($payload->source_type)]['email']) {
            $this->updateState($family, $id, $state, ['email_state' => 'cancelled', 'email_error_code' => null]);

            return null;
        }
        if (CarbonImmutable::parse($delivery->expires_at)->lt(now())) {
            $this->updateState($family, $id, $state, ['email_state' => 'expired', 'email_error_code' => null]);

            return null;
        }
        if (! $this->sender->ready()) {
            $this->updateState($family, $id, $state, ['email_state' => 'pending', 'email_claimed_at' => null,
                'email_next_attempt_at' => now()->addMinutes(5)->toIso8601String(), 'email_error_code' => 'smtp_unavailable']);

            return null;
        }

        return $payload;
    }

    private function finish(string $family, string $id, array $values): void
    {
        $this->inFamily($family, fn (): int => $this->updateState($family, $id, 'sending', $values));
    }

    private function updateState(string $family, string $id, string $expectedState, array $values): int
    {
        return DB::table('calendar_reminder_deliveries')->where('family_id', $family)->where('id', $id)->where('email_state', $expectedState)->update($values);
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
