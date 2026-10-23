<?php

namespace App\Platform\Families;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class InvitationCodes
{
    public function lockValid(string $familyName, string $code): object
    {
        $decoded = preg_match('/^[A-Za-z0-9_-]{43}$/D', $code) === 1
            ? base64_decode(strtr($code, '-_', '+/').'=', true) : false;
        if ($decoded === false || strlen($decoded) !== 32 || rtrim(strtr(base64_encode($decoded), '+/', '-_'), '=') !== $code) {
            $this->invalid();
        }
        $hex = bin2hex(substr($decoded, 0, 16));
        $familyId = substr($hex, 0, 8).'-'.substr($hex, 8, 4).'-'.substr($hex, 12, 4).'-'.substr($hex, 16, 4).'-'.substr($hex, 20);
        $family = DB::table('families')->where('id', $familyId)->lockForUpdate()->first();
        if (! $family || Str::lower($family->name) !== Str::lower(trim($familyName))) {
            $this->invalid();
        }
        DB::select("SELECT set_config('app.family_id', ?, true)", [$familyId]);
        $invitation = DB::table('family_invitation_codes')->where('family_id', $familyId)->where('token_hash', hash('sha256', $code))
            ->whereNull('used_at')->whereNull('revoked_at')->where('expires_at', '>', now())->lockForUpdate()->first();
        if (! $invitation || ! DB::table('family_memberships')->where('family_id', $familyId)->where('user_id', $invitation->created_by)
            ->where('role', 'owner')->whereNull('revoked_at')->exists()) {
            $this->invalid();
        }

        return $invitation;
    }

    public function invalid(): never
    {
        throw ValidationException::withMessages(['invitation_code' => 'Numele familiei sau codul de invitație nu este valid. Cere un cod nou administratorului.']);
    }
}
