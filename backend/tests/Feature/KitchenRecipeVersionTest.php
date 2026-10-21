<?php

namespace Tests\Feature;

use App\Models\User;
use App\Platform\Media\Scanner;
use Database\Seeders\DemoSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

final class KitchenRecipeVersionTest extends TestCase
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

    public function test_edit_appends_a_full_version_and_history_returns_both_without_mutating_the_original(): void
    {
        $original = $this->create();
        $oldRow = DB::connection('admin')->table('recipe_versions')->where('id', $original['recipe_version_id'])->first();
        $updated = $this->postJson($this->path('/'.$original['id'].'/versions'), $this->content(['title' => 'Paste cu roșii', 'ingredients' => ['200 g paste', '2 roșii']]) + ['photo_action' => 'keep'], ['If-Match' => '"1"'])
            ->assertCreated()->assertHeader('ETag', '"2"')->assertJsonPath('version', 2)->assertJsonPath('current_version', 2)->json();
        $this->assertNotSame($original['recipe_version_id'], $updated['recipe_version_id']);
        $this->assertEquals($oldRow, DB::connection('admin')->table('recipe_versions')->where('id', $original['recipe_version_id'])->first());
        $this->getJson($this->path('/'.$original['id']))->assertExactJson($updated);
        $this->getJson($this->path())->assertJsonPath('items.0.title', 'Paste cu roșii');
        $history = $this->getJson($this->path('/'.$original['id'].'/versions'))->assertOk()->assertJsonCount(2, 'items')->assertJsonPath('items.0.version', 2)->assertJsonPath('items.1.version', 1)->assertJsonPath('items.1.current_version', 2)->json();
        $this->getJson($this->path('/'.$original['id'].'/versions/'.$original['recipe_version_id']))->assertExactJson($history['items'][1]);
        $this->assertSame($original['ingredients'], $history['items'][1]['ingredients']);
        $this->assertMatchesRegularExpression('/T.*[+Z]/', $history['items'][1]['version_created_at']);
        foreach (['photo_asset_id', 'storage_key', 'family_id', 'sha256'] as $key) {
            $this->assertArrayNotHasKey($key, $history['items'][0]);
        }
        $audit = DB::connection('admin')->table('audit_events')->where('action', 'recipe.version_created')->first();
        $this->assertStringNotContainsString('roșii', json_encode($audit));
    }

    public function test_missing_or_stale_preconditions_cannot_write_versions_photos_or_audit(): void
    {
        $original = $this->create();
        $path = $this->path('/'.$original['id'].'/versions');
        $data = $this->content() + ['photo_action' => 'replace'];
        $before = Storage::disk('local')->allFiles();
        $this->postJson($path, $data + ['photo' => UploadedFile::fake()->image('no-precondition.png')])->assertStatus(428);
        $this->postJson($path, $this->content() + ['photo_action' => 'keep'], ['If-Match' => '"1"'])->assertCreated();
        $this->postJson($path, $data + ['photo' => UploadedFile::fake()->image('stale.png')], ['If-Match' => '"1"'])->assertStatus(412);
        $this->assertSame(2, DB::connection('admin')->table('recipe_versions')->where('recipe_id', $original['id'])->count());
        $this->assertSame(1, DB::connection('admin')->table('audit_events')->where('action', 'recipe.version_created')->count());
        $this->assertSame($before, Storage::disk('local')->allFiles());
    }

    public function test_only_the_active_author_with_an_adult_role_can_edit_and_history_has_canonical_acl(): void
    {
        $private = $this->actingAs($this->mihai)->create(['visibility' => 'private', 'photo' => UploadedFile::fake()->image('private.png')]);
        $shared = $this->create();
        $this->actingAs($this->ana);
        foreach (['/versions', '/versions/'.$private['recipe_version_id'], '/versions/'.$private['recipe_version_id'].'/photo'] as $suffix) {
            $this->getJson($this->path('/'.$private['id'].$suffix))->assertNotFound();
        }
        $this->postJson($this->path('/'.$private['id'].'/versions'), $this->content() + ['photo_action' => 'keep'], ['If-Match' => '"1"'])->assertNotFound();
        $this->postJson($this->path('/'.$shared['id'].'/versions'), $this->content() + ['photo_action' => 'keep'], ['If-Match' => '"1"'])->assertForbidden();
        $this->getJson($this->path('/'.$shared['id'].'/versions'))->assertOk();
        $this->getJson($this->path('/'.$shared['id'].'/versions/'.$private['recipe_version_id']))->assertNotFound();
        $this->getJson($this->path('/'.$shared['id'].'/versions/'.$private['recipe_version_id'].'/photo'))->assertNotFound();
        $this->getJson($this->path('/'.$shared['id'].'/versions', DemoSeeder::IONESCU))->assertNotFound();
        DB::connection('admin')->table('family_memberships')->where('family_id', DemoSeeder::POPESCU)->where('user_id', $this->mihai->id)->update(['role' => 'limited']);
        $this->actingAs($this->mihai)->postJson($this->path('/'.$private['id'].'/versions'), $this->content() + ['photo_action' => 'keep'], ['If-Match' => '"1"'])->assertForbidden();
        $this->getJson($this->path('/'.$private['id'].'/versions'))->assertOk();
        DB::connection('admin')->table('family_memberships')->where('family_id', DemoSeeder::POPESCU)->where('user_id', $this->mihai->id)->update(['revoked_at' => now()]);
        foreach (['/versions', '/versions/'.$private['recipe_version_id'], '/versions/'.$private['recipe_version_id'].'/photo'] as $suffix) {
            $this->getJson($this->path('/'.$private['id'].$suffix))->assertNotFound();
        }
        $this->postJson($this->path('/'.$private['id'].'/versions'), $this->content() + ['photo_action' => 'keep'], ['If-Match' => '"1"'])->assertNotFound();
    }

    public function test_photo_keep_replace_and_remove_preserve_all_older_version_bytes(): void
    {
        $original = $this->create(['photo' => UploadedFile::fake()->image('original.png', 40, 30)]);
        $photoPath = '/'.$original['id'].'/versions/'.$original['recipe_version_id'].'/photo';
        $bytes = $this->get($this->path($photoPath))->assertOk()->streamedContent();
        $path = $this->path('/'.$original['id'].'/versions');
        $kept = $this->postJson($path, $this->content() + ['photo_action' => 'keep'], ['If-Match' => '"1"'])->assertCreated()->json();
        $replaced = $this->postJson($path, $this->content() + ['photo_action' => 'replace', 'photo' => UploadedFile::fake()->image('replacement.png', 60, 50)], ['If-Match' => '"2"'])->assertCreated()->json();
        $this->assertNotSame($bytes, $this->get($this->path('/'.$original['id'].'/photo'))->assertOk()->streamedContent());
        $this->postJson($path, $this->content() + ['photo_action' => 'remove'], ['If-Match' => '"3"'])->assertCreated()->assertJsonPath('photo_status', null);
        $this->getJson($this->path('/'.$original['id'].'/photo'))->assertNotFound();
        $this->assertSame($bytes, $this->get($this->path($photoPath))->assertOk()->streamedContent());
        $this->assertSame($bytes, $this->get($this->path('/'.$original['id'].'/versions/'.$kept['recipe_version_id'].'/photo'))->assertOk()->streamedContent());
        $this->get($this->path('/'.$original['id'].'/versions/'.$replaced['recipe_version_id'].'/photo'))->assertOk();
    }

    public function test_existing_meal_and_votes_keep_the_exact_recipe_and_photo_after_edit(): void
    {
        $original = $this->create(['photo' => UploadedFile::fake()->image('original.png')]);
        $meals = '/api/v1/families/'.DemoSeeder::POPESCU.'/meals';
        $meal = $this->postJson($meals, ['recipe_id' => $original['id'], 'recipe_version_id' => $original['recipe_version_id'], 'meal_date' => '2026-01-01', 'meal_type' => 'lunch', 'visibility' => 'family'])->assertCreated()->json();
        $this->putJson($meals.'/'.$meal['id'].'/vote', ['liked' => true, 'comment' => 'Bună așa.'], ['If-Match' => '"0"'])->assertOk();
        $bytes = $this->get($meals.'/'.$meal['id'].'/photo')->assertOk()->streamedContent();
        $this->postJson($this->path('/'.$original['id'].'/versions'), $this->content(['title' => 'Alt titlu', 'ingredients' => ['1 alt ingredient'], 'instructions' => 'Altă preparare.']) + ['photo_action' => 'remove'], ['If-Match' => '"1"'])->assertCreated();
        $this->getJson($meals.'/'.$meal['id'])->assertOk()->assertJsonPath('dish_title', $original['title'])->assertJsonPath('liked_count', 1)->assertJsonPath('my_vote.comment', 'Bună așa.')->assertJsonPath('recipe_available', true);
        $this->getJson($meals.'/'.$meal['id'].'/recipe')->assertOk()->assertJsonPath('version', 1)->assertJsonPath('current_version', 2)->assertJsonPath('ingredients', $original['ingredients'])->assertJsonPath('instructions', $original['instructions']);
        $this->assertSame($bytes, $this->get($meals.'/'.$meal['id'].'/photo')->assertOk()->streamedContent());
        $this->postJson($meals, ['recipe_id' => $original['id'], 'recipe_version_id' => $original['recipe_version_id'], 'meal_date' => '2026-01-01', 'meal_type' => 'dinner', 'visibility' => 'family'])->assertConflict();
    }

    public function test_sharing_a_meal_does_not_open_its_private_used_recipe_or_history(): void
    {
        $recipe = $this->create(['visibility' => 'private']);
        $base = '/api/v1/families/'.DemoSeeder::POPESCU.'/meals';
        $meal = $this->postJson($base, ['recipe_id' => $recipe['id'], 'recipe_version_id' => $recipe['recipe_version_id'], 'meal_date' => '2026-01-01', 'meal_type' => 'lunch', 'visibility' => 'family'])->assertCreated()->json();
        $this->actingAs($this->mihai)->getJson($base.'/'.$meal['id'])->assertOk()->assertJsonPath('recipe_available', false);
        $this->getJson($base.'/'.$meal['id'].'/recipe')->assertNotFound();
        $this->getJson($this->path('/'.$recipe['id'].'/versions'))->assertNotFound();
    }

    public function test_invalid_edits_cannot_inject_identity_access_or_photo_references(): void
    {
        $recipe = $this->create();
        foreach ([['visibility' => 'family'], ['owner_id' => $this->mihai->id], ['family_id' => DemoSeeder::IONESCU], ['version' => 10], ['recipe_version_id' => $recipe['recipe_version_id']], ['photo_asset_id' => (string) Str::uuid()], ['is_example' => true], ['ingredients' => []], ['photo_action' => 'replace'], ['photo_action' => 'unknown'], ['photo_action' => 'remove', 'photo' => UploadedFile::fake()->image('unexpected.png')], ['photo_action' => 'replace', 'photo' => UploadedFile::fake()->createWithContent('unsafe.jpg', DemoSeeder::samplePdf())]] as $changes) {
            $this->postJson($this->path('/'.$recipe['id'].'/versions'), array_replace($this->content() + ['photo_action' => 'keep'], $changes), ['If-Match' => '"1"'])->assertUnprocessable();
        }
        $this->assertSame(1, DB::connection('admin')->table('recipe_versions')->count());
        $this->getJson($this->path('/'.$recipe['id'].'/versions?page=0'))->assertUnprocessable();
    }

    public function test_history_is_bounded_and_uses_a_constant_query_count(): void
    {
        $recipe = $this->create();
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->getJson($this->path('/'.$recipe['id'].'/versions'))->assertOk();
        $small = count(DB::getQueryLog());
        DB::disableQueryLog();
        $version = (array) DB::connection('admin')->table('recipe_versions')->where('recipe_id', $recipe['id'])->first();
        for ($i = 2; $i <= 14; $i++) {
            DB::connection('admin')->table('recipe_versions')->insert(array_replace($version, ['id' => (string) Str::uuid(), 'version_no' => $i]));
        }
        DB::connection('admin')->table('recipes')->where('id', $recipe['id'])->update(['version' => 14]);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $first = $this->getJson($this->path('/'.$recipe['id'].'/versions'))->assertJsonCount(12, 'items')->assertJsonPath('has_more', true)->assertJsonPath('items.0.version', 14)->json('items');
        $this->assertSame($small, count(DB::getQueryLog()));
        DB::disableQueryLog();
        $second = $this->getJson($this->path('/'.$recipe['id'].'/versions?page=2'))->assertJsonCount(2, 'items')->assertJsonPath('has_more', false)->json('items');
        $this->assertEmpty(array_intersect(array_column($first, 'recipe_version_id'), array_column($second, 'recipe_version_id')));
    }

    public function test_audit_failure_rolls_back_the_pointer_version_and_new_file(): void
    {
        $recipe = $this->create(['photo' => UploadedFile::fake()->image('old.png')]);
        $before = Storage::disk('local')->allFiles();
        $this->withoutExceptionHandling();
        DB::listen(function ($query) {
            if (str_starts_with($query->sql, 'insert into "audit_events"')) {
                throw new \RuntimeException('Simulated audit failure');
            }
        });
        try {
            $this->postJson($this->path('/'.$recipe['id'].'/versions'), $this->content() + ['photo_action' => 'replace', 'photo' => UploadedFile::fake()->image('new.png')], ['If-Match' => '"1"']);
            $this->fail('Expected the write to fail.');
        } catch (\RuntimeException $error) {
            $this->assertSame('Simulated audit failure', $error->getMessage());
        }
        $this->assertSame(1, DB::connection('admin')->table('recipes')->where('id', $recipe['id'])->value('version'));
        $this->assertSame(1, DB::connection('admin')->table('recipe_versions')->count());
        $this->assertEqualsCanonicalizing($before, Storage::disk('local')->allFiles());
        $this->get($this->path('/'.$recipe['id'].'/photo'))->assertOk();
    }
}
