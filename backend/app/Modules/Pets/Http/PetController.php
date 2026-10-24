<?php

namespace App\Modules\Pets\Http;

use App\Modules\Documents\Contracts\DocumentLinks;
use App\Modules\Pets\Application\PetCalendar;
use App\Platform\Audit\Audit;
use App\Platform\Contracts\PrivateMedia;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class PetController
{
    public function __construct(private readonly PrivateMedia $media, private readonly DocumentLinks $documents, private readonly PetCalendar $calendar) {}

    private function query(string $family): Builder
    {
        return DB::table('pets as p')->leftJoin('media_assets as a', function ($join) {
            $join->on('a.family_id', '=', 'p.family_id')->on('a.id', '=', 'p.photo_asset_id');
        })->where('p.family_id', $family)->select('p.*', 'a.status as photo_status');
    }

    private function find(string $family, string $pet): object
    {
        return $this->query($family)->where('p.id', $pet)->firstOrFail();
    }

    private function payload(Request $request, object $pet): array
    {
        return ['id' => $pet->id, 'name' => $pet->name, 'species' => $pet->species, 'breed' => $pet->breed,
            'birth_date' => $pet->birth_date, 'photo_status' => $pet->photo_status, 'version' => (int) $pet->version,
            'archived_at' => $pet->archived_at, 'can_manage' => $request->attributes->get('membership')->role !== 'limited'];
    }

    private function writable(Request $request): void
    {
        abort_if($request->attributes->get('membership')->role === 'limited', 403, 'Accesul tău permite numai consultarea animalelor.');
    }

    private function version(Request $request, int $version): void
    {
        abort_unless($request->header('If-Match'), 428, 'Versiunea este necesară.');
        abort_unless($request->header('If-Match') === '"'.$version.'"', 412, 'Datele s-au schimbat. Reîncarcă înainte de a salva.');
    }

    private function editable(Request $request, string $family, string $pet): object
    {
        $row = $this->find($family, $pet);
        $this->writable($request);
        $this->version($request, (int) $row->version);

        return $row;
    }

    private function active(string $family, string $pet): void
    {
        abort_if($this->find($family, $pet)->archived_at !== null, 409, 'Restaurează animalul înainte de modificare.');
    }

    private function content(Request $request): array
    {
        $data = $request->validate(['name' => 'required|string|max:100', 'species' => 'required|string|max:60',
            'breed' => 'nullable|string|max:100',
            'birth_date' => 'nullable|date_format:Y-m-d|after_or_equal:1900-01-01|before_or_equal:'.now('Europe/Bucharest')->toDateString(),
            'family_id' => 'prohibited', 'created_by' => 'prohibited', 'photo_asset_id' => 'prohibited', 'version' => 'prohibited'], [
                'name.required' => 'Introdu numele animalului.', 'name.max' => 'Numele poate avea cel mult 100 de caractere.',
                'species.required' => 'Introdu specia animalului.', 'species.max' => 'Specia poate avea cel mult 60 de caractere.',
                'breed.max' => 'Rasa poate avea cel mult 100 de caractere.',
                'birth_date.date_format' => 'Alege o dată de naștere validă.',
                'birth_date.after_or_equal' => 'Data nașterii poate fi cel mai devreme 1 ianuarie 1900.',
                'birth_date.before_or_equal' => 'Data nașterii nu poate fi în viitor.',
                'prohibited' => 'Câmpul :attribute nu poate fi modificat.',
            ], ['name' => 'Nume', 'species' => 'Specie', 'breed' => 'Rasă', 'birth_date' => 'Data nașterii',
                'family_id' => 'Familie', 'created_by' => 'Autor', 'photo_asset_id' => 'Fotografie', 'version' => 'Versiune']);

        return $data + ['breed' => null, 'birth_date' => null];
    }

    public function index(Request $request, string $family): JsonResponse
    {
        $request->validate(['archived' => 'sometimes|boolean']);
        $query = $this->query($family);
        $request->boolean('archived') ? $query->whereNotNull('p.archived_at') : $query->whereNull('p.archived_at');

        return response()->json($query->orderBy('p.created_at')->orderBy('p.id')->get()->map(fn (object $row) => $this->payload($request, $row)));
    }

    public function show(Request $request, string $family, string $pet): JsonResponse
    {
        $row = $this->find($family, $pet);

        return response()->json($this->payload($request, $row))->header('ETag', '"'.$row->version.'"');
    }

    public function store(Request $request, string $family): JsonResponse
    {
        $this->writable($request);
        $data = $this->content($request);
        $id = (string) Str::uuid();

        return DB::transaction(function () use ($request, $family, $data, $id): JsonResponse {
            DB::table('pets')->insert($data + ['id' => $id, 'family_id' => $family, 'created_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);
            Audit::record($family, $request->user()->id, 'pet.created', $id);

            return $this->show($request, $family, $id)->setStatusCode(201);
        });
    }

    public function update(Request $request, string $family, string $pet): JsonResponse
    {
        $row = $this->editable($request, $family, $pet);
        abort_if($row->archived_at !== null, 409, 'Restaurează animalul înainte de modificare.');
        $data = $this->content($request);

        return DB::transaction(function () use ($request, $family, $pet, $row, $data): JsonResponse {
            DB::table('pets')->where('family_id', $family)->where('id', $pet)->update($data + ['version' => $row->version + 1, 'updated_at' => now()]);
            $this->calendar->syncPet($family, $pet);
            Audit::record($family, $request->user()->id, 'pet.updated', $pet);

            return $this->show($request, $family, $pet);
        });
    }

    public function archive(Request $request, string $family, string $pet): JsonResponse
    {
        $row = $this->editable($request, $family, $pet);
        $data = $request->validate(['archived' => 'required|boolean']);

        return DB::transaction(function () use ($request, $family, $pet, $row, $data): JsonResponse {
            DB::table('pets')->where('family_id', $family)->where('id', $pet)->update([
                'archived_at' => $data['archived'] ? now() : null, 'version' => $row->version + 1, 'updated_at' => now(),
            ]);
            $this->calendar->syncPet($family, $pet);
            Audit::record($family, $request->user()->id, $data['archived'] ? 'pet.archived' : 'pet.restored', $pet);

            return $this->show($request, $family, $pet);
        });
    }

    public function photo(Request $request, string $family, string $pet): StreamedResponse
    {
        $row = $this->find($family, $pet);
        abort_unless($row->photo_asset_id, 404);

        return $this->media->image($family, $row->photo_asset_id);
    }

    public function uploadPhoto(Request $request, string $family, string $pet): JsonResponse
    {
        $row = $this->editable($request, $family, $pet);
        abort_if($row->archived_at !== null, 409, 'Restaurează animalul înainte de modificare.');
        $data = $request->validate(['action' => 'required|in:replace,remove', 'photo' => 'required_if:action,replace|prohibited_if:action,remove|file|max:10240'], [
            'photo.required_if' => 'Alege fotografia animalului.', 'photo.file' => 'Alege un fișier JPG, PNG sau WebP.',
            'photo.max' => 'Fotografia poate avea cel mult 10 MB.',
        ], ['photo' => 'Fotografie', 'action' => 'Acțiune']);

        return DB::transaction(function () use ($request, $family, $pet, $row, $data): JsonResponse {
            $asset = $data['action'] === 'replace' ? $this->media->receiveImage($family, $request->user()->id, $request->file('photo')) : null;
            DB::table('pets')->where('family_id', $family)->where('id', $pet)->update([
                'photo_asset_id' => $asset['id'] ?? null, 'version' => $row->version + 1, 'updated_at' => now(),
            ]);
            Audit::record($family, $request->user()->id, 'pet.photo_changed', $pet);

            return $this->show($request, $family, $pet);
        });
    }

    public function retryPhoto(Request $request, string $family, string $pet): JsonResponse
    {
        $row = $this->editable($request, $family, $pet);
        abort_if($row->archived_at !== null, 409, 'Restaurează animalul înainte de modificare.');
        abort_unless($row->photo_asset_id, 404);

        return DB::transaction(function () use ($request, $family, $pet, $row): JsonResponse {
            $this->media->retry($family, $row->photo_asset_id);

            return $this->show($request, $family, $pet);
        });
    }

    private function entryQuery(string $family): Builder
    {
        return DB::table('pet_entries as e')->leftJoinSub($this->calendar->settings($family), 'calendar', 'calendar.source_id', '=', 'e.id')
            ->where('e.family_id', $family)->select('e.*', 'calendar.calendar_event_id', 'calendar.reminder_offset_minutes', 'calendar.recipients_json');
    }

    private function entry(string $family, string $pet, string $entry): object
    {
        return $this->entryQuery($family)->where('e.pet_id', $pet)->where('e.id', $entry)->firstOrFail();
    }

    private function entryPayload(object $entry): array
    {
        return ['id' => $entry->id, 'kind' => $entry->kind, 'title' => $entry->title, 'event_date' => $entry->event_date,
            'weight_kg' => $entry->weight_kg, 'cost' => $entry->cost, 'notes' => $entry->notes, 'next_due_on' => $entry->next_due_on,
            'next_due_completed_at' => $entry->next_due_completed_at, 'version' => (int) $entry->version, 'archived_at' => $entry->archived_at,
            'calendar_event_id' => $entry->calendar_event_id, 'reminder_offset_minutes' => $entry->reminder_offset_minutes,
            'reminder_recipients' => json_decode($entry->recipients_json ?? '[]', true)];
    }

    public function entries(Request $request, string $family, string $pet): JsonResponse
    {
        $this->find($family, $pet);
        $data = $request->validate(['page' => 'sometimes|integer|min:1|max:10000', 'archived' => 'sometimes|boolean',
            'kind' => ['sometimes', Rule::in(['weight', 'vaccination', 'visit', 'care'])]]);
        $query = $this->entryQuery($family)->where('e.pet_id', $pet);
        $request->boolean('archived') ? $query->whereNotNull('e.archived_at') : $query->whereNull('e.archived_at');
        if (isset($data['kind'])) {
            $query->where('e.kind', $data['kind']);
        }
        $page = (int) ($data['page'] ?? 1);
        $rows = $query->orderByDesc('e.event_date')->orderByDesc('e.created_at')->orderBy('e.id')->offset(($page - 1) * 20)->limit(21)->get();

        return response()->json(['items' => $rows->take(20)->map(fn (object $row) => $this->entryPayload($row))->values(),
            'has_more' => $rows->count() > 20, 'page' => $page]);
    }

    public function showEntry(Request $request, string $family, string $pet, string $entry): JsonResponse
    {
        $row = $this->entry($family, $pet, $entry);

        return response()->json($this->entryPayload($row))->header('ETag', '"'.$row->version.'"');
    }

    private function entryContent(Request $request): array
    {
        $data = $request->validate(['kind' => ['required', Rule::in(['weight', 'vaccination', 'visit', 'care'])],
            'title' => 'required|string|max:160',
            'event_date' => 'required|date_format:Y-m-d|after_or_equal:1900-01-01|before_or_equal:'.now('Europe/Bucharest')->toDateString(),
            'weight_kg' => 'nullable|required_if:kind,weight|prohibited_unless:kind,weight|numeric|min:0.001|max:9999.999|decimal:0,3',
            'cost' => 'nullable|numeric|min:0|max:9999999.99|decimal:0,2', 'notes' => 'nullable|string|max:4000',
            'next_due_on' => 'nullable|date_format:Y-m-d|after_or_equal:event_date|before_or_equal:2099-12-31',
            'family_id' => 'prohibited', 'pet_id' => 'prohibited', 'author_id' => 'prohibited', 'version' => 'prohibited',
            'next_due_completed_at' => 'prohibited', 'calendar_event_id' => 'prohibited'], [
                'kind.required' => 'Alege tipul înregistrării.', 'kind.in' => 'Alege un tip de înregistrare disponibil.',
                'title.required' => 'Introdu titlul înregistrării.', 'title.max' => 'Titlul poate avea cel mult 160 de caractere.',
                'event_date.required' => 'Alege data înregistrării.', 'event_date.date_format' => 'Alege o dată validă pentru înregistrare.',
                'event_date.after_or_equal' => 'Data înregistrării poate fi cel mai devreme 1 ianuarie 1900.',
                'event_date.before_or_equal' => 'Înregistrarea nu poate avea o dată în viitor.',
                'weight_kg.required_if' => 'Introdu greutatea.', 'weight_kg.prohibited_unless' => 'Greutatea se introduce numai la cântărire.',
                'weight_kg.numeric' => 'Introdu greutatea în kilograme.', 'weight_kg.min' => 'Greutatea trebuie să fie cel puțin 0,001 kg.',
                'weight_kg.max' => 'Greutatea poate fi cel mult 9.999,999 kg.', 'weight_kg.decimal' => 'Greutatea poate avea cel mult 3 zecimale.',
                'cost.numeric' => 'Introdu costul în lei.', 'cost.min' => 'Costul nu poate fi negativ.',
                'cost.max' => 'Costul poate fi cel mult 9.999.999,99 lei.', 'cost.decimal' => 'Costul poate avea cel mult 2 zecimale.',
                'notes.max' => 'Notițele pot avea cel mult 4.000 de caractere.',
                'next_due_on.date_format' => 'Alege o scadență validă.',
                'next_due_on.after_or_equal' => 'Scadența nu poate fi înaintea datei înregistrării.',
                'next_due_on.before_or_equal' => 'Scadența poate fi cel târziu 31 decembrie 2099.',
                'prohibited' => 'Câmpul :attribute nu poate fi modificat.',
            ], ['kind' => 'Tip', 'title' => 'Titlu', 'event_date' => 'Data înregistrării', 'weight_kg' => 'Greutate', 'cost' => 'Cost',
                'notes' => 'Notițe', 'next_due_on' => 'Următoarea scadență', 'family_id' => 'Familie', 'pet_id' => 'Animal', 'author_id' => 'Autor',
                'version' => 'Versiune', 'next_due_completed_at' => 'Finalizarea scadenței', 'calendar_event_id' => 'Eveniment']);

        return $data + ['weight_kg' => null, 'cost' => null, 'notes' => null, 'next_due_on' => null];
    }

    private function reminderContent(Request $request, string $family, string $pet, string $entry): void
    {
        $data = $request->validate(['reminder_offset_minutes' => 'sometimes|nullable|integer|in:0,1440',
            'reminder_recipients' => 'sometimes|array|max:100', 'reminder_recipients.*' => 'required|uuid|distinct'], [
                'reminder_offset_minutes.in' => 'Alege reminderul în ziua scadenței sau cu o zi înainte.',
                'reminder_recipients.*.uuid' => 'Alege membri activi ai familiei.',
                'reminder_recipients.*.distinct' => 'Alege fiecare destinatar o singură dată.',
            ], ['reminder_offset_minutes' => 'Reminder', 'reminder_recipients' => 'Destinatari']);
        $existing = $this->entry($family, $pet, $entry);
        $offset = array_key_exists('reminder_offset_minutes', $data) ? $data['reminder_offset_minutes'] : $existing->reminder_offset_minutes;
        $recipients = $data['reminder_recipients'] ?? ($offset === null ? [] : json_decode($existing->recipients_json ?? '[]', true));
        if ($existing->next_due_on === null) {
            if (($data['reminder_offset_minutes'] ?? null) !== null || count($data['reminder_recipients'] ?? []) > 0) {
                throw ValidationException::withMessages(['reminder_offset_minutes' => 'Alege următoarea scadență pentru reminder.']);
            }
            $data = ['reminder_offset_minutes' => null, 'reminder_recipients' => []];
            $offset = null;
            $recipients = [];
        }
        $this->calendar->syncEntry($family, $entry, $data === [] ? null : ['offset' => $offset, 'recipients' => $recipients]);
    }

    public function storeEntry(Request $request, string $family, string $pet): JsonResponse
    {
        $this->active($family, $pet);
        $this->writable($request);
        $data = $this->entryContent($request);
        $id = (string) Str::uuid();

        return DB::transaction(function () use ($request, $family, $pet, $data, $id): JsonResponse {
            DB::table('pet_entries')->insert($data + ['id' => $id, 'family_id' => $family, 'pet_id' => $pet,
                'author_id' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);
            $this->reminderContent($request, $family, $pet, $id);
            Audit::record($family, $request->user()->id, 'pet.entry_created', $id);

            return $this->showEntry($request, $family, $pet, $id)->setStatusCode(201);
        });
    }

    public function updateEntry(Request $request, string $family, string $pet, string $entry): JsonResponse
    {
        $row = $this->entry($family, $pet, $entry);
        $this->active($family, $pet);
        $this->writable($request);
        $this->version($request, (int) $row->version);
        abort_if($row->archived_at !== null, 409, 'Restaurează înregistrarea înainte de modificare.');
        $data = $this->entryContent($request);

        return DB::transaction(function () use ($request, $family, $pet, $entry, $row, $data): JsonResponse {
            DB::table('pet_entries')->where('family_id', $family)->where('id', $entry)->update($data + [
                'next_due_completed_at' => $data['next_due_on'] === $row->next_due_on ? $row->next_due_completed_at : null,
                'version' => $row->version + 1, 'updated_at' => now(),
            ]);
            $this->reminderContent($request, $family, $pet, $entry);
            Audit::record($family, $request->user()->id, 'pet.entry_updated', $entry);

            return $this->showEntry($request, $family, $pet, $entry);
        });
    }

    public function archiveEntry(Request $request, string $family, string $pet, string $entry): JsonResponse
    {
        $row = $this->entry($family, $pet, $entry);
        $this->active($family, $pet);
        $this->writable($request);
        $this->version($request, (int) $row->version);
        $data = $request->validate(['archived' => 'required|boolean']);

        return DB::transaction(function () use ($request, $family, $pet, $entry, $row, $data): JsonResponse {
            DB::table('pet_entries')->where('family_id', $family)->where('id', $entry)->update([
                'archived_at' => $data['archived'] ? now() : null, 'version' => $row->version + 1, 'updated_at' => now(),
            ]);
            $this->calendar->syncEntry($family, $entry);
            Audit::record($family, $request->user()->id, $data['archived'] ? 'pet.entry_archived' : 'pet.entry_restored', $entry);

            return $this->showEntry($request, $family, $pet, $entry);
        });
    }

    public function completeEntry(Request $request, string $family, string $pet, string $entry): JsonResponse
    {
        $row = $this->entry($family, $pet, $entry);
        $this->active($family, $pet);
        $this->writable($request);
        $this->version($request, (int) $row->version);
        abort_if($row->archived_at !== null, 409, 'Restaurează înregistrarea înainte de modificare.');
        abort_if($row->next_due_on === null, 409, 'Înregistrarea nu are o scadență.');
        $data = $request->validate(['completed' => 'required|boolean']);

        return DB::transaction(function () use ($request, $family, $pet, $entry, $row, $data): JsonResponse {
            DB::table('pet_entries')->where('family_id', $family)->where('id', $entry)->update([
                'next_due_completed_at' => $data['completed'] ? now() : null, 'version' => $row->version + 1, 'updated_at' => now(),
            ]);
            $this->calendar->syncEntry($family, $entry);
            Audit::record($family, $request->user()->id, $data['completed'] ? 'pet.deadline_completed' : 'pet.deadline_reopened', $entry);

            return $this->showEntry($request, $family, $pet, $entry);
        });
    }

    public function documents(Request $request, string $family, string $pet): JsonResponse
    {
        $this->find($family, $pet);
        $visible = $this->documents->references($family, $request->user()->id, $request->attributes->get('membership')->id);

        return response()->json(DB::table('pet_documents as l')->joinSub($visible, 'd', 'd.id', '=', 'l.document_id')
            ->where('l.family_id', $family)->where('l.pet_id', $pet)->orderBy('d.title')->orderBy('d.id')->get(['d.*']));
    }

    public function linkDocument(Request $request, string $family, string $pet): JsonResponse
    {
        $this->active($family, $pet);
        $this->writable($request);
        $data = $request->validate(['document_id' => 'required|uuid']);
        $this->documents->assertVisible($family, $request->user()->id, $request->attributes->get('membership')->id, $data['document_id']);

        return DB::transaction(function () use ($request, $family, $pet, $data): JsonResponse {
            DB::table('pet_documents')->insertOrIgnore(['family_id' => $family, 'pet_id' => $pet, 'document_id' => $data['document_id']]);
            Audit::record($family, $request->user()->id, 'pet.document_linked', $pet);

            return $this->documents($request, $family, $pet);
        });
    }

    public function unlinkDocument(Request $request, string $family, string $pet, string $document): JsonResponse
    {
        $this->active($family, $pet);
        $this->writable($request);
        $this->documents->assertVisible($family, $request->user()->id, $request->attributes->get('membership')->id, $document);

        return DB::transaction(function () use ($request, $family, $pet, $document): JsonResponse {
            DB::table('pet_documents')->where('family_id', $family)->where('pet_id', $pet)->where('document_id', $document)->delete();
            Audit::record($family, $request->user()->id, 'pet.document_unlinked', $pet);

            return response()->json(['ok' => true]);
        });
    }
}
