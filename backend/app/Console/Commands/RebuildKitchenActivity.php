<?php

namespace App\Console\Commands;

use App\Modules\Kitchen\Application\KitchenActivity;
use App\Platform\Events\DatabaseOutbox;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Throwable;

#[Signature('kitchen:rebuild-activity {--family= : Required family identifier}')]
#[Description('Rebuild Kitchen source references from canonical recipes and meals')]
final class RebuildKitchenActivity extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(DatabaseOutbox $outbox, KitchenActivity $activity): int
    {
        $family = $this->option('family');
        if (! is_string($family) || ! Str::isUuid($family)) {
            $this->error('Alege familia pentru reconstruire.');

            return self::FAILURE;
        }
        try {
            $count = $outbox->inFamily($family, fn () => $activity->rebuild($family));
            $this->info('Referințe reconstruite: '.$count);

            return self::SUCCESS;
        } catch (Throwable) {
            $this->error('Reconstruirea a eșuat; proiecția anterioară a fost păstrată.');

            return self::FAILURE;
        }
    }
}
