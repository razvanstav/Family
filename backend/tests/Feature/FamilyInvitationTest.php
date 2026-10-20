<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

final class FamilyInvitationTest extends TestCase
{
    private User $ana;

    private User $mihai;

    private User $elena;

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
        $this->elena = User::where('email', 'elena@example.test')->firstOrFail();
    }

    protected function tearDown(): void
    {
        DB::disconnect('pgsql');
        DB::disconnect('admin');
        parent::tearDown();
    }

    private function path(string $suffix = ''): string
    {
        return '/api/v1/families/'.DemoSeeder::POPESCU.'/invitations'.$suffix;
    }

    private function acceptPath(): string
    {
        return '/api/v1/invitations/'.DemoSeeder::POPESCU.'/accept';
    }

    private function invite(string $email = 'elena@example.test', string $role = 'adult'): array
    {
        return $this->actingAs($this->ana)->postJson($this->path(), compact('email', 'role'))->assertCreated()->json();
    }

    public function test_guests_and_outsiders_cannot_manage_invitations(): void
    {
        $this->postJson($this->path(), ['email' => 'new@example.test', 'role' => 'adult'])->assertUnauthorized();
        $this->actingAs($this->elena)->getJson($this->path())->assertNotFound();
        $this->postJson($this->path(), ['email' => 'new@example.test', 'role' => 'adult'])->assertNotFound();
        $this->assertDatabaseCount('family_invitations', 0, 'admin');
    }

    #[TestWith(['adult'])]
    #[TestWith(['limited'])]
    public function test_non_owners_cannot_create_list_or_cancel_invitations(string $role): void
    {
        $invite = $this->invite();
        DB::connection('admin')->table('family_memberships')->where('user_id', $this->mihai->id)->update(['role' => $role]);
        $this->actingAs($this->mihai)->postJson($this->path(), ['email' => 'new@example.test', 'role' => 'adult'])->assertForbidden();
        $this->getJson($this->path())->assertForbidden();
        $this->deleteJson($this->path('/'.$invite['id']))->assertForbidden();
        $this->assertDatabaseHas('family_invitations', ['id' => $invite['id'], 'cancelled_at' => null], 'admin');
        $this->assertDatabaseCount('family_invitations', 1, 'admin');
    }

    public function test_owner_creates_scoped_hashed_invitation_without_adding_member(): void
    {
        $this->freezeTime();
        $invite = $this->invite('ELENA@example.test', 'limited');

        $stored = DB::connection('admin')->table('family_invitations')->first();
        $this->assertNotSame($invite['token'], $stored->token_hash);
        $this->assertSame('elena@example.test', $stored->email);
        $this->assertSame('limited', $stored->role);
        $this->assertDatabaseCount('family_memberships', 4, 'admin');
        $this->getJson($this->path())->assertOk()->assertJsonCount(1)->assertJsonMissingPath('0.token_hash')->assertJsonMissingPath('0.token');
        $this->assertDatabaseHas('audit_events', ['action' => 'invitation.created', 'resource_id' => $invite['id']], 'admin');
        $this->assertSame(0, DB::table('family_invitations')->count());
        $this->actingAs($this->elena)->deleteJson('/api/v1/families/'.DemoSeeder::IONESCU.'/invitations/'.$invite['id'])->assertNotFound();
    }

    public function test_wrong_account_and_guest_cannot_accept_an_existing_accounts_invitation(): void
    {
        $invite = $this->invite();
        $this->actingAs($this->mihai)->postJson($this->acceptPath(), ['token' => $invite['token']])->assertForbidden();
        Auth::logout();
        $this->postJson($this->acceptPath(), ['token' => $invite['token'], 'name' => 'Fake', 'password' => 'Something-long123!', 'password_confirmation' => 'Something-long123!'])->assertUnauthorized();
        $this->assertDatabaseCount('family_memberships', 4, 'admin');
        $this->assertDatabaseHas('family_invitations', ['id' => $invite['id'], 'accepted_at' => null], 'admin');
    }

    public function test_acceptance_adds_only_invited_role_and_keeps_private_documents_private(): void
    {
        $invite = $this->invite('elena@example.test', 'limited');
        $this->actingAs($this->elena)->postJson($this->acceptPath(), [
            'token' => $invite['token'], 'role' => 'owner', 'family_id' => DemoSeeder::IONESCU,
        ])->assertOk()->assertJsonCount(2, 'families');
        $this->assertDatabaseHas('family_memberships', ['family_id' => DemoSeeder::POPESCU, 'user_id' => $this->elena->id, 'role' => 'limited'], 'admin');
        $this->assertDatabaseHas('families', ['id' => DemoSeeder::POPESCU, 'authz_revision' => 2], 'admin');
        $this->assertDatabaseHas('audit_events', ['action' => 'invitation.accepted', 'actor_id' => $this->elena->id], 'admin');
        $this->getJson('/api/v1/families/'.DemoSeeder::POPESCU.'/documents')->assertOk()->assertJsonCount(2)
            ->assertJsonMissing(['title' => 'Documentul privat al lui Mihai']);
        $this->postJson('/api/v1/families/'.DemoSeeder::POPESCU.'/documents', [])->assertForbidden();
        $this->postJson($this->acceptPath(), ['token' => $invite['token']])->assertNotFound();
        $this->assertDatabaseCount('family_memberships', 5, 'admin');
    }

    public function test_new_account_acceptance_hashes_password_and_does_not_claim_verified_email(): void
    {
        $invite = $this->invite('nou@example.test');
        Auth::logout();
        $this->postJson($this->acceptPath(), [
            'token' => $invite['token'], 'name' => 'Membru Nou', 'password' => 'Invitatie-Test-2026!',
            'password_confirmation' => 'Invitatie-Test-2026!', 'email' => 'different@example.test',
        ])->assertOk()->assertJsonPath('user.email', 'nou@example.test')->assertJsonCount(1, 'families');
        $user = User::where('email', 'nou@example.test')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertTrue(Hash::check('Invitatie-Test-2026!', $user->password));
        $this->assertNull($user->email_verified_at);
        $this->assertDatabaseHas('family_memberships', ['user_id' => $user->id, 'role' => 'adult'], 'admin');
    }

    public function test_cancelled_expired_and_cross_family_tokens_cannot_be_used(): void
    {
        $this->freezeTime();
        $invite = $this->invite();
        $this->postJson('/api/v1/invitations/'.DemoSeeder::IONESCU.'/inspect', ['token' => $invite['token']])->assertNotFound();
        $this->deleteJson($this->path('/'.$invite['id']))->assertNoContent();
        $this->actingAs($this->elena)->postJson($this->acceptPath(), ['token' => $invite['token']])->assertNotFound();
        $fresh = $this->invite();
        $this->travel(7)->days();
        $this->actingAs($this->elena)->postJson($this->acceptPath(), ['token' => $fresh['token']])->assertNotFound();
        $this->assertDatabaseCount('family_memberships', 4, 'admin');
    }

    public function test_duplicate_member_pending_invite_and_owner_role_are_rejected(): void
    {
        $this->actingAs($this->ana)->postJson($this->path(), ['email' => 'mihai@example.test', 'role' => 'adult'])->assertUnprocessable();
        $this->postJson($this->path(), ['email' => 'new@example.test', 'role' => 'owner'])->assertUnprocessable();
        $this->postJson($this->path(), ['email' => 'invalid', 'role' => 'adult'])->assertUnprocessable();
        $this->invite();
        $this->postJson($this->path(), ['email' => 'ELENA@example.test', 'role' => 'adult'])->assertUnprocessable();
        $this->assertDatabaseCount('family_invitations', 1, 'admin');
    }

    public function test_invalid_registration_does_not_consume_invitation(): void
    {
        $invite = $this->invite('nou@example.test');
        Auth::logout();
        $this->postJson($this->acceptPath(), ['token' => $invite['token'], 'name' => 'Nou', 'password' => 'short', 'password_confirmation' => 'wrong'])
            ->assertUnprocessable()->assertJsonValidationErrors(['password']);
        $this->assertDatabaseMissing('users', ['email' => 'nou@example.test'], 'admin');
        $this->assertDatabaseHas('family_invitations', ['id' => $invite['id'], 'accepted_at' => null], 'admin');
    }

    public function test_revocation_blocks_new_member_and_old_invitation_cannot_restore_access(): void
    {
        $invite = $this->invite();
        $this->actingAs($this->elena)->postJson($this->acceptPath(), ['token' => $invite['token']])->assertOk();
        $member = DB::connection('admin')->table('family_memberships')->where('family_id', DemoSeeder::POPESCU)->where('user_id', $this->elena->id)->first();
        $this->actingAs($this->ana)->deleteJson('/api/v1/families/'.DemoSeeder::POPESCU.'/members/'.$member->id)->assertNoContent();
        $this->postJson($this->path(), ['email' => 'elena@example.test', 'role' => 'adult'])->assertUnprocessable();
        $this->actingAs($this->elena)->getJson('/api/v1/families/'.DemoSeeder::POPESCU.'/members')->assertNotFound();
        $this->postJson($this->acceptPath(), ['token' => $invite['token']])->assertNotFound();
    }

    public function test_invitation_from_revoked_owner_is_invalid(): void
    {
        $invite = $this->invite();
        DB::connection('admin')->table('family_memberships')->where('family_id', DemoSeeder::POPESCU)->where('user_id', $this->ana->id)->update(['revoked_at' => now()]);
        $this->actingAs($this->elena)->postJson($this->acceptPath(), ['token' => $invite['token']])->assertNotFound();
        $this->assertDatabaseCount('family_memberships', 4, 'admin');
    }

    public function test_csrf_protects_creation_cancellation_and_guest_acceptance(): void
    {
        $invite = $this->invite('nou@example.test');
        $this->app->detectEnvironment(fn () => 'production');
        $this->postJson($this->path(), ['email' => 'other@example.test', 'role' => 'adult'])->assertStatus(419);
        $this->deleteJson($this->path('/'.$invite['id']))->assertStatus(419);
        Auth::logout();
        $this->postJson($this->acceptPath(), ['token' => $invite['token']])->assertStatus(419);
        $this->assertDatabaseHas('family_invitations', ['id' => $invite['id'], 'accepted_at' => null, 'cancelled_at' => null], 'admin');
    }
}
