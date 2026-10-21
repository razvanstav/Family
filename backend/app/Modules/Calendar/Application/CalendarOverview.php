<?php

namespace App\Modules\Calendar\Application;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;

final class CalendarOverview
{
    public function __construct(private readonly CalendarAccess $access, private readonly CalendarReminders $reminders) {}

    public function home(string $family, int $actor, string $month): array
    {
        $now = CarbonImmutable::now();
        $today = $now->setTimezone('Europe/Bucharest')->startOfDay();
        $first = CarbonImmutable::createFromFormat('!Y-m', $month, 'Europe/Bucharest');
        $visible = $this->access->visible($family, $actor)->whereNull('calendar_items.archived_at');
        $days = (clone $visible)->whereBetween('event_date', [$first->toDateString(), $first->endOfMonth()->toDateString()])
            ->distinct()->orderBy('event_date')->pluck('event_date');
        $events = $visible->whereBetween('event_date', [$today->toDateString(), $today->addDays(29)->toDateString()])
            ->where(function (Builder $query) use ($now) {
                $query->where('all_day', true)->orWhere('starts_at', '>=', $now->toIso8601String());
            })->orderBy('event_date')->orderByDesc('all_day')->orderBy('starts_at')->orderBy('calendar_items.id')->limit(5)
            ->get(['id', 'title', 'event_date', 'all_day', 'starts_at', 'visibility'])
            ->map(function (object $event): array {
                $data = (array) $event;
                $data['time'] = $event->starts_at ? CarbonImmutable::parse($event->starts_at)->setTimezone('Europe/Bucharest')->format('H:i') : null;
                unset($data['starts_at']);

                return $data;
            });

        return ['today' => $today->toDateString(), 'month' => $month, 'days' => $days, 'events' => $events,
            'reminders' => $this->reminders->upcoming($family, $actor)];
    }
}
