<?php

namespace App\Modules\Expenses\Application;

use App\Modules\Calendar\Contracts\DatedRecords;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class ExpenseDatedRecords implements DatedRecords
{
    public function records(string $family, int $actor, string $membership): Builder
    {
        return DB::table('expenses as e')->join('users as author', 'author.id', '=', 'e.owner_id')
            ->where('e.family_id', $family)->whereNull('e.deleted_at')
            ->where(fn (Builder $access) => $access->where('e.owner_id', $actor)->orWhere('e.visibility', 'family'))
            ->select('e.id as source_id', 'e.id as source_parent_id', 'e.spent_on as event_date', 'e.visibility', 'e.owner_id', 'author.name as owner_name', 'e.version')
            ->selectRaw("md5('expense:' || e.id::text)::uuid as id, 'expense'::text as source_type, e.category::text as source_kind, left(coalesce(nullif(btrim(e.note), ''), 'Cheltuială'), 160)::text as title");
    }
}
