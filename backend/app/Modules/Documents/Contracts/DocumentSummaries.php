<?php

namespace App\Modules\Documents\Contracts;

use Illuminate\Support\Collection;

interface DocumentSummaries
{
    /** Authorized metadata only, in the actor's tenant transaction; detail and bytes require canonical access.
     * @return Collection<int, object>
     */
    public function recent(string $family, int $actor, string $membership): Collection;
}
