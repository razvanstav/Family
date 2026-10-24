<?php

namespace App\Platform\Identity;

use App\Platform\Contracts\NotificationPreferences;
use Illuminate\Support\Facades\DB;

final class MemberNotificationPreferences implements NotificationPreferences
{
    public const CATEGORIES = ['calendar', 'tasks', 'birthdays', 'deadlines', 'memories'];

    /** @return array<string, array{in_app: bool, email: bool}> */
    private function normalize(?string $stored): array
    {
        $values = json_decode($stored ?? '{}', true, flags: JSON_THROW_ON_ERROR);
        $result = [];
        foreach (self::CATEGORIES as $category) {
            $result[$category] = [
                'in_app' => $values[$category]['in_app'] ?? in_array($category, ['calendar', 'birthdays', 'deadlines'], true),
                'email' => $values[$category]['email'] ?? false,
            ];
        }

        return $result;
    }

    public function forUser(int $user): array
    {
        return $this->normalize(DB::table('user_notification_preferences')->where('user_id', $user)->value('preferences'));
    }

    public function configured(int $user): bool
    {
        return DB::table('user_notification_preferences')->where('user_id', $user)->exists();
    }

    public function category(?string $sourceType): string
    {
        return match ($sourceType) {
            'task' => 'tasks',
            'birthday' => 'birthdays',
            'vehicle_entry', 'pet_entry', 'document_expiry' => 'deadlines',
            'memory_anniversary' => 'memories',
            default => 'calendar',
        };
    }

    /** @return array{version: int, items: list<array{category: string, in_app: bool, email: bool}>} */
    public function snapshot(int $user): array
    {
        $row = DB::table('user_notification_preferences')->where('user_id', $user)->first();
        $values = $this->normalize($row?->preferences);

        return ['version' => (int) ($row?->version ?? 0), 'items' => array_map(fn (string $category): array => [
            'category' => $category, ...$values[$category],
        ], self::CATEGORIES)];
    }
}
