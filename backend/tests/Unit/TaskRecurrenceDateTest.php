<?php

namespace Tests\Unit;

use App\Modules\Tasks\Application\TaskRecurrence;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

final class TaskRecurrenceDateTest extends TestCase
{
    #[TestWith(['daily', '2026-10-24', '2026-10-24', '2026-10-25'])]
    #[TestWith(['daily', '1900-01-01', '2099-12-30', '2099-12-31'])]
    #[TestWith(['weekly', '2026-10-01', '2026-10-24', '2026-10-29'])]
    #[TestWith(['weekly', '2026-10-01', '2026-10-29', '2026-11-05'])]
    #[TestWith(['monthly', '2026-01-31', '2026-01-31', '2026-02-28'])]
    #[TestWith(['monthly', '2026-01-31', '2026-02-28', '2026-03-31'])]
    #[TestWith(['monthly', '2026-01-31', '2026-04-15', '2026-04-30'])]
    #[TestWith(['monthly', '2026-01-31', '2026-04-30', '2026-05-31'])]
    #[TestWith(['monthly', '2028-01-31', '2028-01-31', '2028-02-29'])]
    #[TestWith(['yearly', '2028-02-29', '2028-02-29', '2029-02-28'])]
    #[TestWith(['yearly', '2028-02-29', '2031-02-28', '2032-02-29'])]
    #[TestWith(['yearly', '2026-12-31', '2026-12-31', '2027-12-31'])]
    #[TestWith(['daily', '2099-12-31', '2099-12-31', null])]
    #[TestWith(['weekly', '2099-12-30', '2099-12-30', null])]
    #[TestWith(['monthly', '2099-12-01', '2099-12-01', null])]
    #[TestWith(['yearly', '2099-01-01', '2099-01-01', null])]
    public function test_next_date_keeps_anchor_skips_missed_cycles_and_respects_supported_range(string $recurrence, string $anchor, string $after, ?string $expected): void
    {
        $this->assertSame($expected, TaskRecurrence::nextDate($recurrence, $anchor, $after));
    }
}
