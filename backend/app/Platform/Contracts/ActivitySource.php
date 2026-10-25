<?php

namespace App\Platform\Contracts;

use Carbon\CarbonImmutable;

interface ActivitySource
{
    /**
     * Current authorized activity inside the active member's tenant transaction.
     * The half-open interval is expressed as instants; limit follows source authorization.
     *
     * @return list<array{id:string,action:string,actor_name:string,occurred_at:string,title:string,target:array<string,mixed>}>
     */
    public function forDay(string $family, int $actor, CarbonImmutable $from, CarbonImmutable $until, int $limit): array;
}
