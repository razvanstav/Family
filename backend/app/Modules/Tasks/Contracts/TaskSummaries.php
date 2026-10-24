<?php

namespace App\Modules\Tasks\Contracts;

use Illuminate\Support\Collection;

interface TaskSummaries
{
    /** Authorized incomplete tasks, inside the actor's tenant transaction.
     * @return Collection<int, array<string, mixed>>
     */
    public function forHome(string $family, int $actor, string $membership, string $role): Collection;
}
