<?php

namespace App\Modules\Kitchen\Http;

use App\Modules\Kitchen\Application\RecipeCatalog;
use App\Modules\Kitchen\Contracts\Recipes;
use App\Platform\Audit\Audit;
use App\Platform\Contracts\PrivateMedia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class RecipeController
{
    private const CATEGORIES = ['main', 'soup', 'dessert', 'breakfast', 'salad', 'snack'];

    public function __construct(private Recipes $recipes, private RecipeCatalog $catalog, private PrivateMedia $media) {}

    public function index(Request $request, string $family): array
    {
        $filters = $request->validate([
            'q' => 'sometimes|nullable|string|max:120', 'category' => ['sometimes', Rule::in(self::CATEGORIES)],
            'visibility' => ['sometimes', Rule::in(['private', 'family'])], 'page' => 'sometimes|integer|min:1|max:10000',
        ]);

        return $this->recipes->listing($family, $request->user()->id, $filters);
    }

    public function show(Request $request, string $family, string $recipe): JsonResponse
    {
        $data = $this->recipes->find($family, $request->user()->id, $recipe);

        return response()->json($data)->header('ETag', '"'.$data['version'].'"');
    }

    private function contentRules(): array
    {
        return [
            'title' => 'required|string|max:160', 'category' => ['required', Rule::in(self::CATEGORIES)],
            'ingredients' => 'required|array|min:1|max:60', 'ingredients.*' => 'required|string|max:200',
            'instructions' => 'required|string|max:16000', 'duration_minutes' => 'required|integer|min:1|max:1440',
            'servings' => 'required|integer|min:1|max:50', 'difficulty' => ['required', Rule::in(['easy', 'medium', 'advanced'])],
            'owner_id' => 'prohibited', 'family_id' => 'prohibited', 'version' => 'prohibited',
            'photo_asset_id' => 'prohibited', 'is_example' => 'prohibited',
            'recipe_version_id' => 'prohibited', 'current_version' => 'prohibited',
        ];
    }

    public function store(Request $request, string $family): JsonResponse
    {
        abort_if($request->attributes->get('membership')->role === 'limited', 403);
        $data = $request->validate($this->contentRules() + [
            'visibility' => ['required', Rule::in(['private', 'family'])], 'photo' => 'sometimes|file|max:10240',
        ]);

        return response()->json($this->catalog->create($family, $request->user()->id, $data, $request->file('photo')), 201)->header('ETag', '"1"');
    }

    public function revise(Request $request, string $family, string $recipe): JsonResponse
    {
        abort_if($request->attributes->get('membership')->role === 'limited', 403);
        $data = $request->validate($this->contentRules() + [
            'visibility' => 'prohibited', 'photo_action' => ['required', Rule::in(['keep', 'replace', 'remove'])],
            'photo' => ['required_if:photo_action,replace', 'prohibited_unless:photo_action,replace', 'file', 'max:10240'],
        ]);
        $item = $this->catalog->revise($family, $request->user()->id, $recipe, $data, $request->file('photo'), $request->header('If-Match'));

        return response()->json($item, 201)->header('ETag', '"'.$item['version'].'"');
    }

    public function history(Request $request, string $family, string $recipe): array
    {
        $data = $request->validate(['page' => 'sometimes|integer|min:1|max:10000']);

        return $this->recipes->history($family, $request->user()->id, $recipe, (int) ($data['page'] ?? 1));
    }

    public function version(Request $request, string $family, string $recipe, string $version): array
    {
        return $this->recipes->findVersion($family, $request->user()->id, $recipe, $version);
    }

    private function photoAsset(string $family, string $version): string
    {
        $asset = DB::table('recipe_versions')->where('family_id', $family)->where('id', $version)->value('photo_asset_id');
        abort_unless($asset, 404);

        return $asset;
    }

    public function photo(Request $request, string $family, string $recipe, ?string $version = null): StreamedResponse
    {
        $item = $version ? $this->recipes->findVersion($family, $request->user()->id, $recipe, $version) : $this->recipes->find($family, $request->user()->id, $recipe);

        return $this->media->image($family, $this->photoAsset($family, $item['recipe_version_id']));
    }

    public function retryPhoto(Request $request, string $family, string $recipe): JsonResponse
    {
        $item = $this->recipes->find($family, $request->user()->id, $recipe);
        abort_unless($item['owner_id'] === $request->user()->id, 403);
        $this->media->retry($family, $this->photoAsset($family, $item['recipe_version_id']));
        Audit::record($family, $request->user()->id, 'recipe.photo_checked', $recipe);

        return $this->show($request, $family, $recipe);
    }
}
