<?php

namespace App\Platform\Families;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class TenantContext
{
    public function handle(Request $request, Closure $next)
    {
        $familyId = $request->route('family');
        abort_unless(is_string($familyId) && Str::isUuid($familyId), 404);

        return DB::transaction(function () use ($request, $next, $familyId) {
            // Serialize policy checks and revocations in this small first vertical.
            // Revocation is effective for every request beginning after its commit.
            $membership = DB::table('family_memberships')->where('family_id', $familyId)
                ->where('user_id', $request->user()->id)->whereNull('revoked_at')->first();
            abort_unless($membership, 404);
            DB::table('families')->where('id', $familyId)->lockForUpdate()->firstOrFail();
            $membership = DB::table('family_memberships')->where('id', $membership->id)->whereNull('revoked_at')->first();
            abort_unless($membership, 404);
            if (DB::getDriverName() === 'pgsql') {
                DB::select("SELECT set_config('app.family_id', ?, true)", [$familyId]);
                DB::select("SELECT set_config('app.actor_id', ?, true)", [(string) $request->user()->id]);
            }
            $request->attributes->set('membership', $membership);

            return $next($request);
        });
    }
}
