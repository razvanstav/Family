<?php

namespace App\Modules\Tasks\Application;

use App\Modules\Calendar\Contracts\DeadlineSource;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class TaskDeadlineSource implements DeadlineSource
{
    public function type(): string
    {
        return 'task';
    }

    public function references(string $family): Builder
    {
        return DB::table('family_tasks')->where('family_id', $family)->whereNull('deleted_at')
            ->whereNull('completed_at')->whereNotNull('due_on')
            ->select('id', 'id as parent_id', 'version')->selectRaw('true as active');
    }
}
