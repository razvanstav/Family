<?php

namespace App\Modules\Tasks\Application;

use App\Platform\Audit\ActivityEvents;
use App\Platform\Contracts\ActivitySource;
use Carbon\CarbonImmutable;

final class TaskActivitySource implements ActivitySource
{
    public function __construct(private readonly TaskAccess $access) {}

    public function forDay(string $family, int $actor, CarbonImmutable $from, CarbonImmutable $until, int $limit): array
    {
        $visible = $this->access->query($family, $actor)->select('t.id', 't.title', 't.visibility', 't.due_on');
        $query = ActivityEvents::query($family, ['task.created', 'task.completed', 'task.reopened'], $from, $until)
            ->joinSub($visible, 'task_source', 'task_source.id', '=', 'activity.resource_id')
            ->addSelect('task_source.title', 'task_source.visibility', 'task_source.due_on');

        return ActivityEvents::read($query, $limit, fn (object $row): array => ['kind' => 'task', 'id' => (string) $row->resource_id,
            'date' => $row->due_on, 'visibility' => $row->visibility]);
    }
}
