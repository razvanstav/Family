<?php

namespace App\Platform\Identity;

use App\Modules\Calendar\Contracts\ReminderReconciliation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

final class NotificationSettingsController
{
    public function show(Request $request, MemberNotificationPreferences $preferences): JsonResponse
    {
        $snapshot = $preferences->snapshot($request->user()->id);

        return response()->json($snapshot)->header('ETag', '"'.$snapshot['version'].'"');
    }

    public function update(Request $request, MemberNotificationPreferences $preferences, ReminderReconciliation $reminders): JsonResponse
    {
        $data = $request->validate([
            'items' => 'required|array|size:5',
            'items.*' => 'required|array:category,in_app,email',
            'items.*.category' => ['required', Rule::in(MemberNotificationPreferences::CATEGORIES), 'distinct'],
            'items.*.in_app' => 'required|boolean', 'items.*.email' => 'required|boolean',
            'user_id' => 'prohibited', 'family_id' => 'prohibited',
        ]);
        abort_unless($request->header('If-Match'), 428, 'Versiunea setărilor este necesară.');
        $values = [];
        foreach ($data['items'] as $item) {
            $values[$item['category']] = ['in_app' => (bool) $item['in_app'], 'email' => (bool) $item['email']];
        }
        $ordered = [];
        foreach (MemberNotificationPreferences::CATEGORIES as $category) {
            $ordered[$category] = $values[$category];
        }

        return DB::transaction(function () use ($request, $preferences, $ordered, $reminders): JsonResponse {
            $user = $request->user()->id;
            DB::table('users')->where('id', $user)->lockForUpdate()->firstOrFail();
            $current = DB::table('user_notification_preferences')->where('user_id', $user)->first();
            $version = (int) ($current?->version ?? 0);
            abort_unless($request->header('If-Match') === '"'.$version.'"', 412, 'Setările s-au schimbat. Reîncarcă înainte de a salva.');
            if (! $current || json_decode($current->preferences, true) != $ordered) {
                $row = ['preferences' => json_encode($ordered, JSON_THROW_ON_ERROR), 'version' => $version + 1, 'updated_at' => now()];
                if ($current) {
                    DB::table('user_notification_preferences')->where('user_id', $user)->update($row);
                } else {
                    DB::table('user_notification_preferences')->insert($row + ['user_id' => $user, 'created_at' => now()]);
                }
                $reminders->invalidateUser($user);
            }

            return $this->show($request, $preferences);
        });
    }
}
