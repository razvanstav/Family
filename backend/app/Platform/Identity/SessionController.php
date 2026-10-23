<?php

namespace App\Platform\Identity;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class SessionController
{
    public function show(Request $request)
    {
        $user = $request->user();
        $families = $user ? DB::table('families')->join('family_memberships as m', 'm.family_id', '=', 'families.id')
            ->where('m.user_id', $user->id)->whereNull('m.revoked_at')
            ->orderByRaw("CASE WHEN m.role = 'owner' THEN 0 ELSE 1 END")->orderBy('families.name')
            ->get(['families.id', 'families.name', 'm.role', 'm.id as membership_id']) : [];

        return response()->json([
            'user' => $user ? ['id' => $user->id, 'name' => $user->name, 'email' => $user->email] : null,
            'families' => $families, 'csrf_token' => csrf_token(),
            'demo' => app()->environment('local') && config('memoria.demo_enabled'),
        ])->header('Cache-Control', 'no-store');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate(['email' => 'required|email|max:254', 'password' => 'required|string|max:200']);
        $credentials['email'] = Str::lower(trim($credentials['email']));
        if (! Auth::attempt($credentials)) {
            throw ValidationException::withMessages(['email' => 'Adresa de email sau parola nu este corectă.']);
        }
        $request->session()->regenerate();

        return $this->show($request);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return $this->show($request);
    }
}
