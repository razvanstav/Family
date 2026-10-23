<?php

namespace App\Modules\Calendar\Http;

use App\Modules\Calendar\Application\BirthdayCalendar;
use App\Modules\Calendar\Application\CalendarAccess;
use App\Platform\Audit\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class BirthdayReminderController
{
    public function __invoke(Request $request, string $family, string $event, CalendarAccess $access, BirthdayCalendar $birthdays, CalendarController $calendar): JsonResponse
    {
        $item = $access->visible($family, $request->user()->id)->where('calendar_items.id', $event)->where('source_type', 'birthday')->whereNull('archived_at')->firstOrFail();
        abort_unless($request->header('If-Match'), 428, 'Versiunea este necesară.');
        $preference = DB::table('birthday_reminder_preferences')->where('family_id', $family)->where('subject_membership_id', $item->source_parent_id)
            ->where('recipient_membership_id', $request->attributes->get('membership')->id)->first();
        abort_unless($request->header('If-Match') === '"'.($preference?->version ?? 0).'"', 412, 'Reminderul s-a schimbat. Reîncarcă înainte de a salva.');
        $data = $request->validate(['offset_minutes' => 'present|nullable|integer|in:0,1440,10080', 'source_version' => 'required|integer|min:1', 'recipient_id' => 'prohibited', 'subject_id' => 'prohibited']);
        abort_unless($data['source_version'] === $item->version, 412, 'Ziua de naștere s-a schimbat. Reîncarcă înainte de a salva.');

        return DB::transaction(function () use ($request, $family, $event, $item, $data, $preference, $birthdays, $calendar): JsonResponse {
            $key = ['family_id' => $family, 'subject_membership_id' => $item->source_parent_id, 'recipient_membership_id' => $request->attributes->get('membership')->id];
            if ($data['offset_minutes'] === null) {
                DB::table('birthday_reminder_preferences')->where($key)->delete();
            } else {
                DB::table('birthday_reminder_preferences')->updateOrInsert($key, ['offset_minutes' => $data['offset_minutes'], 'version' => ($preference?->version ?? 0) + 1]);
            }
            $birthdays->syncFamily($family, (int) substr($item->event_date, 0, 4));
            Audit::record($family, $request->user()->id, 'birthday.reminder_configured', $item->source_parent_id);

            return $calendar->show($request, $family, $event);
        });
    }
}
