<?php

namespace App\Modules\Expenses\Contracts;

use Illuminate\Support\Collection;

interface UpcomingPayments
{
    /** Authorized planned payments due in the next seven days, inside the tenant transaction.
     * @return Collection<int, array<string, mixed>>
     */
    public function forHome(string $family, int $actor, string $role): Collection;
}
