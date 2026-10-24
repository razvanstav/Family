<?php

namespace App\Platform;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

final class RecurrenceDates
{
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
            throw new InvalidArgumentException('Unknown recurrence.');
        }

        return $next->format('Y-m-d') <= '2099-12-31' ? $next->format('Y-m-d') : null;
    }
}
