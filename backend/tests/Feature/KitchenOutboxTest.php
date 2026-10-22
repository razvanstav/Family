<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Kitchen\Application\KitchenActivity;
use App\Platform\Contracts\EventHandler;
use App\Platform\Contracts\StoredEvent;
use App\Platform\Events\DatabaseOutbox;
use App\Platform\Media\Scanner;
use Database\Seeders\DemoSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

final class KitchenOutboxTest extends TestCase
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
        $this->travelTo(now()->startOfSecond());
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

    private function outbox(): DatabaseOutbox
    {
        return $this->app->make(DatabaseOutbox::class);
    }

    private function activity(string $query = '', string $family = DemoSeeder::POPESCU): string
    {
        return '/api/v1/families/'.$family.'/kitchen-activity'.$query;
    }

    private function meal(array $recipe): string
    {
        $base = '/api/v1/families/'.DemoSeeder::POPESCU.'/meals';

        return $base.'/'.$this->postJson($base, ['recipe_id' => $recipe['id'], 'recipe_version_id' => $recipe['recipe_version_id'],
            'meal_date' => '2026-01-01', 'meal_type' => 'lunch', 'visibility' => 'family'])->assertCreated()->json('id');
    }

    public function test_source_audit_event_and_deliveries_commit_together_and_the_source_is_immediately_available(): void
    {
        $recipe = $this->create(['visibility' => 'private']);
        $meal = $this->meal($recipe);
        $this->putJson($meal.'/vote', ['liked' => true, 'comment' => 'Comentariu privat'], ['If-Match' => '"0"'])->assertOk();
        $this->getJson($meal)->assertOk()->assertJsonPath('liked_count', 1)->assertJsonPath('version', 1);
        $events = DB::connection('admin')->table('outbox_events')->get();
        $this->assertCount(3, $events);
        $this->assertSame([1, 2], $events->where('source_kind', 'kitchen.meal')->sortBy('source_version')->pluck('source_version')->all());
        $this->assertStringNotContainsString($recipe['title'], json_encode($events));
        $this->assertStringNotContainsString('Comentariu privat', json_encode($events));
        $this->assertSame(5, DB::connection('admin')->table('outbox_deliveries')->whereNull('delivered_at')->count());
        $this->getJson($this->activity())->assertJsonCount(0, 'items');
        $this->assertSame(['delivered' => 5, 'failed' => 0], $this->outbox()->deliverFamily(DemoSeeder::POPESCU));
        $items = $this->getJson($this->activity())->assertOk()->assertJsonCount(2, 'items')->json('items');
        $this->assertEqualsCanonicalizing(['kitchen.meal', 'kitchen.recipe'], array_column($items, 'kind'));
        $this->assertSame(2, DB::connection('admin')->table('kitchen_activity_sources')->count());
        $this->assertSame(0, $this->outbox()->deliverFamily(DemoSeeder::POPESCU)['delivered']);
        $this->assertSame(1, DB::connection('admin')->table('kitchen_meals')->count());
    }

    public function test_append_requires_the_correct_tenant_transaction_and_is_idempotent_for_a_source_version(): void
    {
        $recipe = $this->create();
        $event = DB::connection('admin')->table('outbox_events')->value('id');
        $same = $this->outbox()->inFamily(DemoSeeder::POPESCU, fn () => $this->outbox()->append(DemoSeeder::POPESCU, 'kitchen.recipe.changed', 'kitchen.recipe', $recipe['id'], 1));
        $this->assertSame($event, $same);
        foreach ([fn () => $this->outbox()->append(DemoSeeder::POPESCU, 'kitchen.recipe.changed', 'kitchen.recipe', $recipe['id'], 1),
            fn () => $this->outbox()->inFamily(DemoSeeder::IONESCU, fn () => $this->outbox()->append(DemoSeeder::POPESCU, 'kitchen.recipe.changed', 'kitchen.recipe', $recipe['id'], 1))] as $invalid) {
            try {
                $invalid();
                $this->fail('Expected a tenant guard failure.');
            } catch (\LogicException) {
                $this->assertTrue(true);
            }
        }
        $this->assertSame(1, DB::connection('admin')->table('outbox_events')->count());
        $this->assertSame(1, DB::connection('admin')->table('outbox_deliveries')->count());
    }

    public function test_outbox_failure_rolls_back_content_access_archive_meal_vote_audit_and_new_photo(): void
    {
        $recipe = $this->create(['photo' => UploadedFile::fake()->image('old.png')]);
        $meal = $this->meal($recipe);
        $beforeRecipe = DB::connection('admin')->table('recipes')->first();
        $beforeMeal = DB::connection('admin')->table('kitchen_meals')->first();
        $beforeAssets = DB::connection('admin')->table('media_assets')->count();
        $beforeFiles = Storage::disk('local')->allFiles();
        $audit = DB::connection('admin')->table('audit_events')->count();
        $this->withoutExceptionHandling();
        DB::listen(function ($query) {
            if (str_starts_with($query->sql, 'insert into "outbox_deliveries"')) {
                throw new \RuntimeException('Outbox failure');
            }
        });
        $requests = [
            ['POST', $this->path('/'.$recipe['id'].'/versions'), $this->content() + ['photo_action' => 'replace', 'photo' => UploadedFile::fake()->image('new.png')]],
            ['PUT', $this->path('/'.$recipe['id'].'/sharing'), ['visibility' => 'private', 'membership_ids' => []]],
            ['POST', $this->path('/'.$recipe['id'].'/archive'), []],
            ['PUT', $meal.'/vote', ['liked' => true, 'comment' => 'Nu rămâne în DB']],
            ['POST', $this->path(), $this->content() + ['visibility' => 'private', 'photo' => UploadedFile::fake()->image('new-recipe.png')]],
            ['POST', '/api/v1/families/'.DemoSeeder::POPESCU.'/meals', ['recipe_id' => $recipe['id'], 'recipe_version_id' => $recipe['recipe_version_id'],
                'meal_date' => '2026-01-01', 'meal_type' => 'dinner', 'visibility' => 'family']],
        ];
        foreach ($requests as [$method, $path, $data]) {
            try {
                $this->json($method, $path, $data, ['If-Match' => str_ends_with($path, '/vote') ? '"0"' : '"1"']);
                $this->fail('Expected rollback.');
            } catch (\RuntimeException $error) {
                $this->assertSame('Outbox failure', $error->getMessage());
            }
            $this->assertEquals($beforeRecipe, DB::connection('admin')->table('recipes')->first());
            $this->assertEquals($beforeMeal, DB::connection('admin')->table('kitchen_meals')->first());
            $this->assertSame(1, DB::connection('admin')->table('recipe_versions')->count());
            $this->assertSame(0, DB::connection('admin')->table('kitchen_meal_votes')->count());
            $this->assertSame(2, DB::connection('admin')->table('outbox_events')->count());
            $this->assertSame(3, DB::connection('admin')->table('outbox_deliveries')->count());
            $this->assertSame($audit, DB::connection('admin')->table('audit_events')->count());
            $this->assertSame($beforeAssets, DB::connection('admin')->table('media_assets')->count());
            $this->assertEqualsCanonicalizing($beforeFiles, Storage::disk('local')->allFiles());
        }
    }

    public function test_a_failure_after_projection_before_receipt_rolls_back_and_retries_without_duplicates(): void
    {
        $this->create();
        $fail = true;
        DB::listen(function ($query) use (&$fail) {
            if ($fail && str_starts_with($query->sql, 'update "outbox_deliveries"') && str_contains($query->sql, '"delivered_at"')) {
                throw new \RuntimeException('Private details must not be logged');
            }
        });
        $this->assertSame(['delivered' => 0, 'failed' => 1], $this->outbox()->deliverFamily(DemoSeeder::POPESCU));
        $this->assertSame(0, DB::connection('admin')->table('kitchen_activity_sources')->count());
        $receipt = DB::connection('admin')->table('outbox_deliveries')->first();
        $this->assertNull($receipt->delivered_at);
        $this->assertSame(1, $receipt->attempts);
        $this->assertSame('handler_failed', $receipt->last_error);
        $fail = false;
        $this->assertSame(['delivered' => 0, 'failed' => 0], $this->outbox()->deliverFamily(DemoSeeder::POPESCU));
        $this->travel(31)->seconds();
        $this->assertSame(['delivered' => 1, 'failed' => 0], $this->outbox()->deliverFamily(DemoSeeder::POPESCU));
        $this->assertSame(1, DB::connection('admin')->table('kitchen_activity_sources')->count());
        $receipt = DB::connection('admin')->table('outbox_deliveries')->first();
        $this->assertNotNull($receipt->delivered_at);
        $this->assertSame(2, $receipt->attempts);
        $this->assertNull($receipt->last_error);
    }

    public function test_each_handler_has_an_independent_atomic_receipt_and_replay_can_register_a_new_handler(): void
    {
        $this->create();
        $handler = new class implements EventHandler
        {
            public bool $fail = true;

            public function name(): string
            {
                return 'probe.v1';
            }

            public function events(): array
            {
                return ['kitchen.recipe.changed'];
            }

            public function handle(StoredEvent $event): void
            {
                if ($this->fail) {
                    throw new \RuntimeException('Failure');
                }
            }
        };
        $worker = new DatabaseOutbox([$this->app->make(KitchenActivity::class), $handler]);
        $this->assertSame(1, $worker->replayFamily(DemoSeeder::POPESCU, 'probe.v1'));
        $this->assertSame(['delivered' => 1, 'failed' => 1], $worker->deliverFamily(DemoSeeder::POPESCU));
        $this->assertSame(1, DB::connection('admin')->table('outbox_deliveries')->whereNotNull('delivered_at')->count());
        $this->assertSame(1, DB::connection('admin')->table('kitchen_activity_sources')->count());
        $handler->fail = false;
        $this->travel(31)->seconds();
        $this->assertSame(['delivered' => 1, 'failed' => 0], $worker->deliverFamily(DemoSeeder::POPESCU));
        $this->assertSame(2, DB::connection('admin')->table('outbox_deliveries')->whereNotNull('delivered_at')->count());
        $this->assertSame(1, DB::connection('admin')->table('outbox_deliveries')->where('handler', 'kitchen.activity.v1')->value('attempts'));
    }

    public function test_reordered_and_replayed_events_reconcile_the_current_source_without_resurrecting_an_archive(): void
    {
        $recipe = $this->create();
        $this->putJson($this->path('/'.$recipe['id'].'/sharing'), ['visibility' => 'private', 'membership_ids' => []], ['If-Match' => '"1"'])->assertOk();
        $this->postJson($this->path('/'.$recipe['id'].'/archive'), [], ['If-Match' => '"2"'])->assertOk();
        $this->postJson($this->path('/'.$recipe['id'].'/restore'), [], ['If-Match' => '"3"'])->assertOk();
        $newest = DB::connection('admin')->table('outbox_events')->where('source_version', 4)->value('id');
        DB::connection('admin')->table('outbox_deliveries')->where('event_id', '!=', $newest)->update(['available_at' => now()->addHour()]);
        $this->assertSame(1, $this->outbox()->deliverFamily(DemoSeeder::POPESCU)['delivered']);
        $this->assertSame(4, DB::connection('admin')->table('kitchen_activity_sources')->value('source_version'));
        $this->assertSame(4, $this->outbox()->replayFamily(DemoSeeder::POPESCU, 'kitchen.activity.v1'));
        $this->assertSame(4, $this->outbox()->deliverFamily(DemoSeeder::POPESCU)['delivered']);
        $this->getJson($this->activity())->assertJsonCount(1, 'items');
        $this->postJson($this->path('/'.$recipe['id'].'/archive'), [], ['If-Match' => '"4"'])->assertOk();
        $this->outbox()->replayFamily(DemoSeeder::POPESCU, 'kitchen.activity.v1');
        $this->assertSame(5, $this->outbox()->deliverFamily(DemoSeeder::POPESCU)['delivered']);
        $row = DB::connection('admin')->table('kitchen_activity_sources')->first();
        $this->assertSame(5, $row->source_version);
        $this->assertTrue($row->removed);
        $this->getJson($this->activity())->assertJsonCount(0, 'items');
        $this->assertSame(1, DB::connection('admin')->table('recipe_versions')->count());
    }

    public function test_current_policy_blocks_stale_cards_before_delivery_and_a_meal_never_opens_a_private_recipe(): void
    {
        $recipe = $this->create(['photo' => UploadedFile::fake()->image('photo.png')]);
        $meal = $this->meal($recipe);
        $this->outbox()->deliverFamily(DemoSeeder::POPESCU);
        $this->actingAs($this->mihai)->getJson($this->activity())->assertJsonCount(2, 'items');
        $this->actingAs($this->ana)->putJson($this->path('/'.$recipe['id'].'/sharing'), ['visibility' => 'private', 'membership_ids' => []], ['If-Match' => '"1"'])->assertOk();
        $items = $this->actingAs($this->mihai)->getJson($this->activity())->assertJsonCount(1, 'items')->assertJsonPath('items.0.kind', 'kitchen.meal')->assertJsonPath('items.0.photo_status', null)->assertJsonPath('items.0.recipe_available', false)->json('items');
        foreach (['ingredients', 'instructions', 'recipe_version_id', 'photo_asset_id', 'grants'] as $key) {
            $this->assertArrayNotHasKey($key, $items[0]);
        }
        $this->getJson($meal.'/recipe')->assertNotFound();
        $member = DB::table('family_memberships')->where('family_id', DemoSeeder::POPESCU)->where('user_id', $this->mihai->id)->value('id');
        $this->actingAs($this->ana)->deleteJson('/api/v1/families/'.DemoSeeder::POPESCU.'/members/'.$member)->assertNoContent();
        $this->outbox()->deliverFamily(DemoSeeder::POPESCU);
        $this->actingAs($this->mihai)->getJson($this->activity())->assertNotFound();
        $this->actingAs($this->ana)->getJson($this->activity('', DemoSeeder::IONESCU))->assertJsonCount(0, 'items');
    }

    public function test_sparse_pages_use_an_encrypted_last_scanned_cursor_and_do_not_expose_inaccessible_source_ids(): void
    {
        $recipe = $this->create();
        $this->outbox()->deliverFamily(DemoSeeder::POPESCU);
        $hidden = [];
        for ($i = 0; $i < 201; $i++) {
            $hidden[] = ['family_id' => DemoSeeder::POPESCU, 'source_kind' => 'kitchen.recipe', 'source_id' => (string) Str::uuid(), 'source_version' => 1, 'occurred_at' => now()->addHour(), 'removed' => false];
        }
        DB::connection('admin')->table('kitchen_activity_sources')->insert($hidden);
        $first = $this->actingAs($this->mihai)->getJson($this->activity('?limit=1'))->assertJsonCount(0, 'items')->assertJsonPath('has_more', true)->json();
        $this->assertStringNotContainsString($hidden[0]['source_id'], $first['next_cursor']);
        $second = $this->getJson($this->activity('?limit=1&cursor='.urlencode($first['next_cursor'])))->assertJsonCount(1, 'items')->assertJsonPath('items.0.id', $recipe['id'])->assertJsonPath('has_more', false)->json();
        $this->assertNull($second['next_cursor']);
        $this->actingAs($this->ana)->getJson($this->activity('?cursor='.urlencode($first['next_cursor']), DemoSeeder::IONESCU))->assertUnprocessable();
        $this->getJson($this->activity('?cursor=invalid'))->assertUnprocessable();
        $this->getJson($this->activity('?limit=51'))->assertUnprocessable();
    }

    public function test_rebuild_uses_canonical_sources_preserves_them_and_rolls_back_a_failed_rebuild(): void
    {
        $recipe = $this->create();
        $this->meal($recipe);
        $before = DB::connection('admin')->table('recipe_versions')->first();
        $this->artisan('kitchen:rebuild-activity', ['--family' => DemoSeeder::POPESCU])->assertSuccessful();
        $this->getJson($this->activity())->assertJsonCount(2, 'items');
        $rows = DB::connection('admin')->table('kitchen_activity_sources')->orderBy('source_kind')->get()->all();
        $fail = true;
        DB::listen(function ($query) use (&$fail) {
            if ($fail && str_starts_with($query->sql, 'insert into "kitchen_activity_sources"')) {
                throw new \RuntimeException('Rebuild failure');
            }
        });
        $this->artisan('kitchen:rebuild-activity', ['--family' => DemoSeeder::POPESCU])->assertFailed();
        $this->assertEquals($rows, DB::connection('admin')->table('kitchen_activity_sources')->orderBy('source_kind')->get()->all());
        $fail = false;
        $this->artisan('outbox:deliver', ['--family' => DemoSeeder::POPESCU, '--replay' => 'kitchen.activity.v1'])->assertSuccessful();
        $this->assertEquals($rows, DB::connection('admin')->table('kitchen_activity_sources')->orderBy('source_kind')->get()->all());
        $this->assertEquals($before, DB::connection('admin')->table('recipe_versions')->first());
        $this->assertSame(2, DB::connection('admin')->table('outbox_events')->count());
    }

    public function test_schema_errors_remain_retryable_and_missing_sources_are_hidden_and_tombstoned(): void
    {
        $recipe = $this->create();
        $original = DB::connection('admin')->table('outbox_events')->first();
        $future = array_replace((array) $original, ['id' => (string) Str::uuid(), 'schema_version' => 2, 'source_version' => 2]);
        DB::connection('admin')->table('outbox_events')->insert($future);
        DB::connection('admin')->table('outbox_deliveries')->where('event_id', $original->id)->update(['available_at' => now()->addHour()]);
        DB::connection('admin')->table('outbox_deliveries')->insert(['family_id' => DemoSeeder::POPESCU, 'event_id' => $future['id'], 'handler' => 'kitchen.activity.v1', 'available_at' => now()]);
        $this->assertSame(1, $this->outbox()->deliverFamily(DemoSeeder::POPESCU)['failed']);
        $this->assertSame(0, DB::connection('admin')->table('kitchen_activity_sources')->count());
        DB::connection('admin')->table('outbox_events')->where('id', $future['id'])->delete();
        DB::connection('admin')->table('outbox_deliveries')->where('event_id', $original->id)->update(['available_at' => now()]);
        $this->assertSame(1, $this->outbox()->deliverFamily(DemoSeeder::POPESCU)['delivered']);
        DB::connection('admin')->table('recipe_versions')->where('recipe_id', $recipe['id'])->delete();
        DB::connection('admin')->table('recipes')->where('id', $recipe['id'])->delete();
        $this->getJson($this->activity())->assertJsonCount(0, 'items');
        $this->outbox()->replayFamily(DemoSeeder::POPESCU, 'kitchen.activity.v1');
        $this->assertSame(1, $this->outbox()->deliverFamily(DemoSeeder::POPESCU)['delivered']);
        $this->assertTrue(DB::connection('admin')->table('kitchen_activity_sources')->value('removed'));
        $this->artisan('outbox:deliver', ['--limit' => 0])->assertFailed();
        $this->artisan('outbox:deliver', ['--replay' => 'unknown'])->assertFailed();
        $this->artisan('kitchen:rebuild-activity')->assertFailed();
    }

    public function test_rls_and_composite_delivery_keys_isolate_families_and_context_does_not_leak(): void
    {
        $this->create();
        $this->outbox()->deliverFamily(DemoSeeder::POPESCU);
        foreach (['outbox_events', 'outbox_deliveries', 'kitchen_activity_sources'] as $table) {
            $this->assertSame(0, DB::table($table)->count());
            $this->outbox()->inFamily(DemoSeeder::IONESCU, function () use ($table) {
                $this->assertSame(0, DB::table($table)->count());
            });
            $this->assertSame(0, DB::table($table)->count());
        }
        $role = DB::selectOne('SELECT rolsuper, rolbypassrls FROM pg_roles WHERE rolname = current_user');
        $this->assertFalse($role->rolsuper);
        $this->assertFalse($role->rolbypassrls);
        $this->expectException(QueryException::class);
        DB::connection('admin')->table('outbox_deliveries')->insert(['family_id' => DemoSeeder::IONESCU,
            'event_id' => DB::connection('admin')->table('outbox_events')->value('id'), 'handler' => 'foreign', 'available_at' => now()]);
    }

    public function test_two_restarted_worker_processes_acknowledge_one_delivery_once(): void
    {
        $this->create();
        $connection = config('database.connections.pgsql');
        $environment = ['APP_ENV' => 'testing', 'DB_DATABASE' => 'memoria_test', 'DB_HOST' => $connection['host'],
            'DB_PORT' => (string) $connection['port'], 'DB_USERNAME' => $connection['username'], 'DB_PASSWORD' => $connection['password']];
        $command = [PHP_BINARY, '-d', 'extension=pdo_pgsql', base_path('artisan'), 'outbox:deliver', '--family='.DemoSeeder::POPESCU, '--no-interaction'];
        $first = new Process($command, base_path(), $environment, timeout: 30);
        $second = new Process($command, base_path(), $environment, timeout: 30);
        $first->start();
        $second->start();
        $this->assertSame(0, $first->wait(), $first->getOutput());
        $this->assertSame(0, $second->wait(), $second->getOutput());
        $this->assertSame(1, DB::connection('admin')->table('outbox_deliveries')->value('attempts'));
        $this->assertNotNull(DB::connection('admin')->table('outbox_deliveries')->value('delivered_at'));
        $this->assertSame(1, DB::connection('admin')->table('kitchen_activity_sources')->count());
        $this->assertSame(1, DB::connection('admin')->table('recipes')->count());
    }

    public function test_pagination_is_stable_after_rebuild_and_card_queries_do_not_grow_with_the_list(): void
    {
        $this->create();
        $this->outbox()->deliverFamily(DemoSeeder::POPESCU);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->getJson($this->activity())->assertJsonCount(1, 'items');
        $small = count(DB::getQueryLog());
        DB::disableQueryLog();
        for ($i = 0; $i < 14; $i++) {
            $this->create();
        }
        $this->outbox()->deliverFamily(DemoSeeder::POPESCU);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->getJson($this->activity())->assertJsonCount(15, 'items');
        $this->assertSame($small, count(DB::getQueryLog()));
        DB::disableQueryLog();
        $first = $this->getJson($this->activity('?limit=7'))->assertJsonCount(7, 'items')->assertJsonPath('has_more', true)->json();
        $this->artisan('kitchen:rebuild-activity', ['--family' => DemoSeeder::POPESCU])->assertSuccessful();
        $second = $this->getJson($this->activity('?limit=7&cursor='.urlencode($first['next_cursor'])))->assertJsonCount(7, 'items')->json();
        $third = $this->getJson($this->activity('?limit=7&cursor='.urlencode($second['next_cursor'])))->assertJsonCount(1, 'items')->assertJsonPath('has_more', false)->json();
        $ids = array_merge(array_column($first['items'], 'id'), array_column($second['items'], 'id'), array_column($third['items'], 'id'));
        $this->assertCount(15, array_unique($ids));
    }

    public function test_vote_withdrawal_emits_a_new_activity_revision_without_rewriting_the_meal_or_recipe_version(): void
    {
        $recipe = $this->create();
        $meal = $this->meal($recipe);
        $this->putJson($meal.'/vote', ['liked' => true, 'comment' => 'Bun'], ['If-Match' => '"0"'])->assertOk();
        $this->deleteJson($meal.'/vote', [], ['If-Match' => '"1"'])->assertOk()->assertJsonPath('liked_count', 0);
        $this->outbox()->deliverFamily(DemoSeeder::POPESCU);
        $root = DB::connection('admin')->table('kitchen_meals')->first();
        $this->assertSame(1, $root->version);
        $this->assertSame(3, $root->activity_revision);
        $this->assertSame($recipe['recipe_version_id'], $root->recipe_version_id);
        $this->assertSame(3, DB::connection('admin')->table('outbox_events')->where('source_kind', 'kitchen.meal')->count());
        $this->assertSame(2, DB::connection('admin')->table('kitchen_activity_sources')->count());
    }

    public function test_outbox_facts_cannot_be_overwritten_even_by_a_database_writer(): void
    {
        $this->create();
        $this->expectException(QueryException::class);
        DB::connection('admin')->table('outbox_events')->update(['source_version' => 99]);
    }
}
