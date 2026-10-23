<?php

namespace App\Modules\Calendar\Application;

use App\Modules\Calendar\Contracts\DeadlineSource;
use Illuminate\Database\Query\Builder;

final class CalendarSources
{
    /** @param iterable<DeadlineSource> $sources */
    public function __construct(private readonly iterable $sources) {}

    public function restrict(Builder $query, string $family, string $alias): Builder
    {
        return $query->where(function (Builder $allowed) use ($family, $alias) {
            $allowed->whereNull($alias.'.source_type');
            foreach ($this->sources as $source) {
                $allowed->orWhere(function (Builder $derived) use ($source, $family, $alias) {
                    $derived->where($alias.'.source_type', $source->type())->whereExists(function (Builder $reference) use ($source, $family, $alias) {
                        $reference->selectRaw('1')->fromSub($source->references($family), 'deadline_source')
                            ->whereColumn('deadline_source.id', $alias.'.source_id')
                            ->whereColumn('deadline_source.parent_id', $alias.'.source_parent_id')
                            ->whereColumn('deadline_source.version', $alias.'.source_version')
                            ->whereRaw('deadline_source.active = ('.$alias.'.archived_at IS NULL)');
                    });
                });
            }
        });
    }
}
