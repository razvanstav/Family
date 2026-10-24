<?php

namespace App\Modules\Pets\Application;

use App\Modules\Calendar\Contracts\DatedRecords;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class PetDatedRecords implements DatedRecords
{
    public function __construct(private readonly PetDeadlineSource $source) {}

    public function records(string $family, int $actor, string $membership): Builder
    {
        $birth = DB::table('pets as p')->join('users as author', 'author.id', '=', 'p.created_by')
            ->where('p.family_id', $family)->whereNull('p.archived_at')->whereNotNull('p.birth_date')
            ->select('p.id as source_id', 'p.id as source_parent_id', 'p.birth_date as event_date', 'p.created_by as owner_id', 'author.name as owner_name', 'p.version')
            ->selectRaw("md5('pet_birth:' || p.id::text)::uuid as id, 'pet_birth'::text as source_type, NULL::text as source_kind, 'family'::text as visibility, left('Naștere · ' || p.name, 160)::text as title");
        $entries = $this->source->entries($family)->join('users as author', 'author.id', '=', 'e.author_id')
            ->whereNull('e.archived_at')->whereNull('p.archived_at')
            ->select('e.id as source_id', 'e.pet_id as source_parent_id', 'e.event_date', 'e.author_id as owner_id', 'author.name as owner_name', 'e.version')
            ->selectRaw("md5('pet_record:' || e.id::text)::uuid as id, 'pet_record'::text as source_type, e.kind::text as source_kind, 'family'::text as visibility, left(e.title || ' · ' || p.name, 160)::text as title");
        $due = $this->source->entries($family)->join('users as author', 'author.id', '=', 'e.author_id')
            ->whereNull('e.archived_at')->whereNull('p.archived_at')->whereNotNull('e.next_due_on')
            ->select('e.id as source_id', 'e.pet_id as source_parent_id', 'e.next_due_on as event_date', 'e.author_id as owner_id', 'author.name as owner_name', 'e.version')
            ->selectRaw("md5('pet_due:' || e.id::text)::uuid as id, 'pet_due'::text as source_type, e.kind::text as source_kind, 'family'::text as visibility, left(e.title || ' · ' || p.name, 160)::text as title");

        return $birth->unionAll($entries)->unionAll($due);
    }
}
