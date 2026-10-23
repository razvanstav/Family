<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

final class BirthdayCalendarTest extends TestCase
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
        (new DemoSeeder)->run();
        DB::setDefaultConnection('pgsql');
        Storage::fake('local');
        $this->ana = User::where('email', 'ana@example.test')->firstOrFail();
        $this->mihai = User::where('email', 'mihai@example.test')->firstOrFail();
        $members = DB::table('family_memberships')->where('family_id', DemoSeeder::POPESCU)->pluck('id', 'user_id');
        $this->anaMember = $members[$this->ana->id];
        $this->mihaiMember = $members[$this->mihai->id];
        $this->base = '/api/v1/families/'.DemoSeeder::POPESCU;
        $this->actingAs($this->ana);
        $this->travelTo(CarbonImmutable::parse('2026-10-20T06:00:00Z'));
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        DB::disconnect('pgsql');
        DB::disconnect('admin');
        parent::tearDown();
    }

    private function profile(string $member, string $birth, bool $shared = true, int $version = 0, array $parents = []): TestResponse
    {
        return $this->withHeader('If-Match', '"'.$version.'"')->patchJson($this->base.'/members/'.$member.'/profile', ['birth_date' => $birth, 'parent_ids' => $parents, 'birthday_shared' => $shared]);
    }

    private function event(string $member, string $month = '2026-10'): array
    {
        $items = $this->getJson($this->base.'/calendar-items?month='.$month)->assertOk()->json();
        $event = collect($items)->first(fn (array $item) => ($item['source']['parent_id'] ?? null) === $member);
        $this->assertNotNull($event);

        return $event;
    }

    private function reminder(array $event, ?int $offset, int $version = 0): TestResponse
    {
        return $this->withHeader('If-Match', '"'.$version.'"')->patchJson($this->base.'/calendar-items/'.$event['id'].'/birthday-reminder', ['offset_minutes' => $offset, 'source_version' => $event['version']]);
    }

    private function deliver(): void
    {
        $this->artisan('calendar:deliver-reminders')->assertSuccessful();
    }

    public function test_birthdays_require_explicit_sharing_and_do_not_disclose_birth_year(): void
    {
        $this->profile($this->anaMember, '1990-11-10', false)->assertOk()->assertJsonPath('birthday_shared', false);
        $this->getJson($this->base.'/calendar-items?month=2026-11')->assertExactJson([]);
        $this->profile($this->anaMember, '1990-11-10', true, 1)->assertOk();
        $event = $this->event($this->anaMember, '2026-11');
        $this->assertSame('2026-11-10', $event['event_date']);
        $this->assertSame('birthday', $event['source']['type']);
        $this->assertFalse($event['can_edit']);
        $this->assertNull($event['my_reminder_offset_minutes']);
        $this->assertNull($event['reminder']);
        $this->assertStringNotContainsString('1990', json_encode($event));
        $this->actingAs($this->mihai)->getJson($this->base.'/members/'.$this->anaMember.'/profile')->assertJsonPath('birth_date', null);
        $this->assertSame('2027-11-10', $this->event($this->anaMember, '2027-11')['event_date']);
        $this->getJson('/api/v1/families/'.DemoSeeder::IONESCU.'/calendar-items/'.$event['id'])->assertNotFound();
    }

    public function test_leap_day_uses_february_28_in_non_leap_years(): void
    {
        $this->profile($this->anaMember, '2000-02-29')->assertOk();
        $this->assertSame('2027-02-28', $this->event($this->anaMember, '2027-02')['event_date']);
        $this->assertSame('2028-02-29', $this->event($this->anaMember, '2028-02')['event_date']);
        $this->getJson($this->base.'/calendar-items?month=1999-02')->assertExactJson([]);
    }

    public function test_personal_preferences_do_not_cancel_or_resend_other_members_notifications(): void
    {
        $this->profile($this->anaMember, '1990-10-28')->assertOk();
        $event = $this->event($this->anaMember);
        $this->reminder($event, 1440)->assertOk()->assertJsonPath('my_reminder_offset_minutes', 1440)->assertJsonPath('my_reminder_version', 1);
        $this->actingAs($this->mihai);
        $this->reminder($event, 10080)->assertOk()->assertJsonPath('my_reminder_offset_minutes', 10080);
        $this->travelTo(CarbonImmutable::parse('2026-10-21T06:00:00Z'));
        $this->deliver();
        $this->getJson($this->base.'/calendar-notifications')->assertJsonCount(1, 'items');
        $this->actingAs($this->ana);
        $this->reminder($event, 0, 1)->assertOk();
        $this->getJson($this->base.'/calendar-notifications')->assertJsonCount(0, 'items');
        $this->actingAs($this->mihai)->getJson($this->base.'/calendar-notifications')->assertJsonCount(1, 'items');
        $this->deliver();
        $this->getJson($this->base.'/calendar-notifications')->assertJsonCount(1, 'items');
        $this->reminder($event, null, 1)->assertOk();
        $this->getJson($this->base.'/calendar-notifications')->assertJsonCount(0, 'items');
        $this->assertSame(2, DB::connection('admin')->table('calendar_reminder_deliveries')->where('membership_id', $this->anaMember)->where('state', 'pending')->count());
    }

    public function test_preference_recurs_next_year_and_job_retry_is_idempotent(): void
    {
        $this->profile($this->anaMember, '1990-01-02')->assertOk();
        $event = $this->event($this->anaMember, '2027-01');
        $this->reminder($event, 10080)->assertOk();
        $this->travelTo(CarbonImmutable::parse('2026-12-26T07:00:00Z'));
        $this->deliver();
        $this->deliver();
        $this->getJson($this->base.'/calendar-notifications')->assertJsonCount(1, 'items')->assertJsonPath('items.0.event_id', $event['id']);
        $this->travelTo(CarbonImmutable::parse('2027-12-26T07:00:00Z'));
        $this->deliver();
        $this->deliver();
        $this->getJson($this->base.'/calendar-notifications')->assertJsonCount(2, 'items')->assertJsonPath('items.0.event_date', '2028-01-02');
        $this->assertSame(10080, $this->event($this->anaMember, '2028-01')['my_reminder_offset_minutes']);
    }

    public function test_date_changes_and_unsharing_invalidate_old_notifications_immediately(): void
    {
        $this->profile($this->anaMember, '1990-10-28')->assertOk();
        $event = $this->event($this->anaMember);
        $this->reminder($event, 0)->assertOk();
        $this->travelTo(CarbonImmutable::parse('2026-10-28T07:00:00Z'));
        $this->deliver();
        $this->getJson($this->base.'/calendar-notifications')->assertJsonCount(1, 'items');
        $this->profile($this->anaMember, '1990-10-29', true, 1)->assertOk();
        $this->assertSame($event['id'], $this->event($this->anaMember)['id']);
        $this->getJson($this->base.'/calendar-notifications')->assertJsonCount(0, 'items');
        $this->profile($this->anaMember, '1990-10-29', false, 2)->assertOk();
        $this->getJson($this->base.'/calendar-items?month=2026-10')->assertExactJson([]);
        $this->getJson($this->base.'/calendar-items/'.$event['id'])->assertNotFound();
        $this->travelTo(CarbonImmutable::parse('2026-10-29T07:00:00Z'));
        $this->deliver();
        $this->getJson($this->base.'/calendar-notifications')->assertJsonCount(0, 'items');
    }

    public function test_preference_versions_and_source_versions_prevent_stale_updates(): void
    {
        $this->profile($this->anaMember, '1990-10-28')->assertOk();
        $event = $this->event($this->anaMember);
        $this->reminder($event, 0)->assertOk();
        $this->reminder($event, 1440)->assertStatus(412);
        $this->assertSame(0, $this->event($this->anaMember)['my_reminder_offset_minutes']);
        $this->withHeader('If-Match', '"1"')->patchJson($this->base.'/calendar-items/'.$event['id'].'/birthday-reminder', ['offset_minutes' => 1440, 'source_version' => 999])->assertStatus(412);
        $this->patchJson($this->base.'/calendar-items/'.$event['id'].'/birthday-reminder', ['offset_minutes' => 1440, 'source_version' => $event['version'], 'recipient_id' => $this->mihaiMember])->assertUnprocessable();
        DB::connection('admin')->table('family_memberships')->where('id', $this->mihaiMember)->update(['role' => 'limited']);
        $this->actingAs($this->mihai);
        $this->reminder($event, 10080)->assertOk();
        $this->profile($this->anaMember, '1990-10-28', false, 1)->assertForbidden();
    }

    public function test_profile_changes_outside_the_shared_day_do_not_remove_delivered_reminders(): void
    {
        $this->profile($this->anaMember, '1990-10-28')->assertOk();
        $event = $this->event($this->anaMember);
        $this->reminder($event, 0)->assertOk();
        $this->travelTo(CarbonImmutable::parse('2026-10-28T07:00:00Z'));
        $this->deliver();
        $this->profile($this->anaMember, '1991-10-28', true, 1)->assertOk();
        $this->getJson($this->base.'/calendar-notifications')->assertJsonCount(1, 'items');
        $this->assertSame($event['version'], $this->event($this->anaMember)['version']);
        DB::connection('admin')->table('users')->where('id', $this->ana->id)->update(['name' => 'Ana Test']);
        $this->deliver();
        $this->getJson($this->base.'/calendar-notifications')->assertJsonCount(1, 'items')->assertJsonPath('items.0.title', 'Ziua de naștere · Ana Test');
    }

    public function test_revocation_rechecks_subject_and_recipient_and_does_not_restore_preferences_on_readmission(): void
    {
        $this->profile($this->mihaiMember, '1988-10-28')->assertOk();
        $event = $this->event($this->mihaiMember);
        $this->reminder($event, 10080)->assertOk();
        $this->actingAs($this->mihai);
        $this->reminder($event, 10080)->assertOk();
        $this->actingAs($this->ana)->deleteJson($this->base.'/members/'.$this->mihaiMember)->assertNoContent();
        $this->getJson($this->base.'/calendar-items?month=2026-10')->assertExactJson([]);
        $this->travelTo(CarbonImmutable::parse('2026-10-21T06:00:00Z'));
        $this->deliver();
        $this->getJson($this->base.'/calendar-notifications')->assertJsonCount(0, 'items');
        $this->assertSame(0, DB::connection('admin')->table('birthday_reminder_preferences')->count());
        DB::connection('admin')->table('family_memberships')->where('id', $this->mihaiMember)->update(['revoked_at' => null]);
        $this->assertNull($this->event($this->mihaiMember)['my_reminder_offset_minutes']);
    }

    public function test_child_without_account_is_a_birthday_subject_and_never_a_reminder_recipient(): void
    {
        $users = DB::table('users')->count();
        $child = $this->postJson($this->base.'/members/children', ['name' => 'Copil test', 'birth_date' => '2020-11-04', 'parent_ids' => [$this->anaMember]])->assertCreated()->json('id');
        $this->profile($child, '2020-11-04', true, 1, [$this->anaMember])->assertOk();
        $event = $this->event($child, '2026-11');
        $this->reminder($event, 0)->assertOk();
        $this->assertSame($users, DB::table('users')->count());
        $this->assertNull(DB::connection('admin')->table('family_memberships')->where('id', $child)->value('user_id'));
        $this->assertSame(0, DB::connection('admin')->table('calendar_reminder_recipients')->where('membership_id', $child)->count());
    }
}
