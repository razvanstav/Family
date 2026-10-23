<?php

namespace App\Console\Commands;

use App\Modules\Tasks\Application\TaskCalendar;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('tasks:sync-calendar')]
#[Description('Reconcile existing task deadlines without creating reminders')]
final class SyncTaskCalendar extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(TaskCalendar $calendar): int
    {
        DB::table('families')->orderBy('id')->pluck('id')->each(function (string $family) use ($calendar): void {
            DB::transaction(function () use ($family, $calendar): void {
                DB::table('families')->where('id', $family)->lockForUpdate()->firstOrFail();
                DB::select("SELECT set_config('app.family_id', ?, true)", [$family]);
                $calendar->syncFamily($family);
            });
        });
        $this->info('Termenele sarcinilor sunt sincronizate.');

        return self::SUCCESS;
    }
}
