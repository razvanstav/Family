<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

final class MemoryAlbumTest extends TestCase
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
        $this->travelBack();
        DB::disconnect('pgsql');
        DB::disconnect('admin');
        parent::tearDown();
    }

    /** @return array{owner: User, family: string, membership: string} */
    private function family(): array
    {
        $owner = User::factory()->make();
        $owner->setConnection('admin')->save();
        $family = (string) Str::uuid();
        $membership = (string) Str::uuid();
        DB::connection('admin')->table('families')->insert(['id' => $family, 'name' => 'Familia test']);
        DB::connection('admin')->table('family_memberships')->insert(['id' => $membership, 'family_id' => $family, 'user_id' => $owner->id, 'role' => 'owner']);

        return compact('owner', 'family', 'membership');
    }

    private function moment(string $family, User $owner, string $date, array $changes = []): string
    {
        $id = (string) Str::uuid();
        DB::connection('admin')->table('memory_moments')->insert(array_replace([
            'id' => $id, 'family_id' => $family, 'owner_id' => $owner->id, 'kind' => 'moment',
            'title' => 'Amintire '.$date, 'occurred_on' => $date, 'visibility' => 'family',
            'created_at' => now(), 'updated_at' => now(),
        ], $changes));

        return $id;
    }

    private function path(string $family): string
    {
        return '/api/v1/families/'.$family.'/moments';
    }

    public function test_anniversaries_use_the_day_in_romania_and_include_history_beyond_the_current_page(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-22T22:30:00Z'));
        ['owner' => $owner, 'family' => $family] = $this->family();
        $lastYear = $this->moment($family, $owner, '2025-10-23');
        $older = $this->moment($family, $owner, '2024-10-23');
        $this->moment($family, $owner, '2026-10-23');
        $this->moment($family, $owner, '2027-10-23');
        $this->moment($family, $owner, '2023-10-22');
        $this->moment($family, $owner, '2022-10-23', ['archived_at' => now()]);
        for ($day = 1; $day <= 13; $day++) {
            $this->moment($family, $owner, sprintf('2026-10-%02d', $day));
        }

        $response = $this->actingAs($owner)->getJson($this->path($family));

        $response->assertOk()->assertJsonPath('today', '2026-10-23')->assertJsonPath('has_more', true)
            ->assertJsonCount(12, 'items')->assertJsonPath('years', [2027, 2026, 2025, 2024, 2023])
            ->assertJsonPath('on_this_day.0.id', $lastYear)->assertJsonPath('on_this_day.1.id', $older)
            ->assertJsonCount(2, 'on_this_day')->assertJsonPath('on_this_day.0.version', 1)
            ->assertJsonPath('on_this_day.0.owner_name', $owner->name);
        $this->assertNotContains($lastYear, array_column($response->json('items'), 'id'));
    }

    public function test_year_filter_keeps_stable_pagination_and_all_available_years(): void
    {
        ['owner' => $owner, 'family' => $family] = $this->family();
        $this->moment($family, $owner, '2026-01-01');
        $this->moment($family, $owner, '2024-12-31');
        $ids = [];
        for ($day = 1; $day <= 14; $day++) {
            $ids[] = $this->moment($family, $owner, sprintf('2025-12-%02d', $day));
        }

        $first = $this->actingAs($owner)->getJson($this->path($family).'?year=2025');
        $second = $this->getJson($this->path($family).'?year=2025&page=2');

        $first->assertOk()->assertJsonPath('years', [2026, 2025, 2024])->assertJsonPath('has_more', true)
            ->assertJsonPath('items.0.id', $ids[13])->assertJsonCount(12, 'items')->assertJsonCount(0, 'on_this_day');
        $second->assertOk()->assertJsonPath('has_more', false)->assertJsonCount(2, 'items')
            ->assertJsonPath('items.0.id', $ids[1])->assertJsonPath('items.1.id', $ids[0]);
        $this->getJson($this->path($family).'?year=2025&kind=note')->assertOk()->assertJsonCount(0, 'items');
    }

    public function test_years_and_anniversaries_follow_private_selected_archived_and_family_access(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-23T09:00:00Z'));
        ['owner' => $owner, 'family' => $family, 'membership' => $membership] = $this->family();
        ['owner' => $otherOwner, 'family' => $otherFamily] = $this->family();
        $author = User::factory()->make();
        $author->setConnection('admin')->save();
        DB::connection('admin')->table('family_memberships')->insert(['id' => (string) Str::uuid(), 'family_id' => $family, 'user_id' => $author->id, 'role' => 'adult']);
        $this->moment($otherFamily, $otherOwner, '2020-10-23');
        $this->moment($family, $author, '2021-10-23', ['visibility' => 'private']);
        $this->moment($family, $owner, '2022-10-23', ['archived_at' => now()]);
        $shared = $this->moment($family, $author, '2024-10-23');
        $selected = $this->moment($family, $author, '2025-10-23', ['visibility' => 'selected']);
        DB::connection('admin')->table('memory_moment_grants')->insert(['family_id' => $family, 'moment_id' => $selected, 'membership_id' => $membership]);

        $response = $this->actingAs($owner)->getJson($this->path($family));

        $response->assertOk()->assertJsonPath('years', [2025, 2024])->assertJsonCount(2, 'on_this_day')
            ->assertJsonPath('on_this_day.0.id', $selected)->assertJsonPath('on_this_day.1.id', $shared)
            ->assertJsonPath('on_this_day.0.can_edit', false)->assertJsonPath('on_this_day.0.grants', []);
        $this->getJson($this->path($otherFamily))->assertNotFound();
        DB::connection('admin')->table('memory_moment_grants')->where('moment_id', $selected)->delete();
        $this->getJson($this->path($family))->assertOk()->assertJsonPath('years', [2024])->assertJsonCount(1, 'on_this_day');
        $this->getJson($this->path($family).'?archived=1')->assertOk()->assertJsonPath('years', [2022])->assertJsonCount(0, 'on_this_day');
        DB::connection('admin')->table('family_memberships')->where('id', $membership)->update(['role' => 'limited']);
        $this->getJson($this->path($family))->assertOk()->assertJsonPath('years', [2024]);
        DB::connection('admin')->table('family_memberships')->where('id', $membership)->update(['revoked_at' => now()]);
        $this->getJson($this->path($family))->assertNotFound();
    }

    public function test_leap_day_requires_an_exact_month_and_day_match(): void
    {
        $this->travelTo(CarbonImmutable::parse('2028-02-29T09:00:00Z'));
        ['owner' => $owner, 'family' => $family] = $this->family();
        $leap = $this->moment($family, $owner, '2024-02-29');
        $this->moment($family, $owner, '2025-02-28');
        $this->moment($family, $owner, '2024-03-01');

        $response = $this->actingAs($owner)->getJson($this->path($family));

        $response->assertOk()->assertJsonCount(1, 'on_this_day')->assertJsonPath('on_this_day.0.id', $leap);
    }

    #[TestWith(['1899'])]
    #[TestWith(['2101'])]
    #[TestWith(['2025 OR 1=1'])]
    public function test_returns_422_for_an_invalid_year(string $year): void
    {
        ['owner' => $owner, 'family' => $family] = $this->family();

        $this->actingAs($owner)->getJson($this->path($family).'?'.http_build_query(['year' => $year]))
            ->assertUnprocessable()->assertJsonValidationErrors('year');
    }
}
