<?php

namespace App\Modules\Kitchen\Contracts;

interface Recipes
{
    /** @return array{items:array,has_more:bool,page:int} */
    public function listing(string $familyId, int $actorId, array $filters): array;

    public function find(string $familyId, int $actorId, string $recipeId): array;

    public function findVersion(string $familyId, int $actorId, string $recipeId, string $versionId): array;

    public function history(string $familyId, int $actorId, string $recipeId, int $page): array;
}
