<?php

namespace App\Modules\Kitchen\Http;

use App\Modules\Kitchen\Application\MealCatalog;
use App\Platform\Contracts\PrivateMedia;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class MealController
{
    public function __construct(private MealCatalog $meals, private PrivateMedia $media) {}

    public function index(Request $request, string $family): array
    {
        $filters = $request->validate(['date' => 'sometimes|date_format:Y-m-d|after_or_equal:1900-01-01|before_or_equal:2100-12-31', 'page' => 'sometimes|integer|min:1|max:10000']);

        return $this->meals->listing($family, $request->user()->id, $request->attributes->get('membership')->id,
            $filters['date'] ?? CarbonImmutable::now('Europe/Bucharest')->toDateString(), (int) ($filters['page'] ?? 1));
    }

    public function show(Request $request, string $family, string $meal): array
    {
        $data = $request->validate(['page' => 'sometimes|integer|min:1|max:10000']);

        return $this->meals->details($family, $request->user()->id, $request->attributes->get('membership')->id, $meal, (int) ($data['page'] ?? 1));
    }

    public function store(Request $request, string $family): JsonResponse
    {
        abort_if($request->attributes->get('membership')->role === 'limited', 403);
        $data = $request->validate([
            'recipe_id' => 'required|uuid', 'recipe_version_id' => 'required|uuid',
            'meal_date' => 'required|date_format:Y-m-d|after_or_equal:1900-01-01|before_or_equal:2100-12-31',
            'meal_type' => ['required', Rule::in(['breakfast', 'lunch', 'dinner', 'snack'])],
            'visibility' => ['required', Rule::in(['private', 'family'])],
            'family_id' => 'prohibited', 'owner_id' => 'prohibited', 'dish_title' => 'prohibited', 'version' => 'prohibited',
        ]);

        return response()->json($this->meals->create($family, $request->user()->id, $request->attributes->get('membership')->id, $data), 201);
    }

    public function vote(Request $request, string $family, string $meal): array
    {
        $data = $request->validate([
            'liked' => 'required|boolean', 'comment' => 'nullable|string|max:1000',
            'family_id' => 'prohibited', 'meal_id' => 'prohibited', 'membership_id' => 'prohibited',
            'user_id' => 'prohibited', 'owner_id' => 'prohibited', 'version' => 'prohibited',
        ]);

        return $this->meals->vote($family, $request->user()->id, $request->attributes->get('membership')->id, $meal, $data, $request->header('If-Match'));
    }

    public function withdraw(Request $request, string $family, string $meal): array
    {
        $request->validate(['family_id' => 'prohibited', 'meal_id' => 'prohibited', 'membership_id' => 'prohibited', 'user_id' => 'prohibited', 'owner_id' => 'prohibited']);

        return $this->meals->vote($family, $request->user()->id, $request->attributes->get('membership')->id, $meal, [], $request->header('If-Match'), true);
    }

    public function photo(Request $request, string $family, string $meal): StreamedResponse
    {
        return $this->media->image($family, $this->meals->photoAsset($family, $request->user()->id, $request->attributes->get('membership')->id, $meal));
    }

    public function recipe(Request $request, string $family, string $meal): array
    {
        return $this->meals->usedRecipe($family, $request->user()->id, $request->attributes->get('membership')->id, $meal);
    }
}
