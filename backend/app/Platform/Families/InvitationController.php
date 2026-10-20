<?php

namespace App\Platform\Families;

use App\Models\User;
use App\Platform\Audit\Audit;
use App\Platform\Identity\SessionController;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class InvitationController
{
    public function index(Request $request, string $family): JsonResponse
    {
        $this->authorizeOwner($request);

        return response()->json(DB::table('family_invitations')->where('family_id', $family)
            ->whereNull('accepted_at')->whereNull('cancelled_at')->where('expires_at', '>', now())
            ->orderByDesc('created_at')->get(['id', 'email', 'role', 'expires_at']));
    }

    public function store(Request $request, string $family): JsonResponse
    {
        $this->authorizeOwner($request);
        $data = $request->validate(['email' => 'required|email|max:254', 'role' => 'required|in:adult,limited']);
        $email = Str::lower(trim($data['email']));
        $member = DB::table('family_memberships as m')->join('users as u', 'u.id', '=', 'm.user_id')
            ->where('m.family_id', $family)->whereRaw('lower(u.email) = ?', [$email])->first(['m.revoked_at']);
        abort_if($member, 422, $member && $member->revoked_at
            ? 'Accesul acestui membru a fost revocat. Reactivarea nu este disponibilă.'
            : 'Această persoană este deja membru.');
        abort_if(DB::table('family_invitations')->where('family_id', $family)->where('email', $email)
            ->whereNull('accepted_at')->whereNull('cancelled_at')->where('expires_at', '>', now())->exists(),
            422, 'Există deja o invitație activă. Anuleaz-o înainte de a crea alta.');
        $id = (string) Str::uuid();
        $token = bin2hex(random_bytes(32));
        DB::table('family_invitations')->insert([
            'id' => $id, 'family_id' => $family, 'email' => $email, 'role' => $data['role'],
            'invited_by' => $request->user()->id, 'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addDays(7), 'created_at' => now(),
        ]);
        Audit::record($family, $request->user()->id, 'invitation.created', $id);

        return response()->json(['id' => $id, 'token' => $token], 201);
    }

    public function cancel(Request $request, string $family, string $invitation): Response
    {
        $this->authorizeOwner($request);
        $changed = DB::table('family_invitations')->where('family_id', $family)->where('id', $invitation)
            ->whereNull('accepted_at')->whereNull('cancelled_at')->update(['cancelled_at' => now()]);
        abort_unless($changed, 404);
        Audit::record($family, $request->user()->id, 'invitation.cancelled', $invitation);

        return response()->noContent();
    }

    public function inspect(Request $request, string $family): JsonResponse
    {
        return $this->withInvitation($request, $family, function (object $invitation, object $familyRow): JsonResponse {
            return response()->json([
                'family_name' => $familyRow->name, 'email' => $invitation->email, 'role' => $invitation->role,
                'existing_account' => User::whereRaw('lower(email) = ?', [$invitation->email])->exists(),
            ]);
        });
    }

    public function accept(Request $request, string $family): JsonResponse
    {
        $user = $this->withInvitation($request, $family, function (object $invitation) use ($request, $family): User {
            // Serialize account creation across invitations for the same address in different families.
            DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', [$invitation->email]);
            $existing = User::whereRaw('lower(email) = ?', [$invitation->email])->first();
            if ($request->user()) {
                abort_unless(Str::lower($request->user()->email) === $invitation->email, 403, 'Autentifică-te cu adresa invitată.');
                $user = $request->user();
            } else {
                abort_if($existing, 401, 'Autentifică-te pentru a accepta invitația.');
                $data = $request->validate([
                    'name' => 'required|string|max:120',
                    'password' => 'required|string|min:12|max:200|confirmed',
                ], [
                    'password.min' => 'Parola trebuie să aibă cel puțin 12 caractere.',
                    'password.confirmed' => 'Parolele nu coincid.',
                ]);
                $user = User::create(['name' => $data['name'], 'email' => $invitation->email, 'password' => $data['password']]);
            }
            abort_if(DB::table('family_memberships')->where('family_id', $family)->where('user_id', $user->id)->exists(),
                422, 'Există deja un membru asociat acestui cont.');
            $membership = (string) Str::uuid();
            DB::table('family_memberships')->insert([
                'id' => $membership, 'family_id' => $family, 'user_id' => $user->id, 'role' => $invitation->role,
            ]);
            DB::table('family_invitations')->where('id', $invitation->id)->update(['accepted_at' => now()]);
            DB::table('families')->where('id', $family)->increment('authz_revision');
            Audit::record($family, $user->id, 'invitation.accepted', $invitation->id);
            Audit::record($family, $user->id, 'membership.added', $membership);

            return $user;
        });
        Auth::login($user);
        $request->session()->regenerate();

        return (new SessionController)->show($request);
    }

    private function authorizeOwner(Request $request): void
    {
        abort_unless($request->attributes->get('membership')->role === 'owner', 403);
    }

    private function withInvitation(Request $request, string $family, Closure $callback): mixed
    {
        $data = $request->validate(['token' => 'required|string|size:64|regex:/^[a-f0-9]+$/']);

        return DB::transaction(function () use ($family, $data, $callback): mixed {
            $familyRow = DB::table('families')->where('id', $family)->lockForUpdate()->first();
            abort_unless($familyRow, 404, 'Invitația nu mai este disponibilă.');
            DB::select("SELECT set_config('app.family_id', ?, true)", [$family]);
            $invitation = DB::table('family_invitations')->where('family_id', $family)
                ->where('token_hash', hash('sha256', $data['token']))->whereNull('accepted_at')
                ->whereNull('cancelled_at')->where('expires_at', '>', now())->first();
            abort_unless($invitation, 404, 'Invitația nu mai este disponibilă.');
            abort_unless(DB::table('family_memberships')->where('family_id', $family)
                ->where('user_id', $invitation->invited_by)->where('role', 'owner')->whereNull('revoked_at')->exists(),
                404, 'Invitația nu mai este disponibilă.');

            return $callback($invitation, $familyRow);
        });
    }
}
