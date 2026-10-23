<?php

namespace App\Console\Commands;

use App\Modules\Vehicles\Application\VehicleCalendar;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('vehicles:sync-calendar')]
#[Description('Reconcile existing vehicle deadlines without adding reminder recipients')]
final class SyncVehicleCalendar extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(VehicleCalendar $calendar): int
    {
        DB::table('families')->orderBy('id')->pluck('id')->each(function (string $family) use ($calendar): void {
            DB::transaction(function () use ($family, $calendar): void {
                DB::table('families')->where('id', $family)->lockForUpdate()->firstOrFail();
                DB::select("SELECT set_config('app.family_id', ?, true)", [$family]);
                DB::table('vehicles')->where('family_id', $family)->orderBy('id')->select('id')->chunk(100, function ($vehicles) use ($family, $calendar): void {
                    foreach ($vehicles as $vehicle) {
                        $calendar->syncVehicle($family, $vehicle->id);
                    }
                });
            });
        });
        $this->info('Scadențele Auto sunt sincronizate.');

        return self::SUCCESS;
    }
}
