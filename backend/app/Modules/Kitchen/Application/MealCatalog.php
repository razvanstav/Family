<?php

namespace App\Modules\Kitchen\Application;

use App\Modules\Kitchen\Contracts\Recipes;
use App\Platform\Audit\Audit;
use App\Platform\Contracts\Outbox;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class MealCatalog
{
    public function __construct(private Recipes $recipes, private Outbox $outbox) {}

    private function visible(string $familyId, int $actorId, string $membershipId): Builder
    {
        $totals = $this->activeVotes($familyId)->groupBy('votes.family_id', 'votes.meal_id')
            ->selectRaw('votes.family_id, votes.meal_id, SUM(CASE WHEN votes.liked THEN 1 ELSE 0 END) AS liked_count, SUM(CASE WHEN NOT votes.liked THEN 1 ELSE 0 END) AS disliked_count');

        return DB::table('kitchen_meals as m')->where('m.family_id', $familyId)->whereNull('m.archived_at')
            ->where(fn (Builder $query) => $query->where('m.owner_id', $actorId)->orWhere('m.visibility', 'family'))
            ->join('users as u', 'u.id', '=', 'm.owner_id')
            ->join('recipe_versions as v', function ($join) {
                $join->on('v.family_id', '=', 'm.family_id')->on('v.recipe_id', '=', 'm.recipe_id')->on('v.id', '=', 'm.recipe_version_id');
            })
            ->leftJoin('recipes as r', function ($join) use ($actorId) {
                $join->on('r.id', '=', 'm.recipe_id')->on('r.family_id', '=', 'm.family_id')->whereNull('r.archived_at')
                    ->where(function (Builder $access) use ($actorId) {
                        RecipeAccess::constrain($access, $actorId);
                    });
            })
            ->leftJoin('media_assets as a', function ($join) {
                $join->on('a.id', '=', 'v.photo_asset_id')->on('a.family_id', '=', 'v.family_id');
            })
            ->leftJoin('kitchen_meal_votes as mine', function ($join) use ($membershipId) {
                $join->on('mine.meal_id', '=', 'm.id')->on('mine.family_id', '=', 'm.family_id')->where('mine.membership_id', $membershipId);
            })
            ->leftJoinSub($totals, 'totals', function ($join) {
                $join->on('totals.family_id', '=', 'm.family_id')->on('totals.meal_id', '=', 'm.id');
            });
    }

    private function activeVotes(string $familyId): Builder
    {
        return DB::table('kitchen_meal_votes as votes')
            ->join('family_memberships as voters', function ($join) {
                $join->on('voters.family_id', '=', 'votes.family_id')->on('voters.id', '=', 'votes.membership_id');
            })->where('votes.family_id', $familyId)->whereNull('voters.revoked_at')->whereNull('votes.withdrawn_at');
    }

    private function fields(): array
    {
        return ['m.id', 'm.dish_title', 'm.meal_date', 'm.meal_type', 'm.visibility', 'm.owner_id', 'm.version', 'm.created_at',
            'u.name as owner_name', 'mine.liked as my_liked', 'mine.comment as my_comment', 'mine.version as my_version',
            DB::raw('COALESCE(totals.liked_count, 0) AS liked_count'), DB::raw('COALESCE(totals.disliked_count, 0) AS disliked_count'),
            DB::raw('CASE WHEN r.id IS NOT NULL THEN a.status ELSE NULL END AS photo_status'),
            DB::raw('r.id IS NOT NULL AS recipe_available'),
            DB::raw('CASE WHEN r.id IS NOT NULL THEN r.is_example ELSE FALSE END AS photo_is_example')];
    }

    private function payload(object $row, string $today): array
    {
        $data = (array) $row;
        $data['liked_count'] = (int) $row->liked_count;
        $data['disliked_count'] = (int) $row->disliked_count;
        $data['voting_open'] = $row->meal_date <= $today;
        $data['my_vote'] = $row->my_version === null ? null : [
            'liked' => $row->my_liked, 'comment' => $row->my_comment, 'version' => $row->my_version,
        ];
        unset($data['my_liked'], $data['my_comment'], $data['my_version']);

        return $data;
    }

    public function listing(string $familyId, int $actorId, string $membershipId, string $date, int $page): array
    {
        $rows = $this->visible($familyId, $actorId, $membershipId)->where('m.meal_date', $date)
            ->orderByRaw("CASE m.meal_type WHEN 'breakfast' THEN 1 WHEN 'lunch' THEN 2 WHEN 'dinner' THEN 3 ELSE 4 END")
            ->orderBy('m.created_at')->orderBy('m.id')->offset(($page - 1) * 12)->limit(13)->get($this->fields());
        $today = CarbonImmutable::now('Europe/Bucharest')->toDateString();

        return ['items' => $rows->take(12)->map(fn (object $row) => $this->payload($row, $today))->all(), 'page' => $page, 'has_more' => $rows->count() > 12];
    }

    public function find(string $familyId, int $actorId, string $membershipId, string $mealId): array
    {
        abort_unless(Str::isUuid($mealId), 404);
        $row = $this->visible($familyId, $actorId, $membershipId)->where('m.id', $mealId)->firstOrFail($this->fields());

        return $this->payload($row, CarbonImmutable::now('Europe/Bucharest')->toDateString());
    }

    public function details(string $familyId, int $actorId, string $membershipId, string $mealId, int $page = 1): array
    {
        $meal = $this->find($familyId, $actorId, $membershipId, $mealId);
        $votes = $this->activeVotes($familyId)->where('votes.meal_id', $mealId)
            ->join('users as author', 'author.id', '=', 'voters.user_id')->orderBy('votes.created_at')->orderBy('votes.membership_id')
            ->offset(($page - 1) * 20)->limit(21)->get(['votes.membership_id', 'author.name as author_name', 'votes.liked', 'votes.comment']);

        return $meal + ['feedback' => $votes->take(20)->all(), 'feedback_page' => $page, 'feedback_has_more' => $votes->count() > 20];
    }

    public function create(string $familyId, int $actorId, string $membershipId, array $data): array
    {
        return DB::transaction(function () use ($familyId, $actorId, $membershipId, $data) {
            $recipe = $this->recipes->find($familyId, $actorId, $data['recipe_id']);
            abort_if($recipe['archived_at'], 409, 'Restaurează rețeta înainte de a adăuga masa.');
            abort_unless($recipe['recipe_version_id'] === $data['recipe_version_id'], 409, 'Rețeta s-a schimbat. Redeschide rețeta înainte de a adăuga masa.');
            $id = (string) Str::uuid();
            DB::table('kitchen_meals')->insert([
                'id' => $id, 'family_id' => $familyId, 'owner_id' => $actorId, 'recipe_id' => $data['recipe_id'],
                'recipe_version_id' => $recipe['recipe_version_id'], 'dish_title' => $recipe['title'],
                'meal_date' => $data['meal_date'], 'meal_type' => $data['meal_type'], 'visibility' => $data['visibility'],
                'version' => 1, 'created_at' => now(), 'updated_at' => now(),
            ]);
            Audit::record($familyId, $actorId, 'meal.created', $id);
            $this->outbox->append($familyId, 'kitchen.meal.changed', 'kitchen.meal', $id, 1);

            return $this->details($familyId, $actorId, $membershipId, $id);
        });
    }

    private function checkVoteVersion(?string $expected, int $version): void
    {
        abort_unless($expected, 428, 'Versiunea votului este necesară.');
        abort_unless($expected === '"'.$version.'"', 412, 'Votul tău s-a schimbat. Reîncarcă masa înainte de a salva din nou.');
    }

    public function vote(string $familyId, int $actorId, string $membershipId, string $mealId, array $data, ?string $expected, bool $withdraw = false): array
    {
        return DB::transaction(function () use ($familyId, $actorId, $membershipId, $mealId, $data, $expected, $withdraw) {
            $meal = $this->find($familyId, $actorId, $membershipId, $mealId);
            abort_unless($meal['voting_open'], 422, 'Poți vota începând cu ziua mesei.');
            $query = DB::table('kitchen_meal_votes')->where('family_id', $familyId)->where('meal_id', $mealId)->where('membership_id', $membershipId);
            $previous = (clone $query)->lockForUpdate()->first();
            $this->checkVoteVersion($expected, $previous->version ?? 0);
            abort_if($withdraw && (! $previous || $previous->withdrawn_at !== null), 404);
            $values = ['liked' => $withdraw ? null : $data['liked'], 'comment' => $withdraw ? null : ($data['comment'] ?? null),
                'withdrawn_at' => $withdraw ? now() : null, 'version' => ($previous->version ?? 0) + 1, 'updated_at' => now()];
            if ($previous) {
                $query->update($values);
            } else {
                DB::table('kitchen_meal_votes')->insert($values + ['family_id' => $familyId, 'meal_id' => $mealId, 'membership_id' => $membershipId, 'created_at' => now()]);
            }
            Audit::record($familyId, $actorId, $withdraw ? 'meal.vote_withdrawn' : 'meal.vote_saved', $mealId);
            $revision = DB::selectOne('UPDATE kitchen_meals SET activity_revision = activity_revision + 1 WHERE family_id = ? AND id = ? RETURNING activity_revision', [$familyId, $mealId])->activity_revision;
            $this->outbox->append($familyId, 'kitchen.meal.changed', 'kitchen.meal', $mealId, $revision);

            return $this->details($familyId, $actorId, $membershipId, $mealId);
        });
    }

    public function photoAsset(string $familyId, int $actorId, string $membershipId, string $mealId): string
    {
        abort_unless(Str::isUuid($mealId), 404);
        $row = $this->visible($familyId, $actorId, $membershipId)->where('m.id', $mealId)
            ->firstOrFail(['r.id as allowed_recipe', 'v.photo_asset_id']);
        abort_unless($row->allowed_recipe && $row->photo_asset_id, 404);

        return $row->photo_asset_id;
    }

    public function usedRecipe(string $familyId, int $actorId, string $membershipId, string $mealId): array
    {
        abort_unless(Str::isUuid($mealId), 404);
        $row = $this->visible($familyId, $actorId, $membershipId)->where('m.id', $mealId)
            ->firstOrFail(['r.id as allowed_recipe', 'm.recipe_version_id']);
        abort_unless($row->allowed_recipe, 404);

        return $this->recipes->findVersion($familyId, $actorId, $row->allowed_recipe, $row->recipe_version_id);
    }

    public function activityCards(string $familyId, int $actorId, string $membershipId, array $ids): array
    {
        return $this->visible($familyId, $actorId, $membershipId)->whereIn('m.id', $ids)
            ->select(['m.id', 'm.dish_title as title', 'm.visibility', 'm.activity_revision as source_version', 'u.name as owner_name',
                'm.meal_date as occurred_on', 'm.meal_type',
                DB::raw('CASE WHEN r.id IS NOT NULL THEN r.is_example ELSE FALSE END AS photo_is_example'),
                DB::raw('CASE WHEN r.id IS NOT NULL THEN a.status ELSE NULL END AS photo_status'), DB::raw('r.id IS NOT NULL AS recipe_available')])
            ->get()->mapWithKeys(fn (object $row) => [$row->id => ['kind' => 'kitchen.meal'] + (array) $row])->all();
    }
}
