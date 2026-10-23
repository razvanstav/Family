<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

final class TaskTest extends TestCase
{
    private User $ana;

    private User $mihai;

    private string $anaMember;

    private string $mihaiMember;

    private string $otherMember;

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
        DB::setDefaultConnection('admin');
        Storage::fake('local');
        (new DemoSeeder)->run();
        DB::setDefaultConnection('pgsql');
        $this->ana = User::where('email', 'ana@example.test')->firstOrFail();
        $this->mihai = User::where('email', 'mihai@example.test')->firstOrFail();
        $this->anaMember = DB::table('family_memberships')->where('family_id', DemoSeeder::POPESCU)->where('user_id', $this->ana->id)->value('id');
        $this->mihaiMember = DB::table('family_memberships')->where('family_id', DemoSeeder::POPESCU)->where('user_id', $this->mihai->id)->value('id');
        $this->otherMember = DB::table('family_memberships')->where('family_id', DemoSeeder::IONESCU)->value('id');
        $this->base = '/api/v1/families/'.DemoSeeder::POPESCU.'/tasks';
        $this->actingAs($this->ana);
        $this->travelTo(CarbonImmutable::parse('2026-10-24T10:00:00Z'));
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        DB::disableQueryLog();
        DB::disconnect('pgsql');
        DB::disconnect('admin');
        parent::tearDown();
    }

    private function content(array $changes = []): array
    {
        return array_replace(['title' => 'Cumpărături pentru cină', 'assignee_membership_id' => $this->anaMember,
            'due_on' => null], $changes);
    }

    private function create(array $changes = []): array
    {
        return $this->postJson($this->base, $this->content($changes))->assertCreated()->json();
    }

    public function test_family_default_self_assignment_optional_deadline_and_order(): void
    {
        $row = $this->postJson($this->base, ['title' => '  Cumpărături  '])->assertCreated()
            ->assertJsonPath('visibility', 'family')->assertJsonPath('title', 'Cumpărături')
            ->assertJsonPath('assignee_membership_id', $this->anaMember)->assertJsonPath('due_on', null)
            ->assertJsonPath('completed_at', null)->assertJsonPath('version', 1)
            ->assertJsonPath('can_edit', true)->assertJsonPath('can_complete', true)->json();
        $later = $this->create(['due_on' => '2026-11-02', 'assignee_membership_id' => $this->mihaiMember]);
        $earlier = $this->create(['due_on' => '2026-10-25']);
        $items = $this->getJson($this->base)->assertOk()->assertJsonCount(3, 'items')->assertJsonCount(2, 'assignees')
            ->assertJsonPath('next_page', null)->json('items');
        $this->assertSame([$earlier['id'], $later['id'], $row['id']], array_column($items, 'id'));
        $this->assertSame('Mihai Popescu', $items[1]['assignee_name']);
        $this->actingAs($this->mihai)->getJson($this->base.'/'.$later['id'])->assertOk()
            ->assertJsonPath('can_edit', false)->assertJsonPath('can_complete', true);
        $this->getJson($this->base.'/'.$row['id'])->assertOk()->assertJsonPath('can_complete', false);
    }

    public function test_personal_tasks_are_only_visible_to_author_including_against_family_owner(): void
    {
        $family = $this->create();
        $private = $this->create(['visibility' => 'private']);
        $this->getJson($this->base)->assertJsonCount(1, 'items')->assertJsonPath('items.0.id', $family['id']);
        $this->getJson($this->base.'?visibility=private')->assertJsonCount(1, 'items')->assertJsonPath('items.0.id', $private['id']);
        $this->postJson($this->base, $this->content(['visibility' => 'private', 'assignee_membership_id' => $this->mihaiMember]))
            ->assertUnprocessable()->assertJsonValidationErrors('assignee_membership_id');
        $this->actingAs($this->mihai);
        $this->getJson($this->base.'?visibility=private')->assertJsonCount(0, 'items');
        $this->getJson($this->base.'/'.$private['id'])->assertNotFound();
        $this->withHeader('If-Match', '"1"')->patchJson($this->base.'/'.$private['id'].'/completion', ['completed' => true])->assertNotFound();
        $otherPrivate = $this->create(['visibility' => 'private', 'assignee_membership_id' => $this->mihaiMember]);
        $this->actingAs($this->ana)->getJson($this->base.'/'.$otherPrivate['id'])->assertNotFound();
        $this->deleteJson($this->base.'/'.$otherPrivate['id'])->assertNotFound();
    }

    public function test_only_author_edits_and_deletes_and_updates_require_current_version(): void
    {
        $row = $this->create(['assignee_membership_id' => $this->mihaiMember]);
        $path = $this->base.'/'.$row['id'];
        $update = $this->content(['title' => 'Programare dentist', 'due_on' => '2026-11-04', 'visibility' => 'private']);
        $this->actingAs($this->mihai)->withHeader('If-Match', '"1"')->patchJson($path, $update)->assertForbidden();
        $this->deleteJson($path)->assertForbidden();
        $this->actingAs($this->ana)->withoutHeader('If-Match');
        $this->patchJson($path, $update)->assertStatus(428);
        $this->withHeader('If-Match', '"1"')->patchJson($path, $update)->assertOk()->assertJsonPath('title', 'Programare dentist')
            ->assertJsonPath('due_on', '2026-11-04')->assertJsonPath('version', 2)->assertJsonPath('visibility', 'private');
        $this->patchJson($path, $update)->assertStatus(412);
        $this->deleteJson($path)->assertStatus(412);
        $this->getJson($this->base)->assertJsonCount(0, 'items');
        $this->getJson($this->base.'?visibility=private')->assertJsonCount(1, 'items');
        $this->withHeader('If-Match', '"2"')->deleteJson($path)->assertNoContent();
        $this->getJson($path)->assertNotFound();
        $this->getJson($this->base.'?visibility=private')->assertJsonCount(0, 'items');
        $this->assertNotNull(DB::connection('admin')->table('family_tasks')->where('id', $row['id'])->value('deleted_at'));
        $this->assertSame(3, DB::connection('admin')->table('audit_events')->where('resource_id', $row['id'])->count());
    }

    public function test_author_and_responsible_can_complete_and_reopen_without_overwriting_newer_changes(): void
    {
        $row = $this->create(['assignee_membership_id' => $this->mihaiMember]);
        $path = $this->base.'/'.$row['id'].'/completion';
        $this->actingAs($this->mihai)->patchJson($path, ['completed' => true])->assertStatus(428);
        $this->withHeader('If-Match', '"1"')->patchJson($path, ['completed' => true])->assertOk()
            ->assertJsonPath('version', 2)->assertJsonPath('can_edit', false);
        $this->assertNotNull(DB::connection('admin')->table('family_tasks')->where('id', $row['id'])->value('completed_at'));
        $this->getJson($this->base)->assertJsonCount(0, 'items');
        $this->getJson($this->base.'?status=completed')->assertJsonCount(1, 'items')->assertJsonPath('items.0.id', $row['id']);
        $this->patchJson($path, ['completed' => false])->assertStatus(412);
        $this->actingAs($this->ana)->withHeader('If-Match', '"2"')->patchJson($path, ['completed' => false])->assertOk()
            ->assertJsonPath('completed_at', null)->assertJsonPath('version', 3);
        $this->getJson($this->base)->assertJsonCount(1, 'items');
        $this->getJson($this->base.'?status=completed')->assertJsonCount(0, 'items');
        $unassigned = $this->create();
        $this->actingAs($this->mihai)->withHeader('If-Match', '"1"')
            ->patchJson($this->base.'/'.$unassigned['id'].'/completion', ['completed' => true])->assertForbidden();
        $actions = DB::connection('admin')->table('audit_events')->where('resource_id', $row['id'])->pluck('action')->all();
        $this->assertEqualsCanonicalizing(['task.created', 'task.completed', 'task.reopened'], $actions);
    }

    public function test_limited_member_can_only_complete_assigned_tasks_and_cannot_edit_or_create(): void
    {
        $this->actingAs($this->mihai);
        $own = $this->create(['assignee_membership_id' => $this->anaMember]);
        $this->actingAs($this->ana);
        $assigned = $this->create(['assignee_membership_id' => $this->mihaiMember]);
        $unassigned = $this->create();
        DB::connection('admin')->table('family_memberships')->where('id', $this->mihaiMember)->update(['role' => 'limited']);
        $this->actingAs($this->mihai);
        $this->getJson($this->base.'/'.$assigned['id'])->assertOk()->assertJsonPath('can_edit', false)->assertJsonPath('can_complete', true);
        $this->getJson($this->base.'/'.$own['id'])->assertOk()->assertJsonPath('can_edit', false)->assertJsonPath('can_complete', false);
        $this->postJson($this->base, $this->content())->assertForbidden();
        $this->withHeader('If-Match', '"1"')->patchJson($this->base.'/'.$own['id'], $this->content(['visibility' => 'family']))->assertForbidden();
        $this->deleteJson($this->base.'/'.$own['id'])->assertForbidden();
        $this->patchJson($this->base.'/'.$own['id'].'/completion', ['completed' => true])->assertForbidden();
        $this->patchJson($this->base.'/'.$unassigned['id'].'/completion', ['completed' => true])->assertForbidden();
        $this->patchJson($this->base.'/'.$assigned['id'].'/completion', ['completed' => true])->assertOk()->assertJsonPath('version', 2);
    }

    public function test_revocation_other_families_and_database_rls_prevent_access(): void
    {
        $row = $this->create(['assignee_membership_id' => $this->mihaiMember]);
        $other = '/api/v1/families/'.DemoSeeder::IONESCU.'/tasks';
        $this->getJson($other)->assertOk()->assertJsonCount(0, 'items');
        $this->getJson($other.'/'.$row['id'])->assertNotFound();
        $this->withHeader('If-Match', '"1"')->patchJson($other.'/'.$row['id'].'/completion', ['completed' => true])->assertNotFound();
        $this->actingAs($this->mihai)->getJson($this->base)->assertOk();
        DB::connection('admin')->table('family_memberships')->where('id', $this->mihaiMember)->update(['revoked_at' => now()]);
        $this->getJson($this->base)->assertNotFound();
        $this->getJson($this->base.'/'.$row['id'])->assertNotFound();
        $this->patchJson($this->base.'/'.$row['id'].'/completion', ['completed' => true])->assertNotFound();
        $this->postJson($this->base, $this->content())->assertNotFound();
        DB::connection('admin')->table('family_memberships')->where('id', $this->mihaiMember)->update(['revoked_at' => null]);
        $this->getJson($this->base)->assertOk()->assertJsonCount(1, 'items');
        DB::transaction(function () {
            DB::select("SELECT set_config('app.family_id', ?, true)", [DemoSeeder::IONESCU]);
            $this->assertSame(0, DB::table('family_tasks')->count());
        });
        $this->assertSame(0, DB::table('family_tasks')->count());
    }

    public function test_assignment_rejects_accountless_revoked_and_foreign_members_but_keeps_existing_history(): void
    {
        $child = (string) Str::uuid();
        DB::connection('admin')->table('family_memberships')->insert(['id' => $child, 'family_id' => DemoSeeder::POPESCU,
            'user_id' => null, 'child_name' => 'Copil', 'role' => 'limited']);
        $row = $this->create(['assignee_membership_id' => $this->mihaiMember]);
        DB::connection('admin')->table('family_memberships')->where('id', $this->mihaiMember)->update(['revoked_at' => now()]);
        foreach ([$child, $this->mihaiMember, $this->otherMember, (string) Str::uuid()] as $assignee) {
            $this->postJson($this->base, $this->content(['assignee_membership_id' => $assignee]))
                ->assertUnprocessable()->assertJsonValidationErrors('assignee_membership_id');
        }
        $this->getJson($this->base)->assertOk()->assertJsonCount(1, 'assignees')->assertJsonPath('items.0.assignee_name', 'Mihai Popescu');
        $this->withHeader('If-Match', '"1"')->patchJson($this->base.'/'.$row['id'], $this->content([
            'title' => 'Sarcină păstrată', 'visibility' => 'family', 'assignee_membership_id' => $this->mihaiMember,
        ]))->assertOk()->assertJsonPath('version', 2);
    }

    public function test_validation_blocks_invalid_content_ownership_and_completion_payloads(): void
    {
        foreach ([['title' => ''], ['title' => '   '], ['title' => str_repeat('a', 161)], ['due_on' => '2026-02-30'],
            ['due_on' => '1899-12-31'], ['due_on' => '2100-01-01'], ['due_on' => '24.10.2026'], ['visibility' => 'selected'],
            ['assignee_membership_id' => null], ['owner_id' => $this->mihai->id], ['family_id' => DemoSeeder::IONESCU],
            ['version' => 4], ['completed_at' => '2026-10-24T10:00:00Z']] as $changes) {
            $this->postJson($this->base, $this->content($changes))->assertUnprocessable();
        }
        foreach (['?status=all', '?visibility=all', '?page=0'] as $query) {
            $this->getJson($this->base.$query)->assertUnprocessable();
        }
        $row = $this->create(['due_on' => '2028-02-29']);
        $path = $this->base.'/'.$row['id'].'/completion';
        $this->withHeader('If-Match', '"1"')->patchJson($path, [])->assertUnprocessable()->assertJsonValidationErrors('completed');
        $this->patchJson($path, ['completed' => 'yes'])->assertUnprocessable()->assertJsonValidationErrors('completed');
        $this->getJson($this->base.'/'.$row['id'])->assertJsonPath('version', 1)->assertJsonPath('completed_at', null);
    }

    public function test_pagination_is_stable_and_query_count_is_constant(): void
    {
        $this->create();
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->getJson($this->base)->assertOk();
        $small = count(DB::getQueryLog());
        $rows = [];
        for ($index = 0; $index < 52; $index++) {
            $rows[] = ['id' => (string) Str::uuid(), 'family_id' => DemoSeeder::POPESCU, 'owner_id' => $this->ana->id,
                'assignee_membership_id' => $this->anaMember, 'title' => 'Sarcina '.$index, 'created_at' => now(), 'updated_at' => now()];
        }
        DB::connection('admin')->table('family_tasks')->insert($rows);
        DB::flushQueryLog();
        $first = $this->getJson($this->base)->assertOk()->assertJsonCount(50, 'items')->assertJsonPath('next_page', 2);
        $this->assertSame($small, count(DB::getQueryLog()));
        $second = $this->getJson($this->base.'?page=2')->assertOk()->assertJsonCount(3, 'items')->assertJsonPath('next_page', null);
        $ids = array_merge(array_column($first->json('items'), 'id'), array_column($second->json('items'), 'id'));
        $this->assertCount(53, array_unique($ids));
    }

    public function test_assignee_filter_combines_with_status_and_visibility_without_exposing_other_members_private_tasks(): void
    {
        $own = $this->create();
        $assigned = $this->create(['assignee_membership_id' => $this->mihaiMember]);
        $completed = $this->create(['assignee_membership_id' => $this->mihaiMember]);
        $this->withHeader('If-Match', '"1"')->patchJson($this->base.'/'.$completed['id'].'/completion', ['completed' => true])->assertOk();
        $private = $this->create(['visibility' => 'private']);
        $this->actingAs($this->mihai);
        $this->create(['visibility' => 'private', 'assignee_membership_id' => $this->mihaiMember]);
        $this->actingAs($this->ana);

        $this->getJson($this->base.'?assignee_membership_id='.$this->anaMember)->assertOk()
            ->assertJsonCount(1, 'items')->assertJsonPath('items.0.id', $own['id'])->assertJsonCount(2, 'assignees');
        $this->getJson($this->base.'?assignee_membership_id='.$this->mihaiMember)->assertOk()
            ->assertJsonCount(1, 'items')->assertJsonPath('items.0.id', $assigned['id'])->assertJsonCount(2, 'assignees');
        $this->getJson($this->base.'?assignee_membership_id='.$this->mihaiMember.'&status=completed')->assertOk()
            ->assertJsonCount(1, 'items')->assertJsonPath('items.0.id', $completed['id']);
        $this->getJson($this->base.'?assignee_membership_id='.$this->anaMember.'&visibility=private')->assertOk()
            ->assertJsonCount(1, 'items')->assertJsonPath('items.0.id', $private['id']);
        $this->getJson($this->base.'?assignee_membership_id='.$this->mihaiMember.'&visibility=private')->assertOk()->assertJsonCount(0, 'items');
        foreach ([$this->otherMember, (string) Str::uuid()] as $assignee) {
            $this->getJson($this->base.'?assignee_membership_id='.$assignee)->assertOk()->assertJsonCount(0, 'items');
        }
        $this->getJson($this->base.'?assignee_membership_id=invalid')->assertUnprocessable()->assertJsonValidationErrors('assignee_membership_id');
    }

    public function test_assignee_filter_is_applied_before_pagination_with_constant_query_count(): void
    {
        $firstMatch = $this->create(['assignee_membership_id' => $this->mihaiMember]);
        $filtered = $this->base.'?assignee_membership_id='.$this->mihaiMember;
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->getJson($filtered)->assertOk()->assertJsonCount(1, 'items');
        $small = count(DB::getQueryLog());
        $rows = [];
        for ($index = 0; $index < 132; $index++) {
            $rows[] = ['id' => (string) Str::uuid(), 'family_id' => DemoSeeder::POPESCU, 'owner_id' => $this->ana->id,
                'assignee_membership_id' => $index < 52 ? $this->mihaiMember : $this->anaMember,
                'title' => 'Sarcina '.$index, 'due_on' => $index < 52 ? null : '2026-10-24', 'created_at' => now(), 'updated_at' => now()];
        }
        DB::connection('admin')->table('family_tasks')->insert($rows);
        DB::flushQueryLog();
        $first = $this->getJson($filtered)->assertOk()->assertJsonCount(50, 'items')->assertJsonPath('next_page', 2)->assertJsonCount(2, 'assignees');
        $this->assertSame($small, count(DB::getQueryLog()));
        $second = $this->getJson($filtered.'&page=2')->assertOk()->assertJsonCount(3, 'items')->assertJsonPath('next_page', null);
        $items = array_merge($first->json('items'), $second->json('items'));
        $ids = array_column($items, 'id');
        $this->assertCount(53, array_unique($ids));
        $this->assertContains($firstMatch['id'], $ids);
        $this->assertSame([$this->mihaiMember], array_values(array_unique(array_column($items, 'assignee_membership_id'))));
    }

    public function test_completed_list_is_ordered_by_completion_time(): void
    {
        $first = $this->create();
        $second = $this->create();
        $this->withHeader('If-Match', '"1"')->patchJson($this->base.'/'.$first['id'].'/completion', ['completed' => true])->assertOk();
        $this->travelTo(CarbonImmutable::parse('2026-10-25T10:00:00Z'));
        $this->patchJson($this->base.'/'.$second['id'].'/completion', ['completed' => true])->assertOk();
        $items = $this->getJson($this->base.'?status=completed')->assertOk()->assertJsonCount(2, 'items')->json('items');

        $this->assertSame([$second['id'], $first['id']], array_column($items, 'id'));
    }
}
