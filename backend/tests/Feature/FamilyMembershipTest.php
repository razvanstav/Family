<?php

namespace Tests\Feature;

use App\Models\User;
use App\Platform\Media\Scanner;
use Database\Seeders\DemoSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

final class FamilyMembershipTest extends TestCase
{
    private User $ana;

    private User $mihai;

    private User $elena;

    private string $member;

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
        $this->member = DB::table('family_memberships')->where('family_id', DemoSeeder::POPESCU)->where('user_id', $this->mihai->id)->value('id');
    }

    protected function tearDown(): void
    {
        DB::disconnect('pgsql');
        DB::disconnect('admin');
        parent::tearDown();
    }

    private function path(?string $member = null, string $family = DemoSeeder::POPESCU): string
    {
        return '/api/v1/families/'.$family.'/members/'.($member ?? $this->member).'/role';
    }

    public function test_guests_and_non_members_cannot_change_roles(): void
    {
        $this->patchJson($this->path(), ['role' => 'limited', 'expected_role' => 'adult'])->assertUnauthorized();
        $this->actingAs($this->elena)->patchJson($this->path(), ['role' => 'limited', 'expected_role' => 'adult'])->assertNotFound();
        $this->assertDatabaseHas('family_memberships', ['id' => $this->member, 'role' => 'adult'], 'admin');
    }

    #[TestWith(['adult'])]
    #[TestWith(['limited'])]
    public function test_only_owner_can_change_member_roles(string $role): void
    {
        DB::connection('admin')->table('family_memberships')->where('id', $this->member)->update(['role' => $role]);
        $this->actingAs($this->mihai)->patchJson($this->path(), ['role' => 'adult', 'expected_role' => $role])->assertForbidden();
        $this->assertDatabaseHas('family_memberships', ['id' => $this->member, 'role' => $role], 'admin');
        $this->assertDatabaseMissing('audit_events', ['action' => 'membership.role_changed'], 'admin');
    }

    public function test_owner_role_is_protected_and_owner_promotion_is_not_accepted(): void
    {
        $owner = DB::table('family_memberships')->where('family_id', DemoSeeder::POPESCU)->where('user_id', $this->ana->id)->value('id');
        $this->actingAs($this->ana)->patchJson($this->path($owner), ['role' => 'limited', 'expected_role' => 'owner'])->assertUnprocessable();
        $this->patchJson($this->path(), ['role' => 'owner', 'expected_role' => 'adult'])->assertUnprocessable()->assertJsonValidationErrors('role');
        $this->patchJson($this->path(), ['role' => 'limited'])->assertUnprocessable()->assertJsonValidationErrors('expected_role');
        $this->assertDatabaseHas('family_memberships', ['id' => $owner, 'role' => 'owner'], 'admin');
        $this->assertDatabaseHas('family_memberships', ['id' => $this->member, 'role' => 'adult'], 'admin');
    }

    public function test_downgrade_takes_effect_in_existing_session_and_preserves_document_ownership(): void
    {
        $private = DB::connection('admin')->table('documents')->where('title', 'Documentul privat al lui Mihai')->firstOrFail();
        $this->actingAs($this->ana)->patchJson($this->path(), [
            'role' => 'limited', 'expected_role' => 'adult', 'family_id' => DemoSeeder::IONESCU, 'user_id' => $this->ana->id,
        ])->assertNoContent();
        $this->assertDatabaseHas('families', ['id' => DemoSeeder::POPESCU, 'authz_revision' => 2], 'admin');
        $this->assertDatabaseHas('audit_events', ['action' => 'membership.role_changed', 'resource_id' => $this->member, 'actor_id' => $this->ana->id], 'admin');
        $this->getJson('/api/v1/families/'.DemoSeeder::POPESCU.'/documents/'.$private->id)->assertNotFound();

        $this->actingAs($this->mihai)->getJson('/api/v1/session')->assertOk()->assertJsonPath('families.0.role', 'limited');
        $this->postJson('/api/v1/families/'.DemoSeeder::POPESCU.'/documents', [])->assertForbidden();
        $this->getJson('/api/v1/families/'.DemoSeeder::POPESCU.'/documents/'.$private->id)->assertOk()->assertJsonPath('visibility', 'private');
        $this->getJson('/api/v1/families/'.DemoSeeder::POPESCU.'/documents/'.$private->id.'/content')->assertOk();
        $this->assertDatabaseHas('documents', ['id' => $private->id, 'owner_id' => $this->mihai->id, 'visibility' => 'private'], 'admin');
        $this->assertDatabaseHas('family_memberships', ['family_id' => DemoSeeder::IONESCU, 'user_id' => $this->ana->id, 'role' => 'adult'], 'admin');
    }

    public function test_promotion_allows_upload_without_revealing_other_adults_private_documents(): void
    {
        DB::connection('admin')->table('family_memberships')->where('id', $this->member)->update(['role' => 'limited']);
        $this->actingAs($this->ana)->patchJson($this->path(), ['role' => 'adult', 'expected_role' => 'limited'])->assertNoContent();
        $this->app->instance(Scanner::class, new class implements Scanner
        {
            public function scan(string $path): string
            {
                return 'clean';
            }
        });
        $this->actingAs($this->mihai)->postJson('/api/v1/families/'.DemoSeeder::POPESCU.'/documents', [
            'title' => 'Document după schimbarea rolului', 'category' => 'personal',
            'file' => UploadedFile::fake()->createWithContent('rol.pdf', DemoSeeder::samplePdf()),
        ])->assertCreated()->assertJsonPath('visibility', 'private')->assertJsonPath('owner_id', $this->mihai->id);
        $otherPrivate = DB::connection('admin')->table('documents')->where('title', 'Dosarul meu personal')->value('id');
        $this->getJson('/api/v1/families/'.DemoSeeder::POPESCU.'/documents/'.$otherPrivate)->assertNotFound();
        $this->assertDatabaseHas('family_memberships', ['id' => $this->member, 'role' => 'adult'], 'admin');
    }

    public function test_stale_role_change_is_rejected_without_overwriting_newer_selection(): void
    {
        $this->actingAs($this->ana)->patchJson($this->path(), ['role' => 'limited', 'expected_role' => 'adult'])->assertNoContent();
        $this->patchJson($this->path(), ['role' => 'adult', 'expected_role' => 'adult'])->assertConflict();
        $this->assertDatabaseHas('family_memberships', ['id' => $this->member, 'role' => 'limited'], 'admin');
        $this->assertSame(1, DB::connection('admin')->table('audit_events')->where('action', 'membership.role_changed')->count());
    }

    public function test_unchanged_role_does_not_create_audit_or_invalidate_family_revision(): void
    {
        $this->actingAs($this->ana)->patchJson($this->path(), ['role' => 'adult', 'expected_role' => 'adult'])->assertNoContent();
        $this->assertDatabaseHas('families', ['id' => DemoSeeder::POPESCU, 'authz_revision' => 1], 'admin');
        $this->assertDatabaseMissing('audit_events', ['action' => 'membership.role_changed'], 'admin');
    }

    public function test_cross_family_and_revoked_targets_cannot_be_changed(): void
    {
        $foreign = DB::table('family_memberships')->where('family_id', DemoSeeder::IONESCU)->where('user_id', $this->elena->id)->value('id');
        $this->actingAs($this->ana)->patchJson($this->path($foreign), ['role' => 'limited', 'expected_role' => 'adult'])->assertNotFound();
        DB::connection('admin')->table('family_memberships')->where('id', $this->member)->update(['revoked_at' => now()]);
        $this->patchJson($this->path(), ['role' => 'limited', 'expected_role' => 'adult'])->assertNotFound();
        $this->assertDatabaseHas('family_memberships', ['id' => $this->member, 'role' => 'adult'], 'admin');
        $this->assertDatabaseMissing('audit_events', ['action' => 'membership.role_changed'], 'admin');
    }

    public function test_cookie_authenticated_role_change_requires_csrf(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        $this->actingAs($this->ana)->patchJson($this->path(), ['role' => 'limited', 'expected_role' => 'adult'])->assertStatus(419);
        $this->assertDatabaseHas('family_memberships', ['id' => $this->member, 'role' => 'adult'], 'admin');
    }
}
