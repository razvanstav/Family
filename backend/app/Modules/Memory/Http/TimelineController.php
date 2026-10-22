<?php

namespace App\Modules\Memory\Http;

use App\Modules\Memory\Application\TimelineReader;
use Illuminate\Http\Request;

final class TimelineController
{
    public function __invoke(Request $request, string $family, TimelineReader $timeline): array
    {
        $data = $request->validate(['cursor' => 'sometimes|nullable|string|max:2048',
            'kind' => 'sometimes|in:all,memory,meals', 'limit' => 'sometimes|integer|min:1|max:50']);

        return $timeline->listing($family, $request->user()->id, $request->attributes->get('membership')->id,
            $data['kind'] ?? 'all', $data['cursor'] ?? null, (int) ($data['limit'] ?? 20));
    }
}
