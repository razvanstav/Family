<?php

namespace App\Modules\Documents\Application;

use App\Modules\Calendar\Contracts\DatedRecords;
use Illuminate\Database\Query\Builder;

final class DocumentDatedRecords implements DatedRecords
{
    public function __construct(private readonly DocumentAccess $access) {}

    public function records(string $family, int $actor, string $membership): Builder
    {
        return $this->access->visible($family, $actor, $membership)
            ->join('users as author', 'author.id', '=', 'd.owner_id')->whereNotNull('d.expires_on')
            ->select('d.id as source_id', 'd.id as source_parent_id', 'd.expires_on as event_date', 'd.title', 'd.visibility', 'd.owner_id', 'author.name as owner_name', 'd.version')
            ->selectRaw("md5('document_date:' || d.id::text)::uuid as id, 'document_date'::text as source_type, NULL::text as source_kind");
    }
}
