<?php

namespace App\Platform\Administration;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

final class AdminContext
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(AdministratorAccess::allows($request->user()), 403);

        return DB::transaction(function () use ($request, $next): Response {
            DB::select("SELECT set_config('app.email_settings_access', 'admin', true)");

            return $next($request);
        });
    }
}
