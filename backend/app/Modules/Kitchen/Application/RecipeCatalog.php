<?php

namespace App\Modules\Kitchen\Application;

use App\Modules\Kitchen\Contracts\Recipes;
use App\Platform\Audit\Audit;
use App\Platform\Contracts\PrivateMedia;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class RecipeCatalog implements Recipes
{
    public function __construct(private PrivateMedia $media) {}

    private function visible(string $familyId, int $actorId, bool $currentOnly = true): Builder
    {
        $query = DB::table('recipes as r')->where('r.family_id', $familyId)->whereNull('r.archived_at')
            ->where(fn (Builder $query) => $query->where('r.owner_id', $actorId)->orWhere('r.visibility', 'family'))
            ->join('recipe_versions as v', function ($join) {
                $join->on('v.family_id', '=', 'r.family_id')->on('v.recipe_id', '=', 'r.id');
            })
            ->join('users as u', 'u.id', '=', 'r.owner_id')
            ->leftJoin('media_assets as a', function ($join) {
                $join->on('a.family_id', '=', 'v.family_id')->on('a.id', '=', 'v.photo_asset_id');
            });

        return $currentOnly ? $query->whereColumn('v.version_no', 'r.version') : $query;
    }

    private function fields(): array
    {
        return ['r.id', 'r.visibility', 'r.owner_id', 'v.version_no as version', 'r.version as current_version', 'r.is_example', 'r.created_at', 'u.name as owner_name',
            'v.created_at as version_created_at',
            'v.id as recipe_version_id', 'v.title', 'v.category', 'v.ingredients', 'v.instructions',
            'v.duration_minutes', 'v.servings', 'v.difficulty', 'a.status as photo_status'];
    }

    private function payload(object $recipe): array
    {
        $data = (array) $recipe;
        $data['ingredients'] = json_decode($recipe->ingredients, true, flags: JSON_THROW_ON_ERROR);
        $data['version_created_at'] = CarbonImmutable::parse($recipe->version_created_at, config('app.timezone'))->toIso8601String();

        return $data;
    }

    public function listing(string $familyId, int $actorId, array $filters): array
    {
        $query = $this->visible($familyId, $actorId);
        if (isset($filters['q']) && $filters['q'] !== '') {
            $query->whereLike('v.title', '%'.addcslashes($filters['q'], '%_\\').'%');
        }
        if (! empty($filters['category'])) {
            $query->where('v.category', $filters['category']);
        }
        if (! empty($filters['visibility'])) {
            $query->where('r.visibility', $filters['visibility']);
        }
        $page = (int) ($filters['page'] ?? 1);
        $rows = $query->orderByDesc('r.created_at')->orderByDesc('r.id')->offset(($page - 1) * 12)->limit(13)->get($this->fields());

        return ['items' => $rows->take(12)->map(fn (object $recipe) => $this->payload($recipe))->all(), 'has_more' => $rows->count() > 12, 'page' => $page];
    }

    public function find(string $familyId, int $actorId, string $recipeId): array
    {
        abort_unless(Str::isUuid($recipeId), 404);

        return $this->payload($this->visible($familyId, $actorId)->where('r.id', $recipeId)->firstOrFail($this->fields()));
    }

    public function findVersion(string $familyId, int $actorId, string $recipeId, string $versionId): array
    {
        abort_unless(Str::isUuid($recipeId) && Str::isUuid($versionId), 404);

        return $this->payload($this->visible($familyId, $actorId, false)->where('r.id', $recipeId)->where('v.id', $versionId)->firstOrFail($this->fields()));
    }

    public function history(string $familyId, int $actorId, string $recipeId, int $page): array
    {
        $this->find($familyId, $actorId, $recipeId);
        $rows = $this->visible($familyId, $actorId, false)->where('r.id', $recipeId)
            ->orderByDesc('v.version_no')->offset(($page - 1) * 12)->limit(13)->get($this->fields());

        return ['items' => $rows->take(12)->map(fn (object $row) => $this->payload($row))->all(), 'has_more' => $rows->count() > 12, 'page' => $page];
    }

    private function insertVersion(string $familyId, string $recipeId, int $version, array $data, ?string $assetId): void
    {
        DB::table('recipe_versions')->insert([
            'id' => (string) Str::uuid(), 'family_id' => $familyId, 'recipe_id' => $recipeId, 'version_no' => $version,
            'title' => $data['title'], 'category' => $data['category'], 'ingredients' => json_encode($data['ingredients'], JSON_THROW_ON_ERROR),
            'instructions' => $data['instructions'], 'duration_minutes' => $data['duration_minutes'],
            'servings' => $data['servings'], 'difficulty' => $data['difficulty'], 'photo_asset_id' => $assetId,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function revise(string $familyId, int $actorId, string $recipeId, array $data, ?UploadedFile $photo, ?string $expected): array
    {
        return DB::transaction(function () use ($familyId, $actorId, $recipeId, $data, $photo, $expected) {
            $recipe = $this->find($familyId, $actorId, $recipeId);
            abort_unless($recipe['owner_id'] === $actorId, 403);
            abort_unless($expected, 428, 'Versiunea rețetei este necesară.');
            abort_unless($expected === '"'.$recipe['version'].'"', 412, 'Rețeta s-a schimbat. Reîncarcă rețeta înainte de a salva din nou.');
            $assetId = match ($data['photo_action']) {
                'replace' => $this->media->receiveImage($familyId, $actorId, $photo)['id'],
                'remove' => null,
                default => DB::table('recipe_versions')->where('family_id', $familyId)->where('id', $recipe['recipe_version_id'])->value('photo_asset_id'),
            };
            $version = $recipe['version'] + 1;
            $this->insertVersion($familyId, $recipeId, $version, $data, $assetId);
            DB::table('recipes')->where('family_id', $familyId)->where('id', $recipeId)->update(['version' => $version, 'updated_at' => now()]);
            Audit::record($familyId, $actorId, 'recipe.version_created', $recipeId);

            return $this->find($familyId, $actorId, $recipeId);
        });
    }

    public function create(string $familyId, int $actorId, array $data, ?UploadedFile $photo, bool $example = false): array
    {
        return DB::transaction(function () use ($familyId, $actorId, $data, $photo, $example) {
            $asset = $photo ? $this->media->receiveImage($familyId, $actorId, $photo) : null;
            $id = (string) Str::uuid();
            DB::table('recipes')->insert([
                'id' => $id, 'family_id' => $familyId, 'owner_id' => $actorId, 'visibility' => $data['visibility'],
                'version' => 1, 'is_example' => $example, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->insertVersion($familyId, $id, 1, $data, $asset['id'] ?? null);
            Audit::record($familyId, $actorId, 'recipe.created', $id);

            return $this->find($familyId, $actorId, $id);
        });
    }
}
