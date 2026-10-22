<?php

namespace App\Modules\Kitchen\Application;

use App\Modules\Kitchen\Contracts\Recipes;
use App\Platform\Audit\Audit;
use App\Platform\Contracts\Outbox;
use App\Platform\Contracts\PrivateMedia;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class RecipeCatalog implements Recipes
{
    public function __construct(private PrivateMedia $media, private Outbox $outbox) {}

    private function visible(string $familyId, int $actorId, bool $currentOnly = true): Builder
    {
        $grants = DB::table('recipe_grants as g')->join('family_memberships as recipient', function ($join) {
            $join->on('recipient.family_id', '=', 'g.family_id')->on('recipient.id', '=', 'g.membership_id');
        })->where('g.family_id', $familyId)->whereNull('recipient.revoked_at')
            ->selectRaw('g.recipe_id, json_agg(g.membership_id ORDER BY g.membership_id) as grants_json')->groupBy('g.recipe_id');
        $query = DB::table('recipes as r')->where('r.family_id', $familyId)
            ->join('recipe_versions as v', function ($join) {
                $join->on('v.family_id', '=', 'r.family_id')->on('v.recipe_id', '=', 'r.id');
            })
            ->join('users as u', 'u.id', '=', 'r.owner_id')
            ->leftJoinSub($grants, 'grants', 'grants.recipe_id', '=', 'r.id')
            ->selectRaw('CASE WHEN r.owner_id = ? THEN grants.grants_json ELSE NULL END AS grants_json', [$actorId])
            ->leftJoin('media_assets as a', function ($join) {
                $join->on('a.family_id', '=', 'v.family_id')->on('a.id', '=', 'v.photo_asset_id');
            });

        RecipeAccess::constrain($query, $actorId);

        return ($currentOnly ? $query->whereColumn('v.version_no', 'r.version') : $query)->addSelect($this->fields());
    }

    private function fields(): array
    {
        return ['r.id', 'r.visibility', 'r.owner_id', 'r.revision', 'r.archived_at', 'v.version_no as version', 'r.version as current_version', 'r.is_example', 'r.created_at', 'u.name as owner_name',
            'v.created_at as version_created_at',
            'v.id as recipe_version_id', 'v.title', 'v.category', 'v.ingredients', 'v.instructions',
            'v.duration_minutes', 'v.servings', 'v.difficulty', 'a.status as photo_status'];
    }

    private function payload(object $recipe): array
    {
        $data = (array) $recipe;
        $data['ingredients'] = json_decode($recipe->ingredients, true, flags: JSON_THROW_ON_ERROR);
        $data['grants'] = json_decode($recipe->grants_json ?? '[]', true, flags: JSON_THROW_ON_ERROR);
        unset($data['grants_json']);
        $data['version_created_at'] = CarbonImmutable::parse($recipe->version_created_at, config('app.timezone'))->toIso8601String();

        return $data;
    }

    public function listing(string $familyId, int $actorId, array $filters): array
    {
        $query = $this->visible($familyId, $actorId);
        ($filters['archived'] ?? false) ? $query->whereNotNull('r.archived_at') : $query->whereNull('r.archived_at');
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
        $rows = $query->orderByDesc('r.created_at')->orderByDesc('r.id')->offset(($page - 1) * 12)->limit(13)->get();

        return ['items' => $rows->take(12)->map(fn (object $recipe) => $this->payload($recipe))->all(), 'has_more' => $rows->count() > 12, 'page' => $page];
    }

    public function find(string $familyId, int $actorId, string $recipeId): array
    {
        abort_unless(Str::isUuid($recipeId), 404);

        return $this->payload($this->visible($familyId, $actorId)->where('r.id', $recipeId)->firstOrFail());
    }

    public function findVersion(string $familyId, int $actorId, string $recipeId, string $versionId): array
    {
        abort_unless(Str::isUuid($recipeId) && Str::isUuid($versionId), 404);

        return $this->payload($this->visible($familyId, $actorId, false)->where('r.id', $recipeId)->where('v.id', $versionId)->firstOrFail());
    }

    public function history(string $familyId, int $actorId, string $recipeId, int $page): array
    {
        $this->find($familyId, $actorId, $recipeId);
        $rows = $this->visible($familyId, $actorId, false)->where('r.id', $recipeId)
            ->orderByDesc('v.version_no')->offset(($page - 1) * 12)->limit(13)->get();

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
            $this->checkMutation($recipe, $actorId, $expected);
            abort_if($recipe['archived_at'], 409, 'Restaurează rețeta înainte de a o edita.');
            $assetId = match ($data['photo_action']) {
                'replace' => $this->media->receiveImage($familyId, $actorId, $photo)['id'],
                'remove' => null,
                default => DB::table('recipe_versions')->where('family_id', $familyId)->where('id', $recipe['recipe_version_id'])->value('photo_asset_id'),
            };
            $version = $recipe['version'] + 1;
            $this->insertVersion($familyId, $recipeId, $version, $data, $assetId);
            DB::table('recipes')->where('family_id', $familyId)->where('id', $recipeId)->update(['version' => $version, 'revision' => $recipe['revision'] + 1, 'updated_at' => now()]);
            Audit::record($familyId, $actorId, 'recipe.version_created', $recipeId);
            $this->outbox->append($familyId, 'kitchen.recipe.changed', 'kitchen.recipe', $recipeId, $recipe['revision'] + 1);

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
            $this->outbox->append($familyId, 'kitchen.recipe.changed', 'kitchen.recipe', $id, 1);

            return $this->find($familyId, $actorId, $id);
        });
    }

    private function checkMutation(array $recipe, int $actorId, ?string $expected): void
    {
        abort_unless($recipe['owner_id'] === $actorId, 403);
        abort_unless($expected, 428, 'Versiunea rețetei este necesară.');
        abort_unless($expected === '"'.$recipe['revision'].'"', 412, 'Rețeta s-a schimbat. Reîncarcă rețeta înainte de a salva din nou.');
    }

    public function share(string $familyId, int $actorId, string $recipeId, array $data, ?string $expected): array
    {
        return DB::transaction(function () use ($familyId, $actorId, $recipeId, $data, $expected) {
            $recipe = $this->find($familyId, $actorId, $recipeId);
            $this->checkMutation($recipe, $actorId, $expected);
            abort_if($recipe['archived_at'], 409, 'Restaurează rețeta înainte de a schimba accesul.');
            $ids = $data['membership_ids'];
            abort_if(($data['visibility'] === 'selected') !== (count($ids) > 0), 422, 'Alege persoane numai pentru accesul „Persoane alese”.');
            $count = DB::table('family_memberships')->where('family_id', $familyId)->whereNull('revoked_at')
                ->where('user_id', '!=', $actorId)->whereIn('id', $ids)->count();
            abort_unless($count === count($ids), 422, 'Un membru selectat nu mai este disponibil.');
            DB::table('recipe_grants')->where('family_id', $familyId)->where('recipe_id', $recipeId)->delete();
            foreach ($ids as $id) {
                DB::table('recipe_grants')->insert(['family_id' => $familyId, 'recipe_id' => $recipeId, 'membership_id' => $id]);
            }
            DB::table('recipes')->where('family_id', $familyId)->where('id', $recipeId)->update([
                'visibility' => $data['visibility'], 'revision' => $recipe['revision'] + 1, 'updated_at' => now(),
            ]);
            DB::table('families')->where('id', $familyId)->increment('authz_revision');
            Audit::record($familyId, $actorId, 'recipe.access_changed', $recipeId);
            $this->outbox->append($familyId, 'kitchen.recipe.changed', 'kitchen.recipe', $recipeId, $recipe['revision'] + 1);

            return $this->find($familyId, $actorId, $recipeId);
        });
    }

    public function archive(string $familyId, int $actorId, string $recipeId, ?string $expected, bool $restore = false): array
    {
        return DB::transaction(function () use ($familyId, $actorId, $recipeId, $expected, $restore) {
            $recipe = $this->find($familyId, $actorId, $recipeId);
            $this->checkMutation($recipe, $actorId, $expected);
            abort_if(($recipe['archived_at'] !== null) !== $restore, 409, 'Starea rețetei s-a schimbat. Reîncarcă rețeta.');
            DB::table('recipes')->where('family_id', $familyId)->where('id', $recipeId)->update([
                'archived_at' => $restore ? null : now(), 'visibility' => 'private', 'revision' => $recipe['revision'] + 1, 'updated_at' => now(),
            ]);
            DB::table('recipe_grants')->where('family_id', $familyId)->where('recipe_id', $recipeId)->delete();
            DB::table('families')->where('id', $familyId)->increment('authz_revision');
            Audit::record($familyId, $actorId, $restore ? 'recipe.restored' : 'recipe.archived', $recipeId);
            $this->outbox->append($familyId, 'kitchen.recipe.changed', 'kitchen.recipe', $recipeId, $recipe['revision'] + 1);

            return $this->find($familyId, $actorId, $recipeId);
        });
    }

    public function revokeMembership(string $familyId, string $membershipId): void
    {
        DB::table('recipe_grants')->where('family_id', $familyId)->where('membership_id', $membershipId)->delete();
    }

    public function activityCards(string $familyId, int $actorId, array $ids): array
    {
        return $this->visible($familyId, $actorId)->whereNull('r.archived_at')->whereIn('r.id', $ids)
            ->select(['r.id', 'v.title', 'r.visibility', 'r.revision as source_version', 'u.name as owner_name', 'a.status as photo_status'])
            ->get()->mapWithKeys(fn (object $row) => [$row->id => ['kind' => 'kitchen.recipe'] + (array) $row])->all();
    }
}
