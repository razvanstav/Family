<?php

namespace App\Modules\Memory\Application;

use App\Modules\Calendar\Contracts\DatedRecords;
use Illuminate\Database\Query\Builder;

final class MemoryDatedRecords implements DatedRecords
{
    public function __construct(private readonly MomentAccess $access) {}

    public function records(string $family, int $actor, string $membership): Builder
    {
        return $this->access->visible($family, $actor)->whereNull('memory_moments.archived_at')
            ->join('users as author', 'author.id', '=', 'memory_moments.owner_id')
            ->select('memory_moments.id as source_id', 'memory_moments.id as source_parent_id', 'memory_moments.occurred_on as event_date', 'memory_moments.title', 'memory_moments.visibility', 'memory_moments.owner_id', 'author.name as owner_name', 'memory_moments.version')
            ->selectRaw("md5('memory_date:' || memory_moments.id::text)::uuid as id, 'memory_date'::text as source_type, memory_moments.kind::text as source_kind");
    }
}
