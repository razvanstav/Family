<?php

namespace App\Modules\Kitchen\Contracts;

interface Activity
{
    /** Caller supplies the actor's tenant transaction; cards use current source authorization.
     * @return array{items:array,has_more:bool,next_cursor:?string}
     */
    public function listing(string $familyId, int $actorId, string $membershipId, ?string $cursor, int $limit = 20): array;

    /** Current authorized meal summaries, keyed by source ID; at most 200 identifiers per call.
     * @param  list<string>  $ids
     * @return array<string,array>
     */
    public function mealCards(string $familyId, int $actorId, string $membershipId, array $ids): array;
}
