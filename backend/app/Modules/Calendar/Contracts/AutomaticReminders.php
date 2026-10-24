<?php

namespace App\Modules\Calendar\Contracts;

interface AutomaticReminders
{
    /** Reconcile opted-in automatic sources inside the caller's locked tenant transaction. */
    public function syncFamily(string $family): void;
}
