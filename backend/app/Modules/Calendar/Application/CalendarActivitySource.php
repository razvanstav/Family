<?php

namespace App\Modules\Calendar\Application;

use App\Platform\Audit\ActivityEvents;
use App\Platform\Contracts\ActivitySource;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;

final class CalendarActivitySource implements ActivitySource
{
    public function __construct(private readonly CalendarAccess $access) {}

    public function forDay(string $family, int $actor, CarbonImmutable $from, CarbonImmutable $until, int $limit): array
    {
        $visible = $this->access->visible($family, $actor)->whereNull('calendar_items.archived_at')->whereNull('calendar_items.source_type')
            ->whereExists(fn (Builder $member) => $member->selectRaw('1')->from('family_memberships as reader')
                ->whereColumn('reader.family_id', 'calendar_items.family_id')->where('reader.user_id', $actor)->whereNull('reader.revoked_at'))
            ->select('calendar_items.id', 'calendar_items.family_id', 'calendar_items.title', 'calendar_items.event_date');
        $query = ActivityEvents::query($family, ['calendar.created'], $from, $until)
            ->joinSub($visible, 'event', function ($join) {
                $join->on('event.family_id', '=', 'activity.family_id')->on('event.id', '=', 'activity.resource_id');
            })->addSelect('event.title', 'event.event_date');

        return ActivityEvents::read($query, $limit, fn (object $row): array => ['kind' => 'calendar', 'id' => $row->resource_id, 'date' => $row->event_date]);
    }
}
