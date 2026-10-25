<?php

namespace App\Modules\Kitchen\Http;

use App\Modules\Kitchen\Application\DiningCatalog;
use App\Modules\Kitchen\Application\MealCatalog;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class DiningController
{
    public function __construct(private DiningCatalog $dining) {}

    public function restaurants(Request $request, string $family): array
    {
        $filters = $request->validate(['q' => 'nullable|string|max:160', 'page' => 'sometimes|integer|min:1|max:10000']);

        return $this->dining->restaurants($family, $request->user()->id, $filters);
    }

    public function storeRestaurant(Request $request, string $family): JsonResponse
    {
        abort_if($request->attributes->get('membership')->role === 'limited', 403);
        $data = $request->validate(['name' => 'required|string|max:160', 'city' => 'nullable|string|max:120',
            'family_id' => 'prohibited', 'owner_id' => 'prohibited', 'identity_key' => 'prohibited', 'id' => 'prohibited']);

        return response()->json($this->dining->createRestaurant($family, $request->user()->id, $request->attributes->get('membership')->id, $data), 201);
    }

    public function visits(Request $request, string $family): array
    {
        $filters = $request->validate(['restaurant_id' => 'sometimes|uuid', 'scope' => ['sometimes', Rule::in(['mine', 'family'])], 'page' => 'sometimes|integer|min:1|max:10000']);

        return $this->dining->visits($family, $request->user()->id, $request->attributes->get('membership')->id, $filters);
    }

    public function showVisit(Request $request, string $family, string $visit): array
    {
        $data = $request->validate(['rating_page' => 'sometimes|integer|min:1|max:10000']);

        return $this->dining->visit($family, $request->user()->id, $request->attributes->get('membership')->id, $visit, (int) ($data['rating_page'] ?? 1));
    }

    public function storeVisit(Request $request, string $family): JsonResponse
    {
        abort_if($request->attributes->get('membership')->role === 'limited', 403);
        $data = $request->validate([
            'restaurant_id' => 'required|uuid',
            'meal_date' => 'sometimes|date_format:Y-m-d|after_or_equal:1900-01-01|before_or_equal:2100-12-31',
            'meal_type' => ['sometimes', Rule::in(['breakfast', 'lunch', 'dinner', 'snack'])],
            'visibility' => ['sometimes', Rule::in(['private', 'family'])],
            'restaurant_score' => 'nullable|required_with:restaurant_comment|integer|min:1|max:10', 'restaurant_comment' => 'nullable|string|max:1000',
            'dishes' => 'required|array|min:1|max:20', 'dishes.*' => 'required|array:dish_title,category,score,comment',
            'dishes.*.dish_title' => 'required|string|max:160', 'dishes.*.category' => ['sometimes', Rule::in(MealCatalog::CATEGORIES)],
            'dishes.*.score' => 'nullable|required_with:dishes.*.comment|integer|min:1|max:10', 'dishes.*.comment' => 'nullable|string|max:1000',
            'family_id' => 'prohibited', 'owner_id' => 'prohibited', 'version' => 'prohibited', 'id' => 'prohibited',
        ]);
        $data += ['meal_date' => CarbonImmutable::now('Europe/Bucharest')->toDateString(), 'meal_type' => 'dinner', 'visibility' => 'family'];

        return response()->json($this->dining->createVisit($family, $request->user()->id, $request->attributes->get('membership')->id, $data), 201);
    }

    public function rateVisit(Request $request, string $family, string $visit): array
    {
        $data = $request->validate(['score' => 'required|integer|min:1|max:10', 'comment' => 'nullable|string|max:1000',
            'family_id' => 'prohibited', 'visit_id' => 'prohibited', 'membership_id' => 'prohibited', 'user_id' => 'prohibited', 'owner_id' => 'prohibited', 'version' => 'prohibited']);

        return $this->dining->rateVisit($family, $request->user()->id, $request->attributes->get('membership')->id, $visit, $data, $request->header('If-Match'));
    }

    public function withdrawVisitRating(Request $request, string $family, string $visit): array
    {
        $request->validate(['family_id' => 'prohibited', 'visit_id' => 'prohibited', 'membership_id' => 'prohibited', 'user_id' => 'prohibited', 'owner_id' => 'prohibited']);

        return $this->dining->rateVisit($family, $request->user()->id, $request->attributes->get('membership')->id, $visit, [], $request->header('If-Match'), true);
    }

    public function rankings(Request $request, string $family): array
    {
        $filters = $request->validate(['type' => ['sometimes', Rule::in(['dishes', 'restaurants'])],
            'scope' => ['sometimes', Rule::in(['mine', 'family'])], 'category' => ['sometimes', Rule::in(MealCatalog::CATEGORIES)],
            'restaurant_id' => 'sometimes|uuid', 'page' => 'sometimes|integer|min:1|max:10000']);

        return $this->dining->rankings($family, $request->user()->id, $request->attributes->get('membership')->id, $filters);
    }
}
