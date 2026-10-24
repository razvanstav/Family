<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Calendar\Application\CalendarReminders;
use App\Modules\Calendar\Application\ReminderEmailDelivery;
use App\Platform\Contracts\NotificationPreferences;
use App\Platform\Contracts\ReminderEmailSender;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;
use PHPUnit\Framework\Attributes\TestWith;
use RuntimeException;
use Tests\TestCase;

final class NotificationEmailTest extends TestCase
{
    private User $owner;

    private User $reader;

    private string $family;

    private string $ownerMember;

    private string $readerMember;

    private object $preferences;

    private object $sender;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('memoria_test', config('database.connections.pgsql.database'));
        $admin = config('database.connections.pgsql');
        $admin['username'] = 'memoria_admin';
        $admin['password'] = env('DB_ADMIN_PASSWORD');
        config(['database.connections.admin' => $admin]);
        $this->owner = User::factory()->make();
        $this->owner->setConnection('admin')->save();
        $this->reader = User::factory()->make();
        $this->reader->setConnection('admin')->save();
        $this->family = (string) Str::uuid();
        $this->ownerMember = (string) Str::uuid();
        $this->readerMember = (string) Str::uuid();
        DB::connection('admin')->table('families')->insert(['id' => $this->family, 'name' => 'Familia email test']);
        DB::connection('admin')->table('family_memberships')->insert([
            ['id' => $this->ownerMember, 'family_id' => $this->family, 'user_id' => $this->owner->id, 'role' => 'owner'],
            ['id' => $this->readerMember, 'family_id' => $this->family, 'user_id' => $this->reader->id, 'role' => 'adult'],
        ]);
        $this->preferences = new class implements NotificationPreferences
        {
            public array $users = [];

            public function forUser(int $user): array
            {
                return $this->users[$user] ?? [
                    'calendar' => ['in_app' => true, 'email' => false],
                    'birthdays' => ['in_app' => true, 'email' => false],
                    'deadlines' => ['in_app' => true, 'email' => false],
                    'tasks' => ['in_app' => false, 'email' => false],
                    'memories' => ['in_app' => false, 'email' => false],
                ];
            }

            public function configured(int $user): bool
            {
                return isset($this->users[$user]);
            }

            public function category(?string $sourceType): string
            {
                return match ($sourceType) {
                    'birthday' => 'birthdays',
                    'task' => 'tasks',
                    'memory_anniversary' => 'memories',
                    'vehicle_entry', 'pet_entry', 'document_expiry' => 'deadlines',
                    default => 'calendar',
                };
            }
        };
        $this->sender = new class implements ReminderEmailSender
        {
            public bool $available = true;

            public bool $fail = false;

            public int $checks = 0;

            public ?Closure $onReady = null;

            public ?Closure $onSend = null;

            public array $calls = [];

            public function ready(): bool
            {
                $this->checks++;
                if ($this->onReady !== null) {
                    ($this->onReady)($this->checks);
                }

                return $this->available;
            }

            public function send(string $address, string $subject, string $body): void
            {
                $this->calls[] = compact('address', 'subject', 'body') + ['transaction_level' => DB::transactionLevel()];
                if ($this->onSend !== null) {
                    ($this->onSend)();
                }
                if ($this->fail) {
                    throw new RuntimeException('Synthetic ambiguous SMTP result');
                }
            }
        };
        $this->app->instance(NotificationPreferences::class, $this->preferences);
        $this->app->instance(ReminderEmailSender::class, $this->sender);
        $this->actingAs($this->owner)->withHeader('Accept', 'application/json');
        $this->travelTo(CarbonImmutable::parse('2026-10-24T05:00:00Z'));
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        try {
            if (isset($this->family)) {
                foreach (['audit_events', 'calendar_items', 'family_memberships'] as $table) {
                    DB::connection('admin')->table($table)->where('family_id', $this->family)->delete();
                }
                DB::connection('admin')->table('families')->where('id', $this->family)->delete();
            }
            foreach ([$this->owner ?? null, $this->reader ?? null] as $user) {
                if ($user !== null) {
                    DB::connection('admin')->table('users')->where('id', $user->id)->delete();
                }
            }
        } finally {
            DB::disconnect('pgsql');
            DB::disconnect('admin');
            parent::tearDown();
        }
    }

    private function path(string $suffix = ''): string
    {
        return '/api/v1/families/'.$this->family.'/calendar-items'.$suffix;
    }

    private function inbox(): string
    {
        return '/api/v1/families/'.$this->family.'/calendar-notifications';
    }

    private function create(array $changes = []): string
    {
        return $this->postJson($this->path(), array_replace([
            'title' => 'Programare email', 'event_date' => '2026-10-24', 'all_day' => true, 'visibility' => 'family',
            'reminder_offset_minutes' => 0, 'reminder_recipients' => [$this->ownerMember],
        ], $changes))->assertCreated()->json('id');
    }

    private function channels(bool $inApp, bool $email, ?User $user = null): void
    {
        $user ??= $this->owner;
        $this->preferences->users[$user->id] = $this->preferences->forUser($user->id);
        $this->preferences->users[$user->id]['calendar'] = ['in_app' => $inApp, 'email' => $email];
    }

    private function due(): int
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-24T06:00:00Z'));

        return app(CalendarReminders::class)->deliverFamily($this->family);
    }

    private function send(): int
    {
        return app(ReminderEmailDelivery::class)->deliverFamily($this->family);
    }

    private function delivery(string $event, ?string $member = null): object
    {
        return DB::connection('admin')->table('calendar_reminder_deliveries')->where('calendar_item_id', $event)
            ->where('membership_id', $member ?? $this->ownerMember)->firstOrFail();
    }

    public function test_defaults_preserve_in_app_delivery_without_sending_email(): void
    {
        $event = $this->create();
        $this->assertSame(1, $this->due());
        $this->assertSame(0, $this->send());
        $this->assertNull($this->delivery($event)->email_state);
        $this->assertSame([], $this->sender->calls);
        $this->getJson($this->inbox())->assertOk()->assertJsonPath('unread_count', 1)->assertJsonCount(1, 'items');
    }

    public function test_email_only_is_sent_once_without_creating_a_bell_notification(): void
    {
        $this->channels(false, true);
        $event = $this->create();
        $this->sender->onSend = function () use ($event): void {
            $this->assertSame('sending', $this->delivery($event)->email_state);
            $this->assertSame(1, $this->delivery($event)->email_attempts);
            try {
                DB::connection('admin')->transaction(function (): void {
                    DB::connection('admin')->statement("SET LOCAL lock_timeout = '100ms'");
                    DB::connection('admin')->table('users')->where('id', $this->owner->id)->lockForUpdate()->first();
                });
                $this->fail('The recipient row must stay locked during SMTP.');
            } catch (QueryException $error) {
                $this->assertSame('55P03', $error->errorInfo[0]);
            }
        };
        $this->assertSame(0, $this->due());
        $this->getJson($this->inbox())->assertOk()->assertJsonCount(0, 'items');
        $this->assertSame(1, $this->send());
        $this->assertSame(0, $this->send());
        $this->assertSame(0, app(CalendarReminders::class)->deliverFamily($this->family));
        $this->assertCount(1, $this->sender->calls);
        $this->assertSame($this->owner->email, $this->sender->calls[0]['address']);
        $this->assertSame(1, $this->sender->calls[0]['transaction_level']);
        $this->assertStringContainsString('Programare email', $this->sender->calls[0]['subject']);
        $this->assertStringContainsString('24 octombrie 2026', $this->sender->calls[0]['body']);
        $this->assertSame('sent', $this->delivery($event)->email_state);
        $this->assertSame(1, $this->delivery($event)->email_attempts);
        $this->assertFalse($this->delivery($event)->in_app_enabled);
    }

    public function test_future_in_app_notifications_stop_after_opt_out_while_history_remains(): void
    {
        $this->channels(true, true);
        $first = $this->create();
        $this->create(['title' => 'Programare mâine', 'event_date' => '2026-10-25']);
        $this->assertSame(1, $this->due());
        $this->assertSame(1, $this->send());
        $this->channels(false, true);
        $this->travelTo(CarbonImmutable::parse('2026-10-25T07:00:00Z'));
        $this->assertSame(0, app(CalendarReminders::class)->deliverFamily($this->family));
        $this->assertSame(1, $this->send());
        $this->getJson($this->inbox())->assertOk()->assertJsonCount(1, 'items')->assertJsonPath('items.0.event_id', $first);
        $this->assertCount(2, $this->sender->calls);
    }

    public function test_disabled_channels_do_not_replay_a_due_occurrence_after_opt_in(): void
    {
        $this->channels(false, false);
        $event = $this->create();
        $this->assertSame(0, $this->due());
        $this->channels(true, true);
        $this->assertSame(0, app(CalendarReminders::class)->deliverFamily($this->family));
        $this->assertSame(0, $this->send());
        $this->assertNull($this->delivery($event)->email_state);
        $this->getJson($this->inbox())->assertOk()->assertJsonCount(0, 'items');
    }

    public function test_live_email_opt_out_cancels_an_unsent_occurrence_permanently(): void
    {
        $this->channels(true, true);
        $event = $this->create();
        $this->due();
        $this->channels(true, false);
        $this->assertSame(0, $this->send());
        $this->assertSame('cancelled', $this->delivery($event)->email_state);
        $this->channels(true, true);
        $this->assertSame(0, $this->send());
        $this->assertSame([], $this->sender->calls);
        $this->getJson($this->inbox())->assertOk()->assertJsonCount(1, 'items');
    }

    #[TestWith(['membership'])]
    #[TestWith(['private'])]
    #[TestWith(['version'])]
    #[TestWith(['archived'])]
    public function test_current_access_and_event_version_are_rechecked_before_email(string $change): void
    {
        $this->channels(false, true, $this->reader);
        $event = $this->create(['reminder_recipients' => [$this->readerMember]]);
        $this->due();
        match ($change) {
            'membership' => DB::connection('admin')->table('family_memberships')->where('id', $this->readerMember)->update(['revoked_at' => now()]),
            'private' => DB::connection('admin')->table('calendar_items')->where('id', $event)->update(['visibility' => 'private']),
            'version' => DB::connection('admin')->table('calendar_items')->where('id', $event)->increment('version'),
            'archived' => DB::connection('admin')->table('calendar_items')->where('id', $event)->update(['archived_at' => now()]),
        };
        $this->assertSame(0, $this->send());
        $this->assertSame('cancelled', $this->delivery($event, $this->readerMember)->email_state);
        $this->assertSame([], $this->sender->calls);
    }

    public function test_authorization_is_checked_again_after_the_durable_claim(): void
    {
        $this->channels(false, true);
        $event = $this->create();
        $this->due();
        $this->sender->onReady = function (int $check): void {
            if ($check === 1) {
                $this->channels(false, false);
            }
        };
        $this->assertSame(0, $this->send());
        $this->assertSame('cancelled', $this->delivery($event)->email_state);
        $this->assertSame(1, $this->delivery($event)->email_attempts);
        $this->assertSame([], $this->sender->calls);
    }

    public function test_unavailable_smtp_defers_without_claiming_and_then_sends_once(): void
    {
        $this->channels(false, true);
        $event = $this->create();
        $this->due();
        $this->sender->available = false;
        $this->assertSame(0, $this->send());
        $this->assertSame('pending', $this->delivery($event)->email_state);
        $this->assertSame(0, $this->delivery($event)->email_attempts);
        $this->sender->available = true;
        $this->assertSame(0, $this->send());
        $this->travelTo(CarbonImmutable::parse('2026-10-24T06:05:00Z'));
        $this->assertSame(1, $this->send());
        $this->assertSame('sent', $this->delivery($event)->email_state);
        $this->assertCount(1, $this->sender->calls);
    }

    public function test_expired_pending_email_does_not_arrive_after_smtp_is_restored(): void
    {
        $this->channels(false, true);
        $event = $this->create();
        $this->due();
        $this->sender->available = false;
        $this->send();
        $this->sender->available = true;
        $this->travelTo(CarbonImmutable::parse('2026-10-24T21:00:01Z'));
        $this->assertSame(0, $this->send());
        $this->assertSame('expired', $this->delivery($event)->email_state);
        $this->assertSame([], $this->sender->calls);
    }

    public function test_ambiguous_transport_failure_is_never_automatically_resent(): void
    {
        $this->channels(false, true);
        $event = $this->create();
        $this->due();
        $this->sender->fail = true;
        $this->assertSame(0, $this->send());
        $this->assertSame('uncertain', $this->delivery($event)->email_state);
        $this->assertSame('transport_uncertain', $this->delivery($event)->email_error_code);
        $this->sender->fail = false;
        $this->travelTo(CarbonImmutable::parse('2026-10-24T07:00:00Z'));
        $this->assertSame(0, $this->send());
        $this->assertCount(1, $this->sender->calls);
        $this->assertSame(1, $this->delivery($event)->email_attempts);
    }

    public function test_a_committed_claim_blocks_a_second_worker_and_recovers_as_uncertain(): void
    {
        $this->channels(false, true);
        $event = $this->create();
        $this->due();
        DB::connection('admin')->table('calendar_reminder_deliveries')->where('calendar_item_id', $event)
            ->update(['email_state' => 'sending', 'email_claimed_at' => now()->toIso8601String(), 'email_attempts' => 1]);
        $this->assertSame(0, $this->send());
        $this->assertSame('sending', $this->delivery($event)->email_state);
        $this->travelTo(CarbonImmutable::parse('2026-10-24T06:05:00Z'));
        $this->assertSame(0, $this->send());
        $this->assertSame('uncertain', $this->delivery($event)->email_state);
        $this->assertSame('interrupted_delivery', $this->delivery($event)->email_error_code);
        $this->assertSame([], $this->sender->calls);
    }

    public function test_delivery_refuses_an_outer_rollbackable_transaction(): void
    {
        $this->channels(false, true);
        $this->create();
        $this->due();
        $this->expectException(LogicException::class);
        DB::transaction(fn (): int => $this->send());
    }

    public function test_channel_states_are_only_exposed_for_the_current_recipient(): void
    {
        $this->channels(false, true);
        $this->channels(true, true, $this->reader);
        $event = $this->create(['reminder_recipients' => [$this->ownerMember, $this->readerMember]]);
        $this->due();
        $this->assertSame(2, $this->send());
        $deliveries = $this->getJson($this->path('/'.$event))->assertOk()->json('reminder.deliveries');
        $byMember = array_column($deliveries, null, 'membership_id');
        $this->assertSame('sent', $byMember[$this->ownerMember]['email_state']);
        $this->assertFalse($byMember[$this->ownerMember]['in_app_enabled']);
        $this->assertNull($byMember[$this->readerMember]['email_state']);
        $this->assertNull($byMember[$this->readerMember]['in_app_enabled']);
    }

    public function test_timed_email_uses_the_reminder_time_and_the_local_event_hour(): void
    {
        $this->channels(false, true);
        $event = $this->create(['all_day' => false, 'time' => '10:00', 'reminder_offset_minutes' => 60]);
        $this->travelTo(CarbonImmutable::parse('2026-10-24T05:59:59Z'));
        $this->assertSame(0, app(CalendarReminders::class)->deliverFamily($this->family));
        $this->assertSame(0, $this->send());
        $this->due();
        $this->assertSame(1, $this->send());
        $this->assertStringContainsString('10:00 (ora României)', $this->sender->calls[0]['body']);
        $this->assertSame('sent', $this->delivery($event)->email_state);
    }
}
