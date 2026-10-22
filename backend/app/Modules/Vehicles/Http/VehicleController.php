<?php

namespace App\Modules\Vehicles\Http;

use App\Modules\Documents\Application\DocumentAccess;
use App\Platform\Audit\Audit;
use App\Platform\Contracts\PrivateMedia;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class VehicleController
{
    public function __construct(private PrivateMedia $media, private DocumentAccess $documents) {}

    private function query(string $family): Builder
    {
        return DB::table('vehicles as v')->leftJoin('media_assets as a', function ($join) {
            $join->on('a.family_id', '=', 'v.family_id')->on('a.id', '=', 'v.photo_asset_id');
        })->where('v.family_id', $family)->select('v.*', 'a.status as photo_status');
    }

    private function find(string $family, string $vehicle): object
    {
        return $this->query($family)->where('v.id', $vehicle)->firstOrFail();
    }

    private function payload(Request $request, object $vehicle): array
    {
        return ['id' => $vehicle->id, 'name' => $vehicle->name, 'make' => $vehicle->make, 'model' => $vehicle->model,
            'registration' => $vehicle->registration, 'year' => $vehicle->year, 'version' => (int) $vehicle->version,
            'photo_status' => $vehicle->photo_status, 'archived_at' => $vehicle->archived_at,
            'can_manage' => $request->attributes->get('membership')->role !== 'limited'];
    }

    private function writable(Request $request): void
    {
        abort_if($request->attributes->get('membership')->role === 'limited', 403, 'Accesul tău permite numai consultarea mașinilor.');
    }

    private function version(Request $request, int $version): void
    {
        abort_unless($request->header('If-Match'), 428, 'Versiunea este necesară.');
        abort_unless($request->header('If-Match') === '"'.$version.'"', 412, 'Datele s-au schimbat. Reîncarcă înainte de a salva.');
    }

    public function index(Request $request, string $family): JsonResponse
    {
        $request->validate(['archived' => 'sometimes|boolean']);
        $query = $this->query($family);
        $request->boolean('archived') ? $query->whereNotNull('v.archived_at') : $query->whereNull('v.archived_at');

        return response()->json($query->orderBy('v.created_at')->orderBy('v.id')->get()->map(fn (object $row) => $this->payload($request, $row)));
    }

    public function show(Request $request, string $family, string $vehicle): JsonResponse
    {
        $row = $this->find($family, $vehicle);

        return response()->json($this->payload($request, $row))->header('ETag', '"'.$row->version.'"');
    }

    private function content(Request $request): array
    {
        return $request->validate(['name' => 'required|string|max:100', 'make' => 'required|string|max:60', 'model' => 'required|string|max:60',
            'registration' => 'present|nullable|string|max:24', 'year' => 'present|nullable|integer|min:1900|max:'.(now()->year + 1),
            'family_id' => 'prohibited', 'created_by' => 'prohibited', 'photo_asset_id' => 'prohibited']);
    }

    public function store(Request $request, string $family): JsonResponse
    {
        $this->writable($request);
        $data = $this->content($request);
        $id = (string) Str::uuid();
        DB::table('vehicles')->insert($data + ['id' => $id, 'family_id' => $family, 'created_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);
        Audit::record($family, $request->user()->id, 'vehicle.created', $id);

        return $this->show($request, $family, $id)->setStatusCode(201);
    }

    private function editable(Request $request, string $family, string $vehicle): object
    {
        $row = $this->find($family, $vehicle);
        $this->writable($request);
        $this->version($request, (int) $row->version);

        return $row;
    }

    private function active(string $family, string $vehicle): void
    {
        abort_if($this->find($family, $vehicle)->archived_at, 409, 'Restaurează mașina înainte de modificare.');
    }

    public function update(Request $request, string $family, string $vehicle): JsonResponse
    {
        $row = $this->editable($request, $family, $vehicle);
        abort_if($row->archived_at, 409, 'Restaurează mașina înainte de modificare.');
        DB::table('vehicles')->where('family_id', $family)->where('id', $vehicle)->update($this->content($request) + ['version' => $row->version + 1, 'updated_at' => now()]);
        Audit::record($family, $request->user()->id, 'vehicle.updated', $vehicle);

        return $this->show($request, $family, $vehicle);
    }

    public function archive(Request $request, string $family, string $vehicle): JsonResponse
    {
        $row = $this->editable($request, $family, $vehicle);
        $data = $request->validate(['archived' => 'required|boolean']);
        DB::table('vehicles')->where('family_id', $family)->where('id', $vehicle)->update(['archived_at' => $data['archived'] ? now() : null, 'version' => $row->version + 1, 'updated_at' => now()]);
        Audit::record($family, $request->user()->id, $data['archived'] ? 'vehicle.archived' : 'vehicle.restored', $vehicle);

        return $this->show($request, $family, $vehicle);
    }

    public function photo(Request $request, string $family, string $vehicle): StreamedResponse
    {
        $row = $this->find($family, $vehicle);
        abort_unless($row->photo_asset_id, 404);

        return $this->media->image($family, $row->photo_asset_id);
    }

    public function uploadPhoto(Request $request, string $family, string $vehicle): JsonResponse
    {
        $row = $this->editable($request, $family, $vehicle);
        abort_if($row->archived_at, 409, 'Restaurează mașina înainte de modificare.');
        $data = $request->validate(['action' => 'required|in:replace,remove', 'photo' => 'required_if:action,replace|prohibited_if:action,remove|file|max:10240']);
        $asset = $data['action'] === 'replace' ? $this->media->receiveImage($family, $request->user()->id, $request->file('photo')) : null;
        DB::table('vehicles')->where('family_id', $family)->where('id', $vehicle)->update(['photo_asset_id' => $asset['id'] ?? null, 'version' => $row->version + 1, 'updated_at' => now()]);
        Audit::record($family, $request->user()->id, 'vehicle.photo_changed', $vehicle);

        return $this->show($request, $family, $vehicle);
    }

    public function retryPhoto(Request $request, string $family, string $vehicle): JsonResponse
    {
        $row = $this->editable($request, $family, $vehicle);
        abort_if($row->archived_at, 409, 'Restaurează mașina înainte de modificare.');
        abort_unless($row->photo_asset_id, 404);
        $this->media->retry($family, $row->photo_asset_id);

        return $this->show($request, $family, $vehicle);
    }

    public function entries(Request $request, string $family, string $vehicle): JsonResponse
    {
        $this->find($family, $vehicle);
        $data = $request->validate(['page' => 'sometimes|integer|min:1|max:10000', 'state' => 'sometimes|in:planned,completed',
            'group' => 'sometimes|in:maintenance,costs', 'archived' => 'sometimes|boolean']);
        $query = DB::table('vehicle_entries')->where('family_id', $family)->where('vehicle_id', $vehicle);
        $request->boolean('archived') ? $query->whereNotNull('archived_at') : $query->whereNull('archived_at');
        if (isset($data['state'])) {
            $query->where('state', $data['state']);
        }
        if (($data['group'] ?? null) === 'maintenance') {
            $query->whereNotIn('kind', ['fuel', 'other']);
        } elseif (($data['group'] ?? null) === 'costs') {
            $query->where('state', 'completed');
        }
        $page = (int) ($data['page'] ?? 1);
        $rows = $query->orderBy('event_date', ($data['state'] ?? null) === 'planned' ? 'asc' : 'desc')->orderBy('id')->offset(($page - 1) * 20)->limit(21)->get();

        return response()->json(['items' => $rows->take(20)->map(fn (object $entry) => $this->entryPayload($entry))->values(), 'has_more' => $rows->count() > 20, 'page' => $page]);
    }

    private function entryPayload(object $row): array
    {
        return ['id' => $row->id, 'kind' => $row->kind, 'title' => $row->title, 'state' => $row->state, 'event_date' => $row->event_date,
            'cost' => $row->cost, 'odometer_km' => $row->odometer_km, 'notes' => $row->notes, 'version' => (int) $row->version, 'archived_at' => $row->archived_at];
    }

    private function entryContent(Request $request): array
    {
        $kinds = $request->input('state') === 'planned' ? 'itp,rca,rovinieta,service' : 'itp,rca,rovinieta,service,fuel,other';

        return $request->validate(['kind' => 'required|in:'.$kinds, 'title' => 'required|string|max:160', 'state' => 'required|in:planned,completed',
            'event_date' => 'required|date_format:Y-m-d|after_or_equal:1900-01-01|before_or_equal:'.($request->input('state') === 'completed' ? now('Europe/Bucharest')->toDateString() : '2100-12-31'),
            'cost' => 'present|nullable|prohibited_if:state,planned|numeric|min:0|max:9999999999.99|decimal:0,2',
            'odometer_km' => 'present|nullable|prohibited_if:state,planned|integer|min:0|max:9999999',
            'notes' => 'present|nullable|string|max:4000', 'family_id' => 'prohibited', 'vehicle_id' => 'prohibited', 'author_id' => 'prohibited']);
    }

    public function storeEntry(Request $request, string $family, string $vehicle): JsonResponse
    {
        $this->active($family, $vehicle);
        $this->writable($request);
        $id = (string) Str::uuid();
        $data = $this->entryContent($request);
        DB::table('vehicle_entries')->insert($data + ['id' => $id, 'family_id' => $family, 'vehicle_id' => $vehicle, 'author_id' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);
        Audit::record($family, $request->user()->id, 'vehicle.entry_created', $id);

        return $this->showEntry($request, $family, $vehicle, $id)->setStatusCode(201);
    }

    private function entry(string $family, string $vehicle, string $entry): object
    {
        return DB::table('vehicle_entries')->where('family_id', $family)->where('vehicle_id', $vehicle)->where('id', $entry)->firstOrFail();
    }

    public function showEntry(Request $request, string $family, string $vehicle, string $entry): JsonResponse
    {
        return response()->json($this->entryPayload($this->entry($family, $vehicle, $entry)));
    }

    public function updateEntry(Request $request, string $family, string $vehicle, string $entry): JsonResponse
    {
        $row = $this->entry($family, $vehicle, $entry);
        $this->active($family, $vehicle);
        $this->writable($request);
        $this->version($request, (int) $row->version);
        abort_if($row->archived_at, 409, 'Restaurează înregistrarea înainte de editare.');
        DB::table('vehicle_entries')->where('family_id', $family)->where('id', $entry)->update($this->entryContent($request) + ['version' => $row->version + 1, 'updated_at' => now()]);
        Audit::record($family, $request->user()->id, 'vehicle.entry_updated', $entry);

        return $this->showEntry($request, $family, $vehicle, $entry);
    }

    public function archiveEntry(Request $request, string $family, string $vehicle, string $entry): JsonResponse
    {
        $row = $this->entry($family, $vehicle, $entry);
        $this->active($family, $vehicle);
        $this->writable($request);
        $this->version($request, (int) $row->version);
        $data = $request->validate(['archived' => 'required|boolean']);
        DB::table('vehicle_entries')->where('family_id', $family)->where('id', $entry)->update(['archived_at' => $data['archived'] ? now() : null, 'version' => $row->version + 1, 'updated_at' => now()]);
        Audit::record($family, $request->user()->id, $data['archived'] ? 'vehicle.entry_archived' : 'vehicle.entry_restored', $entry);

        return $this->showEntry($request, $family, $vehicle, $entry);
    }

    public function documents(Request $request, string $family, string $vehicle): JsonResponse
    {
        $this->find($family, $vehicle);
        $visible = $this->documents->references($family, $request->user()->id, $request->attributes->get('membership')->id);

        return response()->json(DB::table('vehicle_documents as l')->joinSub($visible, 'd', 'd.id', '=', 'l.document_id')
            ->where('l.family_id', $family)->where('l.vehicle_id', $vehicle)->orderBy('d.title')->get(['d.*']));
    }

    public function linkDocument(Request $request, string $family, string $vehicle): JsonResponse
    {
        $this->active($family, $vehicle);
        $this->writable($request);
        $data = $request->validate(['document_id' => 'required|uuid']);
        $this->documents->find($family, $request->user()->id, $request->attributes->get('membership')->id, $data['document_id']);
        DB::table('vehicle_documents')->insertOrIgnore(['family_id' => $family, 'vehicle_id' => $vehicle, 'document_id' => $data['document_id']]);
        Audit::record($family, $request->user()->id, 'vehicle.document_linked', $vehicle);

        return $this->documents($request, $family, $vehicle);
    }

    public function unlinkDocument(Request $request, string $family, string $vehicle, string $document): JsonResponse
    {
        $this->active($family, $vehicle);
        $this->writable($request);
        $this->documents->find($family, $request->user()->id, $request->attributes->get('membership')->id, $document);
        DB::table('vehicle_documents')->where('family_id', $family)->where('vehicle_id', $vehicle)->where('document_id', $document)->delete();
        Audit::record($family, $request->user()->id, 'vehicle.document_unlinked', $vehicle);

        return response()->json(['ok' => true]);
    }
}
