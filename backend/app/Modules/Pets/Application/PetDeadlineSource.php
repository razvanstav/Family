<?php

namespace App\Modules\Pets\Application;

use App\Modules\Calendar\Contracts\DeadlineSource;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class PetDeadlineSource implements DeadlineSource
{
    public function type(): string
    {
        return 'pet_entry';
    }

    public function references(string $family): Builder
    {
        return $this->entries($family)->whereNotNull('e.next_due_on')
            ->select('e.id', 'e.pet_id as parent_id', 'e.version')
            ->selectRaw('(e.next_due_completed_at IS NULL AND e.archived_at IS NULL AND p.archived_at IS NULL) as active');
    }

    public function entries(string $family): Builder
    {
        return DB::table('pet_entries as e')->join('pets as p', function ($join) {
            $join->on('p.id', '=', 'e.pet_id')->on('p.family_id', '=', 'e.family_id');
        })->where('e.family_id', $family);
    }
}
