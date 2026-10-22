<?php

namespace App\Platform\Families;

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

final class MemberProfileController
{
    private const KINDS = ['visit' => 'health', 'medication' => 'health', 'book' => 'learning', 'culture' => 'learning', 'activity' => 'activities'];

    public function __construct(private MemberProfileAccess $access, private PrivateMedia $media) {}

    public function show(Request $request, string $family, string $member): JsonResponse
    {
        $subject = $this->access->subject($family, $member);
        $parents = DB::table('member_profile_parents as p')->join('family_memberships as m', function ($join) {
            $join->on('m.family_id', '=', 'p.family_id')->on('m.id', '=', 'p.parent_membership_id');
        })->join('users as u', 'u.id', '=', 'm.user_id')->where('p.family_id', $family)->where('p.membership_id', $member)->whereNull('m.revoked_at')->whereIn('m.role', ['owner', 'adult'])->get(['m.id', 'u.name']);

        return response()->json([
            'id' => $subject->id, 'user_id' => $subject->user_id, 'name' => $subject->name, 'role' => $subject->role,
            'age' => $this->access->age($subject->birth_date),
            'birth_date' => $request->attributes->get('membership')->role === 'owner' || (int) $subject->user_id === $request->user()->id ? $subject->birth_date : null,
            'parents' => $parents, 'version' => (int) $subject->version, 'photo_status' => $subject->photo_status,
            'can_write' => $this->access->canWrite($request, $family, $subject),
            'can_configure' => $request->attributes->get('membership')->role === 'owner',
        ])->header('ETag', '"'.$subject->version.'"');
    }

    public function configure(Request $request, string $family, string $member): JsonResponse
    {
        abort_unless($request->attributes->get('membership')->role === 'owner', 403);
        $subject = $this->access->subject($family, $member);
        $this->access->version($request, (int) $subject->version);
        $data = $request->validate([
            'birth_date' => 'present|nullable|date_format:Y-m-d|after_or_equal:1900-01-01|before_or_equal:'.now('Europe/Bucharest')->toDateString(),
            'parent_ids' => 'present|array|max:2', 'parent_ids.*' => 'required|uuid|distinct',
            'name' => $subject->user_id === null ? 'sometimes|required|string|max:100' : 'prohibited',
        ]);
        if ($subject->user_id === null && $data['birth_date'] === null) {
            throw ValidationException::withMessages(['birth_date' => 'Data nașterii este necesară pentru profilul copilului.']);
        }
        $age = $this->access->age($data['birth_date']);
        $parents = $data['parent_ids'];
        if ($age !== null && $age < 18) {
            if ($subject->role === 'owner' || $parents === [] || DB::table('member_profile_parents')->where('family_id', $family)->where('parent_membership_id', $member)->exists()) {
                throw ValidationException::withMessages(['parent_ids' => 'Un copil are nevoie de cel puțin un părinte desemnat și nu poate fi proprietar sau părinte al altui profil.']);
            }
        } elseif ($parents !== []) {
            throw ValidationException::withMessages(['parent_ids' => 'Părinții desemnați se configurează numai pentru minori.']);
        }
        $this->access->validateParents($family, $member, $parents);
        if (isset($data['name'])) {
            DB::table('family_memberships')->where('family_id', $family)->where('id', $member)->update(['child_name' => $data['name']]);
        }
        $this->saveProfile($family, $member, (int) $subject->version, ['birth_date' => $data['birth_date']]);
        DB::table('member_profile_parents')->where('family_id', $family)->where('membership_id', $member)->delete();
        if ($parents !== []) {
            DB::table('member_profile_parents')->insert(array_map(fn (string $parent) => ['family_id' => $family, 'membership_id' => $member, 'parent_membership_id' => $parent], $parents));
        }
        DB::table('families')->where('id', $family)->increment('authz_revision');
        Audit::record($family, $request->user()->id, 'profile.configured', $member);

        return $this->show($request, $family, $member);
    }

