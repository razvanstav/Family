<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class AccountTest extends TestCase
{
    private User $mihai;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('memoria_test', config('database.connections.pgsql.database'));
        $admin = config('database.connections.pgsql');
        $admin['username'] = 'memoria_admin';
        $admin['password'] = env('DB_ADMIN_PASSWORD');
        config(['database.connections.admin' => $admin]);
        DB::connection('admin')->statement('TRUNCATE families, users RESTART IDENTITY CASCADE');
        DB::setDefaultConnection('admin');
        $this->mihai = User::factory()->create(['name' => 'Mihai Popescu', 'email' => 'mihai@example.test']);
        User::factory()->create(['name' => 'Ana Popescu', 'email' => 'ana@example.test']);
        DB::table('families')->insert(['id' => DemoSeeder::POPESCU, 'name' => 'Familia Popescu']);
        DB::table('family_memberships')->insert(['id' => (string) Str::uuid(), 'family_id' => DemoSeeder::POPESCU, 'user_id' => $this->mihai->id, 'role' => 'adult']);
        DB::setDefaultConnection('pgsql');
    }

    protected function tearDown(): void
    {
        DB::disconnect('pgsql');
        DB::disconnect('admin');
        parent::tearDown();
    }

    public function test_only_the_signed_in_account_is_updated_and_the_session_keeps_membership_context(): void
    {
        $this->patchJson('/api/v1/account', ['name' => 'Mihai', 'expected_name' => $this->mihai->name])->assertUnauthorized();
        $email = $this->mihai->email;
        $password = $this->mihai->password;
        $this->actingAs($this->mihai)->patchJson('/api/v1/account', ['name' => 'Mihai Test', 'expected_name' => $this->mihai->name])
            ->assertOk()->assertExactJson(['id' => $this->mihai->id, 'name' => 'Mihai Test', 'email' => $email])
            ->assertHeader('Cache-Control', 'max-age=0, no-store, private');
        $this->mihai->refresh();
        $this->assertSame($password, $this->mihai->password);
        $this->assertSame('Ana Popescu', User::where('email', 'ana@example.test')->value('name'));
        $member = DB::table('family_memberships')->where('user_id', $this->mihai->id)->where('family_id', DemoSeeder::POPESCU)->value('id');
        $this->getJson('/api/v1/session')->assertOk()->assertJsonPath('user.name', 'Mihai Test')->assertJsonPath('families.0.membership_id', $member);
    }

    public function test_stale_or_invalid_edits_never_overwrite_the_current_name(): void
    {
        $this->actingAs($this->mihai);
        $this->patchJson('/api/v1/account', ['name' => 'Alt nume', 'expected_name' => 'Nume vechi'])->assertStatus(412);
        foreach (['', str_repeat('a', 101)] as $name) {
            $this->patchJson('/api/v1/account', ['name' => $name, 'expected_name' => $this->mihai->name])->assertUnprocessable();
        }
        $this->patchJson('/api/v1/account', ['name' => 'Alt nume'])->assertUnprocessable();
        $this->assertSame('Mihai Popescu', $this->mihai->fresh()->name);
    }

    public function test_account_editor_cannot_change_credentials_or_target_another_user(): void
    {
        $this->actingAs($this->mihai);
        foreach (['id' => 1, 'user_id' => 1, 'email' => 'other@example.test', 'password' => 'new-password'] as $field => $value) {
            $this->patchJson('/api/v1/account', ['name' => 'Alt nume', 'expected_name' => $this->mihai->name, $field => $value])->assertUnprocessable();
        }
        $this->assertSame('Mihai Popescu', $this->mihai->fresh()->name);
    }
}
