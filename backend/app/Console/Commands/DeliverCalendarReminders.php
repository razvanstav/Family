<?php

namespace App\Console\Commands;

use App\Modules\Calendar\Application\CalendarReminders;
use App\Modules\Calendar\Application\ReminderEmailDelivery;
use App\Modules\Calendar\Application\ReminderReconciler;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

#[Signature('calendar:deliver-reminders {--family= : Deliver only one family} {--watch : Check once per minute until stopped}')]
#[Description('Deliver authorized reminders through enabled in-app and email channels')]
final class DeliverCalendarReminders extends Command
{
    public function handle(CalendarReminders $reminders, ReminderReconciler $reconciler, ReminderEmailDelivery $emails): int
    {
        $family = $this->option('family');
        if ($family !== null && ! Str::isUuid($family)) {
            $this->error('Alege o familie validă.');

            return self::FAILURE;
        }
        do {
            try {
                DB::table('families')->when($family, fn ($query) => $query->where('id', $family))
                    ->select('id')->chunkById(100, function ($families) use ($reminders, $reconciler, $emails): void {
                        foreach ($families as $row) {
                            $work = $reconciler->prepareFamily($row->id);
                            if ($work['reminders']) {
                                $reminders->deliverFamily($row->id);
                            }
                            if ($work['reminders'] || $work['email']) {
                                $emails->deliverFamily($row->id);
                            }
                        }
                    });
            } catch (Throwable $error) {
                report($error);
                $this->error('Reminderele nu au putut fi livrate. Înregistrările rămân pentru reîncercare.');
                if (! $this->option('watch')) {
                    return self::FAILURE;
                }
            }
            if ($this->option('watch')) {
                sleep(60);
            }
        } while ($this->option('watch'));

        return self::SUCCESS;
    }
}
