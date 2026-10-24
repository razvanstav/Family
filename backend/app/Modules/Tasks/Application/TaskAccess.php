<?php

namespace App\Modules\Tasks\Application;

use App\Modules\Tasks\Contracts\TaskSummaries;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class TaskAccess implements TaskSummaries
{
    public function query(string $family, int $actor): Builder
    {
        return DB::table('family_tasks as t')->where('t.family_id', $family)->whereNull('t.deleted_at')
            ->where(fn (Builder $access) => $access->where('t.visibility', 'family')->orWhere('t.owner_id', $actor));
    }

    public function rows(Builder $query): Builder
    {
        return $query->join('users as owner', 'owner.id', '=', 't.owner_id')
            ->join('family_memberships as assignee', function ($join) {
                $join->on('assignee.family_id', '=', 't.family_id')->on('assignee.id', '=', 't.assignee_membership_id');
            })->join('users as assigned_to', 'assigned_to.id', '=', 'assignee.user_id')
            ->select('t.*', 'owner.name as owner_name', 'assigned_to.name as assignee_name');
    }

    public function canComplete(object $row, int $actor, string $membership, string $role): bool
    {
        return $row->assignee_membership_id === $membership || ($row->owner_id === $actor && $role !== 'limited');
    }

    public function payload(object $row, int $actor, string $membership, string $role): array
    {
        return ['id' => $row->id, 'title' => $row->title, 'assignee_membership_id' => $row->assignee_membership_id,
            'assignee_name' => $row->assignee_name, 'due_on' => $row->due_on, 'visibility' => $row->visibility,
            'owner_name' => $row->owner_name, 'completed_at' => $row->completed_at, 'recurrence' => $row->recurrence, 'version' => (int) $row->version,
            'can_edit' => $row->owner_id === $actor && $role !== 'limited',
            'can_complete' => $this->canComplete($row, $actor, $membership, $role)];
    }

    public function forHome(string $family, int $actor, string $membership, string $role): Collection
    {
        return $this->rows($this->query($family, $actor))->whereNull('t.completed_at')
            ->orderByRaw('t.due_on ASC NULLS LAST')->orderByDesc('t.created_at')->orderByDesc('t.id')->limit(5)->get()
            ->map(fn (object $row) => $this->payload($row, $actor, $membership, $role));
    }
}
