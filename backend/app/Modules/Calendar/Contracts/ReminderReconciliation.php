<?php

namespace App\Modules\Calendar\Contracts;

interface ReminderReconciliation
{
    /** Mark automatic reminder sources for reconciliation in the current tenant transaction. */
    public function invalidateFamily(string $family): void;

    /** Mark the active families of an account after its notification preferences change. */
    public function invalidateUser(int $user): void;
}
