<?php

namespace App\Modules\Kitchen\Contracts;

use Closure;

interface MealReferences
{
    /** Caller supplies the tenant transaction. Metadata only; no readable content or media identifiers.
     * @return array{id:string,source_version:int,occurred_on:string,removed:bool}|null
     */
    public function find(string $familyId, string $mealId): ?array;

    /** @param Closure(list<array{id:string,source_version:int,occurred_on:string,removed:bool}>):void $consume */
    public function each(string $familyId, Closure $consume): void;
}
