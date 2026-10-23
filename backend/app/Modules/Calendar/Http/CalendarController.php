<?php

namespace App\Modules\Calendar\Http;

use App\Modules\Calendar\Application\BirthdayCalendar;
use App\Modules\Calendar\Application\CalendarAccess;
use App\Modules\Calendar\Application\CalendarReminders;
use App\Platform\Audit\Audit;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class CalendarController
{
    private const TIMEZONE = 'Europe/Bucharest';

    public function __construct(private readonly CalendarReminders $reminders, private readonly CalendarAccess $access, private readonly BirthdayCalendar $birthdays) {}

    private function visible(Request $request, string $family): Builder
    {
        return $this->access->visible($family, $request->user()->id)
            ->join('users as u', 'u.id', '=', 'calendar_items.owner_id')
            ->leftJoin('birthday_reminder_preferences as preference', function ($join) use ($request) {
                $join->on('preference.family_id', '=', 'calendar_items.family_id')->on('preference.subject_membership_id', '=', 'calendar_items.source_parent_id')
                    ->where('preference.recipient_membership_id', $request->attributes->get('membership')->id);
            })
            ->leftJoinSub(DB::table('calendar_reminder_recipients')->selectRaw('calendar_item_id, json_agg(membership_id ORDER BY membership_id) as recipients_json, json_object_agg(membership_id, offset_minutes) as offsets_json')->groupBy('calendar_item_id'), 'r', 'r.calendar_item_id', '=', 'calendar_items.id')
            ->leftJoinSub(DB::table('calendar_reminder_deliveries')->selectRaw("calendar_item_id, source_version, json_agg(json_build_object('membership_id', membership_id, 'state', state) ORDER BY membership_id) as deliveries_json")
                ->groupBy('calendar_item_id', 'source_version'), 'd', function ($join) {
                    $join->on('d.calendar_item_id', '=', 'calendar_items.id')->on('d.source_version', '=', 'calendar_items.version');
                })
            ->select('calendar_items.*', 'u.name as owner_name', 'r.recipients_json', 'r.offsets_json', 'd.deliveries_json')->selectRaw('coalesce(preference.version, 0) as my_reminder_version');
    }

    private function payload(Request $request, object $event): array
    {
        $data = (array) $event;
        unset($data['family_id'], $data['recipients_json'], $data['offsets_json'], $data['deliveries_json'], $data['reminder_offset_minutes']);
        $startsAt = $event->starts_at ? new DateTimeImmutable($event->starts_at) : null;
        $data['time'] = $startsAt?->setTimezone(new DateTimeZone(self::TIMEZONE))->format('H:i');
        $data['starts_at'] = $startsAt?->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
        $data['source'] = $event->source_type === null ? null : ['type' => $event->source_type, 'id' => $event->source_id, 'parent_id' => $event->source_parent_id];
        unset($data['source_type'], $data['source_id'], $data['source_parent_id'], $data['source_version']);
        $data['can_edit'] = $event->source_type === null && $event->owner_id === $request->user()->id && $request->attributes->get('membership')->role !== 'limited';
        $myOffset = json_decode($event->offsets_json ?? '{}', true)[$request->attributes->get('membership')->id] ?? null;
        $data['my_reminder_offset_minutes'] = $event->source_type === 'birthday' ? $myOffset : null;
        $data['reminder'] = $event->source_type !== 'birthday' && $event->reminder_offset_minutes !== null && $event->owner_id === $request->user()->id ? [
            'offset_minutes' => $event->reminder_offset_minutes,
            'recipients' => json_decode($event->recipients_json ?? '[]', true),
            'due_at' => $this->reminders->schedule($event)['due_at']->toIso8601String(),
            'deliveries' => json_decode($event->deliveries_json ?? '[]', true),
        ] : null;

        return $data;
    }

    public function index(Request $request, string $family): JsonResponse
    {
        $data = $request->validate(['month' => ['required', 'regex:/^(19\d{2}|20\d{2}|2100)-(0[1-9]|1[0-2])$/'], 'archived' => 'sometimes|boolean']);
        $first = DateTimeImmutable::createFromFormat('!Y-m', $data['month']);
        if (! $request->boolean('archived')) {
            $this->birthdays->syncYear($family, (int) $first->format('Y'));
        }
        $query = $this->visible($request, $family)->whereBetween('event_date', [$first->format('Y-m-d'), $first->format('Y-m-t')]);
        $request->boolean('archived') ? $query->whereNotNull('archived_at') : $query->whereNull('archived_at');

        return response()->json($query->orderBy('event_date')->orderByDesc('all_day')->orderBy('starts_at')->orderBy('calendar_items.id')->get()
            ->map(fn (object $event): array => $this->payload($request, $event)));
    }

    public function show(Request $request, string $family, string $event): JsonResponse
    {
        $item = $this->visible($request, $family)->where('calendar_items.id', $event)->firstOrFail();

        return response()->json($this->payload($request, $item))->header('ETag', '"'.$item->version.'"');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => 'required|string|max:160', 'notes' => 'nullable|string|max:2000',
            'event_date' => 'required|date_format:Y-m-d|after_or_equal:1900-01-01|before_or_equal:2100-12-31',
            'all_day' => 'required|boolean', 'time' => 'nullable|required_if:all_day,false|date_format:H:i',
            'visibility' => ['required', Rule::in(['private', 'family'])],
            'reminder_offset_minutes' => 'sometimes|nullable|integer|in:0,15,60,1440',
            'reminder_recipients' => 'sometimes|array|max:100', 'reminder_recipients.*' => 'required|uuid|distinct',
            'owner_id' => 'prohibited', 'family_id' => 'prohibited', 'starts_at' => 'prohibited', 'timezone' => 'prohibited',
            'source' => 'prohibited', 'source_type' => 'prohibited', 'source_id' => 'prohibited', 'source_parent_id' => 'prohibited', 'source_version' => 'prohibited',
        ], [
            'time.required_if' => 'Alege ora evenimentului.', 'time.date_format' => 'Introdu o oră validă.',
            'event_date.date_format' => 'Alege o dată validă.', 'title.required' => 'Completează numele evenimentului.',
        ]);
        $data['starts_at'] = null;
        if (! $data['all_day']) {
            $local = $data['event_date'].' '.$data['time'];
            $wallClock = new DateTimeImmutable($local, new DateTimeZone('UTC'));
            $zone = new DateTimeZone(self::TIMEZONE);
            $offsets = array_unique(array_column($zone->getTransitions($wallClock->getTimestamp() - 86400, $wallClock->getTimestamp() + 86400), 'offset'));
            $candidates = [];
            foreach ($offsets as $offset) {
                $instant = $wallClock->modify('-'.$offset.' seconds');
                if ($instant->setTimezone($zone)->format('Y-m-d H:i') === $local) {
                    $candidates[] = $instant;
                }
            }
            if (count($candidates) !== 1) {
                throw ValidationException::withMessages(['time' => count($candidates) === 0
                    ? 'Ora aleasă nu există la trecerea la ora de vară. Alege o altă oră.'
                    : 'Ora aleasă apare de două ori la revenirea la ora de iarnă. Alege o oră din afara intervalului repetat.']);
            }
            $data['starts_at'] = $candidates[0]->format('Y-m-d H:i:sP');
        }
        unset($data['time']);
        $data['timezone'] = self::TIMEZONE;

        return $data;
    }

    public function store(Request $request, string $family): JsonResponse
    {
        abort_if($request->attributes->get('membership')->role === 'limited', 403, 'Rolul tău permite doar consultarea calendarului.');
        $data = $this->validated($request);

        return DB::transaction(function () use ($request, $family, $data): JsonResponse {
            $recipients = $data['reminder_recipients'] ?? [];
            unset($data['reminder_recipients']);
            $data['reminder_offset_minutes'] ??= null;
            $id = (string) Str::uuid();
            DB::table('calendar_items')->insert($data + [
                'id' => $id, 'family_id' => $family, 'owner_id' => $request->user()->id,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $item = DB::table('calendar_items')->where('id', $id)->firstOrFail();
            $this->reminders->configure($family, $item, $item->reminder_offset_minutes, $recipients);
            $this->reminders->replan($family, $item);
            Audit::record($family, $request->user()->id, 'calendar.created', $id);

            return $this->show($request, $family, $id)->setStatusCode(201);
        });
    }

    private function editable(Request $request, string $family, string $event): object
    {
        $item = $this->visible($request, $family)->where('calendar_items.id', $event)->firstOrFail();
        abort_if($item->source_type !== null, 409, match ($item->source_type) {
            'birthday' => 'Modifică data nașterii din profil.',
            'task' => 'Modifică termenul din Sarcini.',
            default => 'Modifică scadența din înregistrarea Auto.',
        });
        abort_unless($item->owner_id === $request->user()->id && $request->attributes->get('membership')->role !== 'limited', 403, 'Doar autorul poate modifica acest eveniment.');
        abort_unless($request->header('If-Match'), 428, 'Versiunea evenimentului este necesară.');
        abort_unless($request->header('If-Match') === '"'.$item->version.'"', 412, 'Evenimentul s-a schimbat. Reîncarcă-l înainte de a salva.');

        return $item;
    }

    public function update(Request $request, string $family, string $event): JsonResponse
    {
        $item = $this->editable($request, $family, $event);
        abort_if($item->archived_at !== null, 409, 'Restaurează evenimentul înainte de a-l modifica.');
        $data = $this->validated($request);

        return DB::transaction(function () use ($request, $family, $event, $item, $data): JsonResponse {
            $offset = array_key_exists('reminder_offset_minutes', $data) ? $data['reminder_offset_minutes'] : $item->reminder_offset_minutes;
            $recipients = $data['reminder_recipients'] ?? ($offset === null ? [] : json_decode($item->recipients_json ?? '[]', true));
            unset($data['reminder_recipients']);
            DB::table('calendar_items')->where('id', $event)->where('family_id', $family)
                ->update($data + ['version' => $item->version + 1, 'updated_at' => now()]);
            $updated = DB::table('calendar_items')->where('id', $event)->firstOrFail();
            $this->reminders->configure($family, $updated, $offset, $recipients);
            $this->reminders->replan($family, $updated);
            Audit::record($family, $request->user()->id, 'calendar.updated', $event);

            return $this->show($request, $family, $event);
        });
    }

    public function archive(Request $request, string $family, string $event): JsonResponse
    {
        return $this->setArchived($request, $family, $event, true);
    }

    public function restore(Request $request, string $family, string $event): JsonResponse
    {
        return $this->setArchived($request, $family, $event, false);
    }

    private function setArchived(Request $request, string $family, string $event, bool $archived): JsonResponse
    {
        $item = $this->editable($request, $family, $event);
        if (($item->archived_at !== null) !== $archived) {
            DB::table('calendar_items')->where('id', $event)->where('family_id', $family)->update([
                'archived_at' => $archived ? now() : null, 'version' => $item->version + 1, 'updated_at' => now(),
            ]);
            $this->reminders->replan($family, DB::table('calendar_items')->where('id', $event)->firstOrFail());
            Audit::record($family, $request->user()->id, $archived ? 'calendar.archived' : 'calendar.restored', $event);
        }

        return $this->show($request, $family, $event);
    }
}
