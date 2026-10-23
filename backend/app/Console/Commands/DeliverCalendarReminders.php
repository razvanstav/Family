<?php

namespace App\Console\Commands;

use App\Modules\Calendar\Application\BirthdayCalendar;
use App\Modules\Calendar\Application\CalendarReminders;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

#[Signature('calendar:deliver-reminders {--watch : Check every fifteen seconds until stopped}')]
#[Description('Deliver authorized calendar reminders to the in-app inbox')]
final class DeliverCalendarReminders extends Command
{
    public function handle(CalendarReminders $reminders, BirthdayCalendar $birthdays): int
    {
        do {
            try {
                DB::table('families')->orderBy('id')->pluck('id')->each(function (string $family) use ($reminders, $birthdays): void {
                    $reminders->deliverFamily($family, $birthdays);
                });
            } catch (Throwable $error) {
                report($error);
                $this->error('Reminderele nu au putut fi livrate. Înregistrările rămân pentru reîncercare.');
                if (! $this->option('watch')) {
                    return self::FAILURE;
                }
            }
            if ($this->option('watch')) {
                sleep(15);
            }
        } while ($this->option('watch'));

        return self::SUCCESS;
    }
}
