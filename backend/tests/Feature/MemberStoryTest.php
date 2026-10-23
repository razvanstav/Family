<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class MemberStoryTest extends TestCase
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

        return [$owner, $family, $member, '/api/v1/families/'.$family.'/members/'.$member.'/profile/story'];
    }

    private function entry(string $family, string $member, User $author, array $changes = []): string
    {
        $id = (string) Str::uuid();
        DB::connection('admin')->table('member_profile_entries')->insert(array_replace(['id' => $id, 'family_id' => $family, 'membership_id' => $member,
            'author_id' => $author->id, 'kind' => 'activity', 'title' => 'Activitate', 'notes' => 'Poveste completă', 'occurred_on' => '2026-10-23',
            'visibility' => 'private', 'created_at' => now(), 'updated_at' => now()], $changes));

        return $id;
    }

    private function moment(string $family, User $author, ?string $mention, array $changes = []): string
    {
        $id = (string) Str::uuid();
        DB::connection('admin')->table('memory_moments')->insert(array_replace(['id' => $id, 'family_id' => $family, 'owner_id' => $author->id,
            'kind' => 'moment', 'title' => 'Moment', 'body' => 'Poveste', 'occurred_on' => '2026-10-23', 'visibility' => 'private', 'created_at' => now(), 'updated_at' => now()], $changes));
        if ($mention !== null) {
            DB::connection('admin')->table('memory_moment_people')->insert(['family_id' => $family, 'moment_id' => $id, 'membership_id' => $mention]);
        }

        return $id;
    }

    public function test_story_combines_visible_entries_and_explicitly_mentioned_moments_without_exposing_private_or_archived_sources(): void
    {
        [$owner, $family, $member, $path] = $this->family();
        $author = User::factory()->make();
        $author->setConnection('admin')->save();
        DB::connection('admin')->table('family_memberships')->insert(['id' => (string) Str::uuid(), 'family_id' => $family, 'user_id' => $author->id, 'role' => 'adult']);
        $entry = $this->entry($family, $member, $owner);
        $shared = $this->entry($family, $member, $author, ['visibility' => 'family', 'kind' => 'book']);
        $this->entry($family, $member, $author);
        $this->entry($family, $member, $owner, ['archived_at' => now()]);
        $moment = $this->moment($family, $author, $member, ['visibility' => 'family']);
        $this->moment($family, $author, $member);
        $this->moment($family, $owner, null);
        $this->moment($family, $owner, $member, ['archived_at' => now()]);

        $response = $this->actingAs($owner)->getJson($path);

        $response->assertOk()->assertJsonCount(3, 'items')->assertJsonPath('has_more', false)->assertJsonPath('next_cursor', null);
        $this->assertEqualsCanonicalizing([$entry, $shared, $moment], array_column($response->json('items'), 'id'));
        $fullEntry = collect($response->json('items'))->firstWhere('id', $entry);
        $this->assertSame('Poveste completă', $fullEntry['notes']);
        $this->assertSame(1, $fullEntry['version']);
        $this->assertTrue($fullEntry['can_manage']);
        $this->assertSame('moment', collect($response->json('items'))->firstWhere('id', $moment)['source']);
    }

    public function test_cursor_pages_all_sources_on_the_same_day_once_and_is_bound_to_family_and_profile(): void
    {
        [$owner, $family, $member, $path] = $this->family();
        $expected = [];
        for ($i = 0; $i < 15; $i++) {
            $expected[] = $this->entry($family, $member, $owner);
            $expected[] = $this->moment($family, $owner, $member);
        }
        $this->entry($family, $member, $owner, ['occurred_on' => '2025-01-01']);
        $collected = [];
        $cursor = null;
        $firstCursor = null;
        $this->actingAs($owner);
        do {
            $response = $this->getJson($path.($cursor ? '?cursor='.urlencode($cursor) : ''))->assertOk();
            $collected = array_merge($collected, array_column($response->json('items'), 'id'));
            $cursor = $response->json('next_cursor');
            $firstCursor ??= $cursor;
        } while ($cursor !== null && count($collected) <= 40);

        $this->assertCount(31, $collected);
        $this->assertCount(31, array_unique($collected));
        $this->assertEqualsCanonicalizing($expected, array_slice($collected, 0, 30));
        $this->getJson($path.'?cursor=invalid')->assertUnprocessable();
        [$otherOwner, $otherFamily, $otherMember, $otherPath] = $this->family();
        $this->actingAs($otherOwner)->getJson($otherPath.'?cursor='.urlencode($firstCursor))->assertUnprocessable();
        $sameFamilyMember = (string) Str::uuid();
        DB::connection('admin')->table('family_memberships')->insert(['id' => $sameFamilyMember, 'family_id' => $family, 'user_id' => $otherOwner->id, 'role' => 'adult']);
        $this->actingAs($owner)->getJson('/api/v1/families/'.$family.'/members/'.$sameFamilyMember.'/profile/story?cursor='.urlencode($firstCursor))->assertUnprocessable();
    }

    public function test_moment_grants_and_membership_revocation_are_rechecked_for_each_story_read(): void
    {
        [$owner, $family, $member, $path] = $this->family();
        $reader = User::factory()->make();
        $reader->setConnection('admin')->save();
        $readerMember = (string) Str::uuid();
        DB::connection('admin')->table('family_memberships')->insert(['id' => $readerMember, 'family_id' => $family, 'user_id' => $reader->id, 'role' => 'limited']);
        $moment = $this->moment($family, $owner, $member, ['visibility' => 'selected']);
        DB::connection('admin')->table('memory_moment_grants')->insert(['family_id' => $family, 'moment_id' => $moment, 'membership_id' => $readerMember]);
        $this->getJson($path)->assertUnauthorized();

        $this->actingAs($reader)->getJson($path)->assertOk()->assertJsonPath('items.0.id', $moment);

        DB::connection('admin')->table('memory_moment_grants')->where('moment_id', $moment)->delete();
        $this->getJson($path)->assertOk()->assertJsonCount(0, 'items');
        DB::connection('admin')->table('family_memberships')->where('id', $readerMember)->update(['revoked_at' => now()]);
        $this->getJson($path)->assertNotFound();
        [$otherOwner, $otherFamily, $otherMember, $otherPath] = $this->family();
        $this->actingAs($otherOwner)->getJson($path)->assertNotFound();
    }

    public function test_an_achievement_can_be_recorded_and_read_in_activities_and_the_story_with_its_original_access(): void
    {
        [$owner, $family, $member, $path] = $this->family();
        $entriesPath = str_replace('/story', '/entries', $path);

        $response = $this->actingAs($owner)->postJson($entriesPath, ['kind' => 'achievement', 'title' => 'Prima cursă de 5 km', 'occurred_on' => '2026-10-23', 'visibility' => 'private']);

        $id = $response->assertCreated()->assertJsonPath('category', 'activities')->assertJsonPath('visibility', 'private')->json('id');
        $this->getJson($entriesPath.'?category=activities')->assertOk()->assertJsonPath('items.0.id', $id)->assertJsonPath('items.0.kind', 'achievement');
        $this->getJson($path)->assertOk()->assertJsonPath('items.0.id', $id)->assertJsonPath('items.0.can_manage', true);
        $this->assertDatabaseHas('member_profile_entries', ['id' => $id, 'kind' => 'achievement', 'author_id' => $owner->id, 'visibility' => 'private'], 'admin');
    }
}
