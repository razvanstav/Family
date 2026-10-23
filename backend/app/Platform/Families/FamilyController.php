<?php

namespace App\Platform\Families;

use App\Platform\Audit\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class FamilyController
{
    public function members(Request $request, string $family)
    {
        return DB::table('family_memberships as m')->leftJoin('users as u', 'u.id', '=', 'm.user_id')
            ->leftJoin('member_profiles as p', function ($join) {
                $join->on('p.family_id', '=', 'm.family_id')->on('p.membership_id', '=', 'm.id');
            })->leftJoin('media_assets as photo', function ($join) {
                $join->on('photo.family_id', '=', 'm.family_id')->on('photo.id', '=', 'p.photo_asset_id');
            })
            ->where('m.family_id', $family)->whereNull('m.revoked_at')->orderByRaw('coalesce(u.name, m.child_name)')
            ->select('m.id', 'm.user_id', 'm.role', 'photo.status as photo_status')->selectRaw('coalesce(u.name, m.child_name) as name')
            ->selectRaw("coalesce(p.version, 0) as photo_version, extract(year from age((now() at time zone 'Europe/Bucharest')::date, p.birth_date))::integer as age")->get();
    }

    public function storeChild(Request $request, string $family, MemberProfileAccess $access): JsonResponse
    {
        abort_unless($request->attributes->get('membership')->role === 'owner', 403);
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'birth_date' => 'required|date_format:Y-m-d|after_or_equal:1900-01-01|before_or_equal:'.now('Europe/Bucharest')->toDateString(),
            'parent_ids' => 'required|array|min:1|max:2', 'parent_ids.*' => 'required|uuid|distinct',
            'email' => 'prohibited', 'user_id' => 'prohibited', 'role' => 'prohibited',
        ]);
        $age = $access->age($data['birth_date']);
        abort_unless($age < 14, 422, 'Profilul fără cont se adaugă pentru un copil sub 14 ani.');
        $member = (string) Str::uuid();
        $access->validateParents($family, $member, $data['parent_ids']);
        DB::table('family_memberships')->insert(['id' => $member, 'family_id' => $family, 'user_id' => null, 'child_name' => $data['name'], 'role' => 'limited']);
        DB::table('member_profiles')->insert(['membership_id' => $member, 'family_id' => $family, 'birth_date' => $data['birth_date'], 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('member_profile_parents')->insert(array_map(fn (string $parent) => ['family_id' => $family, 'membership_id' => $member, 'parent_membership_id' => $parent], $data['parent_ids']));
        DB::table('families')->where('id', $family)->increment('authz_revision');
        Audit::record($family, $request->user()->id, 'profile.child_created', $member);

        return response()->json(['id' => $member, 'user_id' => null, 'name' => $data['name'], 'role' => 'limited', 'age' => $age, 'photo_status' => null, 'photo_version' => 1], 201);
    }

    public function revoke(Request $request, string $family, string $member)
    {
        abort_unless($request->attributes->get('membership')->role === 'owner', 403);
        $target = DB::table('family_memberships')->where('family_id', $family)->where('id', $member)->whereNull('revoked_at')->first();
        abort_unless($target, 404);
        abort_if($target->user_id === $request->user()->id || $target->role === 'owner', 422, 'Proprietarul familiei nu poate fi revocat din acest ecran.');
        DB::table('family_memberships')->where('id', $member)->update(['revoked_at' => now()]);
        DB::table('families')->where('id', $family)->increment('authz_revision');
        event(new MembershipRevoked($family, $member));
        Audit::record($family, $request->user()->id, 'membership.revoked', $member);

        return response()->noContent();
    }

    public function updateRole(Request $request, string $family, string $member): Response
    {
        abort_unless($request->attributes->get('membership')->role === 'owner', 403);
        $target = DB::table('family_memberships')->where('family_id', $family)->where('id', $member)->whereNull('revoked_at')->first();
        abort_unless($target, 404);
        abort_if($target->user_id === $request->user()->id || $target->role === 'owner', 422, 'Rolul proprietarului nu poate fi schimbat din acest ecran.');
        abort_if($target->user_id === null, 422, 'Profilul fără cont nu primește un rol de autentificare.');
        $data = $request->validate([
            'role' => 'required|in:adult,limited',
            'expected_role' => 'required|in:adult,limited',
        ]);
        abort_unless($target->role === $data['expected_role'], 409, 'Rolul s-a schimbat. Reîncarcă membrii înainte de a salva.');
        if ($target->role === $data['role']) {
            return response()->noContent();
        }
        DB::table('family_memberships')->where('family_id', $family)->where('id', $member)->update(['role' => $data['role']]);
        DB::table('families')->where('id', $family)->increment('authz_revision');
        Audit::record($family, $request->user()->id, 'membership.role_changed', $member);

        return response()->noContent();
    }
}
