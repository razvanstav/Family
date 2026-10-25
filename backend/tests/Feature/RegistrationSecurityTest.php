<?php

namespace Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

final class RegistrationSecurityTest extends TestCase
{
    private ?string $family = null;

    private ?int $user = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('memoria_test', config('database.connections.pgsql.database'));
        $admin = config('database.connections.pgsql');
        $admin['username'] = 'memoria_admin';
        $admin['password'] = env('DB_ADMIN_PASSWORD');
        config(['database.connections.admin' => $admin, 'cache.default' => 'array', 'cache.limiter' => 'array']);
        Cache::store('array')->flush();
        $this->freezeTime();
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.90']);
        Mail::fake();
        Notification::fake();
    }

    protected function tearDown(): void
    {
        try {
            if ($this->family !== null) {
                foreach (['calendar_reminder_syncs', 'audit_events', 'family_memberships'] as $table) {
                    DB::connection('admin')->table($table)->where('family_id', $this->family)->delete();
                }
                DB::connection('admin')->table('families')->where('id', $this->family)->delete();
            }
            if ($this->user !== null) {
                DB::connection('admin')->table('users')->where('id', $this->user)->delete();
            }
        } finally {
            $this->travelBack();
            DB::disconnect('pgsql');
            DB::disconnect('admin');
            parent::tearDown();
        }
    }

    private function invalidSignup(int $number): array
    {
        return ['email' => 'registration-limit-'.$number.'@example.test'];
    }

    public function test_registration_minute_budget_spans_email_values_and_allows_other_networks(): void
    {
        for ($attempt = 1; $attempt <= 6; $attempt++) {
            $this->postJson('/api/v1/register', $this->invalidSignup($attempt))->assertUnprocessable();
        }
        $this->postJson('/api/v1/register', $this->invalidSignup(7))->assertStatus(429)->assertHeader('Retry-After');
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.91']);
        $this->postJson('/api/v1/register', $this->invalidSignup(8))->assertUnprocessable();
    }

    public function test_registration_hour_budget_survives_minute_resets_and_then_expires(): void
    {
        for ($attempt = 1; $attempt <= 6; $attempt++) {
            $this->postJson('/api/v1/register', $this->invalidSignup($attempt))->assertUnprocessable();
        }
        $this->travel(61)->seconds();
        for ($attempt = 7; $attempt <= 10; $attempt++) {
            $this->postJson('/api/v1/register', $this->invalidSignup($attempt))->assertUnprocessable();
        }
        $this->postJson('/api/v1/register', $this->invalidSignup(11))->assertStatus(429)->assertHeader('Retry-After');
        $this->travel(3600)->seconds();
        $this->postJson('/api/v1/register', $this->invalidSignup(12))->assertUnprocessable();
    }

    public function test_registration_daily_budget_survives_hour_resets_and_is_not_permanent(): void
    {
        $started = CarbonImmutable::now();
        for ($hour = 0; $hour < 3; $hour++) {
            for ($attempt = 0; $attempt < 10; $attempt++) {
                $this->travelTo($started->addHours($hour)->addSeconds($attempt * 11));
                $this->postJson('/api/v1/register', $this->invalidSignup($hour * 10 + $attempt))->assertUnprocessable();
            }
        }
        $this->travelTo($started->addHours(3));
        $this->postJson('/api/v1/register', $this->invalidSignup(31))->assertStatus(429)->assertHeader('Retry-After');
        $this->travelTo($started->addDay()->addSecond());
        $this->postJson('/api/v1/register', $this->invalidSignup(32))->assertUnprocessable();
    }

    public function test_normal_signup_and_login_still_work_without_email_confirmation_or_mail(): void
    {
        $email = 'registration-security-'.Str::uuid().'@example.test';
        $password = 'Registration-fixture-password-2026!';
        $this->getJson('/api/v1/session')->assertOk();
        $oldSession = session()->getId();
        $response = $this->postJson('/api/v1/register', ['name' => 'Înregistrare Test', 'email' => $email,
            'password' => $password, 'family_name' => 'SR2 fixture'])->assertCreated();
        $this->family = $response->json('families.0.id');
        $this->user = $response->json('user.id');
        $response->assertJsonPath('families.0.role', 'owner')->assertJsonPath('user.email', $email);
        $this->assertNotSame($oldSession, session()->getId());
        $this->assertNull(DB::connection('admin')->table('users')->where('id', $this->user)->value('email_verified_at'));
        $this->postJson('/api/v1/logout')->assertOk()->assertJsonPath('user', null);
        $this->postJson('/api/v1/login', ['email' => $email, 'password' => $password])->assertOk()->assertJsonPath('user.id', $this->user);
        Mail::assertNothingSent();
        Notification::assertNothingSent();

    }
}
