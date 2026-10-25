<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class KitchenDiningTest extends TestCase
{
    private string $family;

    private string $otherFamily;

    private array $users = [];

    private array $members = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('memoria_test', config('database.connections.pgsql.database'));
        $admin = config('database.connections.pgsql');
        $admin['username'] = 'memoria_admin';
        $admin['password'] = env('DB_ADMIN_PASSWORD');
        config(['database.connections.admin' => $admin]);
        $this->travelTo(CarbonImmutable::parse('2026-10-25T12:00:00Z'));
        $this->family = (string) Str::uuid();
        $this->otherFamily = (string) Str::uuid();
        foreach ([$this->family, $this->otherFamily] as $family) {
            DB::connection('admin')->table('families')->insert(['id' => $family, 'name' => 'Mese dining fixture']);
        }
        foreach (['owner', 'reader', 'outsider'] as $name) {
            $user = User::factory()->make(['name' => 'Dining '.$name, 'email' => 'dining-'.$this->family.'-'.$name.'@example.test']);
            $user->setConnection('admin')->save();
            $this->users[$name] = $user;
            $this->members[$name] = (string) Str::uuid();
            DB::connection('admin')->table('family_memberships')->insert([
                'id' => $this->members[$name], 'family_id' => $name === 'outsider' ? $this->otherFamily : $this->family,
                'user_id' => $user->id, 'role' => $name === 'reader' ? 'adult' : 'owner',
            ]);
        }
    }

    protected function tearDown(): void
    {
        try {
            DB::disableQueryLog();
            foreach ([$this->family, $this->otherFamily] as $family) {
                foreach (['kitchen_meal_ratings', 'kitchen_visit_ratings', 'kitchen_meal_votes', 'kitchen_meals',
                    'kitchen_dining_visits', 'kitchen_restaurant_access', 'kitchen_restaurants', 'recipe_grants', 'recipe_versions', 'recipes',
                    'kitchen_activity_sources', 'memory_timeline_sources', 'outbox_deliveries', 'outbox_events',
                    'calendar_reminder_syncs', 'audit_events', 'member_profiles', 'family_memberships'] as $table) {
                    DB::connection('admin')->table($table)->where('family_id', $family)->delete();
                }
                DB::connection('admin')->table('families')->where('id', $family)->delete();
            }
            DB::connection('admin')->table('users')->whereIn('id', array_map(fn (User $user) => $user->id, $this->users))->delete();
        } finally {
            $this->travelBack();
            DB::disconnect('pgsql');
            DB::disconnect('admin');
            parent::tearDown();
        }
    }

    private function base(?string $family = null): string
    {
        return '/api/v1/families/'.($family ?? $this->family);
    }

    private function meal(array $changes = []): array
    {
        return $this->postJson($this->base().'/meals', array_replace(['dish_title' => 'Cartofi prăjiți'], $changes))->assertCreated()->json();
    }

    private function restaurant(array $changes = []): array
    {
        return $this->postJson($this->base().'/restaurants', array_replace(['name' => 'Restaurant de test', 'city' => 'Brașov'], $changes))->assertCreated()->json();
    }

    private function visit(array $restaurant, array $changes = []): array
    {
        return $this->postJson($this->base().'/dining-visits', array_replace([
            'restaurant_id' => $restaurant['id'], 'meal_date' => '2026-10-25', 'meal_type' => 'lunch', 'visibility' => 'family',
            'dishes' => [['dish_title' => 'Carbonara', 'category' => 'pasta', 'score' => 9]],
        ], $changes))->assertCreated()->json();
    }

    private function rateMeal(string $meal, int $score, int $version = 0, ?string $comment = null)
    {
        return $this->withHeader('If-Match', '"'.$version.'"')->putJson($this->base().'/meals/'.$meal.'/rating', ['score' => $score, 'comment' => $comment]);
    }

    private function rateVisit(string $visit, int $score, int $version = 0, ?string $comment = null)
    {
        return $this->withHeader('If-Match', '"'.$version.'"')->putJson($this->base().'/dining-visits/'.$visit.'/rating', ['score' => $score, 'comment' => $comment]);
    }

    private function ranking(string $type = 'dishes', string $scope = 'family', string $extra = '')
    {
        return $this->getJson($this->base().'/dining-rankings?type='.$type.'&scope='.$scope.$extra)->assertOk();
    }

    private function stored(string $table, string $id): object
    {
        return DB::connection('admin')->table($table)->where('family_id', $this->family)->where('id', $id)->sole();
    }

    public function test_quick_meal_requires_only_a_name_and_is_visible_without_recipe_or_fabricated_photo(): void
    {
        $this->actingAs($this->users['owner']);
        $meal = $this->meal();
        $this->assertSame('Cartofi prăjiți', $meal['dish_title']);
        $this->assertSame('2026-10-25', $meal['meal_date']);
        $this->assertSame('snack', $meal['meal_type']);
        $this->assertSame('family', $meal['visibility']);
        $this->assertSame('other', $meal['category']);
        $this->assertFalse($meal['recipe_available']);
        $this->assertNull($meal['photo_status']);
        $this->assertNull($meal['my_rating']);
        $this->assertSame(0, $meal['rating_count']);
        $stored = $this->stored('kitchen_meals', $meal['id']);
        $this->assertNull($stored->recipe_id);
        $this->assertNull($stored->recipe_version_id);
        $this->assertNull($stored->visit_id);
        $this->getJson($this->base().'/meals')->assertOk()->assertJsonPath('items.0.id', $meal['id'])
            ->assertHeader('Cache-Control', 'max-age=0, no-store, private');
        $this->getJson($this->base().'/meals/'.$meal['id'])->assertOk()->assertJsonPath('dish_title', 'Cartofi prăjiți');
        $this->getJson($this->base().'/meals/'.$meal['id'].'/recipe')->assertNotFound();
        $this->getJson($this->base().'/meals/'.$meal['id'].'/photo')->assertNotFound();
        $this->getJson($this->base().'/meals?history=1&q=Cartofi')->assertOk()->assertJsonPath('items.0.id', $meal['id']);
        $this->ranking()->assertJsonCount(0, 'items');
        $calendar = $this->getJson($this->base().'/calendar-items?month=2026-10')->assertOk()->json();
        $this->assertContains($meal['id'], array_column(array_column($calendar, 'source'), 'id'));
    }

    public function test_recipe_meals_keep_their_snapshot_and_binary_votes_have_no_invented_numeric_score(): void
    {
        $this->actingAs($this->users['owner']);
        $recipe = $this->postJson($this->base().'/recipes', [
            'title' => 'Paste din rețetă', 'category' => 'main', 'ingredients' => ['200 g paste'],
            'instructions' => 'Fierbe pastele.', 'duration_minutes' => 20, 'servings' => 2,
            'difficulty' => 'easy', 'visibility' => 'private',
        ])->assertCreated()->json();
        $meal = $this->postJson($this->base().'/meals', ['recipe_id' => $recipe['id'], 'recipe_version_id' => $recipe['recipe_version_id'],
            'meal_date' => '2026-10-25', 'meal_type' => 'lunch', 'visibility' => 'family'])->assertCreated()->json();
        $this->withHeader('If-Match', '"0"')->putJson($this->base().'/meals/'.$meal['id'].'/vote', ['liked' => true, 'comment' => 'Vot binar existent'])
            ->assertOk()->assertJsonPath('liked_count', 1)->assertJsonPath('rating_count', 0)->assertJsonPath('my_rating', null);
        $this->rateMeal($meal['id'], 8)->assertOk()->assertJsonPath('my_rating.score', 8)->assertJsonPath('liked_count', 1)
            ->assertJsonPath('my_vote.comment', 'Vot binar existent');
        $version = $this->stored('kitchen_meals', $meal['id'])->recipe_version_id;
        $this->assertSame($recipe['recipe_version_id'], $version);
        $this->assertSame('Paste din rețetă', $this->stored('kitchen_meals', $meal['id'])->dish_title);
        $this->actingAs($this->users['reader'])->getJson($this->base().'/meals/'.$meal['id'])->assertOk()
            ->assertJsonPath('recipe_available', false)->assertJsonPath('dish_title', 'Paste din rețetă');
        $this->getJson($this->base().'/meals/'.$meal['id'].'/recipe')->assertNotFound();
    }

    public function test_numeric_ratings_are_personal_versioned_and_can_be_withdrawn_without_losing_legacy_votes(): void
    {
        $this->actingAs($this->users['owner']);
        $meal = $this->meal();
        $this->putJson($this->base().'/meals/'.$meal['id'].'/rating', ['score' => 8])->assertStatus(428);
        $this->rateMeal($meal['id'], 8, 0, 'Crocant')->assertOk()->assertJsonPath('my_rating.version', 1)
            ->assertJsonPath('my_rating.score', 8)->assertJsonPath('rating_count', 1)->assertJsonPath('rating_average', 8);
        $this->rateMeal($meal['id'], 2)->assertStatus(412);
        $this->actingAs($this->users['reader']);
        $this->rateMeal($meal['id'], 6)->assertOk()->assertJsonPath('rating_count', 2)->assertJsonPath('rating_average', 7)
            ->assertJsonPath('my_rating.score', 6);
        $this->withHeader('If-Match', '"1"')->putJson($this->base().'/meals/'.$meal['id'].'/rating',
            ['score' => 9, 'membership_id' => $this->members['owner']])->assertUnprocessable();
        $this->withHeader('If-Match', '"1"')->deleteJson($this->base().'/meals/'.$meal['id'].'/rating')->assertOk()
            ->assertJsonPath('rating_count', 1)->assertJsonPath('my_rating.score', null)->assertJsonPath('my_rating.version', 2);
        $this->rateMeal($meal['id'], 10, 1)->assertStatus(412);
        $this->rateMeal($meal['id'], 10, 2)->assertOk()->assertJsonPath('my_rating.version', 3)->assertJsonPath('rating_average', 9);
        $this->assertSame(2, DB::connection('admin')->table('kitchen_meal_ratings')->where('family_id', $this->family)->count());
        $this->assertSame(0, DB::connection('admin')->table('kitchen_meal_votes')->where('family_id', $this->family)->count());
    }

    public function test_visits_store_multiple_dishes_and_restaurant_scores_are_independent_from_food_scores(): void
    {
        $this->actingAs($this->users['owner']);
        $restaurant = $this->restaurant();
        $visit = $this->visit($restaurant, ['restaurant_score' => 6, 'restaurant_comment' => 'Servire lentă', 'dishes' => [
            ['dish_title' => 'Carbonara', 'category' => 'pasta', 'score' => 10, 'comment' => 'Foarte bune'],
            ['dish_title' => 'Ciorbă de văcuță', 'category' => 'soup', 'score' => 8],
        ]]);
        $this->assertSame($restaurant['id'], $visit['restaurant']['id']);
        $this->assertCount(2, $visit['dishes']);
        $this->assertSame(6, $visit['my_rating']['score']);
        $this->assertSame(6, $visit['rating_average']);
        $this->assertEqualsCanonicalizing([10, 8], array_column(array_column($visit['dishes'], 'my_rating'), 'score'));
        foreach ($visit['dishes'] as $dish) {
            $this->assertSame($visit['id'], $dish['visit_id']);
            $this->assertSame('2026-10-25', $dish['meal_date']);
            $this->assertSame('family', $dish['visibility']);
            $this->assertFalse($dish['recipe_available']);
        }
        $this->getJson($this->base().'/dining-visits?restaurant_id='.$restaurant['id'])->assertOk()->assertJsonPath('items.0.id', $visit['id']);
        $this->ranking('restaurants')->assertJsonPath('items.0.average_score', 6)->assertJsonPath('items.0.rating_count', 1);
        $this->ranking('dishes', 'family', '&category=pasta')->assertJsonCount(1, 'items')->assertJsonPath('items.0.average_score', 10);
        $this->actingAs($this->users['reader']);
        $this->rateVisit($visit['id'], 8)->assertOk()->assertJsonPath('rating_average', 7)->assertJsonPath('rating_count', 2);
        $this->withHeader('If-Match', '"1"')->deleteJson($this->base().'/dining-visits/'.$visit['id'].'/rating')->assertOk()
            ->assertJsonPath('rating_average', 6)->assertJsonPath('my_rating.version', 2);
        $this->rateVisit($visit['id'], 9, 1)->assertStatus(412);
    }

    public function test_history_and_rankings_apply_private_access_foreign_family_and_revocation_before_aggregation(): void
    {
        $this->actingAs($this->users['owner']);
        $restaurant = $this->restaurant();
        $shared = $this->visit($restaurant, ['restaurant_score' => 8]);
        $private = $this->visit($restaurant, ['visibility' => 'private', 'restaurant_score' => 1,
            'dishes' => [['dish_title' => 'Carbonara', 'category' => 'pasta', 'score' => 1]]]);
        $this->actingAs($this->users['reader']);
        $this->getJson($this->base().'/dining-visits')->assertOk()->assertJsonCount(1, 'items')->assertJsonPath('items.0.id', $shared['id']);
        $this->getJson($this->base().'/dining-visits/'.$private['id'])->assertNotFound();
        $this->getJson($this->base().'/meals/'.$private['dishes'][0]['id'])->assertNotFound();
        $this->ranking('dishes')->assertJsonPath('items.0.average_score', 9)->assertJsonPath('items.0.rating_count', 1);
        $this->ranking('restaurants')->assertJsonPath('items.0.average_score', 8);
        $this->rateMeal($shared['dishes'][0]['id'], 5)->assertOk()->assertJsonPath('rating_average', 7);
        $this->rateVisit($shared['id'], 4)->assertOk()->assertJsonPath('rating_average', 6);
        $this->ranking('dishes', 'mine')->assertJsonPath('items.0.average_score', 5)->assertJsonPath('items.0.rating_count', 1);
        $this->actingAs($this->users['outsider']);
        $this->getJson($this->base().'/dining-visits')->assertNotFound();
        $this->getJson($this->base($this->otherFamily).'/dining-visits/'.$shared['id'])->assertNotFound();
        $this->getJson($this->base($this->otherFamily).'/meals/'.$shared['dishes'][0]['id'])->assertNotFound();
        $this->getJson($this->base($this->otherFamily).'/dining-rankings?type=dishes&scope=family')->assertOk()->assertJsonCount(0, 'items');
        DB::connection('admin')->table('family_memberships')->where('id', $this->members['reader'])->update(['revoked_at' => now()]);
        $this->actingAs($this->users['reader'])->getJson($this->base().'/dining-visits')->assertNotFound();
        $this->actingAs($this->users['owner']);
        $this->getJson($this->base().'/dining-visits/'.$shared['id'])->assertOk()->assertJsonPath('rating_average', 8)->assertJsonPath('rating_count', 1);
        $this->getJson($this->base().'/meals/'.$shared['dishes'][0]['id'])->assertOk()->assertJsonPath('rating_average', 9)->assertJsonPath('rating_count', 1);
    }

    public function test_normalized_dish_identity_groups_repeat_visits_but_keeps_restaurants_and_cities_distinct(): void
    {
        $this->actingAs($this->users['owner']);
        $first = $this->restaurant(['name' => 'La Masă', 'city' => 'Brașov']);
        $duplicate = $this->postJson($this->base().'/restaurants', ['name' => '  la   masă  ', 'city' => ' BRAȘOV ']);
        $this->assertContains($duplicate->status(), [200, 201]);
        $this->assertSame($first['id'], $duplicate->json('id'));
        $second = $this->restaurant(['name' => 'La Masă', 'city' => 'Sibiu']);
        $this->assertNotSame($first['id'], $second['id']);
        $separatorInName = $this->restaurant(['name' => 'A|B', 'city' => 'C']);
        $separatorInCity = $this->restaurant(['name' => 'A', 'city' => 'B|C']);
        $this->assertNotSame($separatorInName['id'], $separatorInCity['id']);
        $this->assertSame('A|B', $separatorInName['name']);
        $this->assertSame('B|C', $separatorInCity['city']);
        $this->visit($first, ['meal_date' => '2026-10-20', 'dishes' => [['dish_title' => 'Carbonara', 'category' => 'pasta', 'score' => 10]]]);
        $latest = $this->visit($first, ['meal_date' => '2026-10-24', 'dishes' => [['dish_title' => ' carbonara ', 'category' => 'pasta', 'score' => 6]]]);
        $this->visit($second, ['dishes' => [['dish_title' => 'Carbonara', 'category' => 'pasta', 'score' => 9]]]);
        $rows = $this->ranking('dishes', 'family', '&category=pasta')->assertJsonCount(2, 'items')->json('items');
        $this->assertSame($second['id'], $rows[0]['restaurant']['id']);
        $group = collect($rows)->firstWhere('restaurant.id', $first['id']);
        $this->assertSame(8, $group['average_score']);
        $this->assertSame(2, $group['rating_count']);
        $this->assertSame('2026-10-24', $group['last_eaten_on']);
        $this->assertSame($latest['dishes'][0]['id'], $group['meal_id']);
        $this->getJson($this->base().'/dining-visits?restaurant_id='.$first['id'])->assertOk()->assertJsonCount(2, 'items')
            ->assertJsonPath('items.0.id', $latest['id']);
    }

    public function test_parent_visit_privacy_also_hides_a_drifting_shared_dish_from_calendar_activity_and_rankings(): void
    {
        $this->actingAs($this->users['owner']);
        $visit = $this->visit($this->restaurant(), ['restaurant_score' => 8]);
        $meal = $visit['dishes'][0]['id'];
        $this->actingAs($this->users['reader']);
        $calendar = $this->getJson($this->base().'/calendar-items?month=2026-10')->assertOk()->json();
        $this->assertContains($meal, array_column(array_column($calendar, 'source'), 'id'));
        $activity = $this->getJson($this->base().'/home?month=2026-10')->assertOk()->json('activity');
        $this->assertContains($meal, array_column(array_column($activity, 'target'), 'id'));
        DB::connection('admin')->table('kitchen_dining_visits')->where('family_id', $this->family)
            ->where('id', $visit['id'])->update(['visibility' => 'private']);
        $this->assertSame('family', $this->stored('kitchen_meals', $meal)->visibility);
        $this->getJson($this->base().'/meals')->assertOk()->assertJsonCount(0, 'items');
        $this->getJson($this->base().'/meals/'.$meal)->assertNotFound();
        $this->ranking('dishes')->assertJsonCount(0, 'items');
        $this->ranking('restaurants')->assertJsonCount(0, 'items');
        $calendar = $this->getJson($this->base().'/calendar-items?month=2026-10')->assertOk()->json();
        $this->assertNotContains($meal, array_column(array_column($calendar, 'source'), 'id'));
        $activity = $this->getJson($this->base().'/home?month=2026-10')->assertOk()->json('activity');
        $this->assertNotContains($meal, array_column(array_column($activity, 'target'), 'id'));
        $this->actingAs($this->users['owner']);
        $this->getJson($this->base().'/meals/'.$meal)->assertOk();
        $calendar = $this->getJson($this->base().'/calendar-items?month=2026-10')->assertOk()->json();
        $this->assertContains($meal, array_column(array_column($calendar, 'source'), 'id'));
        $activity = $this->getJson($this->base().'/home?month=2026-10')->assertOk()->json('activity');
        $this->assertContains($meal, array_column(array_column($activity, 'target'), 'id'));
    }

    public function test_registering_the_same_private_restaurant_reuses_its_identity_without_revealing_private_visits(): void
    {
        $this->actingAs($this->users['owner']);
        $restaurant = $this->restaurant(['name' => 'Loc privat', 'city' => 'Sibiu']);
        $private = $this->visit($restaurant, ['visibility' => 'private', 'restaurant_score' => 2,
            'dishes' => [['dish_title' => 'Preparat privat', 'category' => 'main', 'score' => 1]]]);
        $this->actingAs($this->users['reader']);
        $this->getJson($this->base().'/restaurants')->assertOk()->assertJsonCount(0, 'items');
        $registered = $this->postJson($this->base().'/restaurants', ['name' => ' loc   privat ', 'city' => 'SIBIU']);
        $this->assertContains($registered->status(), [200, 201]);
        $this->assertSame($restaurant['id'], $registered->json('id'));
        $this->getJson($this->base().'/restaurants')->assertOk()->assertJsonCount(1, 'items')->assertJsonPath('items.0.id', $restaurant['id']);
        $this->getJson($this->base().'/dining-visits')->assertOk()->assertJsonCount(0, 'items');
        $this->getJson($this->base().'/dining-visits/'.$private['id'])->assertNotFound();
        $this->getJson($this->base().'/meals/'.$private['dishes'][0]['id'])->assertNotFound();
        $this->ranking('restaurants')->assertJsonCount(0, 'items');
        $this->ranking('dishes')->assertJsonCount(0, 'items');
        $ownVisit = $this->visit($registered->json(), ['restaurant_score' => 8]);
        $this->getJson($this->base().'/dining-visits')->assertOk()->assertJsonCount(1, 'items')->assertJsonPath('items.0.id', $ownVisit['id']);
        $this->ranking('restaurants')->assertJsonPath('items.0.average_score', 8)->assertJsonPath('items.0.rating_count', 1);
        $this->assertSame(1, DB::connection('admin')->table('kitchen_restaurants')->where('family_id', $this->family)->count());
        $this->assertTrue(DB::connection('admin')->table('kitchen_restaurant_access')->where('family_id', $this->family)
            ->where('restaurant_id', $restaurant['id'])->where('membership_id', $this->members['reader'])->exists());
    }

    public function test_ranking_aggregates_all_history_and_filters_do_not_depend_on_the_first_page(): void
    {
        $this->actingAs($this->users['owner']);
        $restaurant = $this->restaurant();
        for ($i = 0; $i < 14; $i++) {
            $this->visit($restaurant, ['meal_date' => '2026-10-'.str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT),
                'restaurant_score' => $i === 13 ? 10 : 1,
                'dishes' => [['dish_title' => 'Carbonara', 'category' => 'pasta', 'score' => $i === 13 ? 10 : 1]]]);
        }
        $firstPage = $this->getJson($this->base().'/dining-visits')->assertOk()->assertJsonPath('has_more', true)->json('items');
        $secondPage = $this->getJson($this->base().'/dining-visits?page=2')->assertOk()->assertJsonPath('has_more', false)->json('items');
        $this->assertCount(14, array_unique([...array_column($firstPage, 'id'), ...array_column($secondPage, 'id')]));
        $ranking = $this->ranking('dishes', 'family', '&category=pasta')->assertJsonCount(1, 'items')->assertJsonPath('items.0.rating_count', 14)->json('items.0');
        $this->assertEqualsWithDelta(23 / 14, $ranking['average_score'], 0.051);
        $this->ranking('restaurants')->assertJsonPath('items.0.rating_count', 14);
        $this->getJson($this->base().'/meals?history=1&category=pasta&q=Carbonara')->assertOk()->assertJsonPath('has_more', true);
        $this->getJson($this->base().'/meals?date=2026-10-14')->assertOk()->assertJsonCount(1, 'items');
        $this->getJson($this->base().'/meals?history=1&q=unmatched-title')->assertOk()->assertJsonCount(0, 'items');
    }

    public function test_validation_and_limited_role_reject_invalid_writes_but_allow_personal_scores(): void
    {
        $this->getJson($this->base().'/dining-visits')->assertUnauthorized();
        $this->actingAs($this->users['owner']);
        $meal = $this->meal();
        $restaurant = $this->restaurant();
        foreach ([0, 11, 1.5, 'high', null] as $score) {
            $this->withHeader('If-Match', '"0"')->putJson($this->base().'/meals/'.$meal['id'].'/rating', ['score' => $score])->assertUnprocessable();
        }
        $this->postJson($this->base().'/meals', ['dish_title' => '  '])->assertUnprocessable();
        $this->postJson($this->base().'/meals', ['dish_title' => 'Nume', 'owner_id' => $this->users['reader']->id])->assertUnprocessable();
        $this->postJson($this->base().'/meals', ['recipe_id' => (string) Str::uuid(), 'dish_title' => 'Nume'])->assertUnprocessable();
        $this->postJson($this->base().'/dining-visits', ['restaurant_id' => $restaurant['id'], 'dishes' => []])->assertUnprocessable();
        $this->postJson($this->base().'/dining-visits', ['restaurant_id' => $restaurant['id'], 'dishes' => [['dish_title' => 'Nume', 'score' => 11]]])->assertUnprocessable();
        $future = $this->meal(['meal_date' => '2026-10-26']);
        $this->rateMeal($future['id'], 9)->assertUnprocessable();
        DB::connection('admin')->table('family_memberships')->where('id', $this->members['reader'])->update(['role' => 'limited']);
        $this->actingAs($this->users['reader']);
        $this->postJson($this->base().'/meals', ['dish_title' => 'Interzis'])->assertForbidden();
        $this->postJson($this->base().'/restaurants', ['name' => 'Interzis'])->assertForbidden();
        $this->postJson($this->base().'/dining-visits', ['restaurant_id' => $restaurant['id'], 'dishes' => [['dish_title' => 'Interzis']]])->assertForbidden();
        $this->rateMeal($meal['id'], 7)->assertOk()->assertJsonPath('my_rating.score', 7);
    }

    public function test_visit_and_all_dishes_roll_back_atomically_when_audit_fails(): void
    {
        $this->actingAs($this->users['owner']);
        $restaurant = $this->restaurant();
        $before = [];
        foreach (['kitchen_dining_visits', 'kitchen_meals', 'kitchen_meal_ratings', 'kitchen_visit_ratings', 'outbox_events', 'audit_events'] as $table) {
            $before[$table] = DB::connection('admin')->table($table)->where('family_id', $this->family)->count();
        }
        $this->withoutExceptionHandling();
        DB::listen(function ($query) {
            if (str_starts_with($query->sql, 'insert into "audit_events"')) {
                throw new \RuntimeException('Dining audit failure');
            }
        });
        try {
            $this->postJson($this->base().'/dining-visits', ['restaurant_id' => $restaurant['id'], 'restaurant_score' => 8,
                'dishes' => [['dish_title' => 'Carbonara', 'category' => 'pasta', 'score' => 9], ['dish_title' => 'Ciorbă', 'category' => 'soup', 'score' => 7]]]);
            $this->fail('Audit failure must abort the entire visit.');
        } catch (\RuntimeException $error) {
            $this->assertSame('Dining audit failure', $error->getMessage());
        }
        foreach ($before as $table => $count) {
            $this->assertSame($count, DB::connection('admin')->table($table)->where('family_id', $this->family)->count(), $table);
        }
    }

    public function test_list_query_counts_are_constant_and_new_tables_enforce_family_boundaries(): void
    {
        $this->actingAs($this->users['owner']);
        $restaurant = $this->restaurant();
        $visit = $this->visit($restaurant, ['restaurant_score' => 7]);
        $count = function (string $path): int {
            DB::enableQueryLog();
            DB::flushQueryLog();
            $this->getJson($this->base().$path)->assertOk();
            $queries = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $queries;
        };
        $smallVisits = $count('/dining-visits');
        $smallMeals = $count('/meals?history=1');
        $smallRanking = $count('/dining-rankings?type=dishes&scope=family');
        for ($i = 0; $i < 4; $i++) {
            $this->visit($restaurant, ['dishes' => [['dish_title' => 'Preparat '.$i, 'category' => 'other', 'score' => $i + 4]]]);
        }
        $this->assertSame($smallVisits, $count('/dining-visits'));
        $this->assertSame($smallMeals, $count('/meals?history=1'));
        $this->assertSame($smallRanking, $count('/dining-rankings?type=dishes&scope=family'));
        foreach (['kitchen_restaurants', 'kitchen_restaurant_access', 'kitchen_dining_visits', 'kitchen_meal_ratings', 'kitchen_visit_ratings'] as $table) {
            $this->assertSame(0, DB::table($table)->count(), $table);
        }
        DB::transaction(function () {
            DB::select("SELECT set_config('app.family_id', ?, true)", [$this->otherFamily]);
            foreach (['kitchen_restaurants', 'kitchen_restaurant_access', 'kitchen_dining_visits', 'kitchen_meal_ratings', 'kitchen_visit_ratings'] as $table) {
                $this->assertSame(0, DB::table($table)->count(), $table);
            }
        });
        $this->expectException(QueryException::class);
        DB::connection('admin')->table('kitchen_meal_ratings')->insert(['family_id' => $this->otherFamily,
            'meal_id' => $visit['dishes'][0]['id'], 'membership_id' => $this->members['outsider'], 'score' => 8, 'version' => 1,
            'created_at' => now(), 'updated_at' => now()]);
    }
}
