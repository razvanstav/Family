<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Calendar\Application\CalendarReminders;
use Carbon\CarbonImmutable;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\TestWith;
use RuntimeException;
use Tests\TestCase;

final class CalendarReminderTest extends TestCase
{
    private User $ana;

    private User $mihai;

    private string $anaMember;

    private string $mihaiMember;

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
        $this->actingAs($this->ana);
        $this->travelTo(CarbonImmutable::parse('2026-10-21T06:00:00Z'));
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        DB::disconnect('pgsql');
        DB::disconnect('admin');
        parent::tearDown();
    }

    private function path(string $suffix = ''): string
    {
        return '/api/v1/families/'.DemoSeeder::POPESCU.'/calendar-items'.$suffix;
    }

    private function inbox(string $family = DemoSeeder::POPESCU): string
    {
        return '/api/v1/families/'.$family.'/calendar-notifications';
    }

    private function event(array $changes = []): array
    {
        return array_replace(['title' => 'Plimbare C3', 'event_date' => '2026-10-22', 'all_day' => true, 'visibility' => 'family',
            'reminder_offset_minutes' => 0, 'reminder_recipients' => [$this->anaMember, $this->mihaiMember]], $changes);
    }

    private function deliver(): int
    {
        return app(CalendarReminders::class)->deliverFamily(DemoSeeder::POPESCU);
    }

    public function test_due_delivery_is_durable_idempotent_and_read_state_is_recipient_scoped(): void
    {
        $id = $this->postJson($this->path(), $this->event())->assertCreated()->assertJsonPath('reminder.deliveries.0.state', 'pending')->json('id');
        $this->assertSame(0, $this->deliver());
        $this->travelTo(CarbonImmutable::parse('2026-10-22T05:59:59Z'));
        $this->assertSame(0, $this->deliver());
        $this->travelTo(CarbonImmutable::parse('2026-10-22T06:00:00Z'));
        $this->artisan('calendar:deliver-reminders')->assertSuccessful();
        $this->assertSame(0, $this->deliver());
        $this->assertSame(2, DB::connection('admin')->table('calendar_reminder_deliveries')->where('state', 'delivered')->count());
        $notification = $this->getJson($this->inbox())->assertOk()->assertJsonPath('unread_count', 1)->assertJsonCount(1, 'items')->assertJsonPath('items.0.event_id', $id)->json('items.0.id');
        $this->patchJson($this->inbox().'/'.$notification, ['read' => true])->assertNoContent();
        $this->getJson($this->inbox())->assertJsonPath('unread_count', 0);
        $this->patchJson($this->inbox().'/'.$notification, ['read' => false])->assertNoContent();
        $this->getJson($this->inbox())->assertJsonPath('unread_count', 1);
        $this->getJson($this->inbox(DemoSeeder::IONESCU))->assertJsonCount(0, 'items');
        $this->patchJson($this->inbox(DemoSeeder::IONESCU).'/'.$notification, ['read' => true])->assertNotFound();
        $this->actingAs($this->mihai)->patchJson($this->inbox().'/'.$notification, ['read' => true])->assertNotFound();
        $this->getJson($this->inbox())->assertJsonCount(1, 'items')->assertJsonPath('unread_count', 1);
        $this->assertSame(0, DB::table('calendar_reminder_deliveries')->count());
    }

    #[TestWith(['2026-03-29', 0, '2026-03-29T06:00:00+00:00'])]
    #[TestWith(['2026-03-29', 1440, '2026-03-28T07:00:00+00:00'])]
    #[TestWith(['2026-10-25', 0, '2026-10-25T07:00:00+00:00'])]
    #[TestWith(['2026-10-25', 1440, '2026-10-24T06:00:00+00:00'])]
    public function test_all_day_nine_oclock_uses_local_calendar_days_at_both_dst_changes(string $date, int $offset, string $due): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-01-01T00:00:00Z'));
        $id = $this->postJson($this->path(), $this->event(['event_date' => $date, 'reminder_offset_minutes' => $offset]))->assertCreated()->assertJsonPath('reminder.due_at', $due)->json('id');
        $stored = DB::connection('admin')->table('calendar_reminder_deliveries')->where('calendar_item_id', $id)->value('due_at');
        $this->assertSame($due, CarbonImmutable::parse($stored)->utc()->toIso8601String());
    }

    public function test_timed_reminder_subtracts_elapsed_minutes_from_the_canonical_instant(): void
    {
        $this->postJson($this->path(), $this->event(['event_date' => '2026-12-15', 'all_day' => false, 'time' => '10:00', 'reminder_offset_minutes' => 60]))
            ->assertCreated()->assertJsonPath('reminder.due_at', '2026-12-15T07:00:00+00:00');
    }

    #[TestWith(['2026-10-22', '2026-10-22T07:00:00Z'])]
    #[TestWith(['2026-12-15', '2026-12-15T08:00:00Z'])]
    public function test_timed_delivery_preserves_instants_in_a_non_utc_database_session(string $date, string $utc): void
    {
        DB::statement("SET TIME ZONE 'Europe/Bucharest'");
        $id = $this->postJson($this->path(), $this->event(['event_date' => $date, 'all_day' => false, 'time' => '10:00']))->assertCreated()->json('id');
        $instant = CarbonImmutable::parse($utc);
        $stored = DB::connection('admin')->table('calendar_reminder_deliveries')->where('calendar_item_id', $id)->value('due_at');
        $this->assertSame($instant->toIso8601String(), CarbonImmutable::parse($stored)->utc()->toIso8601String());
        $this->travelTo($instant->subSecond());
        $this->assertSame(0, $this->deliver());
        $this->travelTo($instant->addSeconds(15));
        $this->assertSame(2, $this->deliver());
        $delivered = DB::connection('admin')->table('calendar_reminder_deliveries')->where('calendar_item_id', $id)->value('delivered_at');
        $this->assertSame($instant->addSeconds(15)->toIso8601String(), CarbonImmutable::parse($delivered)->utc()->toIso8601String());
        $notification = $this->getJson($this->inbox())->assertJsonPath('unread_count', 1)->json('items.0.id');
        $this->patchJson($this->inbox().'/'.$notification, ['read' => true])->assertNoContent();
        $read = DB::connection('admin')->table('calendar_reminder_deliveries')->where('id', $notification)->value('read_at');
        $this->assertSame($instant->addSeconds(15)->toIso8601String(), CarbonImmutable::parse($read)->utc()->toIso8601String());
        $this->assertSame(0, $this->deliver());
    }

    public function test_edit_archive_restore_cancel_old_intents_and_stale_update_does_not_replan(): void
    {
        $id = $this->postJson($this->path(), $this->event())->assertCreated()->json('id');
        $this->withHeader('If-Match', '"1"')->patchJson($this->path('/'.$id), $this->event(['event_date' => '2026-10-23']))->assertOk()->assertJsonPath('version', 2);
        $this->patchJson($this->path('/'.$id), $this->event())->assertStatus(412);
        $this->assertSame(2, DB::connection('admin')->table('calendar_reminder_deliveries')->where('state', 'cancelled')->count());
        $this->travelTo(CarbonImmutable::parse('2026-10-22T06:00:00Z'));
        $this->assertSame(0, $this->deliver());
        $this->withHeader('If-Match', '"2"')->postJson($this->path('/'.$id.'/archive'))->assertOk();
        $this->assertSame(0, DB::connection('admin')->table('calendar_reminder_deliveries')->where('state', 'pending')->count());
        $this->withHeader('If-Match', '"3"')->postJson($this->path('/'.$id.'/restore'))->assertOk()->assertJsonPath('version', 4);
        $this->travelTo(CarbonImmutable::parse('2026-10-23T06:00:00Z'));
        $this->assertSame(2, $this->deliver());
        $this->assertSame(0, $this->deliver());
    }

    public function test_invalid_recipients_and_all_day_offsets_roll_back_the_event_and_delivery_intents(): void
    {
        $foreign = DB::table('family_memberships')->where('family_id', DemoSeeder::IONESCU)->value('id');
        foreach ([['visibility' => 'private'], ['reminder_recipients' => [$foreign]], ['reminder_recipients' => []], ['reminder_recipients' => [$this->anaMember, $this->anaMember]], ['reminder_offset_minutes' => 15], ['reminder_offset_minutes' => null]] as $changes) {
            $this->postJson($this->path(), $this->event($changes))->assertUnprocessable();
        }
        $this->assertSame(0, DB::connection('admin')->table('calendar_items')->count());
        $this->assertSame(0, DB::connection('admin')->table('calendar_reminder_deliveries')->count());
    }

    public function test_private_transition_hides_delivered_notifications_and_replans_only_authorized_recipients(): void
    {
        $id = $this->postJson($this->path(), $this->event())->assertCreated()->json('id');
        $this->travelTo(CarbonImmutable::parse('2026-10-22T06:00:00Z'));
        $this->deliver();
        $this->withHeader('If-Match', '"1"')->patchJson($this->path('/'.$id), $this->event(['visibility' => 'private']))->assertUnprocessable();
        $this->getJson($this->inbox())->assertJsonPath('unread_count', 1);
        $this->patchJson($this->path('/'.$id), $this->event(['visibility' => 'private', 'event_date' => '2026-10-23', 'reminder_recipients' => [$this->anaMember]]))->assertOk();
        $this->getJson($this->inbox())->assertJsonCount(0, 'items');
        $this->actingAs($this->mihai)->getJson($this->inbox())->assertJsonCount(0, 'items')->assertJsonPath('unread_count', 0);
        $this->getJson($this->path('/'.$id))->assertNotFound();
        $this->travelTo(CarbonImmutable::parse('2026-10-23T06:00:00Z'));
        $this->assertSame(1, $this->deliver());
    }

    public function test_membership_revocation_cancels_pending_and_delivered_reminders_even_after_rejoining(): void
    {
        $this->postJson($this->path(), $this->event())->assertCreated();
        $this->postJson($this->path(), $this->event(['event_date' => '2026-10-23']))->assertCreated();
        $this->travelTo(CarbonImmutable::parse('2026-10-22T06:00:00Z'));
        $this->deliver();
        $this->deleteJson('/api/v1/families/'.DemoSeeder::POPESCU.'/members/'.$this->mihaiMember)->assertNoContent();
        $this->assertSame(2, DB::connection('admin')->table('calendar_reminder_deliveries')->where('membership_id', $this->mihaiMember)->where('state', 'cancelled')->count());
        $this->actingAs($this->mihai)->getJson($this->inbox())->assertNotFound();
        DB::connection('admin')->table('family_memberships')->where('id', $this->mihaiMember)->update(['revoked_at' => null]);
        $this->getJson($this->inbox())->assertJsonCount(0, 'items');
        $this->travelTo(CarbonImmutable::parse('2026-10-23T06:00:00Z'));
        $this->assertSame(1, $this->deliver());
    }

    public function test_delivery_rechecks_current_source_and_owner_even_if_no_revocation_event_was_emitted(): void
    {
        $this->postJson($this->path(), $this->event())->assertCreated();
        DB::connection('admin')->table('family_memberships')->where('id', $this->anaMember)->update(['revoked_at' => now()]);
        $this->travelTo(CarbonImmutable::parse('2026-10-22T06:00:00Z'));
        $this->assertSame(0, $this->deliver());
        $this->assertSame(2, DB::connection('admin')->table('calendar_reminder_deliveries')->where('state', 'cancelled')->count());
        $this->actingAs($this->mihai)->getJson($this->inbox())->assertJsonCount(0, 'items');
    }

    public function test_revoking_the_event_author_cancels_reminders_for_other_recipients(): void
    {
        $this->actingAs($this->mihai)->postJson($this->path(), $this->event(['reminder_recipients' => [$this->anaMember]]))->assertCreated();
        $this->actingAs($this->ana)->deleteJson('/api/v1/families/'.DemoSeeder::POPESCU.'/members/'.$this->mihaiMember)->assertNoContent();
        $this->assertSame(1, DB::connection('admin')->table('calendar_reminder_deliveries')->where('membership_id', $this->anaMember)->where('state', 'cancelled')->count());
        $this->travelTo(CarbonImmutable::parse('2026-10-22T06:00:00Z'));
        $this->assertSame(0, $this->deliver());
        $this->getJson($this->inbox())->assertJsonCount(0, 'items');
    }

    public function test_rolled_back_delivery_stays_pending_and_can_be_retried_once(): void
    {
        $this->postJson($this->path(), $this->event())->assertCreated();
        $this->travelTo(CarbonImmutable::parse('2026-10-22T06:00:00Z'));
        try {
            DB::transaction(function (): void {
                $this->assertSame(2, $this->deliver());
                throw new RuntimeException('Simulated rollback before commit');
            });
        } catch (RuntimeException $error) {
            $this->assertSame('Simulated rollback before commit', $error->getMessage());
        }
        $this->getJson($this->inbox())->assertJsonCount(0, 'items');
        $this->assertSame(2, DB::connection('admin')->table('calendar_reminder_deliveries')->where('state', 'pending')->count());
        $this->assertSame(2, $this->deliver());
        $this->assertSame(0, $this->deliver());
    }

    public function test_disabled_and_missed_reminders_do_not_send_retroactively(): void
    {
        $id = $this->postJson($this->path(), $this->event())->assertCreated()->json('id');
        $this->withHeader('If-Match', '"1"')->patchJson($this->path('/'.$id), $this->event(['reminder_offset_minutes' => null, 'reminder_recipients' => []]))->assertOk()->assertJsonPath('reminder', null);
        $this->postJson($this->path(), $this->event(['event_date' => '2026-10-20']))->assertCreated()->assertJsonPath('reminder.deliveries.0.state', 'expired');
        $this->postJson($this->path(), $this->event(['event_date' => '2026-10-21', 'all_day' => false, 'time' => '10:00']))->assertCreated();
        $this->travelTo(CarbonImmutable::parse('2026-10-22T06:00:00Z'));
        $this->assertSame(0, $this->deliver());
        $this->getJson($this->inbox())->assertJsonCount(0, 'items');
    }

    public function test_omitted_reminder_fields_preserve_settings_and_list_contains_complete_editor_payload(): void
    {
        $id = $this->postJson($this->path(), $this->event())->assertCreated()->json('id');
        $data = $this->event(['title' => 'Titlu nou']);
        unset($data['reminder_offset_minutes'], $data['reminder_recipients']);
        $this->withHeader('If-Match', '"1"')->patchJson($this->path('/'.$id), $data)->assertOk()->assertJsonPath('reminder.offset_minutes', 0)->assertJsonCount(2, 'reminder.recipients');
        $this->getJson($this->path('?month=2026-10'))->assertOk()->assertJsonPath('0.version', 2)->assertJsonPath('0.reminder.offset_minutes', 0)->assertJsonCount(2, '0.reminder.deliveries');
        $this->actingAs($this->mihai)->getJson($this->path('?month=2026-10'))->assertJsonPath('0.reminder', null)->assertJsonPath('0.can_edit', false);
        DB::connection('admin')->table('family_memberships')->where('id', $this->mihaiMember)->update(['role' => 'limited']);
        $this->postJson($this->path(), $this->event())->assertForbidden();
        $this->travelTo(CarbonImmutable::parse('2026-10-22T06:00:00Z'));
        $this->assertSame(2, $this->deliver());
        $this->getJson($this->inbox())->assertJsonCount(1, 'items')->assertJsonPath('unread_count', 1);
    }
}
