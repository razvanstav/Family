<?php

namespace App\Modules\Pets\Application;

use App\Platform\Audit\ActivityEvents;
use App\Platform\Contracts\ActivitySource;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class PetActivitySource implements ActivitySource
{
    public function __construct(private readonly PetDeadlineSource $source) {}

    public function forDay(string $family, int $actor, CarbonImmutable $from, CarbonImmutable $until, int $limit): array
    {
        $pets = DB::table('pets as p')->where('p.family_id', $family)->whereNull('p.archived_at')
            ->select('p.id as resource_id', 'p.name as title')
            ->selectRaw("'pet.created'::text as source_action, 'pet'::text as source_kind, NULL::uuid as parent_id");
        $entries = $this->source->entries($family)->whereNull('e.archived_at')->whereNull('p.archived_at')
            ->select('e.id as resource_id', 'e.title')
            ->selectRaw("'pet.entry_created'::text as source_action, 'pet_entry'::text as source_kind")
            ->addSelect('e.pet_id as parent_id');
        $query = ActivityEvents::query($family, ['pet.created', 'pet.entry_created'], $from, $until)
            ->joinSub($pets->unionAll($entries), 'visible', function ($join) {
                $join->on('visible.resource_id', '=', 'activity.resource_id')->on('visible.source_action', '=', 'activity.action');
            })->select('visible.title', 'visible.source_kind', 'visible.parent_id');

        return ActivityEvents::read($query, $limit, fn (object $row): array => $row->source_kind === 'pet_entry'
            ? ['kind' => 'pet_entry', 'id' => $row->resource_id, 'parent_id' => $row->parent_id]
            : ['kind' => 'pet', 'id' => $row->resource_id]);
    }
}
