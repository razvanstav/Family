<?php

namespace App\Modules\Calendar\Contracts;

use Illuminate\Database\Query\Builder;

interface DatedRecords
{
    /**
     * Authorized canonical dates, read without creating events or reminder intents.
     * Selects id, source_type, source_id, source_parent_id, source_kind, event_date,
     * title, visibility, owner_id, owner_name and version; one row for each user-entered date.
     */
    public function records(string $family, int $actor, string $membership): Builder;
}
