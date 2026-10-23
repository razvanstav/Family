<?php

namespace App\Modules\Calendar\Contracts;

interface BirthdaySource extends DeadlineSource
{
    /** Canonical shared day/month occurrences; caller supplies the tenant transaction.
     * @return list<array{type:string,id:string,parent_id:string,version:int,owner_id:int,title:string,date:string,active:bool}>
     */
    public function occurrences(string $family, int $year): array;
}
