<?php

namespace App\Modules\Calendar\Contracts;

interface Birthdays
{
    /** Reconcile shared birthdays inside the caller's tenant transaction. */
    public function syncFamily(string $family, ?int $extraYear = null): void;

    public function unshare(string $family, string $member): void;
}
