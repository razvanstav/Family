<?php

namespace App\Modules\Documents\Contracts;

use Illuminate\Database\Query\Builder;

interface DocumentLinks
{
    /** Authorized metadata projection: id, title, visibility, mime and status. Caller supplies the tenant transaction. */
    public function references(string $family, int $actor, string $membership): Builder;

    /** Recheck canonical access without exposing document persistence to the caller. */
    public function assertVisible(string $family, int $actor, string $membership, string $id): void;
}
