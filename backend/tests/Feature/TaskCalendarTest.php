<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Calendar\Contracts\SourceItems;
use Carbon\CarbonImmutable;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\TestCase;

final class TaskCalendarTest extends TestCase
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
        DB::disableQueryLog();
        DB::disconnect('pgsql');
        DB::disconnect('admin');
        parent::tearDown();
    }

    private function create(array $changes = []): array
    {
        return $this->postJson($this->base.'/tasks', array_replace(['title' => 'Cumpărături',
            'assignee_membership_id' => $this->anaMember, 'due_on' => '2026-10-25'], $changes))->assertCreated()->json();
    }

    private function events(string $month = '2026-10', bool $archived = false): array
    {
        $items = $this->getJson($this->base.'/calendar-items?month='.$month.'&archived='.(int) $archived)->assertOk()->json();

        return array_values(array_filter($items, fn (array $item) => ($item['source']['type'] ?? null) === 'task'));
    }

    private function edit(array $task, array $changes): array
    {
        return $this->withHeader('If-Match', '"'.$task['version'].'"')->patchJson($this->base.'/tasks/'.$task['id'],
            array_replace(array_intersect_key($task, array_flip(['title', 'assignee_membership_id', 'due_on', 'visibility'])), $changes))->assertOk()->json();
    }

    private function complete(array $task, bool $completed): array
    {
        return $this->withHeader('If-Match', '"'.$task['version'].'"')->patchJson($this->base.'/tasks/'.$task['id'].'/completion', ['completed' => $completed])->assertOk()->json();
    }

    public function test_only_dated_open_tasks_appear_as_readonly_sources_with_direct_links_and_no_reminders(): void
    {
        $task = $this->create();
        $this->create(['due_on' => null]);
        $this->complete($this->create(), true);
        $events = $this->events();
        $this->assertCount(1, $events);
        $event = $events[0];
        $this->assertSame(['type' => 'task', 'id' => $task['id'], 'parent_id' => $task['id']], $event['source']);
        $this->assertSame('2026-10-25', $event['event_date']);
        $this->assertTrue($event['all_day']);
        $this->assertFalse($event['can_edit']);
        $this->assertNull($event['reminder']);
        $path = $this->base.'/calendar-items/'.$event['id'];
        $this->getJson($path)->assertOk()->assertJsonPath('source.id', $task['id']);
        $this->patchJson($path, [])->assertConflict()->assertJsonPath('message', 'Modifică termenul din Sarcini.');
        $this->postJson($path.'/archive')->assertConflict();
        $this->postJson($path.'/restore')->assertConflict();
        $this->assertSame(0, DB::connection('admin')->table('calendar_reminder_deliveries')->where('calendar_item_id', $event['id'])->count());
        $home = $this->getJson($this->base.'/home?month=2026-10')->assertOk();
        $this->assertNotContains($event['id'], array_column($home->json('events'), 'id'));
    }

    public function test_date_changes_completion_reopening_and_deletion_leave_no_duplicate_or_stale_entries(): void
    {
        $task = $this->create();
        $id = $this->events()[0]['id'];
        $task = $this->edit($task, ['title' => 'Titlu schimbat', 'due_on' => '2026-11-02']);
        $this->assertSame([], $this->events());
        $november = $this->events('2026-11');
        $this->assertCount(1, $november);
        $this->assertSame($id, $november[0]['id']);
        $this->assertSame('Titlu schimbat', $november[0]['title']);
        $task = $this->edit($task, ['due_on' => null]);
        $this->assertSame([], $this->events('2026-11'));
        $this->getJson($this->base.'/calendar-items/'.$id)->assertNotFound();
        $task = $this->edit($task, ['due_on' => '2026-10-28']);
        $this->assertSame($id, $this->events()[0]['id']);
        $task = $this->complete($task, true);
        $this->assertSame([], $this->events());
        $this->assertSame([], $this->events(archived: true));
        $this->getJson($this->base.'/calendar-items/'.$id)->assertNotFound();
        $task = $this->complete($task, false);
        $this->assertSame($id, $this->events()[0]['id']);
        $this->withHeader('If-Match', '"'.$task['version'].'"')->deleteJson($this->base.'/tasks/'.$task['id'])->assertNoContent();
        $this->assertSame([], $this->events());
        $this->assertSame([], $this->events(archived: true));
        $this->getJson($this->base.'/calendar-items/'.$id)->assertNotFound();
        $this->getJson($this->base.'/tasks/'.$task['id'])->assertNotFound();
    }

    public function test_personal_access_tracks_source_changes_without_extra_access_for_family_owner(): void
    {
        $task = $this->create();
        $id = $this->events()[0]['id'];
        $this->actingAs($this->mihai);
        $this->assertCount(1, $this->events());
        $this->actingAs($this->ana);
        $task = $this->edit($task, ['visibility' => 'private']);
        $this->assertSame('private', $this->events()[0]['visibility']);
        $this->actingAs($this->mihai);
        $this->assertSame([], $this->events());
        $this->getJson($this->base.'/calendar-items/'.$id)->assertNotFound();
        $this->getJson($this->base.'/tasks/'.$task['id'])->assertNotFound();
        $other = $this->create(['visibility' => 'private', 'assignee_membership_id' => $this->mihaiMember]);
        $otherId = $this->events()[0]['id'];
        $this->actingAs($this->ana);
        $this->getJson($this->base.'/calendar-items/'.$otherId)->assertNotFound();
        $this->getJson($this->base.'/tasks/'.$other['id'])->assertNotFound();
        $this->edit($task, ['visibility' => 'family']);
        $this->actingAs($this->mihai);
        $this->assertCount(2, $this->events());
    }

    public function test_isolation_limited_assignee_and_revocation_apply_to_calendar_and_direct_task(): void
    {
        $task = $this->create(['assignee_membership_id' => $this->mihaiMember]);
        $id = $this->events()[0]['id'];
        $other = '/api/v1/families/'.DemoSeeder::IONESCU;
        $this->getJson($other.'/calendar-items/'.$id)->assertNotFound();
        $this->getJson($other.'/tasks/'.$task['id'])->assertNotFound();
        DB::connection('admin')->table('family_memberships')->where('id', $this->mihaiMember)->update(['role' => 'limited']);
        $this->actingAs($this->mihai);
        $this->assertCount(1, $this->events());
        $this->getJson($this->base.'/tasks/'.$task['id'])->assertOk()->assertJsonPath('can_edit', false)->assertJsonPath('can_complete', true);
        $task = $this->complete($task, true);
        $this->assertSame([], $this->events());
        $task = $this->complete($task, false);
        DB::connection('admin')->table('family_memberships')->where('id', $this->mihaiMember)->update(['revoked_at' => now()]);
        $this->getJson($this->base.'/calendar-items?month=2026-10')->assertNotFound();
        $this->getJson($this->base.'/calendar-items/'.$id)->assertNotFound();
        $this->getJson($this->base.'/tasks/'.$task['id'])->assertNotFound();
        DB::connection('admin')->table('family_memberships')->where('id', $this->mihaiMember)->update(['revoked_at' => null]);
        $this->assertCount(1, $this->events());
    }

    public function test_canonical_version_blocks_stale_projections_until_reconciliation(): void
    {
        $task = $this->create();
        $id = $this->events()[0]['id'];
        DB::connection('admin')->table('family_tasks')->where('id', $task['id'])->update(['visibility' => 'private', 'version' => 2]);
        $this->actingAs($this->mihai);
        $this->assertSame([], $this->events());
        $this->getJson($this->base.'/calendar-items/'.$id)->assertNotFound();
        $this->artisan('tasks:sync-calendar')->assertSuccessful();
        $this->assertSame([], $this->events());
        $this->actingAs($this->ana);
        $this->assertSame('private', $this->events()[0]['visibility']);
    }

    public function test_projection_failure_rolls_back_source_mutation(): void
    {
        $task = $this->create();
        $id = $this->events()[0]['id'];
        $calendar = Mockery::mock(SourceItems::class);
        $calendar->shouldReceive('sync')->once()->andThrow(new RuntimeException('projection failed'));
        $this->app->instance(SourceItems::class, $calendar);
        $this->withoutExceptionHandling();
        try {
            $this->withHeader('If-Match', '"1"')->patchJson($this->base.'/tasks/'.$task['id'], [
                'title' => 'Nu se salvează', 'due_on' => '2026-11-01', 'visibility' => 'family', 'assignee_membership_id' => $this->anaMember,
            ]);
            $this->fail('Projection failure must propagate.');
        } catch (RuntimeException $error) {
            $this->assertSame('projection failed', $error->getMessage());
        }
        $saved = DB::connection('admin')->table('family_tasks')->where('id', $task['id'])->first();
        $this->assertSame(1, $saved->version);
        $this->assertSame('Cumpărături', $saved->title);
        $this->assertSame('2026-10-25', $saved->due_on);
        $this->assertSame('2026-10-25', DB::connection('admin')->table('calendar_items')->where('id', $id)->value('event_date'));
    }

    public function test_backfill_is_idempotent_preserves_sources_and_list_query_count_is_constant(): void
    {
        $task = $this->create();
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->events();
        $small = count(DB::getQueryLog());
        for ($index = 0; $index < 6; $index++) {
            $this->create(['title' => 'Sarcina '.$index, 'visibility' => $index === 0 ? 'private' : 'family']);
        }
        DB::flushQueryLog();
        $this->assertCount(7, $this->events());
        $this->assertSame($small, count(DB::getQueryLog()));
        DB::connection('admin')->table('calendar_items')->where('source_type', 'task')->delete();
        $this->artisan('tasks:sync-calendar')->assertSuccessful();
        $before = $this->events();
        $this->artisan('tasks:sync-calendar')->assertSuccessful();
        $this->assertSame($before, $this->events());
        $this->assertCount(7, $before);
        $this->getJson($this->base.'/tasks/'.$task['id'])->assertOk()->assertJsonPath('version', 1);
    }
}
