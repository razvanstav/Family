<?php

namespace Tests\Feature;

use App\Models\User;
use App\Platform\Media\Scanner;
use Database\Seeders\DemoSeeder;
use Database\Seeders\KitchenDemoSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

final class KitchenRecipeTest extends TestCase
{
    private User $ana;

    private User $mihai;

    private string $scanResult = 'clean';

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
        $this->scanner('clean');
    }

    protected function tearDown(): void
    {
        DB::disconnect('pgsql');
        DB::disconnect('admin');
        parent::tearDown();
    }

    private function scanner(string $result): void
    {
        $this->scanResult = $result;
        $this->app->instance(Scanner::class, new class(fn () => $this->scanResult) implements Scanner
        {
            public function __construct(private \Closure $result) {}

            public function scan(string $path): string
            {
                return ($this->result)();
            }
        });
    }

    private function path(string $suffix = '', string $family = DemoSeeder::POPESCU): string
    {
        return '/api/v1/families/'.$family.'/recipes'.$suffix;
    }

    private function recipe(array $changes = []): array
    {
        return array_replace(['title' => 'Paste cu ciuperci', 'category' => 'main', 'ingredients' => ['200 g paste', '300 g ciuperci'],
            'instructions' => "Fierbe pastele.\nAdaugă ciupercile.", 'duration_minutes' => 30, 'servings' => 2, 'difficulty' => 'easy', 'visibility' => 'private'], $changes);
    }

    public function test_creation_saves_initial_version_and_returns_complete_safe_payload(): void
    {
        $response = $this->actingAs($this->ana)->postJson($this->path(), $this->recipe())->assertCreated()
            ->assertJsonPath('owner_id', $this->ana->id)->assertJsonPath('owner_name', $this->ana->name)
            ->assertJsonPath('version', 1)->assertJsonPath('visibility', 'private')->assertJsonPath('is_example', false)
            ->assertJsonPath('ingredients', ['200 g paste', '300 g ciuperci'])->assertJsonPath('photo_status', null);
        $id = $response->json('id');
        $this->getJson($this->path('/'.$id))->assertOk()->assertHeader('ETag', '"1"')->assertExactJson($response->json());
        $list = $this->getJson($this->path())->assertOk()->assertJsonCount(1, 'items')->assertJsonPath('has_more', false);
        $this->assertSame($response->json(), $list->json('items.0'));
        foreach (['family_id', 'storage_key', 'photo_asset_id', 'bytes', 'sha256'] as $key) {
            $this->assertArrayNotHasKey($key, $list->json('items.0'));
        }
        $this->assertStringContainsString('no-store', $list->headers->get('Cache-Control'));
        $this->assertSame(1, DB::connection('admin')->table('recipe_versions')->where('recipe_id', $id)->count());
        $this->assertSame(1, DB::connection('admin')->table('audit_events')->where('resource_id', $id)->where('action', 'recipe.created')->count());
    }

    public function test_private_recipes_and_photos_are_hidden_even_from_family_owner(): void
    {
        $id = $this->actingAs($this->mihai)->postJson($this->path(), $this->recipe(['photo' => UploadedFile::fake()->image('ciuperci.png')]))->assertCreated()->json('id');
        $this->get($this->path('/'.$id.'/photo'))->assertOk();
        $this->actingAs($this->ana)->getJson($this->path())->assertOk()->assertJsonCount(0, 'items');
        $this->getJson($this->path('?q=Paste'))->assertJsonCount(0, 'items');
        $this->getJson($this->path('/'.$id))->assertNotFound();
        $this->getJson($this->path('/'.$id.'/photo'))->assertNotFound();
        $this->postJson($this->path('/'.$id.'/photo/scan'))->assertNotFound();
    }

    public function test_family_recipes_are_visible_to_limited_members_but_creation_and_scan_are_authorized(): void
    {
        $id = $this->actingAs($this->ana)->postJson($this->path(), $this->recipe(['visibility' => 'family', 'photo' => UploadedFile::fake()->image('masa.jpg')]))->assertCreated()->json('id');
        DB::connection('admin')->table('family_memberships')->where('family_id', DemoSeeder::POPESCU)->where('user_id', $this->mihai->id)->update(['role' => 'limited']);
        $this->actingAs($this->mihai)->getJson($this->path())->assertOk()->assertJsonCount(1, 'items');
        $this->getJson($this->path('/'.$id))->assertOk();
        $this->get($this->path('/'.$id.'/photo'))->assertOk();
        $this->postJson($this->path(), $this->recipe())->assertForbidden();
        $this->postJson($this->path('/'.$id.'/photo/scan'))->assertForbidden();
    }

    public function test_auth_family_isolation_and_revocation_apply_to_each_surface(): void
    {
        $this->getJson($this->path())->assertUnauthorized();
        $id = $this->actingAs($this->ana)->postJson($this->path(), $this->recipe(['visibility' => 'family', 'photo' => UploadedFile::fake()->image('masa.png')]))->assertCreated()->json('id');
        $this->getJson($this->path('', DemoSeeder::IONESCU))->assertOk()->assertJsonCount(0, 'items');
        $this->getJson($this->path('/'.$id, DemoSeeder::IONESCU))->assertNotFound();
        $this->getJson($this->path('/'.$id.'/photo', DemoSeeder::IONESCU))->assertNotFound();
        $this->actingAs($this->mihai)->getJson($this->path('', DemoSeeder::IONESCU))->assertNotFound();
        $this->getJson($this->path('/'.$id))->assertOk();
        DB::connection('admin')->table('family_memberships')->where('family_id', DemoSeeder::POPESCU)->where('user_id', $this->mihai->id)->update(['revoked_at' => now()]);
        $this->getJson($this->path())->assertNotFound();
        $this->getJson($this->path('/'.$id))->assertNotFound();
        $this->getJson($this->path('/'.$id.'/photo'))->assertNotFound();
        $this->postJson($this->path('/'.$id.'/photo/scan'))->assertNotFound();
        $this->postJson($this->path(), $this->recipe())->assertNotFound();
    }

    public function test_validation_rejects_identity_injection_and_invalid_recipe_values_without_partial_writes(): void
    {
        $this->actingAs($this->ana);
        foreach ([
            ['owner_id' => $this->mihai->id], ['family_id' => DemoSeeder::IONESCU], ['version' => 7],
            ['photo_asset_id' => '11111111-1111-4111-8111-111111111111'], ['is_example' => true],
            ['visibility' => 'selected'], ['ingredients' => []], ['ingredients' => [str_repeat('a', 201)]],
            ['instructions' => ' '], ['title' => ' '], ['duration_minutes' => 0], ['servings' => 51], ['category' => 'unknown'],
        ] as $changes) {
            $this->postJson($this->path(), $this->recipe($changes))->assertUnprocessable();
        }
        $this->assertSame(0, DB::connection('admin')->table('recipes')->count());
        $this->assertSame(0, DB::connection('admin')->table('recipe_versions')->count());
        $this->getJson($this->path('?page=0'))->assertUnprocessable();
        $this->getJson($this->path('?category=unknown'))->assertUnprocessable();
        $this->getJson($this->path('/not-an-id'))->assertNotFound();
    }

    public function test_search_filters_and_pagination_are_authorized_and_query_count_is_constant(): void
    {
        $this->actingAs($this->ana);
        $this->postJson($this->path(), $this->recipe(['title' => 'Mere 100% naturale', 'category' => 'dessert', 'visibility' => 'family']))->assertCreated();
        $this->getJson($this->path('?q=%25&category=dessert&visibility=family'))->assertJsonCount(1, 'items');
        $this->getJson($this->path('?q=_'))->assertJsonCount(0, 'items');
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->getJson($this->path())->assertOk();
        $small = count(DB::getQueryLog());
        DB::disableQueryLog();
        for ($index = 0; $index < 12; $index++) {
            $this->postJson($this->path(), $this->recipe(['title' => 'Rețeta '.$index]))->assertCreated();
        }
        DB::enableQueryLog();
        DB::flushQueryLog();
        $first = $this->getJson($this->path())->assertOk()->assertJsonCount(12, 'items')->assertJsonPath('has_more', true);
        $this->assertSame($small, count(DB::getQueryLog()));
        DB::disableQueryLog();
        $second = $this->getJson($this->path('?page=2'))->assertJsonCount(1, 'items')->assertJsonPath('has_more', false);
        $this->assertEmpty(array_intersect(array_column($first->json('items'), 'id'), array_column($second->json('items'), 'id')));
        $this->getJson($this->path('?visibility=private'))->assertJsonCount(12, 'items');
        $this->getJson($this->path('?category=soup'))->assertJsonCount(0, 'items');
    }

    public function test_photos_are_normalized_and_served_with_private_headers_without_storage_identifiers(): void
    {
        $photo = UploadedFile::fake()->image('imagine.png', 40, 30);
        $original = file_get_contents($photo->getRealPath()).'PRIVATE_TRAILING_METADATA';
        $id = $this->actingAs($this->ana)->postJson($this->path(), $this->recipe(['photo' => UploadedFile::fake()->createWithContent('imagine.png', $original)]))
            ->assertCreated()->assertJsonPath('photo_status', 'ready')->json('id');
        $response = $this->get($this->path('/'.$id.'/photo'))->assertOk()->assertHeader('Content-Type', 'image/png')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringStartsWith('inline;', $response->headers->get('Content-Disposition'));
        $bytes = $response->streamedContent();
        $this->assertStringNotContainsString('PRIVATE_TRAILING_METADATA', $bytes);
        $this->assertSame([40, 30], array_slice(getimagesizefromstring($bytes), 0, 2));
        $this->assertSame(0, DB::connection('admin')->table('documents')->where('title', 'Paste cu ciuperci')->count());
    }

    public function test_unsafe_formats_and_large_photos_are_rejected_and_unscanned_bytes_stay_hidden(): void
    {
        $this->actingAs($this->ana);
        foreach ([
            UploadedFile::fake()->createWithContent('poza.png', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
            UploadedFile::fake()->createWithContent('poza.jpg', DemoSeeder::samplePdf()),
            UploadedFile::fake()->create('mare.png', 10241, 'image/png'),
        ] as $photo) {
            $this->postJson($this->path(), $this->recipe(['photo' => $photo]))->assertUnprocessable();
        }
        $this->assertSame(0, DB::connection('admin')->table('recipes')->count());
        $this->scanner('unavailable');
        $id = $this->postJson($this->path(), $this->recipe(['photo' => UploadedFile::fake()->image('masa.png')]))->assertCreated()->assertJsonPath('photo_status', 'quarantine')->json('id');
        $this->getJson($this->path('/'.$id.'/photo'))->assertConflict();
        $this->scanner('clean');
        $this->postJson($this->path('/'.$id.'/photo/scan'))->assertOk()->assertJsonPath('photo_status', 'ready');
        $this->get($this->path('/'.$id.'/photo'))->assertOk();
        $this->scanner('infected');
        $bad = $this->postJson($this->path(), $this->recipe(['photo' => UploadedFile::fake()->image('respinsa.png')]))->assertCreated()->assertJsonPath('photo_status', 'rejected')->json('id');
        $this->getJson($this->path('/'.$bad.'/photo'))->assertConflict();
        $asset = DB::connection('admin')->table('recipe_versions')->where('recipe_id', $bad)->value('photo_asset_id');
        $key = DB::connection('admin')->table('media_assets')->where('id', $asset)->value('storage_key');
        Storage::disk('local')->assertMissing($key);
    }

    public function test_failed_write_rolls_back_recipe_version_audit_and_private_file(): void
    {
        $before = Storage::disk('local')->allFiles();
        $assets = DB::connection('admin')->table('media_assets')->count();
        $this->withoutExceptionHandling();
        DB::listen(function ($query) {
            if (str_starts_with($query->sql, 'insert into "audit_events"')) {
                throw new \RuntimeException('Simulated audit failure');
            }
        });
        try {
            $this->actingAs($this->ana)->postJson($this->path(), $this->recipe(['photo' => UploadedFile::fake()->image('rollback.png')]));
            $this->fail('Expected the write to fail.');
        } catch (\RuntimeException $error) {
            $this->assertSame('Simulated audit failure', $error->getMessage());
        }
        $this->assertSame(0, DB::connection('admin')->table('recipes')->count());
        $this->assertSame(0, DB::connection('admin')->table('recipe_versions')->count());
        $this->assertSame($assets, DB::connection('admin')->table('media_assets')->count());
        $this->assertEqualsCanonicalizing($before, Storage::disk('local')->allFiles());
    }

    public function test_rls_covers_recipes_and_versions_and_cross_family_foreign_keys_are_enforced(): void
    {
        $id = $this->actingAs($this->ana)->postJson($this->path(), $this->recipe())->assertCreated()->json('id');
        $this->assertSame(0, DB::table('recipes')->count());
        $this->assertSame(0, DB::table('recipe_versions')->count());
        DB::transaction(function () {
            DB::select("SELECT set_config('app.family_id', ?, true)", [DemoSeeder::IONESCU]);
            $this->assertSame(0, DB::table('recipes')->count());
            $this->assertSame(0, DB::table('recipe_versions')->count());
        });
        $version = (array) DB::connection('admin')->table('recipe_versions')->where('recipe_id', $id)->first();
        $version['id'] = (string) Str::uuid();
        $version['family_id'] = DemoSeeder::IONESCU;
        $this->expectException(QueryException::class);
        DB::connection('admin')->table('recipe_versions')->insert($version);
    }

    public function test_demo_examples_are_explicit_idempotent_and_separate_from_documents(): void
    {
        $documents = DB::connection('admin')->table('documents')->count();
        DB::setDefaultConnection('admin');
        (new KitchenDemoSeeder)->run();
        (new KitchenDemoSeeder)->run();
        DB::setDefaultConnection('pgsql');
        $this->actingAs($this->ana)->getJson($this->path())->assertOk()->assertJsonCount(3, 'items')->assertJsonPath('items.0.is_example', true)->assertJsonPath('items.0.photo_status', 'ready');
        $this->assertSame(3, DB::connection('admin')->table('recipes')->count());
        $this->assertSame($documents, DB::connection('admin')->table('documents')->count());
        $this->getJson($this->path('', DemoSeeder::IONESCU))->assertJsonCount(0, 'items');
    }
}
