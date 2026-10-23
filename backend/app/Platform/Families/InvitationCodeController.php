<?php

namespace App\Platform\Families;

use App\Platform\Audit\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class InvitationCodeController
{
    public function index(Request $request, string $family): JsonResponse
    {
        $this->authorizeOwner($request);

        return response()->json(DB::table('family_invitation_codes')->where('family_id', $family)->orderByDesc('created_at')->orderByDesc('id')
            ->get(['id', 'expires_at', 'created_at', 'used_at', 'revoked_at']));
    }

    public function store(Request $request, string $family): JsonResponse
    {
        $this->authorizeOwner($request);
        $id = (string) Str::uuid();
        $code = rtrim(strtr(base64_encode(hex2bin(str_replace('-', '', $family)).random_bytes(16)), '+/', '-_'), '=');
        $created = now();
        $expires = $created->copy()->addDays(7);
        DB::table('family_invitation_codes')->insert([
            'id' => $id, 'family_id' => $family, 'created_by' => $request->user()->id,
            'token_hash' => hash('sha256', $code), 'created_at' => $created, 'expires_at' => $expires,
        ]);
        Audit::record($family, $request->user()->id, 'invitation_code.created', $id);

        return response()->json(['id' => $id, 'code' => $code, 'expires_at' => $expires->toISOString(), 'created_at' => $created->toISOString(), 'used_at' => null, 'revoked_at' => null], 201);
    }

    public function revoke(Request $request, string $family, string $invitationCode): Response
    {
        $this->authorizeOwner($request);
        $changed = DB::table('family_invitation_codes')->where('family_id', $family)->where('id', $invitationCode)
            ->whereNull('used_at')->whereNull('revoked_at')->where('expires_at', '>', now())->update(['revoked_at' => now()]);
        abort_unless($changed, 404);
        Audit::record($family, $request->user()->id, 'invitation_code.revoked', $invitationCode);

        return response()->noContent();
    }

    private function authorizeOwner(Request $request): void
    {
        abort_unless($request->attributes->get('membership')->role === 'owner', 403);
    }
}
