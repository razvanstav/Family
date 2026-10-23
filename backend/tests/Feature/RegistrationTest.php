<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

final class RegistrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('memoria_test', config('database.connections.pgsql.database'));
        $admin = config('database.connections.pgsql');
        $admin['username'] = 'memoria_admin';
        $admin['password'] = env('DB_ADMIN_PASSWORD');
        config(['database.connections.admin' => $admin]);
        DB::connection('admin')->statement('TRUNCATE families, users RESTART IDENTITY CASCADE');
        Mail::fake();
        Notification::fake();
    }

    protected function tearDown(): void
    {
        DB::disconnect('pgsql');
        DB::disconnect('admin');
        parent::tearDown();
    }

    private function signup(array $overrides = []): array
    {
        return array_replace(['name' => 'Ioana Test', 'email' => 'ioana@example.test', 'password' => 'Parola-Test-2026!', 'family_name' => 'Familia Test'], $overrides);
    }

    public function test_signup_creates_an_unverified_account_and_authenticates_without_confirmation_or_email(): void
    {
        $this->getJson('/api/v1/session')->assertOk()->assertJsonPath('user', null);
        $oldSession = session()->getId();
        $response = $this->postJson('/api/v1/register', $this->signup(['email' => 'Ioana@Example.Test']))
            ->assertCreated()->assertJsonPath('user.name', 'Ioana Test')->assertJsonPath('user.email', 'ioana@example.test')
            ->assertHeader('Cache-Control', 'max-age=0, no-store, private');
        $user = User::where('email', 'ioana@example.test')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($oldSession, session()->getId());
        $this->assertTrue(Hash::check('Parola-Test-2026!', $user->password));
        $this->assertNull($user->email_verified_at);
        $this->assertArrayNotHasKey('password', $response->json('user'));
        $family = $response->json('families.0.id');
        $response->assertJsonCount(1, 'families')->assertJsonPath('families.0.name', 'Familia Test')->assertJsonPath('families.0.role', 'owner');
        $this->assertSame(1, DB::table('families')->count());
        $this->assertDatabaseHas('family_memberships', ['id' => $response->json('families.0.membership_id'), 'family_id' => $family, 'user_id' => $user->id, 'role' => 'owner']);
        Mail::assertNothingSent();
        Notification::assertNothingSent();
        $this->getJson('/api/v1/session')->assertOk()->assertJsonPath('user.id', $user->id);
        $this->postJson('/api/v1/logout')->assertOk()->assertJsonPath('user', null);
        $this->postJson('/api/v1/login', ['email' => 'Ioana@Example.Test', 'password' => 'Parola-Test-2026!'])
            ->assertOk()->assertJsonPath('user.id', $user->id);
    }

    public function test_duplicate_address_is_case_insensitive_and_does_not_replace_or_log_into_an_existing_account(): void
    {
        DB::setDefaultConnection('admin');
        $existing = User::factory()->unverified()->create(['name' => 'Cont existent', 'email' => 'Ioana@Example.Test']);
        DB::setDefaultConnection('pgsql');
        $password = $existing->password;
        $this->postJson('/api/v1/register', $this->signup())->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->assertGuest();
        $this->assertSame(1, User::count());
        $this->assertSame($password, $existing->fresh()->password);
        $this->assertSame('Cont existent', $existing->fresh()->name);
        $this->assertSame(0, DB::table('family_memberships')->count());
    }

    #[TestWith(['name', ''])]
    #[TestWith(['email', 'invalid'])]
    #[TestWith(['password', 'short'])]
    #[TestWith(['family_name', ''])]
    #[TestWith(['invitation_code', ''])]
    #[TestWith(['invitation_code', null])]
    #[TestWith(['role', 'owner'])]
    #[TestWith(['family_id', '11111111-1111-4111-8111-111111111111'])]
    #[TestWith(['user_id', 1])]
    #[TestWith(['email_verified_at', '2026-10-23T00:00:00Z'])]
    public function test_invalid_signup_and_requested_privileges_create_no_data(string $field, mixed $value): void
    {
        $this->postJson('/api/v1/register', $this->signup([$field => $value]))->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertGuest();
        $this->assertSame(0, User::count());
        $this->assertSame(0, DB::table('families')->count());
    }

    public function test_authenticated_user_cannot_replace_their_current_identity_through_signup(): void
    {
        DB::setDefaultConnection('admin');
        $existing = User::factory()->create();
        DB::setDefaultConnection('pgsql');
        $this->actingAs($existing)->postJson('/api/v1/register', $this->signup())->assertConflict();
        $this->assertAuthenticatedAs($existing);
        $this->assertSame(1, User::count());
    }

    /** @return array{owner: User, family: string} */
    private function familyOwner(string $name = 'Familia Popescu'): array
    {
        DB::setDefaultConnection('admin');
        $owner = User::factory()->create();
        $family = (string) Str::uuid();
        DB::table('families')->insert(['id' => $family, 'name' => $name]);
        DB::table('family_memberships')->insert(['id' => (string) Str::uuid(), 'family_id' => $family, 'user_id' => $owner->id, 'role' => 'owner']);
        DB::setDefaultConnection('pgsql');

        return compact('owner', 'family');
    }

    private function code(User $owner, string $family): array
    {
        return $this->actingAs($owner)->postJson('/api/v1/families/'.$family.'/invitation-codes')->assertCreated()->json();
    }

    public function test_owner_can_manage_hashed_codes_but_guests_other_families_and_adults_cannot(): void
    {
        ['owner' => $owner, 'family' => $family] = $this->familyOwner();
        $path = '/api/v1/families/'.$family.'/invitation-codes';
        $this->getJson($path)->assertUnauthorized();
        $code = $this->code($owner, $family);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]{43}$/', $code['code']);
        $this->assertSame(hash('sha256', $code['code']), DB::connection('admin')->table('family_invitation_codes')->where('id', $code['id'])->value('token_hash'));
        $this->getJson($path)->assertOk()->assertJsonCount(1)->assertJsonPath('0.id', $code['id'])->assertJsonMissingPath('0.code')->assertJsonMissingPath('0.token_hash');
        $this->assertSame(0, DB::table('family_invitation_codes')->count());
        ['owner' => $other] = $this->familyOwner('Altă familie');
        $this->actingAs($other)->getJson($path)->assertNotFound();
        $this->postJson($path)->assertNotFound();
        $this->deleteJson($path.'/'.$code['id'])->assertNotFound();
        DB::connection('admin')->table('family_memberships')->insert(['id' => (string) Str::uuid(), 'family_id' => $family, 'user_id' => $other->id, 'role' => 'adult']);
        $this->getJson($path)->assertForbidden();
        $this->postJson($path)->assertForbidden();
        $this->deleteJson($path.'/'.$code['id'])->assertForbidden();
        $this->actingAs($owner)->deleteJson($path.'/'.$code['id'])->assertNoContent();
        $this->deleteJson($path.'/'.$code['id'])->assertNotFound();
        $this->getJson($path)->assertOk();
        $this->assertNotNull(DB::connection('admin')->table('family_invitation_codes')->where('id', $code['id'])->value('revoked_at'));
    }

    public function test_code_joins_only_its_family_as_adult_and_is_single_use(): void
    {
        ['owner' => $owner, 'family' => $family] = $this->familyOwner();
        $code = $this->code($owner, $family);
        Auth::logout();
        $response = $this->postJson('/api/v1/register', $this->signup(['family_name' => 'familia POPESCU', 'invitation_code' => $code['code']]))
            ->assertCreated()->assertJsonCount(1, 'families')->assertJsonPath('families.0.id', $family)->assertJsonPath('families.0.role', 'adult');
        $newUser = $response->json('user.id');
        $this->assertDatabaseHas('family_memberships', ['family_id' => $family, 'user_id' => $newUser, 'role' => 'adult']);
        $this->assertSame($newUser, DB::connection('admin')->table('family_invitation_codes')->where('id', $code['id'])->value('used_by'));
        $this->assertSame(1, DB::table('families')->count());
        Auth::logout();
        $this->postJson('/api/v1/register', $this->signup(['email' => 'second@example.test', 'family_name' => 'Familia Popescu', 'invitation_code' => $code['code']]))
            ->assertUnprocessable()->assertJsonValidationErrors('invitation_code');
        $this->assertGuest();
        $this->assertSame(2, User::count());
        $this->assertSame(1, DB::table('families')->count());
        $this->actingAs($owner)->deleteJson('/api/v1/families/'.$family.'/invitation-codes/'.$code['id'])->assertNotFound();
    }

    #[TestWith(['expired'])]
    #[TestWith(['revoked'])]
    #[TestWith(['owner_revoked'])]
    #[TestWith(['wrong_name'])]
    #[TestWith(['forged_code'])]
    public function test_invalid_codes_have_one_error_and_leave_no_orphan_account(string $state): void
    {
        ['owner' => $owner, 'family' => $family] = $this->familyOwner();
        $code = $this->code($owner, $family);
        $name = 'Familia Popescu';
        if ($state === 'expired') {
            DB::connection('admin')->table('family_invitation_codes')->where('id', $code['id'])->update(['expires_at' => now()->subSecond()]);
        } elseif ($state === 'revoked') {
            DB::connection('admin')->table('family_invitation_codes')->where('id', $code['id'])->update(['revoked_at' => now()]);
        } elseif ($state === 'owner_revoked') {
            DB::connection('admin')->table('family_memberships')->where('family_id', $family)->where('user_id', $owner->id)->update(['revoked_at' => now()]);
        } elseif ($state === 'wrong_name') {
            $name = 'Altă familie';
        } else {
            $code['code'] = substr($code['code'], 0, 40).'AAA';
        }
        Auth::logout();
        $this->postJson('/api/v1/register', $this->signup(['family_name' => $name, 'invitation_code' => $code['code']]))
            ->assertUnprocessable()->assertJsonPath('errors.invitation_code.0', 'Numele familiei sau codul de invitație nu este valid. Cere un cod nou administratorului.');
        $this->assertGuest();
        $this->assertSame(1, User::count());
        $this->assertSame(1, DB::table('families')->count());
        $this->assertSame(1, DB::table('family_memberships')->count());
    }

    public function test_duplicate_email_does_not_consume_code(): void
    {
        ['owner' => $owner, 'family' => $family] = $this->familyOwner();
        $code = $this->code($owner, $family);
        Auth::logout();
        $this->postJson('/api/v1/register', $this->signup(['email' => Str::upper($owner->email), 'family_name' => 'Familia Popescu', 'invitation_code' => $code['code']]))
            ->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->assertNull(DB::connection('admin')->table('family_invitation_codes')->where('id', $code['id'])->value('used_at'));
        $this->assertSame(1, User::count());
        $this->postJson('/api/v1/register', $this->signup(['family_name' => 'Familia Popescu', 'invitation_code' => $code['code']]))->assertCreated();
    }

    public function test_signup_is_rate_limited(): void
    {
        for ($attempt = 0; $attempt < 6; $attempt++) {
            $this->postJson('/api/v1/register', $this->signup(['email' => 'invalid']))->assertUnprocessable();
        }
        $this->postJson('/api/v1/register', $this->signup())->assertTooManyRequests();
        $this->assertSame(0, User::count());
    }

    public function test_new_family_with_an_existing_name_never_attaches_to_that_family(): void
    {
        ['family' => $existingFamily] = $this->familyOwner('Familia Test');
        $response = $this->postJson('/api/v1/register', $this->signup())->assertCreated()->assertJsonPath('families.0.role', 'owner');
        $this->assertNotSame($existingFamily, $response->json('families.0.id'));
        $this->assertSame(2, DB::table('families')->count());
        $this->assertDatabaseMissing('family_memberships', ['family_id' => $existingFamily, 'user_id' => $response->json('user.id')]);
    }
}
