<?php

namespace App\Modules\Calendar\Http;

use App\Modules\Calendar\Application\CalendarReminders;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

final class CalendarNotificationController
{
    public function index(Request $request, string $family, CalendarReminders $reminders): JsonResponse
    {
        $data = $request->validate(['page' => 'sometimes|integer|min:1|max:10000']);
        $query = $reminders->notifications($family, $request->user()->id);
        $unread = (clone $query)->whereNull('d.read_at')->count();
        $items = $query->orderByDesc('d.delivered_at')->orderByDesc('d.id')->offset((($data['page'] ?? 1) - 1) * 30)->limit(31)
            ->get(['d.id', 'd.calendar_item_id as event_id', 'd.delivered_at', 'd.read_at', 'e.title', 'e.event_date', 'e.all_day', 'e.starts_at']);

        return response()->json(['items' => $items->take(30)->map(function (object $item): array {
            $data = (array) $item;
            $data['time'] = $item->starts_at ? CarbonImmutable::parse($item->starts_at)->setTimezone('Europe/Bucharest')->format('H:i') : null;
            unset($data['starts_at']);

            return $data;
        })->values(), 'unread_count' => $unread, 'has_more' => $items->count() > 30]);
    }

    public function read(Request $request, string $family, string $notification, CalendarReminders $reminders): Response
    {
        $data = $request->validate(['read' => 'required|boolean']);
        $item = $reminders->notifications($family, $request->user()->id)->where('d.id', $notification)->firstOrFail(['d.id']);
        DB::table('calendar_reminder_deliveries')->where('id', $item->id)->update(['read_at' => $data['read'] ? now()->toIso8601String() : null]);

        return response()->noContent();
    }
}
