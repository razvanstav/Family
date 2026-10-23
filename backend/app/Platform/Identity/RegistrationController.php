<?php

namespace App\Platform\Identity;

use App\Models\User;
use App\Platform\Audit\Audit;
use App\Platform\Families\InvitationCodes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class RegistrationController
{
    public function __invoke(Request $request, InvitationCodes $codes): JsonResponse
    {
        abort_if($request->user(), 409, 'Ești deja autentificat. Deconectează-te înainte de a crea alt cont.');
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|email|max:254',
            'password' => 'required|string|min:12|max:200',
            'family_name' => 'required|string|max:100',
            'invitation_code' => 'sometimes|required|string|max:100',
            'user_id' => 'prohibited', 'family_id' => 'prohibited',
            'role' => 'prohibited', 'email_verified_at' => 'prohibited',
        ], [
            'password.min' => 'Parola trebuie să aibă cel puțin 12 caractere.',
            'invitation_code.required' => 'Numele familiei sau codul de invitație nu este valid. Cere un cod nou administratorului.',
            'invitation_code.string' => 'Numele familiei sau codul de invitație nu este valid. Cere un cod nou administratorului.',
            'invitation_code.max' => 'Numele familiei sau codul de invitație nu este valid. Cere un cod nou administratorului.',
        ]);
        $email = Str::lower(trim($data['email']));
        $user = DB::transaction(function () use ($data, $email, $codes): User {
            $invitation = isset($data['invitation_code']) ? $codes->lockValid($data['family_name'], $data['invitation_code']) : null;
            DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', [$email]);
            if (User::whereRaw('lower(email) = ?', [$email])->exists()) {
                throw ValidationException::withMessages(['email' => 'Există deja un cont cu această adresă. Intră în cont.']);
            }

            $user = User::create(['name' => $data['name'], 'email' => $email, 'password' => $data['password']]);
            $family = $invitation?->family_id ?? (string) Str::uuid();
            if (! $invitation) {
                DB::table('families')->insert(['id' => $family, 'name' => $data['family_name'], 'created_at' => now(), 'updated_at' => now()]);
                DB::select("SELECT set_config('app.family_id', ?, true)", [$family]);
            }
            $membership = (string) Str::uuid();
            DB::table('family_memberships')->insert(['id' => $membership, 'family_id' => $family, 'user_id' => $user->id, 'role' => $invitation ? 'adult' : 'owner']);
            if ($invitation) {
                DB::table('family_invitation_codes')->where('family_id', $family)->where('id', $invitation->id)->update(['used_at' => now(), 'used_by' => $user->id]);
                DB::table('families')->where('id', $family)->increment('authz_revision');
                Audit::record($family, $user->id, 'invitation_code.accepted', $invitation->id);
            } else {
                Audit::record($family, $user->id, 'family.created', $family);
            }
            Audit::record($family, $user->id, 'membership.added', $membership);

            return $user;
        });
        Auth::login($user);
        $request->session()->regenerate();

        return (new SessionController)->show($request)->setStatusCode(201);
    }
}
