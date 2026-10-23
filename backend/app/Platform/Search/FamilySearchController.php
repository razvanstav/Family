<?php

namespace App\Platform\Search;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class FamilySearchController
{
    public function __invoke(Request $request, string $family, FamilySearch $search): JsonResponse
    {
        $data = $request->validate(['q' => 'required|string|max:100', 'type' => 'sometimes|in:document,moment,recipe', 'page' => 'sometimes|integer|min:1|max:1000']);
        $text = trim(preg_replace('/\s+/u', ' ', $data['q']));
        if (mb_strlen($text) < 2) {
            throw ValidationException::withMessages(['q' => 'Scrie cel puțin două caractere.']);
        }

        return response()->json($search->results($family, $request->user()->id, $request->attributes->get('membership')->id, $text, $data['type'] ?? null, (int) ($data['page'] ?? 1)));
    }
}
