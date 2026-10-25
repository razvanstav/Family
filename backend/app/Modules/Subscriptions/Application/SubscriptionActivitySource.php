<?php

namespace App\Modules\Subscriptions\Application;

use App\Platform\Audit\ActivityEvents;
use App\Platform\Contracts\ActivitySource;
use Carbon\CarbonImmutable;

final class SubscriptionActivitySource implements ActivitySource
{
    public function __construct(private readonly SubscriptionAccess $access) {}

    public function forDay(string $family, int $actor, CarbonImmutable $from, CarbonImmutable $until, int $limit): array
    {
        $visible = $this->access->query($family, $actor, false)->where('s.family_id', $family)
            ->select('s.id as resource_id', 's.service_name as title');
        $query = ActivityEvents::query($family, ['subscription.created'], $from, $until)
            ->joinSub($visible, 'visible', 'visible.resource_id', '=', 'activity.resource_id')->select('visible.title');

        return ActivityEvents::read($query, $limit, fn (object $row): array => ['kind' => 'subscription', 'id' => $row->resource_id]);
    }
}
