<?php

namespace Tests\Feature;

use App\Models\User;
use App\Platform\Media\Scanner;
use Database\Seeders\DemoSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

final class KitchenRecipeAccessTest extends TestCase
{
    private User $ana;

    private User $mihai;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('memoria_test', config('database.connections.pgsql.database'));
        $admin = config('database.connections.pgsql');
        $admin['username'] = 'memoria_admin';
        $admin['password'] = env('DB_ADMIN_PASSWORD');
        config(['database.connections.admin' => $admin, 'memoria.demo_enabled' => true]);
        DB::connection('admin')->statement('TRUNCATE families, users RESTART IDENTITY CASCADE');
        Storage::fake('local');
        DB::setDefaultConnection('admin');
        (new DemoSeeder)->run();
        DB::setDefaultConnection('pgsql');
        $this->ana = User::where('email', 'ana@example.test')->firstOrFail();
        $this->mihai = User::where('email', 'mihai@example.test')->firstOrFail();
        $this->app->instance(Scanner::class, new class implements Scanner
        {
            public function scan(string $path): string
            {
                return 'clean';
            }
        });
        $this->actingAs($this->ana);
    }

    protected function tearDown(): void
    {
        DB::disconnect('pgsql');
        DB::disconnect('admin');
        parent::tearDown();
    }

    private function path(string $suffix = '', string $family = DemoSeeder::POPESCU): string
    {
        return '/api/v1/families/'.$family.'/recipes'.$suffix;
    }

    private function content(array $changes = []): array
    {
        return array_replace(['title' => 'Paste cu ciuperci', 'category' => 'main', 'ingredients' => ['200 g paste', '300 g ciuperci'],
            'instructions' => 'Fierbe pastele și adaugă ciupercile.', 'duration_minutes' => 30, 'servings' => 2, 'difficulty' => 'easy'], $changes);
    }

    private function create(array $changes = []): array
    {
        return $this->postJson($this->path(), $this->content($changes) + ['visibility' => 'family'])->assertCreated()->json();
    }

    private function member(): string
    {
        return DB::table('family_memberships')->where('family_id', DemoSeeder::POPESCU)->where('user_id', $this->mihai->id)->value('id');
    }

    private function share(array $recipe, string $visibility, array $members = []): array
    {
        return $this->putJson($this->path('/'.$recipe['id'].'/sharing'), ['visibility' => $visibility, 'membership_ids' => $members], ['If-Match' => '"'.$recipe['revision'].'"'])
            ->assertOk()->assertHeader('ETag', '"'.($recipe['revision'] + 1).'"')->json();
    }

    private function meal(array $recipe): string
    {
        $base = '/api/v1/families/'.DemoSeeder::POPESCU.'/meals';
        $id = $this->postJson($base, ['recipe_id' => $recipe['id'], 'recipe_version_id' => $recipe['recipe_version_id'], 'meal_date' => '2026-01-01', 'meal_type' => 'lunch', 'visibility' => 'family'])->assertCreated()->json('id');

        return $base.'/'.$id;
    }

    private function assertRecipeDenied(array $recipe): void
    {
        foreach (['', '/versions', '/versions/'.$recipe['recipe_version_id'], '/photo', '/versions/'.$recipe['recipe_version_id'].'/photo'] as $suffix) {
            $this->getJson($this->path('/'.$recipe['id'].$suffix))->assertNotFound();
        }
    }

    public function test_selected_access_and_retraction_apply_to_every_recipe_and_meal_surface(): void
    {
        $recipe = $this->create(['visibility' => 'private', 'photo' => UploadedFile::fake()->image('recipe.png')]);
        $meal = $this->meal($recipe);
        $member = $this->member();
        $this->actingAs($this->mihai);
        $this->assertRecipeDenied($recipe);
        $this->actingAs($this->ana);
        $shared = $this->share($recipe, 'selected', [$member]);
        $this->assertSame([$member], $shared['grants']);
        $this->assertSame(1, $shared['version']);
        $elena = User::where('email', 'elena@example.test')->firstOrFail();
        DB::connection('admin')->table('family_memberships')->insert(['id' => (string) Str::uuid(), 'family_id' => DemoSeeder::POPESCU, 'user_id' => $elena->id, 'role' => 'adult']);
        $this->actingAs($elena);
        $this->assertRecipeDenied($recipe);
        $this->getJson($this->path('?visibility=selected'))->assertJsonCount(0, 'items');
        $this->getJson($meal)->assertJsonPath('recipe_available', false)->assertJsonPath('photo_status', null);
        $this->getJson($meal.'/recipe')->assertNotFound();
        $this->getJson($meal.'/photo')->assertNotFound();
        $this->actingAs($this->mihai);
        $this->getJson($this->path('?visibility=selected'))->assertJsonCount(1, 'items')->assertJsonPath('items.0.grants', []);
        $this->getJson($this->path('/'.$recipe['id']))->assertOk()->assertJsonPath('grants', []);
        $this->getJson($this->path('/'.$recipe['id'].'/versions'))->assertOk()->assertJsonPath('items.0.grants', []);
        $this->get($this->path('/'.$recipe['id'].'/versions/'.$recipe['recipe_version_id'].'/photo'))->assertOk();
        $this->getJson($meal)->assertJsonPath('recipe_available', true)->assertJsonPath('photo_status', 'ready');
        $this->getJson($meal.'/recipe')->assertOk()->assertJsonPath('recipe_version_id', $recipe['recipe_version_id']);
        $this->get($meal.'/photo')->assertOk();
        $this->actingAs($this->ana)->share($shared, 'private');
        $this->actingAs($this->mihai);
        $this->assertRecipeDenied($recipe);
        $this->getJson($this->path('?q=Paste'))->assertJsonCount(0, 'items');
        $this->getJson($meal)->assertJsonPath('recipe_available', false)->assertJsonPath('photo_status', null);
        $this->getJson($meal.'/recipe')->assertNotFound();
        $this->getJson($meal.'/photo')->assertNotFound();
    }

    public function test_archive_preserves_versions_meals_votes_and_bytes_and_restore_keeps_access_private(): void
    {
        $recipe = $this->create(['photo' => UploadedFile::fake()->image('recipe.png')]);
        $meal = $this->meal($recipe);
        $this->putJson($meal.'/vote', ['liked' => true, 'comment' => 'Bună.'], ['If-Match' => '"0"'])->assertOk();
        $version = DB::connection('admin')->table('recipe_versions')->where('id', $recipe['recipe_version_id'])->first();
        $bytes = $this->get($this->path('/'.$recipe['id'].'/photo'))->assertOk()->streamedContent();
        $recipe = $this->share($recipe, 'selected', [$this->member()]);
        $archived = $this->postJson($this->path('/'.$recipe['id'].'/archive'), [], ['If-Match' => '"2"'])->assertOk()->assertHeader('ETag', '"3"')->assertJsonPath('version', 1)->assertJsonPath('visibility', 'private')->assertJsonPath('grants', [])->json();
        $this->assertNotNull($archived['archived_at']);
        $this->assertEquals($version, DB::connection('admin')->table('recipe_versions')->where('id', $recipe['recipe_version_id'])->first());
        $this->getJson($this->path())->assertJsonCount(0, 'items');
        $this->getJson($this->path('?archived=1'))->assertJsonCount(1, 'items');
        $this->getJson($this->path('/'.$recipe['id'].'/versions'))->assertOk();
        $this->assertSame($bytes, $this->get($this->path('/'.$recipe['id'].'/photo'))->assertOk()->streamedContent());
        $this->getJson($meal)->assertJsonPath('liked_count', 1)->assertJsonPath('my_vote.comment', 'Bună.')->assertJsonPath('recipe_available', false);
        $this->getJson($meal.'/recipe')->assertNotFound();
        $this->postJson($this->path('/'.$recipe['id'].'/versions'), $this->content() + ['photo_action' => 'keep'], ['If-Match' => '"3"'])->assertConflict();
        $this->putJson($this->path('/'.$recipe['id'].'/sharing'), ['visibility' => 'family', 'membership_ids' => []], ['If-Match' => '"3"'])->assertConflict();
        $this->postJson('/api/v1/families/'.DemoSeeder::POPESCU.'/meals', ['recipe_id' => $recipe['id'], 'recipe_version_id' => $recipe['recipe_version_id'], 'meal_date' => '2026-01-01', 'meal_type' => 'dinner', 'visibility' => 'family'])->assertConflict();
        $this->actingAs($this->mihai);
        $this->assertRecipeDenied($recipe);
        $this->getJson($this->path('?archived=1'))->assertJsonCount(0, 'items');
        $this->getJson($meal)->assertJsonPath('dish_title', $recipe['title'])->assertJsonPath('liked_count', 1);
        $this->getJson($meal.'/photo')->assertNotFound();
        $this->actingAs($this->ana);
        $restored = $this->postJson($this->path('/'.$recipe['id'].'/restore'), [], ['If-Match' => '"3"'])->assertOk()->assertHeader('ETag', '"4"')->assertJsonPath('archived_at', null)->assertJsonPath('visibility', 'private')->json();
        $this->getJson($meal.'/recipe')->assertOk()->assertJsonPath('version', 1);
        $this->assertSame($bytes, $this->get($meal.'/photo')->assertOk()->streamedContent());
        $this->actingAs($this->mihai);
        $this->assertRecipeDenied($recipe);
        $this->actingAs($this->ana)->share($restored, 'family');
        $this->actingAs($this->mihai)->getJson($meal.'/recipe')->assertOk();
    }

    public function test_one_revision_prevents_stale_content_access_archive_restore_and_replay(): void
    {
        $recipe = $this->create();
        $this->putJson($this->path('/'.$recipe['id'].'/sharing'), ['visibility' => 'family', 'membership_ids' => []])->assertStatus(428);
        $this->postJson($this->path('/'.$recipe['id'].'/archive'))->assertStatus(428);
        $recipe = $this->share($recipe, 'private');
        $before = Storage::disk('local')->allFiles();
        $this->postJson($this->path('/'.$recipe['id'].'/versions'), $this->content() + ['photo_action' => 'replace', 'photo' => UploadedFile::fake()->image('stale.png')], ['If-Match' => '"1"'])->assertStatus(412);
        foreach (['sharing', 'archive', 'restore'] as $action) {
            $this->json($action === 'sharing' ? 'PUT' : 'POST', $this->path('/'.$recipe['id'].'/'.$action), ['visibility' => 'private', 'membership_ids' => []], ['If-Match' => '"1"'])->assertStatus(412);
        }
        $this->postJson($this->path('/'.$recipe['id'].'/versions'), $this->content() + ['photo_action' => 'keep'], ['If-Match' => '"2"'])->assertCreated()->assertJsonPath('version', 2)->assertJsonPath('revision', 3)->assertHeader('ETag', '"3"');
        $this->putJson($this->path('/'.$recipe['id'].'/sharing'), ['visibility' => 'private', 'membership_ids' => []], ['If-Match' => '"2"'])->assertStatus(412);
        $this->postJson($this->path('/'.$recipe['id'].'/archive'), [], ['If-Match' => '"3"'])->assertOk();
        $this->postJson($this->path('/'.$recipe['id'].'/archive'), [], ['If-Match' => '"3"'])->assertStatus(412);
        $this->postJson($this->path('/'.$recipe['id'].'/restore'))->assertStatus(428);
        $this->postJson($this->path('/'.$recipe['id'].'/restore'), [], ['If-Match' => '"4"'])->assertOk();
        $this->postJson($this->path('/'.$recipe['id'].'/restore'), [], ['If-Match' => '"4"'])->assertStatus(412);
        $this->assertSame($before, Storage::disk('local')->allFiles());
        $this->assertSame(2, DB::connection('admin')->table('recipe_versions')->count());
        $this->assertSame(5, DB::connection('admin')->table('audit_events')->where('resource_id', $recipe['id'])->count());
    }

    public function test_only_active_adult_authors_can_manage_and_revocation_removes_grants(): void
    {
        $member = $this->member();
        $this->actingAs($this->mihai);
        $private = $this->create(['visibility' => 'private']);
        $shared = $this->create();
        $this->actingAs($this->ana);
        foreach (['sharing', 'archive', 'restore'] as $action) {
            $method = $action === 'sharing' ? 'PUT' : 'POST';
            $data = ['visibility' => 'private', 'membership_ids' => []];
            $this->json($method, $this->path('/'.$private['id'].'/'.$action), $data, ['If-Match' => '"1"'])->assertNotFound();
            $this->json($method, $this->path('/'.$shared['id'].'/'.$action), $data, ['If-Match' => '"1"'])->assertForbidden();
            $this->json($method, $this->path('/'.$shared['id'].'/'.$action, DemoSeeder::IONESCU), $data, ['If-Match' => '"1"'])->assertNotFound();
        }
        $selected = $this->share($this->create(), 'selected', [$member]);
        DB::connection('admin')->table('family_memberships')->where('id', $member)->update(['role' => 'limited']);
        $this->actingAs($this->mihai)->getJson($this->path('/'.$selected['id']))->assertOk();
        foreach (['sharing', 'archive', 'restore'] as $action) {
            $this->json($action === 'sharing' ? 'PUT' : 'POST', $this->path('/'.$private['id'].'/'.$action), ['visibility' => 'family', 'membership_ids' => []], ['If-Match' => '"1"'])->assertForbidden();
        }
        $this->actingAs($this->ana)->deleteJson('/api/v1/families/'.DemoSeeder::POPESCU.'/members/'.$member)->assertNoContent();
        $this->assertSame(0, DB::connection('admin')->table('recipe_grants')->count());
        $this->getJson($this->path('/'.$selected['id']))->assertJsonPath('grants', []);
        $this->actingAs($this->mihai);
        $this->assertRecipeDenied($private);
        $this->assertRecipeDenied($selected);
        $this->postJson($this->path('/'.$private['id'].'/archive'), [], ['If-Match' => '"1"'])->assertNotFound();
    }

    public function test_invalid_recipients_and_metadata_cannot_change_access(): void
    {
        $recipe = $this->create();
        $member = $this->member();
        $owner = DB::table('family_memberships')->where('family_id', DemoSeeder::POPESCU)->where('user_id', $this->ana->id)->value('id');
        $foreign = DB::table('family_memberships')->where('family_id', DemoSeeder::IONESCU)->where('user_id', $this->ana->id)->value('id');
        foreach ([['selected', []], ['private', [$member]], ['family', [$member]], ['selected', [$owner]], ['selected', [$foreign]], ['selected', [(string) Str::uuid()]], ['selected', [$member, $member]]] as [$visibility, $ids]) {
            $this->putJson($this->path('/'.$recipe['id'].'/sharing'), ['visibility' => $visibility, 'membership_ids' => $ids], ['If-Match' => '"1"'])->assertUnprocessable();
        }
        foreach (['owner_id' => $this->mihai->id, 'family_id' => DemoSeeder::IONESCU, 'version' => 42, 'revision' => 42, 'archived_at' => '2026-01-01'] as $key => $value) {
            $this->putJson($this->path('/'.$recipe['id'].'/sharing'), ['visibility' => 'family', 'membership_ids' => [], $key => $value], ['If-Match' => '"1"'])->assertUnprocessable();
        }
        DB::connection('admin')->table('family_memberships')->where('id', $member)->update(['revoked_at' => now()]);
        $this->putJson($this->path('/'.$recipe['id'].'/sharing'), ['visibility' => 'selected', 'membership_ids' => [$member]], ['If-Match' => '"1"'])->assertUnprocessable();
        $this->assertSame(1, DB::connection('admin')->table('recipes')->value('revision'));
        $this->assertSame(0, DB::connection('admin')->table('recipe_grants')->count());
        $this->getJson($this->path('?archived=invalid'))->assertUnprocessable();
    }

    public function test_audit_failure_rolls_back_sharing_archive_and_authorization_revision(): void
    {
        $recipe = $this->share($this->create(), 'selected', [$this->member()]);
        $revision = DB::connection('admin')->table('families')->where('id', DemoSeeder::POPESCU)->value('authz_revision');
        $this->withoutExceptionHandling();
        DB::listen(function ($query) {
            if (str_starts_with($query->sql, 'insert into "audit_events"')) {
                throw new \RuntimeException('Audit failure');
            }
        });
        foreach (['sharing', 'archive'] as $action) {
            try {
                $this->json($action === 'sharing' ? 'PUT' : 'POST', $this->path('/'.$recipe['id'].'/'.$action), ['visibility' => 'private', 'membership_ids' => []], ['If-Match' => '"2"']);
                $this->fail('Expected failure');
            } catch (\RuntimeException $error) {
                $this->assertSame('Audit failure', $error->getMessage());
            }
            $row = DB::connection('admin')->table('recipes')->first();
            $this->assertSame('selected', $row->visibility);
            $this->assertNull($row->archived_at);
            $this->assertSame(2, $row->revision);
            $this->assertSame(1, DB::connection('admin')->table('recipe_grants')->count());
            $this->assertSame($revision, DB::connection('admin')->table('families')->where('id', DemoSeeder::POPESCU)->value('authz_revision'));
        }
    }

    public function test_grants_have_rls_and_composite_family_keys(): void
    {
        $recipe = $this->share($this->create(), 'selected', [$this->member()]);
        $this->assertSame(0, DB::table('recipe_grants')->count());
        DB::transaction(function () {
            DB::select("SELECT set_config('app.family_id', ?, true)", [DemoSeeder::IONESCU]);
            $this->assertSame(0, DB::table('recipe_grants')->count());
        });
        $this->expectException(QueryException::class);
        DB::connection('admin')->table('recipe_grants')->insert(['family_id' => DemoSeeder::IONESCU, 'recipe_id' => $recipe['id'], 'membership_id' => $this->member()]);
    }

    public function test_failed_restore_preserves_the_archive_and_all_recipe_versions(): void
    {
        $recipe = $this->create();
        $this->postJson($this->path('/'.$recipe['id'].'/archive'), [], ['If-Match' => '"1"'])->assertOk();
        $row = DB::connection('admin')->table('recipes')->first();
        $revision = DB::connection('admin')->table('families')->where('id', DemoSeeder::POPESCU)->value('authz_revision');
        $this->withoutExceptionHandling();
        DB::listen(function ($query) {
            if (str_starts_with($query->sql, 'insert into "audit_events"')) {
                throw new \RuntimeException('Audit failure');
            }
        });
        try {
            $this->postJson($this->path('/'.$recipe['id'].'/restore'), [], ['If-Match' => '"2"']);
            $this->fail('Expected failure');
        } catch (\RuntimeException $error) {
            $this->assertSame('Audit failure', $error->getMessage());
        }
        $this->assertEquals($row, DB::connection('admin')->table('recipes')->first());
        $this->assertSame($revision, DB::connection('admin')->table('families')->where('id', DemoSeeder::POPESCU)->value('authz_revision'));
        $this->assertSame(1, DB::connection('admin')->table('recipe_versions')->count());
    }

    public function test_lists_with_recipients_have_constant_query_count_without_duplicate_rows(): void
    {
        $this->share($this->create(), 'selected', [$this->member()]);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->getJson($this->path())->assertJsonCount(1, 'items');
        $small = count(DB::getQueryLog());
        DB::disableQueryLog();
        for ($i = 0; $i < 3; $i++) {
            $this->share($this->create(), 'selected', [$this->member()]);
        }
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->getJson($this->path())->assertJsonCount(4, 'items');
        $this->assertSame($small, count(DB::getQueryLog()));
        DB::disableQueryLog();
    }
}
