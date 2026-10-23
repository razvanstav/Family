<?php

namespace App\Modules\Memory\Contracts;

use Illuminate\Support\Collection;

interface MomentSummaries
{
    /** Authorized summaries in the actor's tenant transaction; detail and photos remain canonical operations.
     * @return Collection<int, array<string, mixed>>
     */
    public function forHome(string $family, int $actor): Collection;

    /** @param ?array{0:string,1:string,2:string,3:string,4:string} $position
     * @return Collection<int, array<string, mixed>>
     */
    public function forMember(string $family, int $actor, string $member, ?array $position): Collection;
}
