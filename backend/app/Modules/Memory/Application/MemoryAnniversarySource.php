<?php

namespace App\Modules\Memory\Application;

use App\Modules\Calendar\Contracts\DeadlineSource;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class MemoryAnniversarySource implements DeadlineSource
{
    public function __construct(private readonly MomentAccess $access) {}

    public function type(): string
    {
        return 'memory_anniversary';
    }

    public function moments(string $family): Builder
    {
        return $this->access->recipients($family)->whereNull('memory_moments.archived_at');
    }

    public function references(string $family): Builder
    {
        return $this->moments($family)->crossJoin(DB::raw('generate_series(1900, 2100) as memory_year(year)'))
            ->whereRaw('memory_year.year > extract(year from memory_moments.occurred_on)')
            ->select('memory_moments.id as parent_id', 'memory_moments.version')
            ->selectRaw("md5('memory-anniversary:' || memory_moments.id::text || ':' || reader.id::text || ':' || memory_year.year::text)::uuid as id, true as active");
    }
}
