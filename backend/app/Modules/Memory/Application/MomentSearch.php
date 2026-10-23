<?php

namespace App\Modules\Memory\Application;

use App\Platform\Contracts\SearchProvider;
use App\Platform\Search\SearchTerm;
use Illuminate\Database\Query\Builder;

final class MomentSearch implements SearchProvider
{
    public function __construct(private readonly MomentAccess $access) {}

    public function type(): string
    {
        return 'moment';
    }

    public function query(string $family, int $actor, string $membership, SearchTerm $term): Builder
    {
        return $term->constrain($this->access->visible($family, $actor)->whereNull('memory_moments.archived_at'), ['memory_moments.title', 'memory_moments.body'])
            ->selectRaw("'moment'::text as type, memory_moments.id, memory_moments.title, left(memory_moments.body, 140) as excerpt, memory_moments.kind as category, memory_moments.visibility, NULL::uuid as member_id, memory_moments.occurred_on as date");
    }
}
