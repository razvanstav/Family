<?php

namespace Tests\Feature;

use App\Models\User;
use App\Platform\Media\Scanner;
use Carbon\CarbonImmutable;
use Database\Seeders\DemoSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

final class KitchenMealTest extends TestCase
{
    private User $ana;

    private User $mihai;

    private string $mihaiMember;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('memoria_test', config('database.connections.pgsql.database'));
        $admin = config('database.connections.pgsql');
        $admin['username'] = 'memoria_admin';
        $admin['password'] = env('DB_ADMIN_PASSWORD');
        config(['database.connections.admin' => $admin, 'memoria.demo_enabled' => true]);
        $this->travelTo(CarbonImmutable::parse('2026-10-21T09:00:00Z'));
        DB::connection('admin')->statement('TRUNCATE families, users RESTART IDENTITY CASCADE');
        Storage::fake('local');
        DB::setDefaultConnection('admin');
        (new DemoSeeder)->run();
        DB::setDefaultConnection('pgsql');
        $this->ana = User::where('email', 'ana@example.test')->firstOrFail();
        $this->mihai = User::where('email', 'mihai@example.test')->firstOrFail();
        $this->mihaiMember = DB::table('family_memberships')->where('family_id', DemoSeeder::POPESCU)->where('user_id', $this->mihai->id)->value('id');
        $this->app->instance(Scanner::class, new class implements Scanner
        {
            public function scan(string $path): string
            {
                return 'clean';
            }
        });
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        DB::disconnect('pgsql');
        DB::disconnect('admin');
        parent::tearDown();
    }

    private function path(string $suffix = '', string $family = DemoSeeder::POPESCU): string
    {
        return '/api/v1/families/'.$family.'/meals'.$suffix;
    }

    private function recipe(User $owner, string $visibility = 'family', bool $photo = false): array
    {
        return $this->actingAs($owner)->postJson('/api/v1/families/'.DemoSeeder::POPESCU.'/recipes', [
            'title' => 'Paste de test', 'category' => 'main', 'ingredients' => ['200 g paste'],
            'instructions' => 'Fierbe pastele.', 'duration_minutes' => 20, 'servings' => 2,
            'difficulty' => 'easy', 'visibility' => $visibility,
        ] + ($photo ? ['photo' => UploadedFile::fake()->image('paste.png', 40, 30)] : []))->assertCreated()->json();
    }

    private function mealData(array $recipe, array $changes = []): array
    {
        return array_replace(['recipe_id' => $recipe['id'], 'recipe_version_id' => $recipe['recipe_version_id'],
            'meal_date' => '2026-10-21', 'meal_type' => 'lunch', 'visibility' => 'family'], $changes);
    }

    private function meal(User $owner, array $recipe, array $changes = []): array
    {
        return $this->actingAs($owner)->postJson($this->path(), $this->mealData($recipe, $changes))->assertCreated()->json();
    }

    private function vote(string $id, bool $liked, ?string $comment = null, int $version = 0)
    {
        return $this->withHeader('If-Match', '"'.$version.'"')->putJson($this->path('/'.$id.'/vote'), ['liked' => $liked, 'comment' => $comment]);
    }

    public function test_meal_records_date_type_author_and_recipe_version_without_exposing_source_internals(): void
    {
        $recipe = $this->recipe($this->ana);
        $meal = $this->meal($this->ana, $recipe);
        $this->assertSame('2026-10-21', $meal['meal_date']);
        $this->assertSame('lunch', $meal['meal_type']);
        $this->assertSame('Paste de test', $meal['dish_title']);
        $this->assertSame($this->ana->id, $meal['owner_id']);
        $this->assertNull($meal['my_vote']);
        $this->assertSame([], $meal['feedback']);
        $stored = DB::connection('admin')->table('kitchen_meals')->where('id', $meal['id'])->first();
        $this->assertSame($recipe['recipe_version_id'], $stored->recipe_version_id);
        $response = $this->actingAs($this->mihai)->getJson($this->path())->assertOk()->assertJsonCount(1, 'items');
        foreach (['recipe_id', 'recipe_version_id', 'photo_asset_id', 'family_id', 'storage_key', 'ingredients', 'instructions', 'bytes'] as $field) {
            $this->assertArrayNotHasKey($field, $response->json('items.0'));
        }
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->getJson($this->path('?date=2026-10-22'))->assertJsonCount(0, 'items');
    }

    public function test_members_vote_only_for_themselves_and_can_change_comment_and_choice_without_duplicate_votes(): void
    {
        $meal = $this->meal($this->ana, $this->recipe($this->ana));
        $this->actingAs($this->mihai);
        $this->vote($meal['id'], true, 'Foarte bune!')->assertOk()->assertJsonPath('liked_count', 1)->assertJsonPath('disliked_count', 0)->assertJsonPath('my_vote.version', 1)->assertJsonPath('feedback.0.author_name', $this->mihai->name);
        $this->vote($meal['id'], false, 'Prea multă sare.', 1)->assertOk()->assertJsonPath('liked_count', 0)->assertJsonPath('disliked_count', 1)->assertJsonPath('my_vote.comment', 'Prea multă sare.')->assertJsonCount(1, 'feedback');
        $this->actingAs($this->ana);
        $this->vote($meal['id'], true)->assertOk()->assertJsonPath('liked_count', 1)->assertJsonPath('disliked_count', 1)->assertJsonCount(2, 'feedback');
        $this->withHeader('If-Match', '"1"')->putJson($this->path('/'.$meal['id'].'/vote'), ['liked' => true, 'membership_id' => $this->mihaiMember])->assertUnprocessable();
        $this->assertSame(2, DB::connection('admin')->table('kitchen_meal_votes')->count());
        $this->assertSame('Prea multă sare.', DB::connection('admin')->table('kitchen_meal_votes')->where('membership_id', $this->mihaiMember)->value('comment'));
    }

    public function test_stale_vote_writes_and_withdrawal_conflicts_do_not_overwrite_new_feedback(): void
    {
        $meal = $this->meal($this->ana, $this->recipe($this->ana));
        $this->putJson($this->path('/'.$meal['id'].'/vote'), ['liked' => true])->assertStatus(428);
        $this->vote($meal['id'], true, 'Prima variantă')->assertOk();
        $this->vote($meal['id'], false, 'Din altă fereastră')->assertStatus(412);
        $this->withHeader('If-Match', '"0"')->deleteJson($this->path('/'.$meal['id'].'/vote'))->assertStatus(412);
        $this->withHeader('If-Match', '"1"')->deleteJson($this->path('/'.$meal['id'].'/vote'))->assertOk()->assertJsonPath('liked_count', 0)->assertJsonCount(0, 'feedback')->assertJsonPath('my_vote.liked', null)->assertJsonPath('my_vote.version', 2);
        $this->vote($meal['id'], false, 'Revin cu alt vot', 2)->assertOk()->assertJsonPath('my_vote.version', 3)->assertJsonPath('disliked_count', 1);
        $this->vote($meal['id'], true, 'Fereastră veche', 1)->assertStatus(412);
        $this->getJson($this->path('/'.$meal['id']))->assertJsonPath('my_vote.comment', 'Revin cu alt vot');
        $this->assertSame(1, DB::connection('admin')->table('kitchen_meal_votes')->count());
    }

    public function test_shared_meal_does_not_grant_access_to_private_recipe_or_photo_and_private_meal_stays_hidden(): void
    {
        $recipe = $this->recipe($this->mihai, 'private', true);
        $shared = $this->meal($this->mihai, $recipe);
        $private = $this->meal($this->mihai, $recipe, ['visibility' => 'private']);
        $this->get($this->path('/'.$shared['id'].'/photo'))->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->actingAs($this->ana)->getJson($this->path())->assertJsonCount(1, 'items')->assertJsonPath('items.0.photo_status', null);
        $this->getJson($this->path('/'.$shared['id']))->assertOk()->assertJsonPath('dish_title', 'Paste de test')->assertJsonPath('photo_status', null);
        $this->getJson('/api/v1/families/'.DemoSeeder::POPESCU.'/recipes/'.$recipe['id'])->assertNotFound();
        $this->getJson($this->path('/'.$shared['id'].'/photo'))->assertNotFound();
        $this->vote($shared['id'], true, 'Mi-a plăcut masa.')->assertOk();
        $this->getJson($this->path('/'.$private['id']))->assertNotFound();
        $this->getJson($this->path('/'.$private['id'].'/photo'))->assertNotFound();
        $this->vote($private['id'], true)->assertNotFound();
    }

    public function test_auth_foreign_family_and_revocation_apply_to_lists_details_photos_and_vote_commands(): void
    {
        $this->getJson($this->path())->assertUnauthorized();
        $recipe = $this->recipe($this->ana, 'family', true);
        $meal = $this->meal($this->ana, $recipe);
        $this->getJson($this->path('', DemoSeeder::IONESCU))->assertJsonCount(0, 'items');
        $this->getJson($this->path('/'.$meal['id'], DemoSeeder::IONESCU))->assertNotFound();
        $this->getJson($this->path('/'.$meal['id'].'/photo', DemoSeeder::IONESCU))->assertNotFound();
        $this->actingAs($this->mihai);
        $this->vote($meal['id'], true, 'Comentariu retras prin revocare')->assertOk();
        DB::connection('admin')->table('family_memberships')->where('id', $this->mihaiMember)->update(['revoked_at' => now()]);
        $this->getJson($this->path())->assertNotFound();
        $this->getJson($this->path('/'.$meal['id']))->assertNotFound();
        $this->getJson($this->path('/'.$meal['id'].'/photo'))->assertNotFound();
        $this->vote($meal['id'], false, null, 1)->assertNotFound();
        $this->withHeader('If-Match', '"1"')->deleteJson($this->path('/'.$meal['id'].'/vote'))->assertNotFound();
        $this->postJson($this->path(), $this->mealData($recipe))->assertNotFound();
        $this->actingAs($this->ana)->getJson($this->path('/'.$meal['id']))->assertOk()->assertJsonPath('liked_count', 0)->assertJsonCount(0, 'feedback');
    }

    public function test_limited_member_can_vote_but_cannot_add_meals_and_future_votes_wait_for_bucharest_date(): void
    {
        $recipe = $this->recipe($this->ana);
        $today = $this->meal($this->ana, $recipe);
        $future = $this->meal($this->ana, $recipe, ['meal_date' => '2026-10-22']);
        DB::connection('admin')->table('family_memberships')->where('id', $this->mihaiMember)->update(['role' => 'limited']);
        $this->actingAs($this->mihai);
        $this->postJson($this->path(), $this->mealData($recipe))->assertForbidden();
        $this->vote($today['id'], true)->assertOk();
        $this->vote($future['id'], true)->assertUnprocessable();
        $this->travelTo(CarbonImmutable::parse('2026-10-21T20:59:00Z'));
        $this->getJson($this->path('/'.$future['id']))->assertJsonPath('voting_open', false);
        $this->travelTo(CarbonImmutable::parse('2026-10-21T21:01:00Z'));
        $this->vote($future['id'], false)->assertOk()->assertJsonPath('voting_open', true);
    }

    public function test_invalid_inputs_and_source_version_do_not_create_partial_records(): void
    {
        $recipe = $this->recipe($this->ana);
        foreach ([
            ['meal_date' => '2026-02-30'], ['meal_type' => 'unknown'], ['visibility' => 'selected'],
            ['owner_id' => $this->mihai->id], ['family_id' => DemoSeeder::IONESCU], ['dish_title' => 'Injected title'],
        ] as $changes) {
            $this->postJson($this->path(), $this->mealData($recipe, $changes))->assertUnprocessable();
        }
        $this->postJson($this->path(), $this->mealData($recipe, ['recipe_version_id' => (string) Str::uuid()]))->assertConflict();
        $this->postJson($this->path(), $this->mealData($recipe, ['recipe_id' => (string) Str::uuid()]))->assertNotFound();
        $this->assertSame(0, DB::connection('admin')->table('kitchen_meals')->count());
        $meal = $this->meal($this->ana, $recipe);
        foreach ([['liked' => 2], ['liked' => true, 'comment' => str_repeat('x', 1001)], ['liked' => true, 'user_id' => $this->mihai->id]] as $data) {
            $this->withHeader('If-Match', '"0"')->putJson($this->path('/'.$meal['id'].'/vote'), $data)->assertUnprocessable();
        }
        $this->assertSame(0, DB::connection('admin')->table('kitchen_meal_votes')->count());
        $this->getJson($this->path('?date=wrong'))->assertUnprocessable();
        $this->getJson($this->path('/not-an-id'))->assertNotFound();
    }

    public function test_vote_audit_failure_rolls_back_feedback_and_does_not_capture_comment_text(): void
    {
        $meal = $this->meal($this->ana, $this->recipe($this->ana));
        $this->withoutExceptionHandling();
        DB::listen(function ($query) {
            if (str_starts_with($query->sql, 'insert into "audit_events"')) {
                throw new \RuntimeException('Simulated audit failure');
            }
        });
        try {
            $this->vote($meal['id'], true, 'PRIVATE_COMMENT');
            $this->fail('Expected vote write to fail.');
        } catch (\RuntimeException $error) {
            $this->assertSame('Simulated audit failure', $error->getMessage());
        }
        $this->assertSame(0, DB::connection('admin')->table('kitchen_meal_votes')->count());
        $audit = DB::connection('admin')->table('audit_events')->where('resource_id', $meal['id'])->get()->toJson();
        $this->assertStringNotContainsString('PRIVATE_COMMENT', $audit);
        $this->assertStringNotContainsString('Paste de test', $audit);
    }

    public function test_meal_keeps_version_title_and_photo_when_a_later_recipe_version_is_appended(): void
    {
        $recipe = $this->recipe($this->ana, 'family', true);
        $meal = $this->meal($this->ana, $recipe);
        $original = (array) DB::connection('admin')->table('recipe_versions')->where('id', $recipe['recipe_version_id'])->first();
        $next = array_replace($original, ['id' => (string) Str::uuid(), 'version_no' => 2, 'title' => 'Altă variantă', 'photo_asset_id' => null]);
        DB::connection('admin')->table('recipe_versions')->insert($next);
        DB::connection('admin')->table('recipes')->where('id', $recipe['id'])->update(['version' => 2]);
        $this->getJson($this->path('/'.$meal['id']))->assertOk()->assertJsonPath('dish_title', 'Paste de test')->assertJsonPath('photo_status', 'ready');
        $this->get($this->path('/'.$meal['id'].'/photo'))->assertOk();
        $this->assertSame($original, (array) DB::connection('admin')->table('recipe_versions')->where('id', $recipe['recipe_version_id'])->first());
        $this->postJson($this->path(), $this->mealData($recipe))->assertConflict();
        $this->expectException(QueryException::class);
        DB::connection('admin')->table('recipe_versions')->where('id', $recipe['recipe_version_id'])->update(['instructions' => 'Mutated history']);
    }

    public function test_lists_and_feedback_are_bounded_without_queries_per_row(): void
    {
        $recipe = $this->recipe($this->ana);
        $meal = $this->meal($this->ana, $recipe);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->getJson($this->path())->assertOk();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();
        for ($index = 0; $index < 12; $index++) {
            $this->meal($this->ana, $recipe);
        }
        for ($index = 0; $index < 21; $index++) {
            $member = (string) Str::uuid();
            DB::connection('admin')->table('family_memberships')->insert(['id' => $member, 'family_id' => DemoSeeder::POPESCU,
                'user_id' => User::factory()->create()->id, 'role' => 'limited']);
            DB::connection('admin')->table('kitchen_meal_votes')->insert(['family_id' => DemoSeeder::POPESCU, 'meal_id' => $meal['id'], 'membership_id' => $member, 'liked' => true, 'comment' => 'Comentariu '.$index, 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
        }
        DB::enableQueryLog();
        DB::flushQueryLog();
        $first = $this->getJson($this->path())->assertOk()->assertJsonCount(12, 'items')->assertJsonPath('has_more', true);
        $this->assertSame($queries, count(DB::getQueryLog()));
        DB::disableQueryLog();
        $second = $this->getJson($this->path('?page=2'))->assertJsonCount(1, 'items')->assertJsonPath('has_more', false);
        $this->assertEmpty(array_intersect(array_column($first->json('items'), 'id'), array_column($second->json('items'), 'id')));
        $page1 = $this->getJson($this->path('/'.$meal['id']))->assertJsonCount(20, 'feedback')->assertJsonPath('feedback_has_more', true)->assertJsonPath('liked_count', 21);
        $page2 = $this->getJson($this->path('/'.$meal['id'].'?page=2'))->assertJsonCount(1, 'feedback')->assertJsonPath('feedback_has_more', false);
        $this->assertEmpty(array_intersect(array_column($page1->json('feedback'), 'membership_id'), array_column($page2->json('feedback'), 'membership_id')));
    }

    public function test_rls_and_composite_foreign_keys_reject_cross_family_recipe_version(): void
    {
        $meal = $this->meal($this->ana, $this->recipe($this->ana));
        $this->assertSame(0, DB::table('kitchen_meals')->count());
        $this->assertSame(0, DB::table('kitchen_meal_votes')->count());
        DB::transaction(function () {
            DB::select("SELECT set_config('app.family_id', ?, true)", [DemoSeeder::IONESCU]);
            $this->assertSame(0, DB::table('kitchen_meals')->count());
        });
        $foreign = (array) DB::connection('admin')->table('kitchen_meals')->where('id', $meal['id'])->first();
        $foreign['id'] = (string) Str::uuid();
        $foreign['family_id'] = DemoSeeder::IONESCU;
        $this->expectException(QueryException::class);
        DB::connection('admin')->table('kitchen_meals')->insert($foreign);
    }
}
