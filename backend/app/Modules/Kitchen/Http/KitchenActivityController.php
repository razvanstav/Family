<?php

namespace App\Modules\Kitchen\Http;

use App\Modules\Kitchen\Contracts\Activity;
use Illuminate\Http\Request;

final class KitchenActivityController
{
    public function __invoke(Request $request, string $family, Activity $activity): array
    {
        $data = $request->validate(['cursor' => 'sometimes|nullable|string|max:2048', 'limit' => 'sometimes|integer|min:1|max:50']);

        return $activity->listing($family, $request->user()->id, $request->attributes->get('membership')->id, $data['cursor'] ?? null, (int) ($data['limit'] ?? 20));
    }
}
