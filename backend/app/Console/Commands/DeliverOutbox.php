<?php

namespace App\Console\Commands;

use App\Platform\Events\DatabaseOutbox;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

#[Signature('outbox:deliver {--family= : Deliver only one family} {--limit=100 : Maximum deliveries per family per pass} {--watch : Poll once per minute} {--replay= : Requeue this handler for the selected family}')]
#[Description('Deliver durable database events with per-handler receipts and retry')]
final class DeliverOutbox extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(DatabaseOutbox $outbox): int
    {
        $family = $this->option('family');
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000]]);
        if (($family !== null && ! Str::isUuid($family)) || $limit === false || ($this->option('replay') && (! $family || $this->option('watch')))) {
            $this->error('Alege o familie validă, un lot între 1 și 1000 și folosește replay separat de watch.');

            return self::FAILURE;
        }
        try {
            if ($this->option('replay')) {
                $this->info('Evenimente reprogramate: '.$outbox->replayFamily($family, $this->option('replay')));
            }
            do {
                $totals = ['delivered' => 0, 'failed' => 0];
                $families = DB::table('families')->when($family, fn ($query) => $query->where('id', $family))->orderBy('id');
                $families->chunk(100, function ($rows) use ($outbox, $limit, &$totals): void {
                    foreach ($rows as $row) {
                        $result = $outbox->deliverFamily($row->id, $limit);
                        $totals['delivered'] += $result['delivered'];
                        $totals['failed'] += $result['failed'];
                    }
                });
                if (! $this->option('watch') || $totals['delivered'] || $totals['failed']) {
                    $this->info('Livrate: '.$totals['delivered'].'; eșuate: '.$totals['failed'].'.');
                }
                if (! $this->option('watch')) {
                    return $totals['failed'] ? self::FAILURE : self::SUCCESS;
                }
                sleep(60);
            } while (true);
        } catch (Throwable) {
            $this->error('Livrarea s-a oprit. Evenimentele rămân în outbox pentru reîncercare.');

            return self::FAILURE;
        }
    }
}
