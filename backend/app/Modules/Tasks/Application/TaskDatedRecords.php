<?php

namespace App\Modules\Tasks\Application;

use App\Modules\Calendar\Contracts\DatedRecords;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class TaskDatedRecords implements DatedRecords
{
    public function records(string $family, int $actor, string $membership): Builder
    {
        return DB::table('family_tasks as t')->join('users as author', 'author.id', '=', 't.owner_id')
            ->where('t.family_id', $family)->whereNull('t.deleted_at')->whereNotNull('t.due_on')
            ->where(fn (Builder $access) => $access->where('t.owner_id', $actor)->orWhere('t.visibility', 'family'))
            ->select('t.id as source_id', 't.id as source_parent_id', 't.due_on as event_date', 't.title', 't.visibility', 't.owner_id', 'author.name as owner_name', 't.version')
            ->selectRaw("md5('task_date:' || t.id::text)::uuid as id, 'task_date'::text as source_type, NULL::text as source_kind");
    }
}
