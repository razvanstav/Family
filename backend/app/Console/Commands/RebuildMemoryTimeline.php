<?php

namespace App\Console\Commands;

use App\Modules\Memory\Application\TimelineProjection;
use App\Platform\Events\DatabaseOutbox;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Throwable;

#[Signature('memory:rebuild-timeline {--family= : Required family identifier}')]
#[Description('Rebuild timeline references from canonical moments and the public Kitchen provider')]
final class RebuildMemoryTimeline extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(DatabaseOutbox $outbox, TimelineProjection $timeline): int
    {
        $family = $this->option('family');
        if (! is_string($family) || ! Str::isUuid($family)) {
            $this->error('Alege familia pentru reconstruire.');

            return self::FAILURE;
        }
        try {
            $count = $outbox->inFamily($family, fn () => $timeline->rebuild($family));
            $this->info('Referințe reconstruite: '.$count);

            return self::SUCCESS;
        } catch (Throwable) {
            $this->error('Reconstruirea a eșuat; cronologia anterioară a fost păstrată.');

            return self::FAILURE;
        }
    }
}
