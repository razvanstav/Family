<?php

namespace App\Platform\Audit;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class Audit
{
    public static function record(string $familyId, int $actorId, string $action, string $resourceId): void
    {
        DB::table('audit_events')->insert([
            'id' => (string) Str::uuid(), 'family_id' => $familyId, 'actor_id' => $actorId,
            'action' => $action, 'resource_id' => $resourceId, 'created_at' => now(),
        ]);
    }
}
