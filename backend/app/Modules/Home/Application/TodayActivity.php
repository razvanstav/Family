<?php

namespace App\Modules\Home\Application;

use App\Platform\Contracts\ActivitySource;
use Carbon\CarbonImmutable;

final class TodayActivity
{
    /** @param iterable<ActivitySource> $sources */
    public function __construct(private readonly iterable $sources) {}

    /** @return list<array{id:string,action:string,actor_name:string,occurred_at:string,title:string,target:array<string,mixed>}> */
    public function forHome(string $family, int $actor): array
    {
        $from = CarbonImmutable::now('Europe/Bucharest')->startOfDay();
        $until = $from->addDay();
        $items = [];
        foreach ($this->sources as $source) {
            array_push($items, ...$source->forDay($family, $actor, $from, $until, 20));
        }
        usort($items, fn (array $left, array $right): int => strcmp($right['occurred_at'], $left['occurred_at']) ?: strcmp($right['id'], $left['id']));

        return array_slice($items, 0, 20);
    }
}
