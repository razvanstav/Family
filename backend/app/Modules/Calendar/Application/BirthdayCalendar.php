<?php

namespace App\Modules\Calendar\Application;

use App\Modules\Calendar\Contracts\Birthdays;
use App\Modules\Calendar\Contracts\BirthdaySource;
use Illuminate\Support\Facades\DB;

final class BirthdayCalendar implements Birthdays
{
    public function __construct(private readonly BirthdaySource $source, private readonly SourceCalendarItems $calendar) {}

    public function syncYear(string $family, int $year): void
    {
        if ($year < 1900 || $year > 2100) {
            return;
        }
        $sources = $this->source->occurrences($family, $year);
        if ($sources === []) {
            return;
        }
        $preferences = DB::table('birthday_reminder_preferences as p')->join('family_memberships as m', function ($join) {
            $join->on('m.id', '=', 'p.recipient_membership_id')->on('m.family_id', '=', 'p.family_id');
        })->where('p.family_id', $family)->whereNull('m.revoked_at')->whereNotNull('m.user_id')
            ->get(['p.subject_membership_id', 'p.recipient_membership_id', 'p.offset_minutes'])->groupBy('subject_membership_id');
        $reminders = [];
        foreach ($sources as $source) {
            $offsets = ($preferences[$source['parent_id']] ?? collect())->pluck('offset_minutes', 'recipient_membership_id')->all();
            $reminders[$source['id']] = ['offset' => $offsets === [] ? null : 0, 'recipients' => array_keys($offsets), 'offsets' => $offsets];
        }
        $this->calendar->syncMany($family, $this->source->type(), $sources, $reminders);
    }

    public function syncFamily(string $family, ?int $extraYear = null): void
    {
        $year = now('Europe/Bucharest')->year;
        foreach (array_unique(array_filter([$year, $year + 1, $extraYear], fn (?int $value) => $value !== null)) as $targetYear) {
            $this->syncYear($family, $targetYear);
        }
    }

    public function unshare(string $family, string $member): void
    {
        $this->calendar->deactivateParent($family, $this->source->type(), $member);
    }
}