    private function saveProfile(string $family, string $member, int $version, array $data): void
    {
        if ($version === 0) {
            DB::table('member_profiles')->insert($data + ['membership_id' => $member, 'family_id' => $family, 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
        } else {
            DB::table('member_profiles')->where('family_id', $family)->where('membership_id', $member)->update($data + ['version' => $version + 1, 'updated_at' => now()]);
        }
    }

    public function uploadPhoto(Request $request, string $family, string $member): JsonResponse
    {
        $subject = $this->access->subject($family, $member);
        abort_unless($this->access->canWrite($request, $family, $subject), 403, 'Nu poți modifica fotografia acestui profil.');
        $this->access->version($request, (int) $subject->version);
        $data = $request->validate(['action' => 'required|in:replace,remove', 'photo' => 'required_if:action,replace|prohibited_if:action,remove|file|max:10240']);
        $asset = $data['action'] === 'replace' ? $this->media->receiveImage($family, $request->user()->id, $request->file('photo')) : null;
        $this->saveProfile($family, $member, (int) $subject->version, ['photo_asset_id' => $asset['id'] ?? null]);
        Audit::record($family, $request->user()->id, 'profile.photo_changed', $member);

        return $this->show($request, $family, $member);
    }

    public function photo(Request $request, string $family, string $member): StreamedResponse
    {
        $subject = $this->access->subject($family, $member);
        abort_unless($subject->photo_asset_id, 404);

        return $this->media->image($family, $subject->photo_asset_id);
    }

    public function retryPhoto(Request $request, string $family, string $member): JsonResponse
    {
        $subject = $this->access->subject($family, $member);
        abort_unless($subject->photo_asset_id && $this->access->canWrite($request, $family, $subject), 403);
        $this->access->version($request, (int) $subject->version);
        $this->media->retry($family, $subject->photo_asset_id);

        return $this->show($request, $family, $member);
    }

    private function visibleEntries(Request $request, string $family, string $member): Builder
    {
        return DB::table('member_profile_entries as e')->join('users as u', 'u.id', '=', 'e.author_id')->where('e.family_id', $family)->where('e.membership_id', $member)
            ->where(fn (Builder $query) => $query->where('e.author_id', $request->user()->id)->orWhere('e.visibility', 'family'))->select('e.*', 'u.name as author_name');
    }

    public function entries(Request $request, string $family, string $member): JsonResponse
    {
        $subject = $this->access->subject($family, $member);
        $filters = $request->validate(['category' => 'sometimes|in:health,learning,activities', 'page' => 'sometimes|integer|min:1|max:10000', 'archived' => 'sometimes|boolean']);
        $query = $this->visibleEntries($request, $family, $member);
        ($filters['archived'] ?? false) ? $query->whereNotNull('e.archived_at')->where('e.author_id', $request->user()->id) : $query->whereNull('e.archived_at');
        if (isset($filters['category'])) {
            $query->whereIn('e.kind', array_keys(self::KINDS, $filters['category'], true));
        }
        $page = (int) ($filters['page'] ?? 1);
        $rows = $query->orderByDesc('e.occurred_on')->orderByDesc('e.id')->offset(($page - 1) * 12)->limit(13)->get();
        $canWrite = $this->access->canWrite($request, $family, $subject);

        return response()->json(['items' => $rows->take(12)->map(fn (object $entry) => $this->entryPayload($request, $entry, $canWrite))->values(), 'page' => $page, 'has_more' => $rows->count() > 12]);
    }

    private function entryPayload(Request $request, object $entry, bool $canWrite): array
    {
        return ['id' => $entry->id, 'kind' => $entry->kind, 'category' => self::KINDS[$entry->kind], 'title' => $entry->title, 'notes' => $entry->notes,
            'occurred_on' => $entry->occurred_on, 'visibility' => $entry->visibility, 'version' => $entry->version, 'archived_at' => $entry->archived_at,
            'author_name' => $entry->author_name, 'can_manage' => $canWrite && (int) $entry->author_id === $request->user()->id];
    }

    private function content(Request $request): array
    {
        return $request->validate(['kind' => ['required', Rule::in(array_keys(self::KINDS))], 'title' => 'required|string|max:160', 'notes' => 'nullable|string|max:8000',
            'occurred_on' => 'required|date_format:Y-m-d|after_or_equal:1900-01-01|before_or_equal:2100-12-31', 'visibility' => 'required|in:private,family']);
    }

    public function storeEntry(Request $request, string $family, string $member): JsonResponse
    {
        $subject = $this->access->subject($family, $member);
        abort_unless($this->access->canWrite($request, $family, $subject), 403, 'Numai persoana sau părinții desemnați, conform vârstei, pot adăuga date.');
        $data = $this->content($request);
        $id = (string) Str::uuid();
        DB::table('member_profile_entries')->insert($data + ['id' => $id, 'family_id' => $family, 'membership_id' => $member, 'author_id' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);
        Audit::record($family, $request->user()->id, 'profile.entry_created', $id);

        return $this->entryResponse($request, $family, $member, $id)->setStatusCode(201);
    }

    private function entryResponse(Request $request, string $family, string $member, string $entry): JsonResponse
    {
        $row = $this->visibleEntries($request, $family, $member)->where('e.id', $entry)->firstOrFail();

        return response()->json($this->entryPayload($request, $row, $this->access->canWrite($request, $family, $this->access->subject($family, $member))))->header('ETag', '"'.$row->version.'"');
    }

    public function updateEntry(Request $request, string $family, string $member, string $entry): JsonResponse
    {
        $row = $this->mutableEntry($request, $family, $member, $entry);
        abort_if($row->archived_at, 409, 'Restaurează însemnarea înainte de editare.');
        DB::table('member_profile_entries')->where('family_id', $family)->where('id', $entry)->update($this->content($request) + ['version' => $row->version + 1, 'updated_at' => now()]);
        Audit::record($family, $request->user()->id, 'profile.entry_updated', $entry);

        return $this->entryResponse($request, $family, $member, $entry);
    }

    private function mutableEntry(Request $request, string $family, string $member, string $entry): object
    {
        $subject = $this->access->subject($family, $member);
        $row = $this->visibleEntries($request, $family, $member)->where('e.id', $entry)->firstOrFail();
        abort_unless((int) $row->author_id === $request->user()->id && $this->access->canWrite($request, $family, $subject), 403, 'Doar autorul poate modifica însemnarea, conform regulilor profilului.');
        $this->access->version($request, (int) $row->version);

        return $row;
    }

    public function archiveEntry(Request $request, string $family, string $member, string $entry): JsonResponse
    {
        $row = $this->mutableEntry($request, $family, $member, $entry);
        $data = $request->validate(['archived' => 'required|boolean']);
        DB::table('member_profile_entries')->where('family_id', $family)->where('id', $entry)->update(['archived_at' => $data['archived'] ? now() : null, 'visibility' => 'private', 'version' => $row->version + 1, 'updated_at' => now()]);
        Audit::record($family, $request->user()->id, $data['archived'] ? 'profile.entry_archived' : 'profile.entry_restored', $entry);

        return $this->entryResponse($request, $family, $member, $entry);
    }
}
