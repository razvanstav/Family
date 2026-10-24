<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Calendar\Application\CalendarReminders;
use App\Modules\Calendar\Application\CalendarSources;
use App\Modules\Documents\Application\DocumentExpiryReminders;
use App\Modules\Memory\Application\MemoryAnniversaryReminders;
use App\Modules\Tasks\Application\TaskCalendar;
use App\Platform\Contracts\NotificationPreferences;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class NotificationSourcesTest extends TestCase
{
    private string $family;

    /** @var array<string, User> */
    private array $users = [];

    /** @var array<string, string> */
    private array $members = [];

    private object $preferences;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('memoria_test', config('database.connections.pgsql.database'));
        $admin = config('database.connections.pgsql');
        $admin['username'] = 'memoria_admin';
        $admin['password'] = env('DB_ADMIN_PASSWORD');
        config(['database.connections.admin' => $admin]);
        $this->travelTo(CarbonImmutable::parse('2026-10-24 08:00', 'Europe/Bucharest'));
        $this->family = (string) Str::uuid();
        DB::connection('admin')->table('families')->insert(['id' => $this->family, 'name' => 'Surse notificări test']);
        foreach (['owner', 'reader', 'minor'] as $name) {
            $user = User::factory()->make();
            $user->setConnection('admin')->save();
            $this->users[$name] = $user;
            $this->members[$name] = (string) Str::uuid();
            DB::connection('admin')->table('family_memberships')->insert(['id' => $this->members[$name],
                'family_id' => $this->family, 'user_id' => $user->id, 'role' => $name === 'owner' ? 'owner' : 'adult']);
        }
        DB::connection('admin')->table('member_profiles')->insert(['membership_id' => $this->members['minor'],
            'family_id' => $this->family, 'birth_date' => '2010-01-01']);
        $this->preferences = new class implements NotificationPreferences
        {
            public array $values = [];

            public array $saved = [];

            public function forUser(int $user): array
            {
                $result = array_fill_keys(['calendar', 'tasks', 'birthdays', 'deadlines', 'memories'], ['in_app' => false, 'email' => false]);
                $result['deadlines']['in_app'] = true;

                return array_replace($result, $this->values[$user] ?? []);
            }

            public function configured(int $user): bool
            {
                return $this->saved[$user] ?? false;
            }

            public function category(?string $sourceType): string
            {
                return match ($sourceType) {
                    'task' => 'tasks', 'memory_anniversary' => 'memories', 'document_expiry' => 'deadlines', default => 'calendar',
                };
            }
        };
        $this->app->instance(NotificationPreferences::class, $this->preferences);
    }

    protected function tearDown(): void
    {
        try {
            if (isset($this->family)) {
                foreach (['calendar_items', 'memory_moments', 'documents', 'media_assets', 'family_tasks', 'member_profiles', 'family_memberships'] as $table) {
                    DB::connection('admin')->table($table)->where('family_id', $this->family)->delete();
                }
                DB::connection('admin')->table('families')->where('id', $this->family)->delete();
            }
            foreach ($this->users as $user) {
                DB::connection('admin')->table('users')->where('id', $user->id)->delete();
            }
        } finally {
            $this->travelBack();
            DB::disconnect('pgsql');
            DB::disconnect('admin');
            parent::tearDown();
        }
    }

    private function tenant(Closure $callback): mixed
    {
        return DB::transaction(function () use ($callback) {
            DB::table('families')->where('id', $this->family)->lockForUpdate()->firstOrFail();
            DB::select("SELECT set_config('app.family_id', ?, true)", [$this->family]);

            return $callback();
        });
    }

    private function enable(string $member, string $category): void
    {
        $id = $this->users[$member]->id;
        $this->preferences->saved[$id] = true;
        $this->preferences->values[$id][$category] = ['in_app' => false, 'email' => true];
    }

    private function document(array $changes = []): string
    {
        $id = (string) Str::uuid();
        $asset = (string) Str::uuid();
        DB::connection('admin')->table('media_assets')->insert(['id' => $asset, 'family_id' => $this->family,
            'created_by' => $this->users['owner']->id, 'storage_key' => $asset, 'original_name' => 'test.pdf',
            'mime' => 'application/pdf', 'bytes' => 10, 'sha256' => str_repeat('a', 64), 'status' => 'ready']);
        DB::connection('admin')->table('documents')->insert(array_replace(['id' => $id, 'family_id' => $this->family,
            'owner_id' => $this->users['owner']->id, 'asset_id' => $asset, 'title' => 'Document test',
            'category' => 'personal', 'visibility' => 'private', 'version' => 1, 'expires_on' => '2026-11-01'], $changes));

        return $id;
    }

    private function moment(array $changes = []): string
    {
        $id = (string) Str::uuid();
        DB::connection('admin')->table('memory_moments')->insert(array_replace(['id' => $id, 'family_id' => $this->family,
            'owner_id' => $this->users['owner']->id, 'title' => 'Amintire test', 'kind' => 'moment',
            'occurred_on' => '2020-10-24', 'visibility' => 'private', 'version' => 1], $changes));

        return $id;
    }

    private function events(string $type): Builder
    {
        return DB::connection('admin')->table('calendar_items')->where('family_id', $this->family)->where('source_type', $type);
    }

    private function visibleSources(string $type): array
    {
        return $this->tenant(fn () => $this->app->make(CalendarSources::class)
            ->restrict(DB::table('calendar_items as c')->where('c.family_id', $this->family)->where('c.source_type', $type), $this->family, 'c')
            ->pluck('c.id')->all());
    }

    public function test_document_expiry_requires_saved_preferences_preserves_access_and_schedules_calendar_days(): void
    {
        $this->enable('reader', 'deadlines');
        $private = $this->document();
        $selected = $this->document(['visibility' => 'selected']);
        $shared = $this->document(['visibility' => 'family']);
        $this->document(['expires_on' => null, 'visibility' => 'family']);
        DB::connection('admin')->table('document_grants')->insert(['family_id' => $this->family,
            'document_id' => $selected, 'membership_id' => $this->members['reader']]);
        $sync = fn () => $this->app->make(DocumentExpiryReminders::class)->syncFamily($this->family);
        $this->tenant($sync);
        $this->tenant($sync);
        $events = $this->events('document_expiry')->get();
        $this->assertCount(2, $events);
        $this->assertEqualsCanonicalizing([$selected, $shared], $events->pluck('source_parent_id')->all());
        $this->assertFalse($events->contains('source_parent_id', $private));
        foreach ($events as $event) {
            $this->assertSame('private', $event->visibility);
            $this->assertSame($this->users['reader']->id, $event->owner_id);
            $this->assertSame(10080, $event->reminder_offset_minutes);
            $delivery = DB::connection('admin')->table('calendar_reminder_deliveries')->where('calendar_item_id', $event->id)->sole();
            $this->assertSame('2026-10-25T07:00:00+00:00', CarbonImmutable::parse($delivery->due_at)->utc()->toIso8601String());
            $this->assertSame($this->members['reader'], $delivery->membership_id);
            $this->assertSame('pending', $delivery->state);
        }
        DB::connection('admin')->table('document_grants')->where('document_id', $selected)->delete();
        $this->assertCount(1, $this->visibleSources('document_expiry'));
        $this->travelTo(CarbonImmutable::parse('2026-10-25 09:01', 'Europe/Bucharest'));
        $this->app->make(CalendarReminders::class)->deliverFamily($this->family);
        $delivered = DB::connection('admin')->table('calendar_reminder_deliveries')->where('family_id', $this->family)->where('state', 'delivered')->sole();
        $this->assertFalse($delivered->in_app_enabled);
        $this->assertSame('pending', $delivered->email_state);
        $this->assertSame(1, DB::connection('admin')->table('calendar_reminder_deliveries')->where('family_id', $this->family)->where('state', 'cancelled')->count());
        DB::connection('admin')->table('family_memberships')->where('id', $this->members['reader'])->update(['revoked_at' => now()]);
        $this->assertSame([], $this->visibleSources('document_expiry'));
    }

    public function test_adult_documents_exclude_registered_minors_and_profiles_without_accounts(): void
    {
        foreach (array_keys($this->users) as $member) {
            $this->enable($member, 'deadlines');
        }
        DB::connection('admin')->table('family_memberships')->insert(['id' => (string) Str::uuid(), 'family_id' => $this->family,
            'user_id' => null, 'role' => 'limited', 'child_name' => 'Copil fără cont']);
        $this->document(['visibility' => 'adults', 'category' => 'home']);
        $this->tenant(fn () => $this->app->make(DocumentExpiryReminders::class)->syncFamily($this->family));
        $this->assertEqualsCanonicalizing([$this->users['owner']->id, $this->users['reader']->id], $this->events('document_expiry')->pluck('owner_id')->all());
    }

    public function test_memory_anniversaries_mirror_on_this_day_and_recheck_private_grants(): void
    {
        $this->enable('owner', 'memories');
        $this->enable('reader', 'memories');
        $private = $this->moment();
        $note = $this->moment(['kind' => 'note', 'visibility' => 'family']);
        $selected = $this->moment(['visibility' => 'selected']);
        $this->moment(['occurred_on' => '2026-10-24', 'visibility' => 'family']);
        $this->moment(['occurred_on' => '2020-10-23', 'visibility' => 'family']);
        $this->moment(['archived_at' => now(), 'visibility' => 'family']);
        DB::connection('admin')->table('memory_moment_grants')->insert(['family_id' => $this->family,
            'moment_id' => $selected, 'membership_id' => $this->members['reader']]);
        $sync = fn () => $this->app->make(MemoryAnniversaryReminders::class)->syncFamily($this->family);
        $this->tenant($sync);
        $this->tenant($sync);
        $events = $this->events('memory_anniversary')->get();
        $this->assertCount(5, $events);
        $this->assertEqualsCanonicalizing([$private, $selected, $note], $events->where('owner_id', $this->users['owner']->id)->pluck('source_parent_id')->all());
        $this->assertEqualsCanonicalizing([$selected, $note], $events->where('owner_id', $this->users['reader']->id)->pluck('source_parent_id')->all());
        foreach ($events as $event) {
            $this->assertSame('private', $event->visibility);
            $this->assertSame('2026-10-24', $event->event_date);
            $this->assertSame(0, $event->reminder_offset_minutes);
        }
        DB::connection('admin')->table('memory_moment_grants')->where('moment_id', $selected)->delete();
        $this->assertCount(4, $this->visibleSources('memory_anniversary'));
        DB::connection('admin')->table('memory_moments')->where('id', $note)->update(['archived_at' => now(), 'version' => 2]);
        $this->assertCount(2, $this->visibleSources('memory_anniversary'));
    }

    public function test_leap_day_is_not_shifted_and_automatic_sources_do_not_replay_past_due_dates(): void
    {
        $this->enable('owner', 'memories');
        $this->enable('owner', 'deadlines');
        $this->moment(['occurred_on' => '2020-02-29']);
        $this->moment(['occurred_on' => '2020-03-01']);
        $this->travelTo(CarbonImmutable::parse('2027-03-01 08:00', 'Europe/Bucharest'));
        $this->tenant(fn () => $this->app->make(MemoryAnniversaryReminders::class)->syncFamily($this->family));
        $this->assertCount(1, $this->events('memory_anniversary')->get());
        $this->document(['expires_on' => '2027-03-02']);
        $this->tenant(fn () => $this->app->make(DocumentExpiryReminders::class)->syncFamily($this->family));
        $event = $this->events('document_expiry')->sole();
        $this->assertSame('expired', DB::connection('admin')->table('calendar_reminder_deliveries')->where('calendar_item_id', $event->id)->sole()->state);
    }

    public function test_tasks_reuse_the_existing_projection_notify_the_assignee_and_cancel_after_completion(): void
    {
        $task = (string) Str::uuid();
        DB::connection('admin')->table('family_tasks')->insert(['id' => $task, 'family_id' => $this->family,
            'owner_id' => $this->users['owner']->id, 'assignee_membership_id' => $this->members['reader'],
            'title' => 'Sarcină test', 'due_on' => '2026-10-24', 'visibility' => 'family', 'version' => 1,
            'created_at' => now(), 'updated_at' => now()]);
        $sync = fn () => $this->app->make(TaskCalendar::class)->syncFamily($this->family);
        $this->tenant($sync);
        $original = $this->events('task')->sole();
        $this->assertNull($original->reminder_offset_minutes);
        $this->enable('reader', 'tasks');
        $this->tenant($sync);
        $this->tenant($sync);
        $event = $this->events('task')->sole();
        $this->assertSame($original->id, $event->id);
        $this->assertSame($task, $event->source_id);
        $this->assertSame(0, $event->reminder_offset_minutes);
        $delivery = DB::connection('admin')->table('calendar_reminder_deliveries')->where('calendar_item_id', $event->id)->sole();
        $this->assertSame($this->members['reader'], $delivery->membership_id);
        $this->assertSame('2026-10-24T06:00:00+00:00', CarbonImmutable::parse($delivery->due_at)->utc()->toIso8601String());
        DB::connection('admin')->table('family_tasks')->where('id', $task)->update(['completed_at' => now(), 'version' => 2]);
        $this->tenant($sync);
        $this->assertNotNull($this->events('task')->sole()->archived_at);
        $this->assertSame('cancelled', DB::connection('admin')->table('calendar_reminder_deliveries')->where('id', $delivery->id)->sole()->state);
        $this->assertSame([], $this->visibleSources('task'));
    }
}
