<?php

namespace App\Platform\Search;

use Illuminate\Database\Query\Builder;

final readonly class SearchTerm
{
    public string $folded;

    public function __construct(string $text)
    {
        $this->folded = strtr(mb_strtolower($text), ['ă' => 'a', 'â' => 'a', 'î' => 'i', 'ș' => 's', 'ş' => 's', 'ț' => 't', 'ţ' => 't']);
    }

    public function normalized(string $expression): string
    {
        return 'translate(lower(coalesce('.$expression.", '')), 'ăâîșşțţ', 'aaisstt')";
    }

    public function prefix(): string
    {
        return addcslashes($this->folded, '%_\\').'%';
    }

    /** @param list<string> $expressions Trusted provider-owned SQL expressions, never request data. */
    public function constrain(Builder $query, array $expressions): Builder
    {
        return $query->where(function (Builder $matches) use ($expressions): void {
            foreach ($expressions as $expression) {
                $matches->orWhereRaw($this->normalized($expression).' LIKE ?', ['%'.$this->prefix()]);
            }
        });
    }
}
