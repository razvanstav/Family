<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Calendar\Application\BirthdayCalendar;
use App\Modules\Calendar\Application\ReminderReconciler;
use App\Modules\Calendar\Contracts\AutomaticReminders;
use App\Modules\Calendar\Contracts\ReminderReconciliation;
use App\Platform\Contracts\ReminderEmailSender;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

final class ReminderReconciliationTest extends TestCase
{
    private User $owner;

    private array $families = [];

    private array $members = [];

    private object $source;

    private ReminderReconciler $reconciler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('memoria_test', config('database.connections.pgsql.database'));
        $admin = config('database.connections.pgsql');
        $admin['username'] = 'memoria_admin';
        $admin['password'] = env('DB_ADMIN_PASSWORD');
        config(['database.connections.admin' => $admin]);
        $this->owner = User::factory()->make(['email' => 'nr1-'.Str::uuid().'@example.test']);
        $this->owner->setConnection('admin')->save();
        foreach (range(0, 1) as $index) {
            $this->families[$index] = (string) Str::uuid();
            $this->members[$index] = (string) Str::uuid();
            DB::connection('admin')->table('families')->insert(['id' => $this->families[$index], 'name' => 'Reconciliere notificări test']);
            DB::connection('admin')->table('family_memberships')->insert(['id' => $this->members[$index], 'family_id' => $this->families[$index], 'user_id' => $this->owner->id, 'role' => 'owner']);
        }
        $this->source = new class implements AutomaticReminders
        {
            public array $calls = [];

            public bool $fail = false;

            public function syncFamily(string $family): void
            {
                $this->calls[] = $family;
                if ($this->fail) {
                    throw new RuntimeException('Synthetic reconciliation failure');
                }
            }
        };
        $this->reconciler = new ReminderReconciler($this->app->make(BirthdayCalendar::class), [$this->source]);
        $this->app->instance(ReminderReconciler::class, $this->reconciler);
        $this->app->instance(ReminderReconciliation::class, $this->reconciler);
        $this->app->instance(ReminderEmailSender::class, new class implements ReminderEmailSender
        {
            public function ready(): bool
            {
                throw new RuntimeException('SMTP must not be used by runtime tests');
            }

            public function send(string $address, string $subject, string $body): void
            {
                throw new RuntimeException('SMTP must not be used by runtime tests');
            }
        });
        $this->travelTo(CarbonImmutable::parse('2026-10-24 08:00', 'Europe/Bucharest'));
        $this->actingAs($this->owner)->withHeader('Accept', 'application/json');
    }

    protected function tearDown(): void
    {
        try {
            foreach ($this->families as $family) {
                foreach (['outbox_events', 'audit_events', 'calendar_items', 'documents', 'memory_moments', 'member_profiles', 'family_memberships'] as $table) {
                    DB::connection('admin')->table($table)->where('family_id', $family)->delete();
                }
                DB::connection('admin')->table('families')->where('id', $family)->delete();
            }
            if (isset($this->owner)) {
                DB::connection('admin')->table('users')->where('id', $this->owner->id)->delete();
            }
        } finally {
            $this->travelBack();
            DB::disconnect('pgsql');
            DB::disconnect('admin');
            parent::tearDown();
        }
    }

    private function tenant(Closure $callback, int $index = 0): mixed
    {
        return DB::transaction(function () use ($callback, $index): mixed {
            DB::table('families')->where('id', $this->families[$index])->lockForUpdate()->firstOrFail();
            DB::select("SELECT set_config('app.family_id', ?, true)", [$this->families[$index]]);

            return $callback();
        });
    }

    private function dirty(int $index = 0): bool
    {
        return (bool) DB::connection('admin')->table('calendar_reminder_syncs')->where('family_id', $this->families[$index])->value('dirty');
    }

    public function test_idle_pass_never_reloads_source_adapters_and_day_rollover_reconciles_once(): void
    {
        $this->assertTrue($this->reconciler->synchronizeFamily($this->families[0]));
        $this->assertFalse($this->dirty());
        $this->assertFalse($this->reconciler->synchronizeFamily($this->families[0]));
        $this->travel(1)->minutes();
        $this->assertFalse($this->reconciler->synchronizeFamily($this->families[0]));
        $this->assertSame([$this->families[0]], $this->source->calls);
        $this->travelTo(CarbonImmutable::parse('2026-10-25 00:01', 'Europe/Bucharest'));
        $this->assertTrue($this->reconciler->synchronizeFamily($this->families[0]));
        $this->assertFalse($this->reconciler->synchronizeFamily($this->families[0]));
        $this->assertCount(2, $this->source->calls);
    }

    public function test_dirty_markers_are_transactional_and_failed_reconciliation_stays_dirty(): void
    {
        $this->reconciler->synchronizeFamily($this->families[0]);
        try {
            $this->tenant(function (): void {
                $this->reconciler->invalidateFamily($this->families[0]);
                throw new RuntimeException('Synthetic rollback');
            });
        } catch (RuntimeException) {
            $this->assertFalse($this->dirty());
        }
        $this->tenant(fn () => $this->reconciler->invalidateFamily($this->families[0]));
        $this->source->fail = true;
        try {
            $this->reconciler->synchronizeFamily($this->families[0]);
            $this->fail('Reconciliation should fail');
        } catch (RuntimeException) {
            $this->assertTrue($this->dirty());
        }
        $this->source->fail = false;
        $this->assertTrue($this->reconciler->synchronizeFamily($this->families[0]));
        $this->assertFalse($this->dirty());
        $this->assertFalse($this->tenant(fn () => DB::table('calendar_reminder_syncs')->where('family_id', $this->families[1])->exists()));
    }

    public function test_settings_mark_active_families_once_and_restore_the_callers_tenant_context(): void
    {
        foreach ($this->families as $family) {
            $this->reconciler->synchronizeFamily($family);
        }
        $settings = $this->getJson('/api/v1/account/notification-settings')->assertOk()->json('items');
        $this->withHeader('If-Match', '"0"')->putJson('/api/v1/account/notification-settings', ['items' => $settings])->assertOk();
        $this->assertTrue($this->dirty());
        $this->assertTrue($this->dirty(1));
        foreach ($this->families as $family) {
            $this->reconciler->synchronizeFamily($family);
        }
        $this->withHeader('If-Match', '"1"')->putJson('/api/v1/account/notification-settings', ['items' => $settings])->assertOk();
        $this->assertFalse($this->dirty());
        $this->assertFalse($this->dirty(1));
        $this->tenant(function (): void {
            $this->reconciler->invalidateUser($this->owner->id);
            $this->assertSame($this->families[0], DB::selectOne("SELECT current_setting('app.family_id', true) AS family_id")->family_id);
        });
        DB::connection('admin')->table('family_memberships')->where('id', $this->members[1])->update(['revoked_at' => now()]);
        $this->reconciler->synchronizeFamily($this->families[1]);
        $this->reconciler->invalidateUser($this->owner->id);
        $this->assertFalse($this->dirty(1));
    }

    public function test_document_and_memory_mutations_mark_sources_but_reading_does_not(): void
    {
        $family = '/api/v1/families/'.$this->families[0];
        $this->reconciler->synchronizeFamily($this->families[0]);
        $document = $this->postJson($family.'/documents', ['title' => 'Document NR1', 'category' => 'home'])->assertCreated()->json('id');
        $this->assertTrue($this->dirty());
        $this->reconciler->synchronizeFamily($this->families[0]);
        $this->getJson($family.'/documents/'.$document)->assertOk();
        $this->assertFalse($this->dirty());
        $this->withHeader('If-Match', '"1"')->patchJson($family.'/documents/'.$document, ['expires_on' => '2026-11-01'])->assertOk();
        $this->assertTrue($this->dirty());
        $this->reconciler->synchronizeFamily($this->families[0]);
        $moment = $this->postJson($family.'/moments', ['kind' => 'note', 'title' => 'Amintire NR1', 'body' => 'Text de test', 'occurred_on' => '2020-10-24', 'visibility' => 'private'])->assertCreated()->json('id');
        $this->assertTrue($this->dirty());
        $this->reconciler->synchronizeFamily($this->families[0]);
        $this->withHeader('If-Match', '"1"')->postJson($family.'/moments/'.$moment.'/archive')->assertOk();
        $this->assertTrue($this->dirty());
        $this->reconciler->synchronizeFamily($this->families[0]);
        $this->withHeader('If-Match', '"2"')->postJson($family.'/moments/'.$moment.'/restore')->assertOk();
        $this->assertTrue($this->dirty());
    }

    public function test_minute_delivery_processes_due_intents_without_reconciling_unchanged_sources(): void
    {
        $family = '/api/v1/families/'.$this->families[0];
        $this->travelTo(CarbonImmutable::parse('2026-10-24 08:59:20', 'Europe/Bucharest'));
        $event = $this->postJson($family.'/calendar-items', ['title' => 'Reminder NR1', 'event_date' => '2026-10-24', 'all_day' => true,
            'visibility' => 'private', 'reminder_offset_minutes' => 0, 'reminder_recipients' => [$this->members[0]]])->assertCreated()->json('id');
        $this->assertSame(['reminders' => false, 'email' => false], $this->reconciler->prepareFamily($this->families[0]));
        $this->artisan('calendar:deliver-reminders', ['--family' => $this->families[0]])->assertSuccessful();
        $this->getJson($family.'/calendar-notifications')->assertOk()->assertJsonCount(0, 'items');
        $this->travel(1)->minutes();
        $this->assertSame(['reminders' => true, 'email' => false], $this->reconciler->prepareFamily($this->families[0]));
        $this->artisan('calendar:deliver-reminders', ['--family' => $this->families[0]])->assertSuccessful();
        $this->getJson($family.'/calendar-notifications')->assertOk()->assertJsonCount(1, 'items')->assertJsonPath('items.0.event_id', $event);
        $this->artisan('calendar:deliver-reminders', ['--family' => $this->families[0]])->assertSuccessful();
        $this->assertCount(1, $this->source->calls);
        $this->getJson($family.'/calendar-notifications')->assertJsonCount(1, 'items');
        $this->artisan('calendar:deliver-reminders', ['--family' => 'invalid'])->assertFailed();
    }

    public function test_email_work_flags_respect_retry_time_and_abandoned_claims_without_smtp(): void
    {
        $family = '/api/v1/families/'.$this->families[0];
        $event = $this->postJson($family.'/calendar-items', ['title' => 'Email NR1', 'event_date' => '2026-10-25', 'all_day' => true,
            'visibility' => 'private', 'reminder_offset_minutes' => 0, 'reminder_recipients' => [$this->members[0]]])->assertCreated()->json('id');
        $delivery = DB::connection('admin')->table('calendar_reminder_deliveries')->where('calendar_item_id', $event);
        $delivery->update(['email_state' => 'pending', 'email_next_attempt_at' => now()->addMinutes(5)->toIso8601String()]);
        $this->assertSame(['reminders' => false, 'email' => false], $this->reconciler->prepareFamily($this->families[0]));
        $this->travel(5)->minutes();
        $this->assertSame(['reminders' => false, 'email' => true], $this->reconciler->prepareFamily($this->families[0]));
        $delivery->update(['email_state' => 'sending', 'email_claimed_at' => now()->toIso8601String(), 'email_next_attempt_at' => null]);
        $this->assertSame(['reminders' => false, 'email' => false], $this->reconciler->prepareFamily($this->families[0]));
        $this->travel(5)->minutes();
        $this->assertSame(['reminders' => false, 'email' => true], $this->reconciler->prepareFamily($this->families[0]));
        $this->assertCount(1, $this->source->calls);
    }
}
