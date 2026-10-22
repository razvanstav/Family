<?php

namespace App\Platform\Contracts;

interface Outbox
{
    /** Requires the caller's open tenant transaction; stores identifiers only. */
    public function append(string $familyId, string $name, string $sourceKind, string $sourceId, int $sourceVersion): string;
}
