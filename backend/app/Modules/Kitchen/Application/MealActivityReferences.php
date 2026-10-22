<?php

namespace App\Modules\Kitchen\Application;

use App\Modules\Kitchen\Contracts\MealReferences;
use Closure;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class MealActivityReferences implements MealReferences
{
    private function query(string $familyId): Builder
    {
        return DB::table('kitchen_meals')->where('family_id', $familyId)
            ->select(['id', 'activity_revision as source_version', 'meal_date as occurred_on', DB::raw('archived_at IS NOT NULL AS removed')]);
    }

    public function find(string $familyId, string $mealId): ?array
    {
        $row = $this->query($familyId)->where('id', $mealId)->first();

        return $row ? (array) $row : null;
    }

    public function each(string $familyId, Closure $consume): void
    {
        $this->query($familyId)->chunkById(100, fn ($rows) => $consume($rows->map(fn (object $row): array => (array) $row)->all()));
    }
}
