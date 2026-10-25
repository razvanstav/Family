<?php

namespace App\Modules\Memory\Application;

use App\Platform\Audit\ActivityEvents;
use App\Platform\Contracts\ActivitySource;
use Carbon\CarbonImmutable;

final class MemoryActivitySource implements ActivitySource
{
    public function __construct(private readonly MomentAccess $access) {}

    public function forDay(string $family, int $actor, CarbonImmutable $from, CarbonImmutable $until, int $limit): array
    {
        $visible = $this->access->visible($family, $actor)->whereNull('memory_moments.archived_at')
            ->select('memory_moments.id', 'memory_moments.family_id', 'memory_moments.title');
        $query = ActivityEvents::query($family, ['memory.created'], $from, $until)
            ->joinSub($visible, 'moment', function ($join) {
                $join->on('moment.family_id', '=', 'activity.family_id')->on('moment.id', '=', 'activity.resource_id');
            })->addSelect('moment.title');

        return ActivityEvents::read($query, $limit, fn (object $row): array => ['kind' => 'memory', 'id' => $row->resource_id]);
    }
}
