<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Calendar\Application\CalendarReminders;
use App\Modules\Vehicles\Application\VehicleCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

final class VehicleCalendarTest extends TestCase
{
    private User $ana;

    private User $mihai;

    private string $anaMember;

    private string $mihaiMember;

    private string $vehicle;

    private string $base;

    private string $family;

    private string $otherFamily;

    private array $fixtureUsers = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('memoria_test', config('database.connections.pgsql.database'));
        $admin = config('database.connections.pgsql');
        $admin['username'] = 'memoria_admin';
        $admin['password'] = env('DB_ADMIN_PASSWORD');
        config(['database.connections.admin' => $admin]);
        $this->family = (string) Str::uuid();
        $this->otherFamily = (string) Str::uuid();
        foreach ([$this->family, $this->otherFamily] as $family) {
            DB::connection('admin')->table('families')->insert(['id' => $family, 'name' => 'Calendar Auto test']);
        }
        foreach (['ana', 'mihai', 'elena'] as $name) {
            $user = User::factory()->make(['email' => 'vehicle-calendar-'.$this->family.'-'.$name.'@example.test']);
            $user->setConnection('admin')->save();
            $this->fixtureUsers[$name] = $user;
        }
        $this->ana = $this->fixtureUsers['ana'];
        $this->mihai = $this->fixtureUsers['mihai'];
        $this->anaMember = (string) Str::uuid();
        $this->mihaiMember = (string) Str::uuid();
        foreach ([[$this->family, $this->anaMember, $this->ana->id, 'owner'], [$this->family, $this->mihaiMember, $this->mihai->id, 'adult'],
            [$this->otherFamily, (string) Str::uuid(), $this->ana->id, 'adult'], [$this->otherFamily, (string) Str::uuid(), $this->fixtureUsers['elena']->id, 'owner']] as [$family, $membership, $user, $role]) {
            DB::connection('admin')->table('family_memberships')->insert(['id' => $membership, 'family_id' => $family, 'user_id' => $user, 'role' => $role]);
        }
        $this->actingAs($this->ana);
        $this->travelTo(CarbonImmutable::parse('2026-10-20T06:00:00Z'));
        $this->base = '/api/v1/families/'.$this->family;
        $this->vehicle = $this->postJson($this->base.'/vehicles', ['name' => 'Dacia familiei', 'make' => 'Dacia', 'model' => 'Duster', 'registration' => null, 'year' => 2022])->assertCreated()->json('id');
    }

    protected function tearDown(): void
    {
        try {
            foreach ([$this->family ?? null, $this->otherFamily ?? null] as $family) {
                if ($family === null) {
                    continue;
                }
                foreach (['calendar_items', 'vehicle_documents', 'vehicle_entries', 'vehicles', 'audit_events', 'family_memberships'] as $table) {
                    DB::connection('admin')->table($table)->where('family_id', $family)->delete();
                }
                DB::connection('admin')->table('families')->where('id', $family)->delete();
            }
            foreach ($this->fixtureUsers as $user) {
                DB::connection('admin')->table('users')->where('id', $user->id)->delete();
            }
        } finally {
            $this->travelBack();
            DB::disconnect('pgsql');
            DB::disconnect('admin');
            parent::tearDown();
        }
    }

    private function path(string $suffix = ''): string
    {
        return $this->base.'/vehicles/'.$this->vehicle.$suffix;
    }

    private function entry(array $changes = []): array
    {
        return array_replace(['kind' => 'itp', 'title' => 'ITP', 'state' => 'planned', 'event_date' => '2026-10-28', 'cost' => null, 'odometer_km' => null, 'notes' => null,
            'reminder_offset_minutes' => 10080, 'reminder_recipients' => [$this->anaMember, $this->mihaiMember]], $changes);
    }

    private function deliver(): int
    {
        return app(CalendarReminders::class)->deliverFamily($this->family);
    }

    private function reconcile(): void
    {
        DB::transaction(function (): void {
            DB::table('families')->where('id', $this->family)->lockForUpdate()->firstOrFail();
            DB::select("SELECT set_config('app.family_id', ?, true)", [$this->family]);
            app(VehicleCalendar::class)->syncVehicle($this->family, $this->vehicle);
        });
    }

    public function test_planned_entry_is_the_single_readonly_calendar_source_with_family_boundaries(): void
    {
        $entry = $this->postJson($this->path('/entries'), $this->entry(['reminder_offset_minutes' => null, 'reminder_recipients' => []]))->assertCreated()->json();
        $id = $entry['calendar_event_id'];
        $this->getJson($this->base.'/calendar-items?month=2026-10')->assertOk()->assertJsonCount(1)->assertJsonPath('0.id', $id)
            ->assertJsonPath('0.title', 'ITP · Dacia familiei')->assertJsonPath('0.can_edit', false)->assertJsonPath('0.source.id', $entry['id'])->assertJsonPath('0.source.parent_id', $this->vehicle);
        $this->getJson($this->base.'/calendar-items/'.$id)->assertOk()->assertJsonPath('source.type', 'vehicle_entry');
        $this->getJson('/api/v1/families/'.$this->otherFamily.'/calendar-items/'.$id)->assertNotFound();
        $this->withHeader('If-Match', '"1"')->patchJson($this->base.'/calendar-items/'.$id, [])->assertConflict();
        $this->postJson($this->base.'/calendar-items/'.$id.'/archive')->assertConflict();
        $this->postJson($this->base.'/calendar-items/'.$id.'/restore')->assertConflict();
        $this->postJson($this->base.'/calendar-items', ['title' => 'Copie', 'event_date' => '2026-10-28', 'all_day' => true, 'visibility' => 'family',
            'source_type' => 'vehicle_entry', 'source_id' => $entry['id']])->assertUnprocessable();
        DB::connection('admin')->table('family_memberships')->where('id', $this->mihaiMember)->update(['role' => 'limited']);
        $this->actingAs($this->mihai)->getJson($this->base.'/calendar-items?month=2026-10')->assertJsonCount(1)->assertJsonPath('0.can_edit', false);
        $this->patchJson($this->path('/entries/'.$entry['id']), $this->entry())->assertForbidden();
        $this->assertSame(0, $this->deliver());
    }

    public function test_delivery_and_date_edits_are_atomic_versioned_and_idempotent(): void
    {
        $entry = $this->postJson($this->path('/entries'), $this->entry())->assertCreated()->json();
        $id = $entry['calendar_event_id'];
        $this->getJson($this->path('/entries'))->assertJsonPath('items.0.reminder_offset_minutes', 10080)->assertJsonCount(2, 'items.0.reminder_recipients');
        $this->travelTo(CarbonImmutable::parse('2026-10-21T05:59:59Z'));
        $this->assertSame(0, $this->deliver());
        $this->travelTo(CarbonImmutable::parse('2026-10-21T06:00:00Z'));
        $this->assertSame(2, $this->deliver());
        $this->assertSame(0, $this->deliver());
        $this->getJson($this->base.'/calendar-notifications')->assertJsonCount(1, 'items')->assertJsonPath('items.0.event_id', $id);
        $this->withHeader('If-Match', '"1"')->patchJson($this->path('/entries/'.$entry['id']), $this->entry(['event_date' => '2026-10-30']))->assertOk()->assertJsonPath('version', 2)->assertJsonPath('calendar_event_id', $id);
        $this->getJson($this->base.'/calendar-notifications')->assertJsonCount(0, 'items')->assertJsonPath('unread_count', 0);
        $this->patchJson($this->path('/entries/'.$entry['id']), $this->entry())->assertStatus(412);
        $this->getJson($this->base.'/calendar-items?month=2026-10')->assertJsonCount(1)->assertJsonPath('0.event_date', '2026-10-30');
        $this->travelTo(CarbonImmutable::parse('2026-10-23T06:00:00Z'));
        $this->assertSame(2, $this->deliver());
        $this->withHeader('If-Match', '"2"')->patchJson($this->path('/entries/'.$entry['id']), $this->entry(['event_date' => '2026-10-30', 'reminder_offset_minutes' => null, 'reminder_recipients' => []]))->assertOk();
        $this->getJson($this->base.'/calendar-notifications')->assertJsonCount(0, 'items');
        $this->assertSame(0, DB::connection('admin')->table('calendar_reminder_deliveries')->where('family_id', $this->family)->where('state', 'pending')->count());
    }

    public function test_entry_and_vehicle_archive_restore_and_completion_cancel_old_occurrences(): void
    {
        $entry = $this->postJson($this->path('/entries'), $this->entry())->assertCreated()->json();
        $path = $this->path('/entries/'.$entry['id']);
        $this->withHeader('If-Match', '"1"')->postJson($path.'/archive', ['archived' => true])->assertOk();
        $this->getJson($this->base.'/calendar-items?month=2026-10')->assertExactJson([]);
        $this->getJson($this->base.'/calendar-items?month=2026-10&archived=1')->assertJsonCount(1);
        $this->travelTo(CarbonImmutable::parse('2026-10-21T06:00:00Z'));
        $this->assertSame(0, $this->deliver());
        $this->withHeader('If-Match', '"2"')->postJson($path.'/archive', ['archived' => false])->assertOk();
        $this->assertSame(2, $this->deliver());
        $this->withHeader('If-Match', '"1"')->postJson($this->path('/archive'), ['archived' => true])->assertOk();
        $this->getJson($this->base.'/calendar-notifications')->assertJsonCount(0, 'items');
        $this->getJson($this->base.'/calendar-items?month=2026-10')->assertExactJson([]);
        $this->withHeader('If-Match', '"2"')->postJson($this->path('/archive'), ['archived' => false])->assertOk();
        $this->getJson($this->base.'/calendar-items?month=2026-10')->assertJsonCount(1);
        $complete = $this->entry(['state' => 'completed', 'event_date' => '2026-10-21']);
        unset($complete['reminder_offset_minutes'], $complete['reminder_recipients']);
        $this->withHeader('If-Match', '"3"')->patchJson($path, $complete)->assertOk()->assertJsonPath('reminder_offset_minutes', null);
        $this->getJson($this->base.'/calendar-items?month=2026-10')->assertJsonCount(1)
            ->assertJsonPath('0.source.type', 'vehicle_date')->assertJsonPath('0.source.id', $entry['id'])
            ->assertJsonPath('0.event_date', '2026-10-21')->assertJsonPath('0.can_edit', false)->assertJsonPath('0.reminder', null);
        $this->assertSame(0, $this->deliver());
        $this->getJson($this->base.'/calendar-notifications')->assertJsonCount(0, 'items');
        $this->assertSame(1, DB::connection('admin')->table('calendar_items')->where('family_id', $this->family)->count());
    }

    public function test_invalid_settings_leave_no_partial_source_or_projection(): void
    {
        $foreign = DB::table('family_memberships')->where('family_id', $this->otherFamily)->value('id');
        foreach ([['reminder_recipients' => [$foreign]], ['reminder_recipients' => [$this->anaMember, $this->anaMember]],
            ['reminder_recipients' => []], ['reminder_offset_minutes' => 60], ['state' => 'completed', 'event_date' => '2026-10-01']] as $changes) {
            $this->postJson($this->path('/entries'), $this->entry($changes))->assertUnprocessable();
            $this->assertSame(0, DB::connection('admin')->table('vehicle_entries')->where('family_id', $this->family)->count());
            $this->assertSame(0, DB::connection('admin')->table('calendar_items')->where('family_id', $this->family)->count());
            $this->assertSame(0, DB::connection('admin')->table('calendar_reminder_deliveries')->where('family_id', $this->family)->count());
        }
        $entry = $this->postJson($this->path('/entries'), $this->entry())->assertCreated()->json();
        $this->withHeader('If-Match', '"1"')->patchJson($this->path('/entries/'.$entry['id']), $this->entry(['event_date' => '2026-10-30', 'reminder_recipients' => [$foreign]]))->assertUnprocessable();
        $this->getJson($this->path('/entries/'.$entry['id']))->assertJsonPath('version', 1)->assertJsonPath('event_date', '2026-10-28');
        $this->getJson($this->base.'/calendar-items/'.$entry['calendar_event_id'])->assertJsonPath('version', 1)->assertJsonPath('event_date', '2026-10-28');
        $this->assertSame(2, DB::connection('admin')->table('calendar_reminder_deliveries')->where('family_id', $this->family)->where('state', 'pending')->count());
    }

    public function test_revocation_removes_recipient_but_preserves_family_owned_deadlines(): void
    {
        $this->actingAs($this->mihai)->postJson($this->path('/entries'), $this->entry())->assertCreated();
        $this->actingAs($this->ana)->deleteJson($this->base.'/members/'.$this->mihaiMember)->assertNoContent();
        $this->travelTo(CarbonImmutable::parse('2026-10-21T06:00:00Z'));
        $this->assertSame(1, $this->deliver());
        $this->getJson($this->base.'/calendar-notifications')->assertJsonCount(1, 'items');
        $this->getJson($this->base.'/calendar-items?month=2026-10')->assertJsonCount(1);
        $this->actingAs($this->mihai)->getJson($this->base.'/calendar-notifications')->assertNotFound();
        DB::connection('admin')->table('family_memberships')->where('id', $this->mihaiMember)->update(['revoked_at' => null]);
        $this->getJson($this->base.'/calendar-notifications')->assertJsonCount(0, 'items');
    }

    public function test_canonical_source_is_rechecked_before_calendar_home_and_delivery(): void
    {
        $entry = $this->postJson($this->path('/entries'), $this->entry())->assertCreated()->json();
        DB::connection('admin')->table('vehicle_entries')->where('family_id', $this->family)->where('id', $entry['id'])->increment('version');
        $this->getJson($this->base.'/calendar-items?month=2026-10')->assertJsonCount(1)
            ->assertJsonPath('0.source.type', 'vehicle_date')->assertJsonPath('0.source.id', $entry['id'])
            ->assertJsonPath('0.version', 2)->assertJsonPath('0.event_date', '2026-10-28')->assertJsonPath('0.reminder', null);
        $this->getJson($this->base.'/calendar-items/'.$entry['calendar_event_id'])->assertNotFound();
        $this->getJson($this->base.'/home?month=2026-10')->assertOk()->assertJsonCount(0, 'events')->assertJsonCount(0, 'reminders');
        $this->travelTo(CarbonImmutable::parse('2026-10-21T06:00:00Z'));
        $this->assertSame(0, $this->deliver());
        $this->getJson($this->base.'/calendar-notifications')->assertJsonCount(0, 'items');
    }

    public function test_reconciliation_backfills_old_entries_and_preserves_settings_without_duplicates(): void
    {
        $entry = $this->postJson($this->path('/entries'), $this->entry())->assertCreated()->json();
        $this->reconcile();
        $this->reconcile();
        $this->assertSame(1, DB::connection('admin')->table('calendar_items')->where('family_id', $this->family)->count());
        $this->assertSame(2, DB::connection('admin')->table('calendar_reminder_deliveries')->where('family_id', $this->family)->count());
        DB::connection('admin')->table('calendar_items')->where('family_id', $this->family)->where('id', $entry['calendar_event_id'])->delete();
        $this->reconcile();
        $this->reconcile();
        $this->getJson($this->base.'/calendar-items?month=2026-10')->assertJsonCount(1)->assertJsonPath('0.version', 1)->assertJsonPath('0.reminder', null);
        $this->assertSame(0, DB::connection('admin')->table('calendar_reminder_deliveries')->where('family_id', $this->family)->count());
    }

    #[TestWith(['2027-03-29', 1440, '2027-03-28T06:00:00+00:00'])]
    #[TestWith(['2026-10-28', 10080, '2026-10-21T06:00:00+00:00'])]
    #[TestWith(['2026-11-01', 43200, '2026-10-02T06:00:00+00:00'])]
    public function test_calendar_days_at_nine_in_romania_across_dst(string $date, int $offset, string $due): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-01-01T00:00:00Z'));
        $entry = $this->postJson($this->path('/entries'), $this->entry(['event_date' => $date, 'reminder_offset_minutes' => $offset]))->assertCreated()->json();
        $event = $this->getJson($this->base.'/calendar-items/'.$entry['calendar_event_id'])->assertOk()->json();
        $this->assertSame($due, $event['reminder']['due_at']);
        $this->travelTo(CarbonImmutable::parse($due)->subSecond());
        $this->assertSame(0, $this->deliver());
        $this->travelTo(CarbonImmutable::parse($due));
        $this->assertSame(2, $this->deliver());
    }

    public function test_past_reminders_expire_and_vehicle_rename_updates_the_same_event(): void
    {
        $entry = $this->postJson($this->path('/entries'), $this->entry(['event_date' => '2026-10-01', 'reminder_offset_minutes' => 0]))->assertCreated()->json();
        $this->assertSame(0, $this->deliver());
        $this->assertSame(2, DB::connection('admin')->table('calendar_reminder_deliveries')->where('family_id', $this->family)->where('state', 'expired')->count());
        $this->withHeader('If-Match', '"1"')->patchJson($this->path(), ['name' => 'Dacia nouă', 'make' => 'Dacia', 'model' => 'Duster', 'registration' => null, 'year' => 2022])->assertOk();
        $this->getJson($this->base.'/calendar-items/'.$entry['calendar_event_id'])->assertJsonPath('title', 'ITP · Dacia nouă');
        $this->assertSame(1, DB::connection('admin')->table('calendar_items')->where('family_id', $this->family)->count());
    }
}
