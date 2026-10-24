<?php

namespace App\Modules\Calendar\Contracts;

use Illuminate\Database\Query\Builder;

interface SourceItems
{
    /** Projection of source_id, calendar_event_id, reminder_offset_minutes and recipients_json; tenant transaction required. */
    public function settings(string $family, string $type): Builder;

    /** Synchronize atomically with the canonical source; null reminder preserves its settings.
     * @param  array{type:string,id:string,parent_id:string,version:int,owner_id:int,title:string,date:string,active:bool,visibility?:string}  $source
     * @param  array{offset:?int,recipients:list<string>,offsets?:array<string,int>}|null  $reminder
     */
    public function sync(string $family, array $source, ?array $reminder = null): void;

    /** Batch equivalent of sync; reminders are indexed by source ID. */
    public function syncMany(string $family, string $type, array $sources, array $reminders): void;
}
