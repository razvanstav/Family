<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

final class CalendarEventTest extends TestCase
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
        return '/api/v1/families/'.$family.'/calendar-items'.$suffix;
    }

    private function event(array $changes = []): array
    {
        return array_replace(['title' => 'Plimbare în familie', 'event_date' => '2026-10-21', 'all_day' => true, 'visibility' => 'private', 'notes' => 'În parc'], $changes);
    }

    public function test_date_only_event_survives_create_edit_archive_and_restore(): void
    {
        $id = $this->postJson($this->path(), $this->event())->assertCreated()->assertJsonPath('starts_at', null)
            ->assertJsonPath('event_date', '2026-10-21')->assertJsonPath('can_edit', true)->json('id');
        $this->getJson($this->path('?month=2026-10'))->assertJsonCount(1);
        $this->getJson($this->path('?month=2026-11'))->assertJsonCount(0);
        $this->withHeader('If-Match', '"1"')->patchJson($this->path('/'.$id), $this->event(['title' => 'Plimbare duminică', 'event_date' => '2026-11-01']))->assertOk()->assertJsonPath('version', 2);
        $this->getJson($this->path('?month=2026-10'))->assertJsonCount(0);
        $this->withHeader('If-Match', '"2"')->postJson($this->path('/'.$id.'/archive'))->assertOk()->assertJsonPath('version', 3);
        $this->getJson($this->path('?month=2026-11'))->assertJsonCount(0);
        $this->getJson($this->path('?month=2026-11&archived=1'))->assertJsonCount(1);
        $this->withHeader('If-Match', '"3"')->patchJson($this->path('/'.$id), $this->event())->assertConflict();
        $this->postJson($this->path('/'.$id.'/restore'))->assertOk()->assertJsonPath('archived_at', null)->assertJsonPath('version', 4);
        $this->getJson($this->path('?month=2026-11'))->assertJsonCount(1);
    }

    #[TestWith(['2026-07-15', '2026-07-15T07:00:00Z'])]
    #[TestWith(['2026-12-15', '2026-12-15T08:00:00Z'])]
    public function test_timed_event_uses_bucharest_offset_and_round_trips(string $date, string $utc): void
    {
        $id = $this->postJson($this->path(), $this->event(['event_date' => $date, 'all_day' => false, 'time' => '10:00']))
            ->assertCreated()->assertJsonPath('starts_at', $utc)->assertJsonPath('time', '10:00')->json('id');
        $this->getJson($this->path('/'.$id))->assertOk()->assertJsonPath('event_date', $date)->assertJsonPath('time', '10:00');
    }

    #[TestWith(['2026-03-29', '03:30'])]
    #[TestWith(['2026-10-25', '03:30'])]
    public function test_nonexistent_and_ambiguous_clock_times_need_another_choice(string $date, string $time): void
    {
        $this->postJson($this->path(), $this->event(['event_date' => $date, 'all_day' => false, 'time' => $time]))->assertUnprocessable()->assertJsonValidationErrors('time');
        $this->assertSame(0, DB::connection('admin')->table('calendar_items')->count());
    }

    public function test_invalid_input_is_rejected_and_does_not_create_an_event(): void
    {
        foreach ([['event_date' => '2026-02-30'], ['title' => ' '], ['title' => str_repeat('x', 161)], ['all_day' => false], ['family_id' => DemoSeeder::IONESCU], ['visibility' => 'selected'], ['timezone' => 'UTC']] as $changes) {
            $this->postJson($this->path(), $this->event($changes))->assertUnprocessable();
        }
        $this->getJson($this->path('?month=2026-99'))->assertUnprocessable();
        $this->getJson($this->path('?month=0000-01'))->assertUnprocessable();
        $this->getJson($this->path('?month=2101-01'))->assertUnprocessable();
        $this->assertSame(0, DB::connection('admin')->table('calendar_items')->count());
    }

    public function test_calendar_visibility_is_scoped_to_family_and_owner(): void
    {
        $private = $this->postJson($this->path(), $this->event())->assertCreated()->json('id');
        $shared = $this->postJson($this->path(), $this->event(['visibility' => 'family']))->assertCreated()->json('id');
        $this->getJson($this->path('/'.$shared, DemoSeeder::IONESCU))->assertNotFound();
        $this->actingAs($this->mihai)->getJson($this->path('?month=2026-10'))->assertJsonCount(1)->assertJsonPath('0.id', $shared)->assertJsonPath('0.can_edit', false);
        $this->getJson($this->path('/'.$private))->assertNotFound();
        $this->withHeader('If-Match', '"1"')->patchJson($this->path('/'.$shared), $this->event())->assertForbidden();
        $this->postJson($this->path('/'.$shared.'/archive'))->assertForbidden();
        $this->assertSame(0, DB::table('calendar_items')->count());
    }

    public function test_stale_updates_and_archive_cannot_overwrite_newer_changes(): void
    {
        $id = $this->postJson($this->path(), $this->event())->assertCreated()->json('id');
        $this->patchJson($this->path('/'.$id), $this->event())->assertStatus(428);
        $this->withHeader('If-Match', '"1"')->patchJson($this->path('/'.$id), $this->event(['title' => 'Versiune nouă']))->assertOk();
        $this->patchJson($this->path('/'.$id), $this->event(['title' => 'Versiune veche']))->assertStatus(412);
        $this->postJson($this->path('/'.$id.'/archive'))->assertStatus(412);
        $this->getJson($this->path('/'.$id))->assertJsonPath('title', 'Versiune nouă')->assertJsonPath('version', 2)->assertJsonPath('archived_at', null);
    }

    public function test_limited_member_cannot_create_or_change_calendar_items(): void
    {
        $id = $this->postJson($this->path(), $this->event(['visibility' => 'family']))->assertCreated()->json('id');
        DB::connection('admin')->table('family_memberships')->where('family_id', DemoSeeder::POPESCU)->where('user_id', $this->ana->id)->update(['role' => 'limited']);
        $this->getJson($this->path('/'.$id))->assertOk()->assertJsonPath('can_edit', false);
        $this->postJson($this->path(), $this->event())->assertForbidden();
        $this->withHeader('If-Match', '"1"')->patchJson($this->path('/'.$id), $this->event())->assertForbidden();
        DB::connection('admin')->table('family_memberships')->where('family_id', DemoSeeder::POPESCU)->where('user_id', $this->ana->id)->update(['revoked_at' => now()]);
        $this->getJson($this->path('/'.$id))->assertNotFound();
    }
}
