<?php

namespace Tests\Feature;

use App\Models\User;
use App\Platform\Media\Scanner;
use Database\Seeders\DemoSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

final class MemberProfileTest extends TestCase
{
    private User $ana;

    private User $child;

    private User $parent;

    private string $member;

    private string $parentMember;

    private string $ownMember;

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
        $this->travelTo(Carbon::parse('2026-10-22 12:00:00', 'Europe/Bucharest'));
        $this->ana = User::where('email', 'ana@example.test')->firstOrFail();
        $this->child = User::where('email', 'mihai@example.test')->firstOrFail();
        $this->parent = User::where('email', 'elena@example.test')->firstOrFail();
        $this->member = DB::table('family_memberships')->where('family_id', DemoSeeder::POPESCU)->where('user_id', $this->child->id)->value('id');
        $this->ownMember = DB::table('family_memberships')->where('family_id', DemoSeeder::POPESCU)->where('user_id', $this->ana->id)->value('id');
        DB::connection('admin')->table('family_memberships')->where('id', $this->member)->update(['role' => 'limited']);
        $this->parentMember = (string) Str::uuid();
        DB::connection('admin')->table('family_memberships')->insert(['id' => $this->parentMember, 'family_id' => DemoSeeder::POPESCU, 'user_id' => $this->parent->id, 'role' => 'adult']);
        $this->app->instance(Scanner::class, new class implements Scanner
        {
            public function scan(string $path): string
            {
                return 'clean';
            }
        });
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        DB::disconnect('pgsql');
        DB::disconnect('admin');
        parent::tearDown();
    }

    private function path(?string $member = null, string $family = DemoSeeder::POPESCU): string
    {
        return '/api/v1/families/'.$family.'/members/'.($member ?? $this->member).'/profile';
    }

    private function configureChild(string $birth): void
    {
        $this->actingAs($this->ana)->withHeader('If-Match', '"0"')->patchJson($this->path(), ['birth_date' => $birth, 'parent_ids' => [$this->parentMember]])->assertOk()->assertJsonPath('version', 1);
    }

    private function content(string $visibility = 'private'): array
    {
        return ['kind' => 'visit', 'title' => 'Control medical', 'occurred_on' => '2026-10-22', 'notes' => 'Date sintetice pentru test.', 'visibility' => $visibility];
    }

    public function test_under_fourteen_only_designated_parents_can_write_and_child_cannot_change_birth_date(): void
    {
        $this->configureChild('2012-10-23');
        $this->actingAs($this->child)->postJson($this->path().'/entries', $this->content())->assertForbidden();
        $this->withHeader('If-Match', '"1"')->patchJson($this->path(), ['birth_date' => '2000-01-01', 'parent_ids' => []])->assertForbidden();
        $this->actingAs($this->ana)->postJson($this->path().'/entries', $this->content())->assertForbidden();
        $this->actingAs($this->parent)->postJson($this->path().'/entries', $this->content('family'))->assertCreated();
        $this->actingAs($this->child)->getJson($this->path().'/entries')->assertOk()->assertJsonCount(1, 'items');
    }

    public function test_on_fourteenth_birthday_self_entry_is_private_from_both_parent_and_owner(): void
    {
        $this->configureChild('2012-10-22');
        $entry = $this->actingAs($this->child)->postJson($this->path().'/entries', $this->content())->assertCreated()->json('id');
        $this->getJson($this->path().'/entries')->assertJsonCount(1, 'items')->assertJsonPath('items.0.can_manage', true);
        foreach ([$this->parent, $this->ana] as $actor) {
            $this->actingAs($actor)->getJson($this->path().'/entries')->assertOk()->assertJsonCount(0, 'items');
            $this->withHeader('If-Match', '"1"')->patchJson($this->path().'/entries/'.$entry, $this->content('family'))->assertNotFound();
        }
        $this->actingAs($this->parent)->postJson($this->path().'/entries', $this->content('family'))->assertCreated();
    }

    public function test_parent_rights_end_at_eighteen_and_revoked_or_limited_parent_is_rejected(): void
    {
        $this->configureChild('2012-10-22');
        DB::connection('admin')->table('family_memberships')->where('id', $this->parentMember)->update(['role' => 'limited']);
        $this->actingAs($this->ana)->getJson($this->path())->assertOk()->assertJsonCount(0, 'parents');
        $this->actingAs($this->parent)->postJson($this->path().'/entries', $this->content())->assertForbidden();
        DB::connection('admin')->table('family_memberships')->where('id', $this->parentMember)->update(['role' => 'adult', 'revoked_at' => now()]);
        $this->postJson($this->path().'/entries', $this->content())->assertNotFound();
        DB::connection('admin')->table('family_memberships')->where('id', $this->parentMember)->update(['revoked_at' => null]);
        $this->travelTo(Carbon::parse('2030-10-22 12:00:00', 'Europe/Bucharest'));
        $this->postJson($this->path().'/entries', $this->content())->assertForbidden();
        $this->actingAs($this->child)->postJson($this->path().'/entries', $this->content())->assertCreated();
    }

    public function test_entries_preserve_author_version_and_private_restoration(): void
    {
        $response = $this->actingAs($this->ana)->postJson($this->path($this->ownMember).'/entries', $this->content('family'));
        $entry = $response->assertCreated()->json('id');
        $path = $this->path($this->ownMember).'/entries/'.$entry;
        $this->actingAs($this->child)->withHeader('If-Match', '"1"')->patchJson($path, $this->content())->assertForbidden();
        $this->actingAs($this->ana)->withoutHeader('If-Match')->patchJson($path, $this->content())->assertStatus(428);
        $this->withHeader('If-Match', '"1"')->patchJson($path, $this->content())->assertOk()->assertJsonPath('version', 2);
        $this->withHeader('If-Match', '"1"')->patchJson($path, $this->content())->assertStatus(412);
        $this->withHeader('If-Match', '"2"')->postJson($path.'/archive', ['archived' => true])->assertOk()->assertJsonPath('visibility', 'private');
        $this->getJson($this->path($this->ownMember).'/entries')->assertJsonCount(0, 'items');
        $this->getJson($this->path($this->ownMember).'/entries?archived=1')->assertJsonCount(1, 'items');
        $this->withHeader('If-Match', '"3"')->postJson($path.'/archive', ['archived' => false])->assertOk()->assertJsonPath('visibility', 'private');
    }

    public function test_cross_family_parents_profile_and_photo_are_rejected(): void
    {
        $otherMember = DB::table('family_memberships')->where('family_id', DemoSeeder::IONESCU)->where('user_id', $this->ana->id)->value('id');
        $this->actingAs($this->ana)->getJson($this->path(family: DemoSeeder::IONESCU))->assertNotFound();
        $this->getJson($this->path(family: DemoSeeder::IONESCU).'/photo')->assertNotFound();
        $this->withHeader('If-Match', '"0"')->patchJson($this->path(), ['birth_date' => '2015-01-01', 'parent_ids' => [$otherMember]])->assertUnprocessable();
        $this->patchJson($this->path(), ['birth_date' => '2030-01-01', 'parent_ids' => []])->assertUnprocessable();
        $this->assertDatabaseMissing('member_profiles', ['membership_id' => $this->member], 'admin');
    }

    public function test_photo_uses_profile_permissions_and_private_canonical_route(): void
    {
        $this->configureChild('2012-10-23');
        $this->actingAs($this->child)->withHeader('If-Match', '"1"')->post($this->path().'/photo', ['action' => 'replace', 'photo' => UploadedFile::fake()->image('portrait.jpg')], ['Accept' => 'application/json'])->assertForbidden();
        $this->actingAs($this->parent)->post($this->path().'/photo', ['action' => 'replace', 'photo' => UploadedFile::fake()->image('portrait.jpg')], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('photo_status', 'ready')->assertJsonPath('version', 2);
        $this->actingAs($this->child)->get($this->path().'/photo')->assertOk();
        DB::connection('admin')->table('family_memberships')->where('id', $this->member)->update(['revoked_at' => now()]);
        $this->actingAs($this->parent)->getJson($this->path())->assertNotFound();
        $this->get($this->path().'/photo')->assertNotFound();
    }

    public function test_profile_configuration_detects_stale_version_and_rejects_minor_parent(): void
    {
        $this->configureChild('2012-10-22');
        $this->actingAs($this->ana)->withHeader('If-Match', '"0"')->patchJson($this->path(), ['birth_date' => '2012-10-22', 'parent_ids' => [$this->ownMember]])->assertStatus(412);
        $this->withHeader('If-Match', '"0"')->patchJson($this->path($this->parentMember), ['birth_date' => '2010-01-01', 'parent_ids' => [$this->ownMember]])->assertUnprocessable();
        $this->actingAs($this->child)->getJson($this->path())->assertJsonPath('parents.0.id', $this->parentMember)->assertJsonPath('version', 1);
    }
}
