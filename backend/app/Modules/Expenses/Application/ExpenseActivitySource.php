<?php

namespace App\Modules\Expenses\Application;

use App\Platform\Audit\ActivityEvents;
use App\Platform\Contracts\ActivitySource;
use Carbon\CarbonImmutable;

final class ExpenseActivitySource implements ActivitySource
{
    public function __construct(private readonly ExpenseAccess $access) {}

    public function forDay(string $family, int $actor, CarbonImmutable $from, CarbonImmutable $until, int $limit): array
    {
        $visible = $this->access->query($family, $actor)->select('e.id', 'e.visibility', 'e.spent_on', 'e.payment_status')
            ->selectRaw("coalesce(nullif(btrim(e.note), ''), CASE e.category WHEN 'food' THEN 'Alimente' WHEN 'home' THEN 'Casă' WHEN 'transport' THEN 'Transport' WHEN 'health' THEN 'Sănătate' WHEN 'children' THEN 'Copii' WHEN 'leisure' THEN 'Timp liber' ELSE 'Altele' END) as title");
        $query = ActivityEvents::query($family, ['expense.created', 'expense.paid'], $from, $until)
            ->joinSub($visible, 'expense_source', 'expense_source.id', '=', 'activity.resource_id')
            ->addSelect('expense_source.title', 'expense_source.visibility', 'expense_source.spent_on', 'expense_source.payment_status');

        return ActivityEvents::read($query, $limit, fn (object $row): array => ['kind' => 'expense', 'id' => (string) $row->resource_id,
            'date' => $row->spent_on, 'visibility' => $row->visibility, 'payment_status' => $row->payment_status]);
    }
}
