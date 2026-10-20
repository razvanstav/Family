<?php

namespace App\Modules\Documents\Http;

use App\Modules\Documents\Application\DocumentAccess;
use App\Platform\Audit\Audit;
use App\Platform\Contracts\PrivateMedia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class DocumentController
{
    public function __construct(private DocumentAccess $access, private PrivateMedia $media) {}

    public function index(Request $request, string $family)
    {
        return $this->access->visible($family, $request->user()->id, $request->attributes->get('membership')->id)
            ->join('media_assets as a', function ($join) {
                $join->on('a.id', '=', 'd.asset_id')->on('a.family_id', '=', 'd.family_id');
            })
            ->join('users as u', 'u.id', '=', 'd.owner_id')->orderByDesc('d.created_at')
            ->get(['d.id', 'd.title', 'd.category', 'd.visibility', 'd.owner_id', 'd.version', 'd.created_at',
                'a.mime', 'a.bytes', 'a.status', 'u.name as owner_name']);
    }

    private function find(Request $request, string $family, string $id): object
    {
        return $this->access->find($family, $request->user()->id, $request->attributes->get('membership')->id, $id);
    }

    public function show(Request $request, string $family, string $document)
    {
        $doc = $this->find($request, $family, $document);
        $asset = DB::table('media_assets')->where('family_id', $family)->where('id', $doc->asset_id)->firstOrFail();
        $data = (array) $doc;
        unset($data['asset_id'], $data['family_id']);
        $data += ['mime' => $asset->mime, 'bytes' => $asset->bytes, 'status' => $asset->status, 'original_name' => $asset->original_name,
            'owner_name' => DB::table('users')->where('id', $doc->owner_id)->value('name')];
        $data['grants'] = $doc->owner_id === $request->user()->id
            ? DB::table('document_grants')->where('family_id', $family)->where('document_id', $document)->pluck('membership_id') : [];

        return response()->json($data)->header('ETag', '"'.$doc->version.'"');
    }

    public function store(Request $request, string $family)
    {
        abort_if($request->attributes->get('membership')->role === 'limited', 403);
        $validated = $request->validate([
            'title' => 'required|string|max:160',
            'category' => ['required', Rule::in(['personal', 'home', 'health', 'memories'])],
            'file' => 'required|file|max:10240',
            'family_id' => 'prohibited', 'owner_id' => 'prohibited', 'visibility' => 'prohibited',
        ]);
        $asset = $this->media->receive($family, $request->user()->id, $request->file('file'));
        $id = (string) Str::uuid();
        DB::table('documents')->insert([
            'id' => $id, 'family_id' => $family, 'owner_id' => $request->user()->id, 'asset_id' => $asset['id'],
            'title' => $validated['title'], 'category' => $validated['category'], 'visibility' => 'private',
            'version' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        Audit::record($family, $request->user()->id, 'document.created', $id);

        return $this->show($request, $family, $id)->setStatusCode(201);
    }

    public function sharing(Request $request, string $family, string $document)
    {
        $doc = $this->find($request, $family, $document);
        abort_unless($doc->owner_id === $request->user()->id, 403);
        abort_unless($request->header('If-Match'), 428, 'Versiunea documentului este necesară.');
        abort_unless($request->header('If-Match') === '"'.$doc->version.'"', 412, 'Documentul s-a schimbat. Reîncarcă pagina.');
        $data = $request->validate([
            'visibility' => ['required', Rule::in(['private', 'family', 'selected'])],
            'membership_ids' => 'present|array|max:30',
            'membership_ids.*' => 'required|uuid|distinct',
        ]);
        $ids = $data['membership_ids'];
        abort_if($data['visibility'] === 'selected' && count($ids) === 0, 422, 'Alege cel puțin o persoană.');
        abort_if($data['visibility'] !== 'selected' && count($ids) > 0, 422, 'Destinatarii sunt valabili doar pentru acces selectat.');
        $validCount = DB::table('family_memberships')->where('family_id', $family)->whereNull('revoked_at')
            ->where('user_id', '!=', $request->user()->id)->whereIn('id', $ids)->count();
        abort_unless($validCount === count($ids), 422, 'Un membru selectat nu mai este disponibil.');
        DB::table('document_grants')->where('family_id', $family)->where('document_id', $document)->delete();
        foreach ($ids as $id) {
            DB::table('document_grants')->insert(['family_id' => $family, 'document_id' => $document, 'membership_id' => $id]);
        }
        DB::table('documents')->where('family_id', $family)->where('id', $document)->update([
            'visibility' => $data['visibility'], 'version' => $doc->version + 1, 'updated_at' => now(),
        ]);
        DB::table('families')->where('id', $family)->increment('authz_revision');
        Audit::record($family, $request->user()->id, 'document.access_changed', $document);

        return $this->show($request, $family, $document);
    }

    public function download(Request $request, string $family, string $document)
    {
        $doc = $this->find($request, $family, $document);
        Audit::record($family, $request->user()->id, 'document.download', $document);

        return $this->media->download($family, $doc->asset_id);
    }

    public function retry(Request $request, string $family, string $document)
    {
        $doc = $this->find($request, $family, $document);
        abort_unless($doc->owner_id === $request->user()->id, 403);
        $this->media->retry($family, $doc->asset_id);
        Audit::record($family, $request->user()->id, 'document.scan_retried', $document);

        return $this->show($request, $family, $document);
    }
}
