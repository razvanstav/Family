<?php

namespace App\Modules\Tasks\Application;

use App\Platform\Audit\Audit;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class TaskRecurrence
{
    public function __construct(private readonly TaskCalendar $calendar) {}

    public static function nextDate(string $recurrence, string $anchorOn, string $afterOn): ?string
    {
        $zone = new DateTimeZone('UTC');
        $anchor = new DateTimeImmutable($anchorOn, $zone);
        $after = new DateTimeImmutable($afterOn, $zone);
        $days = max(0, (int) $anchor->diff($after)->format('%r%a'));
        if ($recurrence === 'daily' || $recurrence === 'weekly') {
            $interval = $recurrence === 'daily' ? 1 : 7;
            $next = $anchor->modify('+'.((intdiv($days, $interval) + 1) * $interval).' days');
        } elseif ($recurrence === 'monthly' || $recurrence === 'yearly') {
            $months = ((int) $after->format('Y') - (int) $anchor->format('Y')) * 12
                + (int) $after->format('n') - (int) $anchor->format('n');
            $interval = $recurrence === 'monthly' ? 1 : 12;
            $steps = max(1, intdiv(max(0, $months), $interval));
            $month = $anchor->modify('first day of this month')->modify('+'.($steps * $interval).' months');
            $next = $month->setDate((int) $month->format('Y'), (int) $month->format('n'), min((int) $anchor->format('j'), (int) $month->format('t')));
            if ($next <= $after) {
                $month = $month->modify('+'.$interval.' months');
                $next = $month->setDate((int) $month->format('Y'), (int) $month->format('n'), min((int) $anchor->format('j'), (int) $month->format('t')));
            }
        } else {
            throw new InvalidArgumentException('Unknown task recurrence.');
        }

        return $next->format('Y-m-d') <= '2099-12-31' ? $next->format('Y-m-d') : null;
    }

    /** @return array{recurrence_notice: string, next_due_on: ?string} */
    public function createNext(string $family, object $task, int $actor): array
    {
        if (DB::table('family_tasks')->where('family_id', $family)->where('recurrence_parent_id', $task->id)->exists()) {
            return ['recurrence_notice' => 'Sarcina a fost finalizată. Următoarea a fost creată deja; nu am adăugat o copie.', 'next_due_on' => null];
        }
        if (! DB::table('family_memberships')->where('family_id', $family)->where('user_id', $task->owner_id)
            ->whereNull('revoked_at')->where('role', '!=', 'limited')->exists()) {
            return ['recurrence_notice' => 'Sarcina a fost finalizată. Următoarea nu a fost creată: autorul nu mai poate adăuga sarcini în familie.', 'next_due_on' => null];
        }
        if (! DB::table('family_memberships')->where('family_id', $family)->where('id', $task->assignee_membership_id)
            ->whereNull('revoked_at')->whereNotNull('user_id')->exists()) {
            return ['recurrence_notice' => 'Sarcina a fost finalizată. Repetarea s-a oprit: responsabilul nu mai este membru activ.', 'next_due_on' => null];
        }
        $nextOn = self::nextDate($task->recurrence, $task->recurrence_anchor_on, max($task->due_on, now('Europe/Bucharest')->toDateString()));
        if ($nextOn === null) {
            return ['recurrence_notice' => 'Sarcina a fost finalizată. Nu mai există un termen disponibil până la 31 decembrie 2099.', 'next_due_on' => null];
        }
        $id = (string) Str::uuid();
        DB::table('family_tasks')->insert(['id' => $id, 'family_id' => $family, 'owner_id' => $task->owner_id,
            'assignee_membership_id' => $task->assignee_membership_id, 'title' => $task->title, 'due_on' => $nextOn,
            'visibility' => $task->visibility, 'recurrence' => $task->recurrence, 'recurrence_anchor_on' => $task->recurrence_anchor_on,
            'recurrence_parent_id' => $task->id, 'created_at' => now(), 'updated_at' => now()]);
        $this->calendar->syncTask($family, $id);
        Audit::record($family, $actor, 'task.recurrence_created', $id);

        return ['recurrence_notice' => 'Sarcina a fost finalizată și următoarea sarcină a fost creată.', 'next_due_on' => $nextOn];
    }
}
