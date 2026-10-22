<?php

namespace App\Modules\Kitchen\Application;

use App\Modules\Kitchen\Contracts\Activity;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

final class KitchenActivityReader implements Activity
{
    public function __construct(private RecipeCatalog $recipes, private MealCatalog $meals) {}

    /** @return array{items:array,has_more:bool,next_cursor:?string} */
    public function listing(string $familyId, int $actorId, string $membershipId, ?string $cursor, int $limit = 20): array
    {
        abort_unless($limit >= 1 && $limit <= 50, 422);
        abort_unless(DB::table('family_memberships')->where('family_id', $familyId)->where('id', $membershipId)
            ->where('user_id', $actorId)->whereNull('revoked_at')->exists(), 404);
        $query = DB::table('kitchen_activity_sources')->where('family_id', $familyId)->where('removed', false);
        if ($cursor !== null) {
            try {
                $position = json_decode(Crypt::decryptString($cursor), true, flags: JSON_THROW_ON_ERROR);
            } catch (DecryptException|\JsonException) {
                abort(422, 'Cursorul nu este valid.');
            }
            $position = Validator::make(['position' => $position], ['position' => 'required|array|size:4',
                'position.0' => 'required|date_format:Y-m-d H:i:s', 'position.1' => 'required|in:kitchen.recipe,kitchen.meal',
                'position.2' => 'required|uuid', 'position.3' => 'required|in:'.$familyId])->validate()['position'];
            $query->where(fn (Builder $after) => $after->where('occurred_at', '<', $position[0])
                ->orWhere(fn (Builder $sameDate) => $sameDate->where('occurred_at', $position[0])
                    ->where(fn (Builder $tie) => $tie->where('source_kind', '>', $position[1])
                        ->orWhere(fn (Builder $sameKind) => $sameKind->where('source_kind', $position[1])->where('source_id', '<', $position[2])))));
        }
        $rows = $query->orderByDesc('occurred_at')->orderBy('source_kind')->orderByDesc('source_id')->limit(201)->get();
        $scan = $rows->take(200);
        $recipeIds = $scan->where('source_kind', 'kitchen.recipe')->pluck('source_id')->all();
        $mealIds = $scan->where('source_kind', 'kitchen.meal')->pluck('source_id')->all();
        $cards = ['kitchen.recipe' => $recipeIds ? $this->recipes->activityCards($familyId, $actorId, $recipeIds) : [],
            'kitchen.meal' => $mealIds ? $this->meals->activityCards($familyId, $actorId, $membershipId, $mealIds) : []];
        $items = [];
        $scanned = 0;
        $last = null;
        foreach ($scan as $row) {
            $scanned++;
            $last = $row;
            if (isset($cards[$row->source_kind][$row->source_id])) {
                $items[] = $cards[$row->source_kind][$row->source_id] + ['occurred_at' => $row->occurred_at];
            }
            if (count($items) === $limit) {
                break;
            }
        }
        $more = $scanned < $rows->count();

        return ['items' => $items, 'has_more' => $more, 'next_cursor' => $more && $last
            ? Crypt::encryptString(json_encode([$last->occurred_at, $last->source_kind, $last->source_id, $familyId], JSON_THROW_ON_ERROR)) : null];
    }
}
