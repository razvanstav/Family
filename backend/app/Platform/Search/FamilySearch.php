<?php

namespace App\Platform\Search;

use App\Platform\Contracts\SearchProvider;
use Illuminate\Support\Facades\DB;

final class FamilySearch
{
    /** @param iterable<SearchProvider> $providers */
    public function __construct(private readonly iterable $providers) {}

    public function results(string $family, int $actor, string $membership, string $text, ?string $type, int $page): array
    {
        $term = new SearchTerm($text);
        $combined = null;
        foreach ($this->providers as $provider) {
            if ($type !== null && $provider->type() !== $type) {
                continue;
            }
            $query = $provider->query($family, $actor, $membership, $term);
            $combined = $combined === null ? $query : $combined->unionAll($query);
        }
        $title = $term->normalized('title');
        $rows = DB::query()->fromSub($combined, 'results')->orderByRaw('CASE WHEN '.$title.' = ? THEN 0 WHEN '.$title.' LIKE ? THEN 1 ELSE 2 END', [$term->folded, $term->prefix()])
            ->orderBy('title')->orderBy('type')->orderBy('id')->offset(($page - 1) * 12)->limit(13)->get();

        return ['items' => $rows->take(12)->values(), 'page' => $page, 'has_more' => $rows->count() > 12];
    }
}
