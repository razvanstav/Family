<?php

namespace App\Platform\Contracts;

final readonly class StoredEvent
{
    public function __construct(
        public string $id,
        public string $familyId,
        public string $name,
        public int $schemaVersion,
        public string $sourceKind,
        public string $sourceId,
        public int $sourceVersion,
        public string $occurredAt,
    ) {}
}
