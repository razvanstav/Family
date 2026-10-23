<?php

namespace App\Modules\Calendar\Contracts;

interface Overview
{
    /** Current authorized agenda and reminders, inside the actor's tenant transaction.
     * @return array{today:string,month:string,days:mixed,events:mixed,reminders:mixed}
     */
    public function home(string $family, int $actor, string $month): array;
}
