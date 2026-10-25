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
        $filters = $request->validate(['date' => 'sometimes|date_format:Y-m-d|after_or_equal:1900-01-01|before_or_equal:2100-12-31', 'page' => 'sometimes|integer|min:1|max:10000',
            'history' => 'sometimes|boolean', 'q' => 'nullable|string|max:160', 'category' => ['nullable', Rule::in(MealCatalog::CATEGORIES)],
            'scope' => ['sometimes', Rule::in(['mine', 'family'])]]);

        return $this->meals->listing($family, $request->user()->id, $request->attributes->get('membership')->id,
            $filters['date'] ?? CarbonImmutable::now('Europe/Bucharest')->toDateString(), (int) ($filters['page'] ?? 1), $filters);
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
            'recipe_id' => 'nullable|required_with:recipe_version_id|uuid', 'recipe_version_id' => 'nullable|required_with:recipe_id|uuid',
            'dish_title' => [Rule::prohibitedIf($request->filled('recipe_id')), 'required_without:recipe_id', 'string', 'max:160'],
            'meal_date' => 'sometimes|date_format:Y-m-d|after_or_equal:1900-01-01|before_or_equal:2100-12-31',
            'meal_type' => ['sometimes', Rule::in(['breakfast', 'lunch', 'dinner', 'snack'])],
            'visibility' => ['sometimes', Rule::in(['private', 'family'])],
            'category' => ['sometimes', Rule::in(MealCatalog::CATEGORIES)], 'score' => 'nullable|integer|min:1|max:10', 'comment' => 'nullable|string|max:1000',
            'family_id' => 'prohibited', 'owner_id' => 'prohibited', 'version' => 'prohibited', 'visit_id' => 'prohibited', 'restaurant_id' => 'prohibited',
        ]);
        $data += ['meal_date' => CarbonImmutable::now('Europe/Bucharest')->toDateString(), 'meal_type' => 'snack', 'visibility' => 'family', 'category' => 'other'];

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

    public function rate(Request $request, string $family, string $meal): array
    {
        $data = $request->validate(['score' => 'required|integer|min:1|max:10', 'comment' => 'nullable|string|max:1000',
            'family_id' => 'prohibited', 'meal_id' => 'prohibited', 'membership_id' => 'prohibited', 'user_id' => 'prohibited', 'owner_id' => 'prohibited', 'version' => 'prohibited']);

        return $this->meals->rate($family, $request->user()->id, $request->attributes->get('membership')->id, $meal, $data, $request->header('If-Match'));
    }

    public function withdrawRating(Request $request, string $family, string $meal): array
    {
        $request->validate(['family_id' => 'prohibited', 'meal_id' => 'prohibited', 'membership_id' => 'prohibited', 'user_id' => 'prohibited', 'owner_id' => 'prohibited']);

        return $this->meals->rate($family, $request->user()->id, $request->attributes->get('membership')->id, $meal, [], $request->header('If-Match'), true);
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
