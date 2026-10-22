<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Calendar\Application\CalendarReminders;
use Carbon\CarbonImmutable;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

final class HomeOverviewTest extends TestCase
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
        $this->travelTo(CarbonImmutable::parse('2026-10-21T09:00:00Z'));
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
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        DB::disconnect('pgsql');
        DB::disconnect('admin');
        parent::tearDown();
    }

    private function home(string $month = '2026-10', string $family = DemoSeeder::POPESCU): string
    {
        return '/api/v1/families/'.$family.'/home?month='.$month;
    }

    private function calendar(string $family = DemoSeeder::POPESCU): string
    {
        return '/api/v1/families/'.$family.'/calendar-items';
    }

    private function event(array $changes = []): array
    {
        return array_replace(['title' => 'Eveniment H1', 'event_date' => '2026-10-22', 'all_day' => true, 'visibility' => 'family'], $changes);
    }

    public function test_home_requires_active_membership_and_does_not_allow_cross_family_requests(): void
    {
        $this->getJson($this->home())->assertUnauthorized();
        $this->actingAs($this->mihai)->getJson($this->home('2026-10', DemoSeeder::IONESCU))->assertNotFound();
        $this->getJson($this->home('2026-10', 'not-a-family'))->assertNotFound();
        $this->getJson($this->home())->assertOk();
        DB::connection('admin')->table('family_memberships')->where('id', $this->mihaiMember)->update(['revoked_at' => now()]);
        $this->getJson($this->home())->assertNotFound();
    }

    public function test_recent_document_summaries_use_source_acl_and_do_not_expose_private_storage_metadata(): void
    {
        $private = DB::connection('admin')->table('documents')->where('title', 'Documentul privat al lui Mihai')->firstOrFail();
        $this->actingAs($this->ana)->getJson($this->home())->assertOk()->assertJsonCount(4, 'documents')
            ->assertJsonMissing(['id' => $private->id])->assertJsonMissing(['title' => 'Documentul privat al Elenei']);
        DB::connection('admin')->table('documents')->where('id', $private->id)->update(['visibility' => 'selected', 'created_at' => now()->addDay()]);
        DB::connection('admin')->table('document_grants')->insert(['family_id' => DemoSeeder::POPESCU, 'document_id' => $private->id, 'membership_id' => $this->anaMember]);
        $response = $this->getJson($this->home())->assertOk()->assertJsonPath('documents.0.id', $private->id)->assertJsonPath('documents.0.visibility', 'selected');
        $this->assertEqualsCanonicalizing(['id', 'title', 'category', 'visibility', 'owner_id', 'mime', 'status'], array_keys($response->json('documents.0')));
        $response->assertJsonPath('documents.0.owner_id', $this->mihai->id);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        DB::connection('admin')->table('document_grants')->where('document_id', $private->id)->delete();
        $this->getJson($this->home())->assertJsonMissing(['id' => $private->id]);
        $this->getJson('/api/v1/families/'.DemoSeeder::POPESCU.'/documents/'.$private->id)->assertNotFound();
        $this->getJson('/api/v1/families/'.DemoSeeder::POPESCU.'/documents/'.$private->id.'/content')->assertNotFound();
        $this->assertSame(0, DB::table('documents')->count());
    }

    public function test_calendar_markers_and_upcoming_events_apply_visibility_archive_and_time_window(): void
    {
        $private = $this->actingAs($this->mihai)->postJson($this->calendar(), $this->event(['title' => 'Privat Mihai', 'visibility' => 'private', 'event_date' => '2026-10-24']))->assertCreated()->json('id');
        $today = $this->actingAs($this->ana)->postJson($this->calendar(), $this->event(['event_date' => '2026-10-21', 'notes' => 'Notiță privată pentru detaliu']))->assertCreated()->json('id');
        $upcoming = $this->postJson($this->calendar(), $this->event(['event_date' => '2026-11-01']))->assertCreated()->json('id');
        $past = $this->postJson($this->calendar(), $this->event(['event_date' => '2026-10-21', 'all_day' => false, 'time' => '10:00']))->assertCreated()->json('id');
        $archived = $this->postJson($this->calendar(), $this->event(['event_date' => '2026-10-25']))->assertCreated()->json('id');
        $this->withHeader('If-Match', '"1"')->postJson($this->calendar().'/'.$archived.'/archive')->assertOk();
        $this->postJson($this->calendar(), $this->event(['event_date' => '2026-12-01']))->assertCreated();
        $response = $this->getJson($this->home())->assertOk()->assertJsonPath('today', '2026-10-21')->assertJsonPath('days', ['2026-10-21'])
            ->assertJsonCount(2, 'events')->assertJsonPath('events.0.id', $today)->assertJsonPath('events.1.id', $upcoming)->assertJsonMissing(['id' => $private])->assertJsonMissing(['id' => $past])->assertJsonMissing(['id' => $archived]);
        $this->assertArrayNotHasKey('notes', $response->json('events.0'));
        $this->getJson($this->home('2026-11'))->assertJsonPath('days', ['2026-11-01']);
        $this->getJson($this->home('2026-10', DemoSeeder::IONESCU))->assertJsonCount(0, 'days')->assertJsonCount(0, 'events');
    }

    public function test_home_reminders_are_personal_and_disappear_when_delivered_or_source_is_archived(): void
    {
        $id = $this->actingAs($this->ana)->postJson($this->calendar(), $this->event(['reminder_offset_minutes' => 0, 'reminder_recipients' => [$this->anaMember]]))->assertCreated()->json('id');
        $this->getJson($this->home())->assertJsonCount(1, 'reminders')->assertJsonPath('reminders.0.event_id', $id)->assertJsonPath('reminders.0.due_at', '2026-10-22T06:00:00+00:00');
        $this->actingAs($this->mihai)->getJson($this->home())->assertJsonCount(0, 'reminders')->assertJsonCount(1, 'events');
        $this->travelTo(CarbonImmutable::parse('2026-10-22T06:00:15Z'));
        $this->assertSame(1, app(CalendarReminders::class)->deliverFamily(DemoSeeder::POPESCU));
        $this->actingAs($this->ana)->getJson($this->home())->assertJsonCount(0, 'reminders');
        $this->withHeader('If-Match', '"1"')->patchJson($this->calendar().'/'.$id, $this->event(['event_date' => '2026-10-23', 'reminder_offset_minutes' => 0, 'reminder_recipients' => [$this->anaMember]]))->assertOk();
        $this->getJson($this->home())->assertJsonCount(1, 'reminders');
        $this->withHeader('If-Match', '"2"')->postJson($this->calendar().'/'.$id.'/archive')->assertOk();
        $this->getJson($this->home())->assertJsonCount(0, 'reminders')->assertJsonCount(0, 'events')->assertJsonCount(0, 'days');
    }

    public function test_limited_member_sees_only_authorized_sources_and_revoked_author_reminders_are_hidden(): void
    {
        $this->actingAs($this->ana)->postJson($this->calendar(), $this->event(['reminder_offset_minutes' => 0, 'reminder_recipients' => [$this->mihaiMember]]))->assertCreated();
        DB::connection('admin')->table('family_memberships')->where('id', $this->mihaiMember)->update(['role' => 'limited']);
        $response = $this->actingAs($this->mihai)->getJson($this->home())->assertOk()->assertJsonCount(3, 'documents')->assertJsonCount(1, 'events')->assertJsonCount(1, 'reminders');
        $this->assertNotContains('Dosarul meu personal', array_column($response->json('documents'), 'title'));
        DB::connection('admin')->table('family_memberships')->where('id', $this->anaMember)->update(['revoked_at' => now()]);
        $this->getJson($this->home())->assertOk()->assertJsonCount(0, 'reminders');
    }

    public function test_lists_are_bounded_and_query_count_does_not_grow_with_document_count(): void
    {
        $this->actingAs($this->ana);
        DB::enableQueryLog();
        $this->getJson($this->home())->assertOk();
        $before = count(DB::getQueryLog());
        DB::flushQueryLog();
        $admin = DB::connection('admin');
        $source = $admin->table('documents')->where('owner_id', $this->ana->id)->firstOrFail();
        $asset = $admin->table('media_assets')->where('id', $source->asset_id)->firstOrFail();
        for ($i = 0; $i < 12; $i++) {
            $assetId = (string) Str::uuid();
            $admin->table('media_assets')->insert(array_replace((array) $asset, ['id' => $assetId, 'storage_key' => DemoSeeder::POPESCU.'/'.$assetId]));
            $admin->table('documents')->insert(array_replace((array) $source, ['id' => (string) Str::uuid(), 'asset_id' => $assetId, 'title' => 'Document '.$i, 'created_at' => now()->addMinutes($i + 1)->toIso8601String()]));
            $eventId = (string) Str::uuid();
            $date = CarbonImmutable::now('Europe/Bucharest')->startOfDay()->addDays($i + 1);
            $admin->table('calendar_items')->insert(['id' => $eventId, 'family_id' => DemoSeeder::POPESCU, 'owner_id' => $this->ana->id, 'title' => 'Eveniment '.$i,
                'visibility' => 'family', 'event_date' => $date->toDateString(), 'all_day' => true, 'reminder_offset_minutes' => 0, 'created_at' => now(), 'updated_at' => now()]);
            $admin->table('calendar_reminder_recipients')->insert(['family_id' => DemoSeeder::POPESCU, 'calendar_item_id' => $eventId, 'membership_id' => $this->anaMember]);
            $admin->table('calendar_reminder_deliveries')->insert(['id' => (string) Str::uuid(), 'family_id' => DemoSeeder::POPESCU, 'calendar_item_id' => $eventId,
                'membership_id' => $this->anaMember, 'source_version' => 1, 'due_at' => $date->setTime(9, 0)->toIso8601String(), 'expires_at' => $date->addDay()->toIso8601String()]);
        }
        $this->getJson($this->home())->assertOk()->assertJsonCount(4, 'documents')->assertJsonCount(5, 'events')->assertJsonCount(5, 'reminders')->assertJsonPath('documents.0.title', 'Document 11');
        $this->assertSame($before, count(DB::getQueryLog()));
        DB::disableQueryLog();
    }

    public function test_invalid_months_are_rejected_and_empty_lists_are_explicit(): void
    {
        $this->actingAs($this->ana);
        foreach (['0000-01', '2101-01', '2026-13', 'invalid'] as $month) {
            $this->getJson($this->home($month))->assertUnprocessable();
        }
        $this->getJson($this->home('2026-10', DemoSeeder::IONESCU))->assertOk()->assertJsonCount(1, 'documents')->assertJsonPath('days', [])->assertJsonPath('events', [])->assertJsonPath('reminders', []);
    }
}
