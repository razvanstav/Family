<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

final class FamilySearchTest extends TestCase
{
    private User $ana;

    private User $mihai;

    private string $anaMember;

    private string $mihaiMember;

    private string $base;

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
        $members = DB::table('family_memberships')->where('family_id', DemoSeeder::POPESCU)->pluck('id', 'user_id');
        $this->anaMember = $members[$this->ana->id];
        $this->mihaiMember = $members[$this->mihai->id];
        $this->base = '/api/v1/families/'.DemoSeeder::POPESCU;
        $this->actingAs($this->ana);
    }

    protected function tearDown(): void
    {
        DB::disableQueryLog();
        DB::disconnect('pgsql');
        DB::disconnect('admin');
        parent::tearDown();
    }

    private function document(string $title, ?User $owner = null, string $visibility = 'private', string $category = 'personal', string $family = DemoSeeder::POPESCU): string
    {
        $owner ??= $this->ana;
        $asset = (string) Str::uuid();
        $id = (string) Str::uuid();
        $bytes = DemoSeeder::samplePdf();
        $key = $family.'/'.$asset;
        Storage::disk('local')->put($key, $bytes);
        DB::connection('admin')->table('media_assets')->insert(['id' => $asset, 'family_id' => $family, 'created_by' => $owner->id, 'storage_key' => $key,
            'original_name' => 'test.pdf', 'mime' => 'application/pdf', 'bytes' => strlen($bytes), 'sha256' => hash('sha256', $bytes), 'status' => 'ready', 'created_at' => now(), 'updated_at' => now()]);
        $subject = DB::table('family_memberships')->where('family_id', $family)->where('user_id', $owner->id)->value('id');
        DB::connection('admin')->table('documents')->insert(['id' => $id, 'family_id' => $family, 'owner_id' => $owner->id, 'asset_id' => $asset,
            'title' => $title, 'category' => $category, 'visibility' => $visibility, 'subject_membership_id' => in_array($category, ['personal', 'health'], true) ? $subject : null,
            'version' => 1, 'created_at' => now(), 'updated_at' => now()]);

        return $id;
    }

    private function moment(string $title, ?User $owner = null, string $visibility = 'private', string $body = 'Notiță de test.'): string
    {
        return $this->actingAs($owner ?? $this->ana)->postJson($this->base.'/moments', ['kind' => 'note', 'title' => $title, 'body' => $body,
            'occurred_on' => '2026-10-20', 'visibility' => $visibility])->assertCreated()->json('id');
    }

    private function recipe(string $title, ?User $owner = null, string $visibility = 'private', array $changes = []): array
    {
        return $this->actingAs($owner ?? $this->ana)->postJson($this->base.'/recipes', array_replace(['title' => $title, 'category' => 'dessert',
            'ingredients' => ['200 g făină'], 'instructions' => 'Amestecă ingredientele.', 'duration_minutes' => 30, 'servings' => 2, 'difficulty' => 'easy', 'visibility' => $visibility], $changes))->assertCreated()->json();
    }

    private function search(string $term, ?User $actor = null, ?string $type = null, int $page = 1): TestResponse
    {
        return $this->actingAs($actor ?? $this->ana)->getJson($this->base.'/search?'.http_build_query(['q' => $term, 'type' => $type, 'page' => $page]));
    }

    private function grant(string $table, string $column, string $id, string $member): void
    {
        DB::connection('admin')->table($table)->insert(['family_id' => DemoSeeder::POPESCU, $column => $id, 'membership_id' => $member]);
    }

    public function test_all_three_sources_return_authorized_metadata_and_canonical_targets(): void
    {
        $doc = $this->document('Tort — document');
        $moment = $this->moment('Tort — amintire');
        $recipe = $this->recipe('Tort — rețetă');
        $response = $this->search('tort')->assertOk()->assertJsonCount(3, 'items')->assertJsonPath('has_more', false);
        $items = collect($response->json('items'))->keyBy('type');
        $this->assertSame($this->anaMember, $items['document']['member_id']);
        $this->assertSame($doc, $items['document']['id']);
        $this->getJson($this->base.'/documents/'.$doc)->assertOk();
        $this->getJson($this->base.'/moments/'.$moment)->assertOk();
        $this->getJson($this->base.'/recipes/'.$recipe['id'])->assertOk();
        $this->search('tort', type: 'document')->assertJsonCount(1, 'items')->assertJsonPath('items.0.id', $doc);
        $this->search('tort', type: 'recipe')->assertJsonCount(1, 'items')->assertJsonPath('items.0.id', $recipe['id']);
    }

    public function test_family_owner_cannot_search_another_adults_private_titles_or_fragments(): void
    {
        $this->document('Secret cautare document', $this->mihai);
        $this->moment('Secret cautare amintire', $this->mihai);
        $this->recipe('Secret cautare rețetă', $this->mihai);
        $this->search('Secret cautare')->assertExactJson(['items' => [], 'page' => 1, 'has_more' => false]);
        $this->search('Secret cautare', $this->mihai)->assertJsonCount(3, 'items');
        DB::connection('admin')->table('family_memberships')->where('id', $this->mihaiMember)->update(['role' => 'limited']);
        $this->search('Secret cautare', $this->mihai)->assertJsonCount(3, 'items');
    }

    public function test_selective_grants_are_checked_before_results_and_removed_on_revocation(): void
    {
        $doc = $this->document('Selectiv cautare document', visibility: 'selected');
        $moment = $this->moment('Selectiv cautare amintire');
        $recipe = $this->recipe('Selectiv cautare rețetă');
        DB::connection('admin')->table('memory_moments')->where('id', $moment)->update(['visibility' => 'selected']);
        DB::connection('admin')->table('recipes')->where('id', $recipe['id'])->update(['visibility' => 'selected']);
        $this->search('Selectiv cautare', $this->mihai)->assertJsonCount(0, 'items');
        $this->grant('document_grants', 'document_id', $doc, $this->mihaiMember);
        $this->grant('memory_moment_grants', 'moment_id', $moment, $this->mihaiMember);
        $this->grant('recipe_grants', 'recipe_id', $recipe['id'], $this->mihaiMember);
        $this->search('Selectiv cautare', $this->mihai)->assertJsonCount(3, 'items');
        $this->actingAs($this->ana)->deleteJson($this->base.'/members/'.$this->mihaiMember)->assertNoContent();
        $this->search('Selectiv cautare', $this->mihai)->assertNotFound();
        DB::connection('admin')->table('family_memberships')->where('id', $this->mihaiMember)->update(['revoked_at' => null]);
        $this->search('Selectiv cautare', $this->mihai)->assertJsonCount(0, 'items');
    }

    public function test_adult_document_group_includes_limited_adults_and_excludes_registered_minors(): void
    {
        $this->document('Poliță cautare casă', visibility: 'adults', category: 'home');
        DB::connection('admin')->table('family_memberships')->where('id', $this->mihaiMember)->update(['role' => 'limited']);
        $this->search('polita cautare', $this->mihai)->assertJsonCount(1, 'items');
        DB::connection('admin')->table('member_profiles')->insert(['family_id' => DemoSeeder::POPESCU, 'membership_id' => $this->mihaiMember, 'birth_date' => '2012-01-01', 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $this->search('polita cautare', $this->mihai)->assertJsonCount(0, 'items');
        $this->search('polita cautare')->assertJsonCount(1, 'items');
    }

    public function test_archived_sources_and_old_recipe_versions_are_excluded(): void
    {
        $moment = $this->moment('Istoric cautare amintire');
        $recipe = $this->recipe('Istoric cautare rețetă');
        $this->search('Istoric cautare')->assertJsonCount(2, 'items');
        DB::connection('admin')->table('memory_moments')->where('id', $moment)->update(['archived_at' => now()]);
        $version = (array) DB::connection('admin')->table('recipe_versions')->where('recipe_id', $recipe['id'])->first();
        $version['id'] = (string) Str::uuid();
        $version['version_no'] = 2;
        $version['title'] = 'Compot nou';
        DB::connection('admin')->table('recipe_versions')->insert($version);
        DB::connection('admin')->table('recipes')->where('id', $recipe['id'])->update(['version' => 2]);
        $this->search('Istoric cautare')->assertJsonCount(0, 'items');
        $this->search('Compot nou')->assertJsonCount(1, 'items');
        DB::connection('admin')->table('recipes')->where('id', $recipe['id'])->update(['archived_at' => now()]);
        $this->search('Compot nou')->assertJsonCount(0, 'items');
    }

    public function test_diacritics_ingredients_and_sql_wildcards_are_handled_as_literal_text(): void
    {
        $this->moment('Vacanţă la mare', body: 'Fotografii din călătorie.');
        $this->recipe('Prăjitură simplă', changes: ['ingredients' => ['100 g ciocolată']]);
        $this->document('Plan 50%_final');
        $this->search('VACANTA')->assertJsonCount(1, 'items');
        $this->search('calatorie', type: 'moment')->assertJsonCount(1, 'items');
        $this->search('ciocolata')->assertJsonCount(1, 'items');
        $this->search('%_')->assertJsonCount(1, 'items')->assertJsonPath('items.0.title', 'Plan 50%_final');
        $this->search('%%')->assertJsonCount(0, 'items');
        $this->search("' OR 1=1 --")->assertJsonCount(0, 'items');
    }

    public function test_pagination_is_stable_and_hidden_results_do_not_affect_has_more(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        for ($i = 1; $i <= 13; $i++) {
            $this->moment(sprintf('Pagina cautare %02d', $i), visibility: 'family');
        }
        for ($i = 1; $i <= 15; $i++) {
            $this->moment(sprintf('Pagina cautare ascuns %02d', $i), $this->mihai);
        }
        $first = $this->search('Pagina cautare')->assertJsonCount(12, 'items')->assertJsonPath('has_more', true)->json('items');
        $second = $this->search('Pagina cautare', page: 2)->assertJsonCount(1, 'items')->assertJsonPath('has_more', false)->json('items');
        $ids = array_column(array_merge($first, $second), 'id');
        $this->assertCount(13, array_unique($ids));
        $this->search('Pagina cautare 13')->assertJsonCount(1, 'items')->assertJsonPath('has_more', false);
    }

    public function test_query_count_does_not_grow_with_the_number_of_results(): void
    {
        $this->moment('Lot cautare 01', visibility: 'family');
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->search('Lot cautare')->assertJsonCount(1, 'items');
        $before = count(DB::getQueryLog());
        for ($i = 2; $i <= 10; $i++) {
            $this->moment(sprintf('Lot cautare %02d', $i), visibility: 'family');
        }
        DB::flushQueryLog();
        $this->search('Lot cautare')->assertJsonCount(10, 'items');
        $this->assertSame($before, count(DB::getQueryLog()));
    }

    public function test_family_boundaries_and_query_validation_apply_before_search(): void
    {
        $this->document('Limita cautare Popescu', visibility: 'family', category: 'memories');
        $foreign = $this->document('Limita cautare Ionescu', visibility: 'family', category: 'memories', family: DemoSeeder::IONESCU);
        $this->search('Limita cautare')->assertJsonCount(1, 'items')->assertJsonPath('items.0.title', 'Limita cautare Popescu');
        $this->getJson('/api/v1/families/'.DemoSeeder::IONESCU.'/search?q=Limita%20cautare')->assertJsonCount(1, 'items')->assertJsonPath('items.0.id', $foreign);
        $this->actingAs($this->mihai)->getJson('/api/v1/families/'.DemoSeeder::IONESCU.'/search?q=Limita')->assertNotFound();
        foreach ([['q' => 'a'], ['q' => '   '], ['q' => str_repeat('a', 101)], ['q' => 'ok', 'type' => 'unknown'], ['q' => 'ok', 'page' => 0], ['q' => ['ok']]] as $params) {
            $this->actingAs($this->ana)->getJson($this->base.'/search?'.http_build_query($params))->assertUnprocessable();
        }
    }
}
