<?php

namespace App\Platform\Contracts;

use App\Platform\Search\SearchTerm;
use Illuminate\Database\Query\Builder;

interface SearchProvider
{
    public function type(): string;

    /** Authorized active results: type, id, title, excerpt, category, visibility, member_id and date. */
    public function query(string $family, int $actor, string $membership, SearchTerm $term): Builder;
}
