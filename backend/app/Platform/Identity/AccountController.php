<?php

namespace App\Platform\Identity;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class AccountController
{
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'expected_name' => 'required|string|max:255',
            'id' => 'prohibited', 'user_id' => 'prohibited',
            'email' => 'prohibited', 'password' => 'prohibited',
        ]);
        $updated = DB::table('users')->where('id', $request->user()->id)
            ->where('name', $data['expected_name'])
            ->update(['name' => $data['name'], 'updated_at' => now()]);
        abort_unless($updated, 412, 'Numele s-a schimbat. Reîncarcă înainte de a salva.');

        return response()->json($request->user()->fresh()->only(['id', 'name', 'email']));
    }
}
