<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class HomeTasksTest extends TestCase
{
    private User $ana;

    private User $mihai;

    private string $family;

    private string $otherFamily;

    private string $anaMember;

    private string $mihaiMember;

    private string $base;

    private array $families = [];

    private array $fixtureUsers = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('memoria_test', config('database.connections.pgsql.database'));
        $admin = config('database.connections.pgsql');
        $admin['username'] = 'memoria_admin';
        $admin['password'] = env('DB_ADMIN_PASSWORD');
        config(['database.connections.admin' => $admin]);
        $this->travelTo(CarbonImmutable::parse('2026-10-24T10:00:00Z'));
        $this->family = (string) Str::uuid();
        $this->otherFamily = (string) Str::uuid();
        $this->families = [$this->family, $this->otherFamily];
        foreach ($this->families as $family) {
            DB::connection('admin')->table('families')->insert(['id' => $family, 'name' => 'Home Tasks fixture']);
        }
        foreach (['ana' => 'Ana Test', 'mihai' => 'Mihai Test'] as $key => $name) {
            $user = User::factory()->make(['name' => $name, 'email' => 'home-tasks-'.$this->family.'-'.$key.'@example.test']);
            $user->setConnection('admin')->save();
            $this->fixtureUsers[] = $user;
            $this->{$key} = $user;
        }
        $this->anaMember = (string) Str::uuid();
        $this->mihaiMember = (string) Str::uuid();
        DB::connection('admin')->table('family_memberships')->insert([
            ['id' => $this->anaMember, 'family_id' => $this->family, 'user_id' => $this->ana->id, 'role' => 'owner'],
            ['id' => $this->mihaiMember, 'family_id' => $this->family, 'user_id' => $this->mihai->id, 'role' => 'adult'],
            ['id' => (string) Str::uuid(), 'family_id' => $this->otherFamily, 'user_id' => $this->ana->id, 'role' => 'owner'],
        ]);
        $this->base = '/api/v1/families/'.$this->family;
    }

    protected function tearDown(): void
    {
        try {
            foreach ($this->families as $family) {
                foreach (['calendar_items', 'family_tasks', 'audit_events', 'member_profiles', 'family_memberships'] as $table) {
                    DB::connection('admin')->table($table)->where('family_id', $family)->delete();
                }
                DB::connection('admin')->table('families')->where('id', $family)->delete();
            }
            foreach ($this->fixtureUsers as $user) {
                DB::connection('admin')->table('users')->where('id', $user->id)->delete();
            }
        } finally {
            $this->travelBack();
            DB::disableQueryLog();
            DB::disconnect('pgsql');
            DB::disconnect('admin');
            parent::tearDown();
        }
    }

    private function home(): string
    {
        return $this->base.'/home?month=2026-10';
    }

    private function task(array $changes = []): string
    {
        $id = (string) Str::uuid();
        DB::connection('admin')->table('family_tasks')->insert(array_replace([
            'id' => $id, 'family_id' => $this->family, 'owner_id' => $this->ana->id,
            'assignee_membership_id' => $this->anaMember, 'title' => 'Sarcină de test',
            'due_on' => null, 'created_at' => now(), 'updated_at' => now(),
        ], $changes));

        return $id;
    }

    public function test_home_tasks_require_active_family_membership(): void
    {
        $this->getJson($this->home())->assertUnauthorized();
        $this->actingAs($this->mihai)->getJson('/api/v1/families/'.$this->otherFamily.'/home?month=2026-10')->assertNotFound();
        $this->getJson($this->home())->assertOk()->assertJsonPath('tasks', []);
        DB::connection('admin')->table('family_memberships')->where('id', $this->mihaiMember)->update(['revoked_at' => now()]);
        $this->getJson($this->home())->assertNotFound();
    }

    public function test_home_prioritizes_overdue_then_today_and_caps_five_incomplete_tasks(): void
    {
        $future = $this->task(['title' => 'Viitoare', 'due_on' => '2026-10-26']);
        $undated = $this->task(['title' => 'Fără termen']);
        $today = $this->task(['title' => 'Astăzi', 'due_on' => '2026-10-24']);
        $overdue = $this->task(['title' => 'Întârziată', 'due_on' => '2026-10-23']);
        $this->task(['due_on' => '2026-10-20', 'completed_at' => now()]);
        $this->task(['due_on' => '2026-10-19', 'deleted_at' => now()]);
        $this->actingAs($this->ana);
        $this->assertSame([$overdue, $today, $future, $undated], array_column($this->getJson($this->home())->assertOk()->json('tasks'), 'id'));
        $oldest = $this->task(['due_on' => '2026-10-01']);
        $nextFuture = $this->task(['due_on' => '2026-10-27']);
        $this->task(['due_on' => '2026-11-01']);
        $this->assertSame([$oldest, $overdue, $today, $future, $nextFuture], array_column($this->getJson($this->home())->assertOk()->assertJsonCount(5, 'tasks')->json('tasks'), 'id'));
    }

    public function test_home_reuses_canonical_task_payload_and_preserves_private_access(): void
    {
        $shared = $this->task(['owner_id' => $this->mihai->id, 'assignee_membership_id' => $this->anaMember]);
        $mine = $this->task(['visibility' => 'private']);
        $hidden = $this->task(['owner_id' => $this->mihai->id, 'assignee_membership_id' => $this->mihaiMember, 'visibility' => 'private']);
        $otherMembership = DB::connection('admin')->table('family_memberships')->where('family_id', $this->otherFamily)->value('id');
        $other = $this->task(['family_id' => $this->otherFamily, 'assignee_membership_id' => $otherMembership]);
        $response = $this->actingAs($this->ana)->getJson($this->home())->assertOk()->assertJsonCount(2, 'tasks')
            ->assertJsonMissing(['id' => $hidden])->assertJsonMissing(['id' => $other]);
        foreach ([$shared, $mine] as $id) {
            $canonical = $this->getJson($this->base.'/tasks/'.$id)->assertOk()->json();
            $homeTask = collect($response->json('tasks'))->firstWhere('id', $id);
            $this->assertSame($canonical, $homeTask);
            $this->assertSame($this->anaMember, $homeTask['assignee_membership_id']);
            $this->assertSame('Ana Test', $homeTask['assignee_name']);
            $this->assertTrue($homeTask['can_complete']);
        }
        $this->getJson($this->base.'/tasks?visibility=private')->assertOk()->assertJsonCount(1, 'items')->assertJsonPath('items.0.id', $mine);
        $this->actingAs($this->mihai)->getJson($this->home())->assertOk()->assertJsonCount(2, 'tasks')->assertJsonMissing(['id' => $mine]);
    }

    public function test_limited_member_only_completes_assigned_task_with_current_version(): void
    {
        $assigned = $this->task(['assignee_membership_id' => $this->mihaiMember]);
        $other = $this->task();
        DB::connection('admin')->table('family_memberships')->where('id', $this->mihaiMember)->update(['role' => 'limited']);
        $response = $this->actingAs($this->mihai)->getJson($this->home())->assertOk()->assertJsonCount(2, 'tasks');
        $assignedPayload = collect($response->json('tasks'))->firstWhere('id', $assigned);
        $otherPayload = collect($response->json('tasks'))->firstWhere('id', $other);
        $this->assertTrue($assignedPayload['can_complete']);
        $this->assertFalse($assignedPayload['can_edit']);
        $this->assertFalse($otherPayload['can_complete']);
        $this->patchJson($this->base.'/tasks/'.$assigned.'/completion', ['completed' => true])->assertStatus(428);
        $this->withHeader('If-Match', '"9"')->patchJson($this->base.'/tasks/'.$assigned.'/completion', ['completed' => true])->assertStatus(412);
        $this->withHeader('If-Match', '"1"')->patchJson($this->base.'/tasks/'.$other.'/completion', ['completed' => true])->assertForbidden();
        $this->patchJson($this->base.'/tasks/'.$assigned.'/completion', ['completed' => true])->assertOk()->assertJsonPath('version', 2);
        $this->getJson($this->home())->assertOk()->assertJsonCount(1, 'tasks')->assertJsonPath('tasks.0.id', $other);
        $this->postJson($this->base.'/tasks', ['title' => 'Nu poate crea'])->assertForbidden();
    }

    public function test_completing_recurring_home_task_returns_its_successor_in_same_card(): void
    {
        $this->actingAs($this->ana);
        $created = $this->postJson($this->base.'/tasks', ['title' => 'Repetă', 'due_on' => '2026-10-24', 'recurrence' => 'weekly'])->assertCreated()->json();
        $this->getJson($this->home())->assertOk()->assertJsonPath('tasks.0.id', $created['id']);
        $this->withHeader('If-Match', '"1"')->patchJson($this->base.'/tasks/'.$created['id'].'/completion', ['completed' => true])
            ->assertOk()->assertJsonPath('next_due_on', '2026-10-31');
        $response = $this->getJson($this->home())->assertOk()->assertJsonCount(1, 'tasks')
            ->assertJsonPath('tasks.0.due_on', '2026-10-31')->assertJsonPath('tasks.0.recurrence', 'weekly');
        $this->assertNotContains($created['id'], array_column($response->json('tasks'), 'id'));
    }

    public function test_home_task_reads_are_bounded_without_per_row_queries(): void
    {
        $this->task();
        $this->actingAs($this->ana);
        DB::enableQueryLog();
        $this->getJson($this->home())->assertOk()->assertJsonCount(1, 'tasks');
        $before = count(DB::getQueryLog());
        DB::flushQueryLog();
        for ($i = 0; $i < 20; $i++) {
            $this->task(['title' => 'Sarcină '.$i]);
        }
        $this->getJson($this->home())->assertOk()->assertJsonCount(5, 'tasks');
        $this->assertSame($before, count(DB::getQueryLog()));
        $taskQueries = array_filter(DB::getQueryLog(), fn (array $query) => str_starts_with($query['query'], 'select "t".*') && str_contains($query['query'], '"family_tasks"'));
        $this->assertCount(1, $taskQueries);
        $this->assertStringContainsString('limit 5', reset($taskQueries)['query']);
    }
}
