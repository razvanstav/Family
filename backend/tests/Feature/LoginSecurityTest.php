<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class LoginSecurityTest extends TestCase
{
    /** @var list<User> */
    private array $fixtureUsers = [];

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
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.77']);
    }

    protected function tearDown(): void
    {
        try {
            DB::connection('admin')->table('users')->whereIn('id', array_map(fn (User $user): int => $user->id, $this->fixtureUsers))->delete();
        } finally {
            $this->travelBack();
            DB::disconnect('pgsql');
            DB::disconnect('admin');
            parent::tearDown();
        }
    }

    private function user(): User
    {
        $user = User::factory()->make(['email' => 'login-security-'.Str::uuid().'@example.test', 'password' => 'Strong-fixture-password-2026!']);
        $user->setConnection('admin')->save();
        $this->fixtureUsers[] = $user;

        return $user;
    }

    private function credentials(User $user, bool $correct = false): array
    {
        return ['email' => $user->email, 'password' => $correct ? 'Strong-fixture-password-2026!' : 'Wrong-fixture-password-2026!'];
    }

    public function test_authenticated_sessions_cannot_switch_accounts_or_multiply_the_ip_budget(): void
    {
        $victim = $this->user();
        $first = $this->user();
        $second = $this->user();
        $this->actingAs($first);
        for ($attempt = 1; $attempt <= 6; $attempt++) {
            $this->postJson('/api/v1/login', $this->credentials($victim, true))->assertStatus(409);
            $this->assertAuthenticatedAs($first);
        }
        $this->actingAs($second);
        $this->postJson('/api/v1/login', $this->credentials($victim))->assertStatus(429)->assertHeader('Retry-After');
        $this->assertAuthenticatedAs($second);
        Auth::logout();
        $this->postJson('/api/v1/login', $this->credentials($victim))->assertStatus(429);
        $this->assertGuest();
    }

    public function test_guest_ip_budget_applies_across_target_accounts_and_includes_invalid_requests(): void
    {
        for ($attempt = 1; $attempt <= 6; $attempt++) {
            $this->postJson('/api/v1/login', ['email' => 'unknown-'.$attempt.'@example.test', 'password' => 'Wrong-fixture-password-2026!'])->assertUnprocessable();
        }
        $this->postJson('/api/v1/login', ['email' => 'invalid', 'password' => 'wrong'])->assertStatus(429);
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.78']);
        for ($attempt = 1; $attempt <= 6; $attempt++) {
            $this->postJson('/api/v1/login', ['email' => 'invalid', 'password' => 'wrong'])->assertUnprocessable();
        }
        $this->postJson('/api/v1/login', ['email' => 'another@example.test', 'password' => 'wrong'])->assertStatus(429);
    }

    public function test_target_email_budget_spans_ip_addresses_and_expires_without_blocking_other_accounts(): void
    {
        $victim = $this->user();
        $other = $this->user();
        for ($attempt = 1; $attempt <= 10; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.'.$attempt]);
            $credentials = $this->credentials($victim);
            $credentials['email'] = $attempt % 2 ? Str::upper($victim->email) : $victim->email;
            $this->postJson('/api/v1/login', $credentials)->assertUnprocessable();
            $this->assertGuest();
        }
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.11']);
        $this->postJson('/api/v1/login', $this->credentials($victim, true))->assertStatus(429)->assertHeader('Retry-After');
        $this->postJson('/api/v1/login', $this->credentials($other, true))->assertOk()->assertJsonPath('user.id', $other->id);
        $this->assertAuthenticatedAs($other);
        $this->postJson('/api/v1/logout')->assertOk()->assertJsonPath('user', null);
        $this->travel(61)->seconds();
        $this->getJson('/api/v1/session')->assertOk()->assertJsonPath('user', null);
        $oldSession = session()->getId();
        $this->postJson('/api/v1/login', $this->credentials($victim, true))->assertOk()->assertJsonPath('user.id', $victim->id);
        $this->assertAuthenticatedAs($victim);
        $this->assertNotSame($oldSession, session()->getId());
    }

    public function test_malformed_email_values_do_not_crash_the_limiter(): void
    {
        $this->postJson('/api/v1/login', ['email' => ['unexpected'], 'password' => 'wrong'])->assertUnprocessable();
        $this->assertGuest();
    }
}
