<?php

namespace App\Modules\Tasks\Application;

use App\Modules\Calendar\Contracts\AutomaticReminders;
use App\Modules\Calendar\Contracts\SourceItems;
use App\Platform\Contracts\NotificationPreferences;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class TaskCalendar implements AutomaticReminders
{
    public function __construct(private readonly SourceItems $calendar, private readonly TaskDeadlineSource $source, private readonly NotificationPreferences $preferences) {}

    private function tasks(string $family): Builder
    {
        return DB::table('family_tasks as t')->leftJoin('family_memberships as assignee', function ($join) {
            $join->on('assignee.id', '=', 't.assignee_membership_id')->on('assignee.family_id', '=', 't.family_id')->whereNull('assignee.revoked_at');
        })->where('t.family_id', $family)->select('t.*', 'assignee.user_id as recipient_user_id');
    }

    public function syncTask(string $family, string $task): void
    {
        $this->sync($family, $this->tasks($family)->where('t.id', $task)->firstOrFail());
    }

    public function syncFamily(string $family): void
    {
        $preferences = [];
        $this->tasks($family)->orderBy('t.id')->chunk(100, function ($tasks) use ($family, &$preferences): void {
            $sources = [];
            $reminders = [];
            foreach ($tasks as $task) {
                $user = $task->recipient_user_id;
                if ($user && ! isset($preferences[$user])) {
                    $preferences[$user] = $this->preferences->forUser($user)['tasks'];
                }
                [$source, $reminder] = $this->projection($task, $user ? $preferences[$user] : ['in_app' => false, 'email' => false]);
                $sources[] = $source;
                $reminders[$task->id] = $reminder;
            }
            $this->calendar->syncMany($family, $this->source->type(), $sources, $reminders);
        });
    }

    private function sync(string $family, object $task): void
    {
        $settings = $task->recipient_user_id ? $this->preferences->forUser($task->recipient_user_id)['tasks'] : ['in_app' => false, 'email' => false];
        [$source, $reminder] = $this->projection($task, $settings);
        $this->calendar->sync($family, $source, $reminder);
    }

    private function projection(object $task, array $settings): array
    {
        $active = $task->due_on !== null && $task->completed_at === null && $task->deleted_at === null;
        $notify = $active && ($task->visibility === 'family' || $task->owner_id === $task->recipient_user_id)
            && ($settings['in_app'] || $settings['email']);

        return [['type' => $this->source->type(), 'id' => $task->id, 'parent_id' => $task->id,
            'version' => (int) $task->version, 'owner_id' => $task->owner_id, 'title' => $task->title,
            'date' => $task->due_on ?? substr($task->created_at, 0, 10), 'visibility' => $task->visibility,
            'active' => $active],
            ['offset' => $notify ? 0 : null, 'recipients' => $notify ? [$task->assignee_membership_id] : []]];
    }
}
