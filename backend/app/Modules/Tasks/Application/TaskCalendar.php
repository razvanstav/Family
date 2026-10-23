<?php

namespace App\Modules\Tasks\Application;

use App\Modules\Calendar\Contracts\SourceItems;
use Illuminate\Support\Facades\DB;

final class TaskCalendar
{
    public function __construct(private readonly SourceItems $calendar, private readonly TaskDeadlineSource $source) {}

    public function syncTask(string $family, string $task): void
    {
        $this->sync($family, DB::table('family_tasks')->where('family_id', $family)->where('id', $task)->firstOrFail());
    }

    public function syncFamily(string $family): void
    {
        DB::table('family_tasks')->where('family_id', $family)->orderBy('id')->chunk(100, function ($tasks) use ($family): void {
            foreach ($tasks as $task) {
                $this->sync($family, $task);
            }
        });
    }

    private function sync(string $family, object $task): void
    {
        $this->calendar->sync($family, ['type' => $this->source->type(), 'id' => $task->id, 'parent_id' => $task->id,
            'version' => (int) $task->version, 'owner_id' => $task->owner_id, 'title' => $task->title,
            'date' => $task->due_on ?? substr($task->created_at, 0, 10), 'visibility' => $task->visibility,
            'active' => $task->due_on !== null && $task->completed_at === null && $task->deleted_at === null],
            ['offset' => null, 'recipients' => []]);
    }
}
