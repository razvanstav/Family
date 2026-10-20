<?php

namespace App\Platform\Families;

use App\Platform\Audit\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class FamilyController
{
    public function members(Request $request, string $family)
    {
        return DB::table('family_memberships as m')->join('users as u', 'u.id', '=', 'm.user_id')
            ->where('m.family_id', $family)->whereNull('m.revoked_at')->orderBy('u.name')
            ->get(['m.id', 'm.user_id', 'm.role', 'u.name']);
    }

    public function revoke(Request $request, string $family, string $member)
    {
        abort_unless($request->attributes->get('membership')->role === 'owner', 403);
        $target = DB::table('family_memberships')->where('family_id', $family)->where('id', $member)->whereNull('revoked_at')->first();
        abort_unless($target, 404);
        abort_if($target->user_id === $request->user()->id || $target->role === 'owner', 422, 'Proprietarul familiei nu poate fi revocat din acest ecran.');
        DB::table('family_memberships')->where('id', $member)->update(['revoked_at' => now()]);
        DB::table('document_grants')->where('family_id', $family)->where('membership_id', $member)->delete();
        DB::table('families')->where('id', $family)->increment('authz_revision');
        Audit::record($family, $request->user()->id, 'membership.revoked', $member);

        return response()->noContent();
    }
}
