<?php

namespace App\Modules\Kitchen\Application;

use App\Platform\Audit\Audit;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class DiningCatalog
{
    public function __construct(private MealCatalog $meals, private DiningRatings $ratings) {}

    private function restaurantQuery(string $family, int $actor): Builder
    {
        return DB::table('kitchen_restaurants as restaurant')->where('restaurant.family_id', $family)
            ->where(fn (Builder $access) => $access->where('restaurant.owner_id', $actor)
                ->orWhereExists(fn (Builder $known) => $known->selectRaw('1')->from('kitchen_restaurant_access as known')
                    ->join('family_memberships as reader', function ($join) {
                        $join->on('reader.family_id', '=', 'known.family_id')->on('reader.id', '=', 'known.membership_id');
                    })->whereColumn('known.family_id', 'restaurant.family_id')->whereColumn('known.restaurant_id', 'restaurant.id')
                    ->where('reader.user_id', $actor)->whereNull('reader.revoked_at'))
                ->orWhereExists(fn (Builder $visit) => $visit->selectRaw('1')->from('kitchen_dining_visits as visit')
                    ->whereColumn('visit.family_id', 'restaurant.family_id')->whereColumn('visit.restaurant_id', 'restaurant.id')
                    ->where(fn (Builder $visible) => $visible->where('visit.owner_id', $actor)->orWhere('visit.visibility', 'family'))));
    }

    public function restaurants(string $family, int $actor, array $filters): array
    {
        $query = $this->restaurantQuery($family, $actor);
        if (! empty($filters['q'])) {
            $search = '%'.addcslashes($filters['q'], '%_\\').'%';
            $query->where(fn (Builder $q) => $q->where('restaurant.name', 'ilike', $search)->orWhere('restaurant.city', 'ilike', $search));
        }
        $page = (int) ($filters['page'] ?? 1);
        $rows = $query->orderBy('restaurant.name')->orderBy('restaurant.id')->offset(($page - 1) * 20)->limit(21)
            ->get(['restaurant.id', 'restaurant.name', 'restaurant.city']);

        return ['items' => $rows->take(20)->all(), 'page' => $page, 'has_more' => $rows->count() > 20];
    }

    public function createRestaurant(string $family, int $actor, string $membership, array $data): array
    {
        return DB::transaction(function () use ($family, $actor, $membership, $data) {
            $name = Str::squish($data['name']);
            $city = isset($data['city']) ? Str::squish($data['city']) : null;
            $city = $city === '' ? null : $city;
            $identity = hash('sha256', json_encode([Str::lower($name), Str::lower($city ?? '')], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            $query = DB::table('kitchen_restaurants')->where('family_id', $family)->where('identity_key', $identity);
            $previous = $query->first(['id', 'name', 'city']);
            if ($previous) {
                DB::table('kitchen_restaurant_access')->insertOrIgnore(['family_id' => $family, 'restaurant_id' => $previous->id, 'membership_id' => $membership]);

                return (array) $previous;
            }
            $id = (string) Str::uuid();
            DB::table('kitchen_restaurants')->insert(['id' => $id, 'family_id' => $family, 'owner_id' => $actor,
                'name' => $name, 'city' => $city, 'identity_key' => $identity, 'created_at' => now(), 'updated_at' => now()]);
            Audit::record($family, $actor, 'dining.restaurant_created', $id);

            return ['id' => $id, 'name' => $name, 'city' => $city];
        });
    }

    private function visitSources(string $family, int $actor): Builder
    {
        return DB::table('kitchen_dining_visits as visit')->where('visit.family_id', $family)
            ->where(fn (Builder $access) => $access->where('visit.owner_id', $actor)->orWhere('visit.visibility', 'family'))
            ->join('kitchen_restaurants as restaurant', function ($join) {
                $join->on('restaurant.family_id', '=', 'visit.family_id')->on('restaurant.id', '=', 'visit.restaurant_id');
            })->join('users as author', 'author.id', '=', 'visit.owner_id');
    }

    private function visibleVisits(string $family, int $actor, string $membership): Builder
    {
        return $this->visitSources($family, $actor)->leftJoin('kitchen_visit_ratings as my_rating', function ($join) use ($membership) {
            $join->on('my_rating.family_id', '=', 'visit.family_id')->on('my_rating.visit_id', '=', 'visit.id')->where('my_rating.membership_id', $membership);
        })->leftJoinSub($this->ratings->totals('visit', $family), 'rating_totals', function ($join) {
            $join->on('rating_totals.family_id', '=', 'visit.family_id')->on('rating_totals.visit_id', '=', 'visit.id');
        });
    }

    private function visitFields(): array
    {
        return ['visit.id', 'visit.meal_date', 'visit.meal_type', 'visit.visibility', 'visit.owner_id', 'visit.version', 'visit.created_at',
            'author.name as owner_name', 'restaurant.id as restaurant_id', 'restaurant.name as restaurant_name', 'restaurant.city as restaurant_city',
            'my_rating.score as my_score', 'my_rating.comment as my_rating_comment', 'my_rating.version as my_rating_version',
            'rating_totals.rating_average', DB::raw('COALESCE(rating_totals.rating_count, 0) AS rating_count')];
    }

    private function visitPayload(object $row, array $dishes): array
    {
        $data = (array) $row;
        $data = array_replace($data, DiningRatings::payload($row));
        $data['restaurant'] = ['id' => $row->restaurant_id, 'name' => $row->restaurant_name, 'city' => $row->restaurant_city];
        $data['dishes'] = isset($dishes[$row->id]) ? $dishes[$row->id]->values()->all() : [];
        unset($data['restaurant_id'], $data['restaurant_name'], $data['restaurant_city'], $data['my_score'], $data['my_rating_comment'], $data['my_rating_version']);

        return $data;
    }

    public function visits(string $family, int $actor, string $membership, array $filters): array
    {
        $query = $this->visibleVisits($family, $actor, $membership);
        if (! empty($filters['restaurant_id'])) {
            $query->where('visit.restaurant_id', $filters['restaurant_id']);
        }
        if (($filters['scope'] ?? '') === 'mine') {
            $query->where('visit.owner_id', $actor);
        }
        $page = (int) ($filters['page'] ?? 1);
        $rows = $query->orderByDesc('visit.meal_date')->orderByDesc('visit.created_at')->orderByDesc('visit.id')
            ->offset(($page - 1) * 12)->limit(13)->get($this->visitFields());
        $visible = $rows->take(12);
        $dishes = $this->meals->forVisits($family, $actor, $membership, $visible->pluck('id')->all());

        return ['items' => $visible->map(fn (object $row) => $this->visitPayload($row, $dishes))->all(), 'page' => $page, 'has_more' => $rows->count() > 12];
    }

    public function visit(string $family, int $actor, string $membership, string $id, int $ratingPage = 1): array
    {
        abort_unless(Str::isUuid($id), 404);
        $row = $this->visibleVisits($family, $actor, $membership)->where('visit.id', $id)->firstOrFail($this->visitFields());

        return $this->visitPayload($row, $this->meals->forVisits($family, $actor, $membership, [$id]))
            + $this->ratings->details('visit', $family, $id, $ratingPage);
    }

    public function createVisit(string $family, int $actor, string $membership, array $data): array
    {
        return DB::transaction(function () use ($family, $actor, $membership, $data) {
            abort_unless($this->restaurantQuery($family, $actor)->where('restaurant.id', $data['restaurant_id'])->exists(), 404);
            $id = (string) Str::uuid();
            $defaults = ['meal_date' => $data['meal_date'], 'meal_type' => $data['meal_type'], 'visibility' => $data['visibility'], 'visit_id' => $id];
            DB::table('kitchen_dining_visits')->insert(['id' => $id, 'family_id' => $family, 'owner_id' => $actor,
                'restaurant_id' => $data['restaurant_id'], 'meal_date' => $data['meal_date'], 'meal_type' => $data['meal_type'],
                'visibility' => $data['visibility'], 'created_at' => now(), 'updated_at' => now()]);
            foreach ($data['dishes'] as $dish) {
                $this->meals->createRecord($family, $actor, $membership, $dish + $defaults);
            }
            if (isset($data['restaurant_score'])) {
                abort_if($data['meal_date'] > CarbonImmutable::now('Europe/Bucharest')->toDateString(), 422, 'Poți evalua începând cu ziua vizitei.');
                $this->ratings->save('visit', $family, $membership, $id, ['score' => $data['restaurant_score'], 'comment' => $data['restaurant_comment'] ?? null], '"0"');
            }
            Audit::record($family, $actor, 'dining.visit_created', $id);

            return $this->visit($family, $actor, $membership, $id);
        });
    }

    public function rateVisit(string $family, int $actor, string $membership, string $id, array $data, ?string $expected, bool $withdraw = false): array
    {
        return DB::transaction(function () use ($family, $actor, $membership, $id, $data, $expected, $withdraw) {
            $visit = $this->visit($family, $actor, $membership, $id);
            abort_if($visit['meal_date'] > CarbonImmutable::now('Europe/Bucharest')->toDateString(), 422, 'Poți evalua începând cu ziua vizitei.');
            $this->ratings->save('visit', $family, $membership, $id, $data, $expected, $withdraw);
            Audit::record($family, $actor, $withdraw ? 'dining.rating_withdrawn' : 'dining.rating_saved', $id);

            return $this->visit($family, $actor, $membership, $id);
        });
    }

    public function rankings(string $family, int $actor, string $membership, array $filters): array
    {
        $kind = ($filters['type'] ?? 'dishes') === 'restaurants' ? 'visit' : 'meal';
        $query = $this->ratings->active($kind, $family);
        if (($filters['scope'] ?? 'mine') === 'mine') {
            $query->where('ratings.membership_id', $membership);
        }
        if ($kind === 'meal') {
            $query->join('kitchen_meals as m', function ($join) {
                $join->on('m.family_id', '=', 'ratings.family_id')->on('m.id', '=', 'ratings.meal_id');
            })->whereNull('m.archived_at')
                ->where(fn (Builder $access) => $access->where('m.owner_id', $actor)->orWhere('m.visibility', 'family'))
                ->leftJoin('kitchen_dining_visits as visit', function ($join) {
                    $join->on('visit.family_id', '=', 'm.family_id')->on('visit.id', '=', 'm.visit_id');
                })->where(fn (Builder $access) => $access->whereNull('m.visit_id')->orWhere('visit.owner_id', $actor)->orWhere('visit.visibility', 'family'))
                ->leftJoin('kitchen_restaurants as restaurant', function ($join) {
                    $join->on('restaurant.family_id', '=', 'visit.family_id')->on('restaurant.id', '=', 'visit.restaurant_id');
                });
            if (! empty($filters['category'])) {
                $query->where('m.category', $filters['category']);
            }
            $query->groupBy('m.category', 'restaurant.id', 'restaurant.name', 'restaurant.city')
                ->groupByRaw("lower(regexp_replace(btrim(m.dish_title), '\\s+', ' ', 'g'))")
                ->selectRaw('MIN(m.dish_title) AS dish_title, m.category, MAX(m.meal_date) AS last_eaten_on, (array_agg(m.id ORDER BY m.meal_date DESC, m.id DESC))[1] AS meal_id')
                ->orderByDesc('last_eaten_on')->orderBy('dish_title')->orderBy('m.category');
        } else {
            $query->join('kitchen_dining_visits as visit', function ($join) {
                $join->on('visit.family_id', '=', 'ratings.family_id')->on('visit.id', '=', 'ratings.visit_id');
            })->where(fn (Builder $access) => $access->where('visit.owner_id', $actor)->orWhere('visit.visibility', 'family'))
                ->join('kitchen_restaurants as restaurant', function ($join) {
                    $join->on('restaurant.family_id', '=', 'visit.family_id')->on('restaurant.id', '=', 'visit.restaurant_id');
                })->groupBy('restaurant.id', 'restaurant.name', 'restaurant.city')
                ->selectRaw('MAX(visit.meal_date) AS last_visited_on, (array_agg(visit.id ORDER BY visit.meal_date DESC, visit.id DESC))[1] AS visit_id')
                ->orderByDesc('last_visited_on');
        }
        if (! empty($filters['restaurant_id'])) {
            $query->where('restaurant.id', $filters['restaurant_id']);
        }
        $query->addSelect('restaurant.id as restaurant_id', 'restaurant.name as restaurant_name', 'restaurant.city as restaurant_city')
            ->selectRaw('ROUND(AVG(ratings.score), 2) AS average_score, COUNT(*) AS rating_count');
        $orders = $query->orders;
        $query->reorder()->orderByDesc('average_score')->orderByDesc('rating_count');
        foreach ($orders ?? [] as $order) {
            $query->orderBy($order['column'], $order['direction']);
        }
        $query->orderBy('restaurant.id');
        $page = (int) ($filters['page'] ?? 1);
        $rows = $query->offset(($page - 1) * 12)->limit(13)->get();
        $items = $rows->take(12)->map(function (object $row): array {
            $data = (array) $row;
            $data['average_score'] = (float) $row->average_score;
            $data['rating_count'] = (int) $row->rating_count;
            $data['restaurant'] = $row->restaurant_id === null ? null : ['id' => $row->restaurant_id, 'name' => $row->restaurant_name, 'city' => $row->restaurant_city];
            unset($data['restaurant_id'], $data['restaurant_name'], $data['restaurant_city']);

            return $data;
        })->all();

        return ['items' => $items, 'page' => $page, 'has_more' => $rows->count() > 12];
    }
}
