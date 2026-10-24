<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Calendar\Contracts\SourceItems;
use Carbon\CarbonImmutable;
use Database\Seeders\DemoSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery;
use PHPUnit\Framework\Attributes\TestWith;
use RuntimeException;
use Tests\TestCase;

final class TaskRecurrenceTest extends TestCase
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
        DB::setDefaultConnection('admin');
        Storage::fake('local');
        (new DemoSeeder)->run();
        DB::setDefaultConnection('pgsql');
        $this->ana = User::where('email', 'ana@example.test')->firstOrFail();
        $this->mihai = User::where('email', 'mihai@example.test')->firstOrFail();
        $members = DB::table('family_memberships')->where('family_id', DemoSeeder::POPESCU)->pluck('id', 'user_id');
        $this->anaMember = $members[$this->ana->id];
        $this->mihaiMember = $members[$this->mihai->id];
        $this->base = '/api/v1/families/'.DemoSeeder::POPESCU;
        $this->actingAs($this->ana);
        $this->travelTo(CarbonImmutable::parse('2026-10-24T10:00:00Z'));
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        DB::disconnect('pgsql');
        DB::disconnect('admin');
        parent::tearDown();
    }

    private function create(array $changes = []): array
    {
        return $this->postJson($this->base.'/tasks', array_replace(['title' => 'Cumpărături', 'due_on' => '2026-10-24',
            'assignee_membership_id' => $this->anaMember, 'recurrence' => 'weekly'], $changes))->assertCreated()->json();
    }

    private function complete(array $task, bool $completed = true): array
    {
        return $this->withHeader('If-Match', '"'.$task['version'].'"')->patchJson($this->base.'/tasks/'.$task['id'].'/completion', ['completed' => $completed])->assertOk()->json();
    }

    private function open(string $visibility = 'family'): array
    {
        return $this->getJson($this->base.'/tasks?visibility='.$visibility)->assertOk()->json('items');
    }

    private function edit(array $task, array $changes): array
    {
        return $this->withHeader('If-Match', '"'.$task['version'].'"')->patchJson($this->base.'/tasks/'.$task['id'], array_replace(
            array_intersect_key($task, array_flip(['title', 'due_on', 'assignee_membership_id', 'visibility', 'recurrence'])), $changes,
        ))->assertOk()->json();
    }

    public function test_completion_by_assignee_creates_one_future_occurrence_with_latest_content_and_calendar_projection(): void
    {
        $task = $this->create();
        $task = $this->edit($task, ['title' => 'Lista actualizată', 'assignee_membership_id' => $this->mihaiMember]);
        $this->actingAs($this->mihai);
        $completed = $this->complete($task);
        $this->assertSame('2026-10-31', $completed['next_due_on']);
        $this->assertStringContainsString('următoarea sarcină a fost creată', $completed['recurrence_notice']);
        $next = $this->open()[0];
        $this->assertSame('Lista actualizată', $next['title']);
        $this->assertSame($this->mihaiMember, $next['assignee_membership_id']);
        $this->assertSame('Ana Popescu', $next['owner_name']);
        $this->assertFalse($next['can_edit']);
        $this->assertSame('weekly', $next['recurrence']);
        $events = $this->getJson($this->base.'/calendar-items?month=2026-10')->assertOk();
        $events->assertJsonCount(1)->assertJsonPath('0.source.id', $next['id'])->assertJsonPath('0.event_date', '2026-10-31');
        $this->withHeader('If-Match', '"'.$task['version'].'"')->patchJson($this->base.'/tasks/'.$task['id'].'/completion', ['completed' => true])->assertStatus(412);
        $this->assertCount(1, $this->open());
    }

    public function test_monthly_anchor_survives_short_month_and_resets_when_current_due_date_changes(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-01-31T10:00:00Z'));
        $task = $this->create(['due_on' => '2026-01-31', 'recurrence' => 'monthly']);
        $this->complete($task);
        $february = $this->open()[0];
        $this->assertSame('2026-02-28', $february['due_on']);
        $this->travelTo(CarbonImmutable::parse('2026-02-28T10:00:00Z'));
        $february = $this->edit($february, ['title' => 'Titlu nou']);
        $this->complete($february);
        $march = $this->open()[0];
        $this->assertSame('2026-03-31', $march['due_on']);
        $march = $this->edit($march, ['due_on' => '2026-03-15']);
        $this->travelTo(CarbonImmutable::parse('2026-03-15T10:00:00Z'));
        $this->complete($march);
        $this->assertSame('2026-04-15', $this->open()[0]['due_on']);
    }

    public function test_completion_skips_missed_cycles_and_uses_the_bucharest_day_boundary(): void
    {
        $late = $this->create(['due_on' => '2026-10-01']);
        $this->assertSame('2026-10-29', $this->complete($late)['next_due_on']);
        $this->travelTo(CarbonImmutable::parse('2026-10-24T21:30:00Z'));
        $daily = $this->create(['recurrence' => 'daily']);
        $this->assertSame('2026-10-26', $this->complete($daily)['next_due_on']);
    }

    public function test_reopening_and_recompleting_never_duplicates_even_a_deleted_successor(): void
    {
        $task = $this->complete($this->create());
        $next = $this->open()[0];
        $task = $this->complete($task, false);
        $task = $this->complete($task);
        $this->assertStringContainsString('creată deja', $task['recurrence_notice']);
        $this->assertSame(2, DB::connection('admin')->table('family_tasks')->count());
        $this->withHeader('If-Match', '"'.$next['version'].'"')->deleteJson($this->base.'/tasks/'.$next['id'])->assertNoContent();
        $task = $this->complete($task, false);
        $this->complete($task);
        $this->assertSame(2, DB::connection('admin')->table('family_tasks')->count());
        $this->assertSame([], $this->open());
    }

    public function test_personal_successor_stays_private_including_from_family_owner_and_other_family(): void
    {
        $this->actingAs($this->mihai);
        $task = $this->create(['visibility' => 'private', 'assignee_membership_id' => $this->mihaiMember]);
        $completed = $this->complete($task);
        $this->assertSame('2026-10-31', $completed['next_due_on']);
        $nextItems = $this->open('private');
        $this->assertCount(1, $nextItems);
        $next = $nextItems[0];
        $this->assertSame('private', $next['visibility']);
        $this->actingAs($this->ana);
        $this->getJson($this->base.'/tasks/'.$next['id'])->assertNotFound();
        $this->assertSame([], $this->open('private'));
        $this->getJson($this->base.'/calendar-items?month=2026-10')->assertOk()->assertJsonCount(0);
        $this->getJson('/api/v1/families/'.DemoSeeder::IONESCU.'/tasks/'.$next['id'])->assertNotFound();
    }

    public function test_recurrence_requires_a_deadline_rejects_internal_fields_and_can_be_stopped_explicitly(): void
    {
        foreach ([['recurrence' => 'fortnightly'], ['recurrence' => 'weekly', 'due_on' => null], ['recurrence_anchor_on' => '2026-01-01'],
            ['recurrence_parent_id' => (string) Str::uuid()]] as $changes) {
            $this->postJson($this->base.'/tasks', array_replace(['title' => 'Invalid', 'due_on' => '2026-10-24'], $changes))->assertUnprocessable();
        }
        $task = $this->create();
        $this->withHeader('If-Match', '"1"')->patchJson($this->base.'/tasks/'.$task['id'], [
            'title' => 'Titlu fără schimbarea repetării', 'due_on' => '2026-10-24', 'visibility' => 'family',
        ])->assertOk()->assertJsonPath('recurrence', 'weekly');
        $task['version'] = 2;
        $task = $this->edit($task, ['recurrence' => null, 'due_on' => null]);
        $this->assertNull($task['recurrence']);
        $this->assertArrayNotHasKey('recurrence_notice', $this->complete($task));
        $this->assertSame(1, DB::connection('admin')->table('family_tasks')->count());
    }

    #[TestWith(['revoked'])]
    #[TestWith(['limited'])]
    public function test_ineligible_author_does_not_block_completion_or_create_unmanageable_successor(string $state): void
    {
        $task = $this->create(['assignee_membership_id' => $this->mihaiMember]);
        DB::connection('admin')->table('family_memberships')->where('id', $this->anaMember)
            ->update($state === 'revoked' ? ['revoked_at' => now()] : ['role' => 'limited']);
        $this->actingAs($this->mihai);
        $completed = $this->complete($task);
        $this->assertNotNull($completed['completed_at']);
        $this->assertNull($completed['next_due_on']);
        $this->assertStringContainsString('autorul', $completed['recurrence_notice']);
        $this->assertSame(1, DB::connection('admin')->table('family_tasks')->count());
    }

    public function test_revoked_assignee_and_end_of_supported_calendar_do_not_block_completion(): void
    {
        $task = $this->create(['assignee_membership_id' => $this->mihaiMember]);
        DB::connection('admin')->table('family_memberships')->where('id', $this->mihaiMember)->update(['revoked_at' => now()]);
        $completed = $this->complete($task);
        $this->assertNull($completed['next_due_on']);
        $this->assertStringContainsString('responsabilul', $completed['recurrence_notice']);
        $last = $this->create(['due_on' => '2099-12-31', 'recurrence' => 'daily']);
        $last = $this->complete($last);
        $this->assertNull($last['next_due_on']);
        $this->assertStringContainsString('2099', $last['recurrence_notice']);
        $this->assertSame(2, DB::connection('admin')->table('family_tasks')->count());
    }

    public function test_successor_projection_failure_rolls_back_completion_successor_and_audit(): void
    {
        $task = $this->create();
        $calendar = Mockery::mock(SourceItems::class);
        $calls = 0;
        $calendar->shouldReceive('sync')->twice()->andReturnUsing(function () use (&$calls): void {
            if (++$calls === 2) {
                throw new RuntimeException('successor projection failed');
            }
        });
        $this->app->instance(SourceItems::class, $calendar);
        $this->withoutExceptionHandling();
        try {
            $this->withHeader('If-Match', '"1"')->patchJson($this->base.'/tasks/'.$task['id'].'/completion', ['completed' => true]);
            $this->fail('Projection failure must propagate.');
        } catch (RuntimeException $error) {
            $this->assertSame('successor projection failed', $error->getMessage());
        }
        $row = DB::connection('admin')->table('family_tasks')->where('id', $task['id'])->first();
        $this->assertNull($row->completed_at);
        $this->assertSame(1, $row->version);
        $this->assertSame(1, DB::connection('admin')->table('family_tasks')->count());
        $this->assertSame(0, DB::connection('admin')->table('audit_events')->where('action', 'task.completed')->count());
    }

    public function test_database_constraints_reject_duplicate_successors_and_foreign_family_predecessors(): void
    {
        $task = $this->create();
        $this->complete($task);
        $next = DB::connection('admin')->table('family_tasks')->where('recurrence_parent_id', $task['id'])->first();
        $duplicate = (array) $next;
        $duplicate['id'] = (string) Str::uuid();
        try {
            DB::connection('admin')->transaction(fn () => DB::connection('admin')->table('family_tasks')->insert($duplicate));
            $this->fail('A predecessor must have only one successor.');
        } catch (QueryException $error) {
            $this->assertSame('23505', $error->errorInfo[0]);
        }
        $otherMember = DB::connection('admin')->table('family_memberships')->where('family_id', DemoSeeder::IONESCU)->whereNotNull('user_id')->first();
        $otherId = (string) Str::uuid();
        DB::connection('admin')->table('family_tasks')->insert(['id' => $otherId, 'family_id' => DemoSeeder::IONESCU,
            'owner_id' => $otherMember->user_id, 'assignee_membership_id' => $otherMember->id, 'title' => 'Altă familie', 'created_at' => now(), 'updated_at' => now()]);
        try {
            DB::connection('admin')->transaction(fn () => DB::connection('admin')->table('family_tasks')->where('id', $next->id)->update(['recurrence_parent_id' => $otherId]));
            $this->fail('A successor cannot reference another family.');
        } catch (QueryException $error) {
            $this->assertSame('23503', $error->errorInfo[0]);
        }
    }
}
