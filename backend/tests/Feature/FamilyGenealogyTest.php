<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class FamilyGenealogyTest extends TestCase
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
    }

    protected function tearDown(): void
    {
        DB::disconnect('pgsql');
        DB::disconnect('admin');
        parent::tearDown();
    }

    private function family(): array
    {
        $owner = User::factory()->make();
        $owner->setConnection('admin')->save();
        $family = (string) Str::uuid();
        $member = (string) Str::uuid();
        DB::connection('admin')->table('families')->insert(['id' => $family, 'name' => 'Familia test']);
        DB::connection('admin')->table('family_memberships')->insert(['id' => $member, 'family_id' => $family, 'user_id' => $owner->id, 'role' => 'owner']);

        return [$owner, $family, $member, '/api/v1/families/'.$family.'/members/'.$member.'/profile/relations'];
    }

    public function test_parent_and_grandparent_without_accounts_do_not_create_authentication_or_guardianship_records(): void
    {
        [$owner, $family, $member, $path] = $this->family();

        $response = $this->actingAs($owner)->postJson($path, ['from_id' => $member, 'relation' => 'parent', 'name' => 'Maria Bunică']);

        $response->assertCreated()->assertJsonCount(2, 'nodes')->assertJsonCount(1, 'edges');
        $parent = collect($response->json('nodes'))->firstWhere('name', 'Maria Bunică')['id'];
        $this->postJson($path, ['from_id' => $parent, 'relation' => 'parent', 'name' => 'Elena Străbunică'])->assertCreated()->assertJsonCount(3, 'nodes')->assertJsonCount(2, 'edges');
        $this->assertSame(1, User::count());
        $this->assertSame(1, DB::connection('admin')->table('family_memberships')->count());
        $this->assertSame(0, DB::connection('admin')->table('member_profile_parents')->count());
        $this->assertDatabaseHas('genealogy_people', ['id' => $parent, 'family_id' => $family, 'membership_id' => null, 'name' => 'Maria Bunică'], 'admin');
        $this->assertSame(2, DB::connection('admin')->table('audit_events')->where('action', 'genealogy.relation_added')->count());
    }

    public function test_only_the_owner_can_edit_and_revoked_or_foreign_members_and_relations_are_hidden(): void
    {
        [$owner, $family, $member, $path] = $this->family();
        [$otherOwner, $otherFamily, $otherMember, $foreignPath] = $this->family();
        $this->getJson($path)->assertUnauthorized();
        $this->actingAs($owner)->postJson($path, ['from_id' => $member, 'relation' => 'parent', 'name' => 'Părinte'])->assertCreated();
        $reader = User::factory()->make();
        $reader->setConnection('admin')->save();
        $readerMember = (string) Str::uuid();
        DB::connection('admin')->table('family_memberships')->insert(['id' => $readerMember, 'family_id' => $family, 'user_id' => $reader->id, 'role' => 'limited']);
        $this->postJson($path, ['from_id' => $member, 'relation' => 'parent', 'target_id' => $readerMember])->assertCreated();

        $response = $this->actingAs($reader)->getJson($path);

        $response->assertOk()->assertJsonPath('can_manage', false)->assertJsonCount(2, 'edges');
        $this->postJson($path, ['from_id' => $member, 'relation' => 'parent', 'name' => 'Interzis'])->assertForbidden();
        $this->getJson(str_replace('/relations', '', $path))->assertOk()->assertJsonPath('can_write', false);
        $this->getJson($foreignPath)->assertNotFound();
        $this->actingAs($owner)->postJson($path, ['from_id' => $member, 'relation' => 'parent', 'target_id' => $otherMember])->assertNotFound();
        DB::connection('admin')->table('family_memberships')->where('id', $readerMember)->update(['revoked_at' => now()]);
        $this->getJson($path)->assertOk()->assertJsonCount(1, 'edges')->assertJsonMissing(['id' => $readerMember]);
        $this->actingAs($reader)->getJson($path)->assertNotFound();
    }

    public function test_cycles_self_links_duplicates_and_authentication_fields_are_rejected_without_partial_writes(): void
    {
        [$owner, $family, $member, $path] = $this->family();
        $response = $this->actingAs($owner)->postJson($path, ['from_id' => $member, 'relation' => 'parent', 'name' => 'Părinte'])->assertCreated();
        $parent = $response->json('edges.0.from_id');
        $grandparentResponse = $this->postJson($path, ['from_id' => $parent, 'relation' => 'parent', 'name' => 'Bunic'])->assertCreated();
        $grandparent = collect($grandparentResponse->json('nodes'))->firstWhere('name', 'Bunic')['id'];

        $this->postJson($path, ['from_id' => $member, 'relation' => 'child', 'target_id' => $parent])->assertUnprocessable();
        $this->postJson($path, ['from_id' => $grandparent, 'relation' => 'parent', 'target_id' => $member])->assertUnprocessable();
        $this->postJson($path, ['from_id' => $member, 'relation' => 'parent', 'target_id' => $parent])->assertConflict();
        $this->postJson($path, ['from_id' => $member, 'relation' => 'partner', 'target_id' => $member])->assertUnprocessable();
        $this->postJson($path, ['from_id' => $member, 'relation' => 'parent', 'name' => 'Fără cont', 'email' => 'fake@example.test'])->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->postJson($path, ['from_id' => $member, 'relation' => 'parent', 'name' => '   '])->assertUnprocessable();
        $this->assertSame(3, DB::connection('admin')->table('genealogy_people')->count());
        $this->assertSame(2, DB::connection('admin')->table('genealogy_relations')->count());
    }

    public function test_names_versions_soft_removal_and_restoration_preserve_people_and_canonical_symmetric_links(): void
    {
        [$owner, $family, $member, $path] = $this->family();
        $response = $this->actingAs($owner)->postJson($path, ['from_id' => $member, 'relation' => 'sibling', 'name' => 'Ioan'])->assertCreated();
        $person = collect($response->json('nodes'))->firstWhere('name', 'Ioan')['id'];
        $edge = $response->json('edges.0.id');

        $this->withHeader('If-Match', '"1"')->patchJson($path.'/people/'.$person, ['name' => 'Ion'])->assertOk()->assertJsonFragment(['id' => $person, 'name' => 'Ion', 'version' => 2, 'membership_id' => null]);
        $this->patchJson($path.'/people/'.$person, ['name' => 'Vechi'])->assertStatus(412);
        $this->patchJson($path.'/people/'.$member, ['name' => 'Cont modificat'])->assertNotFound();
        $this->postJson($path, ['from_id' => $person, 'relation' => 'sibling', 'target_id' => $member])->assertConflict();
        $this->deleteJson($path.'/'.$edge)->assertOk()->assertJsonCount(0, 'edges')->assertJsonCount(2, 'nodes');
        $restored = $this->postJson($path, ['from_id' => $person, 'relation' => 'sibling', 'target_id' => $member]);

        $restored->assertCreated()->assertJsonPath('edges.0.id', $edge)->assertJsonPath('edges.0.version', 3);
        $this->assertSame(1, DB::connection('admin')->table('genealogy_relations')->count());
        $this->assertSame(1, User::count());
        $this->assertSame(0, DB::connection('admin')->table('member_profile_parents')->count());
    }
}
