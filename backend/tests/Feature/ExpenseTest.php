<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class ExpenseTest extends TestCase
{
    private User $ana;

    private User $mihai;

    private string $anaMember;

    private string $mihaiMember;

    private string $otherMember;

    private string $base;

    private string $family;

    private string $otherFamily;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('memoria_test', config('database.connections.pgsql.database'));
        $admin = config('database.connections.pgsql');
        $admin['username'] = 'memoria_admin';
        $admin['password'] = env('DB_ADMIN_PASSWORD');
        config(['database.connections.admin' => $admin]);
        $this->family = (string) Str::uuid();
        $this->otherFamily = (string) Str::uuid();
        foreach ([$this->family, $this->otherFamily] as $family) {
            DB::connection('admin')->table('families')->insert(['id' => $family, 'name' => 'Expense fixture']);
        }
        foreach (['ana' => 'Ana Popescu', 'mihai' => 'Mihai Popescu'] as $key => $name) {
            $user = User::factory()->make(['name' => $name, 'email' => 'expense-'.$this->family.'-'.$key.'@example.test']);
            $user->setConnection('admin')->save();
            $this->{$key} = $user;
        }
        $this->anaMember = (string) Str::uuid();
        $this->mihaiMember = (string) Str::uuid();
        $this->otherMember = (string) Str::uuid();
        DB::connection('admin')->table('family_memberships')->insert([
            ['id' => $this->anaMember, 'family_id' => $this->family, 'user_id' => $this->ana->id, 'role' => 'owner'],
            ['id' => $this->mihaiMember, 'family_id' => $this->family, 'user_id' => $this->mihai->id, 'role' => 'adult'],
            ['id' => $this->otherMember, 'family_id' => $this->otherFamily, 'user_id' => $this->ana->id, 'role' => 'owner'],
        ]);
        $this->base = '/api/v1/families/'.$this->family.'/expenses';
        $this->actingAs($this->ana);
        $this->travelTo(CarbonImmutable::parse('2026-10-23T22:30:00Z'));
    }

    protected function tearDown(): void
    {
        try {
            DB::disableQueryLog();
            foreach ([$this->family, $this->otherFamily] as $family) {
                foreach (['expenses', 'audit_events', 'member_profiles', 'family_memberships'] as $table) {
                    DB::connection('admin')->table($table)->where('family_id', $family)->delete();
                }
                DB::connection('admin')->table('families')->where('id', $family)->delete();
            }
            DB::connection('admin')->table('users')->whereIn('id', [$this->ana->id, $this->mihai->id])->delete();
        } finally {
            $this->travelBack();
            DB::disconnect('pgsql');
            DB::disconnect('admin');
            parent::tearDown();
        }
    }

    private function content(array $changes = []): array
    {
        return array_replace(['amount' => '87,40', 'category' => 'food', 'spent_on' => '2026-10-24',
            'payer_membership_id' => $this->anaMember, 'note' => 'Cumpărături'], $changes);
    }

    private function create(array $changes = []): array
    {
        return $this->postJson($this->base, $this->content($changes))->assertCreated()->json();
    }

    public function test_family_default_exact_money_and_month_boundaries(): void
    {
        $row = $this->create();
        $this->assertSame('family', $row['visibility']);
        $this->assertSame(8740, $row['amount_bani']);
        $this->assertTrue($row['can_edit']);
        $this->assertSame('Ana Popescu', $row['payer_name']);
        $this->create(['amount' => '0.10', 'spent_on' => '2026-10-01']);
        $this->create(['amount' => '0,20', 'category' => 'home', 'spent_on' => '2026-10-31', 'payer_membership_id' => $this->mihaiMember]);
        $this->create(['amount' => '9', 'spent_on' => '2026-09-30']);
        $this->create(['amount' => '999999.99', 'spent_on' => '2026-11-01']);
        $this->getJson($this->base)->assertOk()->assertJsonPath('month', '2026-10')->assertJsonPath('visibility', 'family')
            ->assertJsonPath('total_bani', 8770)->assertJsonPath('count', 3)->assertJsonCount(3, 'items')->assertJsonPath('items.0.spent_on', '2026-10-31')
            ->assertJsonPath('categories.0.category', 'food')->assertJsonPath('categories.0.total_bani', 8750)->assertJsonPath('categories.1.total_bani', 20);
        $this->getJson($this->base.'?month=2026-11')->assertOk()->assertJsonPath('total_bani', 99999999)->assertJsonPath('count', 1);
        $this->getJson($this->base.'?month=2026-12')->assertOk()->assertJsonPath('total_bani', 0)->assertJsonCount(0, 'items');
    }

    public function test_private_rows_never_leak_to_family_totals_payer_or_family_owner(): void
    {
        $family = $this->create();
        $private = $this->create(['amount' => '25', 'visibility' => 'private', 'payer_membership_id' => $this->mihaiMember]);
        $this->getJson($this->base)->assertJsonPath('total_bani', 8740)->assertJsonCount(1, 'items');
        $this->getJson($this->base.'?visibility=private')->assertJsonPath('total_bani', 2500)->assertJsonCount(1, 'items');
        $this->actingAs($this->mihai);
        $this->getJson($this->base)->assertOk()->assertJsonPath('items.0.id', $family['id'])->assertJsonPath('items.0.can_edit', false);
        $this->getJson($this->base.'?visibility=private')->assertJsonPath('total_bani', 0)->assertJsonCount(0, 'categories');
        $this->getJson($this->base.'/'.$private['id'])->assertNotFound();
        $this->withHeader('If-Match', '"1"')->patchJson($this->base.'/'.$private['id'], $this->content(['visibility' => 'family']))->assertNotFound();
        $this->withHeader('If-Match', '"1"')->deleteJson($this->base.'/'.$family['id'])->assertForbidden();
        $otherPrivate = $this->create(['visibility' => 'private']);
        $this->actingAs($this->ana)->getJson($this->base.'/'.$otherPrivate['id'])->assertNotFound();
        $this->getJson($this->base.'?visibility=private')->assertJsonPath('count', 1)->assertJsonPath('items.0.id', $private['id']);
    }

    public function test_owner_can_edit_move_month_change_access_and_delete_with_version_checks(): void
    {
        $row = $this->create();
        $path = $this->base.'/'.$row['id'];
        $update = $this->content(['amount' => '42.05', 'spent_on' => '2026-11-03', 'visibility' => 'private']);
        $this->patchJson($path, $update)->assertStatus(428);
        $this->withHeader('If-Match', '"1"')->patchJson($path, $update)->assertOk()->assertJsonPath('amount_bani', 4205)->assertJsonPath('version', 2);
        $this->withHeader('If-Match', '"1"')->patchJson($path, $update)->assertStatus(412);
        $this->deleteJson($path)->assertStatus(412);
        $this->getJson($this->base)->assertJsonPath('count', 0);
        $this->getJson($this->base.'?month=2026-11&visibility=private')->assertJsonPath('total_bani', 4205);
        $this->withHeader('If-Match', '"2"')->deleteJson($path)->assertNoContent();
        $this->getJson($path)->assertNotFound();
        $this->getJson($this->base.'?month=2026-11&visibility=private')->assertJsonPath('total_bani', 0)->assertJsonPath('count', 0);
        $this->assertNotNull(DB::connection('admin')->table('expenses')->where('id', $row['id'])->value('deleted_at'));
        $this->assertSame(3, DB::connection('admin')->table('audit_events')->where('resource_id', $row['id'])->count());
    }

    public function test_limited_role_can_read_but_cannot_write_even_own_rows(): void
    {
        $this->actingAs($this->mihai);
        $row = $this->create(['visibility' => 'private']);
        $this->actingAs($this->ana);
        $shared = $this->create();
        DB::connection('admin')->table('family_memberships')->where('id', $this->mihaiMember)->update(['role' => 'limited']);
        $this->actingAs($this->mihai);
        $this->getJson($this->base)->assertOk()->assertJsonPath('items.0.id', $shared['id'])->assertJsonPath('items.0.can_edit', false);
        $this->getJson($this->base.'?visibility=private')->assertOk()->assertJsonPath('items.0.id', $row['id'])->assertJsonPath('items.0.can_edit', false);
        $this->postJson($this->base, $this->content())->assertForbidden();
        $this->withHeader('If-Match', '"1"')->patchJson($this->base.'/'.$row['id'], $this->content(['visibility' => 'private']))->assertForbidden();
        $this->deleteJson($this->base.'/'.$row['id'])->assertForbidden();
    }

    public function test_revoked_sessions_other_families_and_database_rls_are_blocked(): void
    {
        $row = $this->create();
        $other = '/api/v1/families/'.$this->otherFamily.'/expenses';
        $this->getJson($other.'/'.$row['id'])->assertNotFound();
        $this->withHeader('If-Match', '"1"')->patchJson($other.'/'.$row['id'], $this->content(['visibility' => 'family']))->assertNotFound();
        $this->getJson($other)->assertOk()->assertJsonPath('total_bani', 0);
        $this->actingAs($this->mihai)->getJson($this->base)->assertOk();
        DB::connection('admin')->table('family_memberships')->where('id', $this->mihaiMember)->update(['revoked_at' => now()]);
        $this->getJson($this->base)->assertNotFound();
        $this->getJson($this->base.'/'.$row['id'])->assertNotFound();
        $this->postJson($this->base, $this->content())->assertNotFound();
        DB::connection('admin')->table('family_memberships')->where('id', $this->mihaiMember)->update(['revoked_at' => null]);
        $this->getJson($this->base)->assertOk()->assertJsonPath('count', 1);
        DB::transaction(function () {
            DB::select("SELECT set_config('app.family_id', ?, true)", [$this->otherFamily]);
            $this->assertSame(0, DB::table('expenses')->count());
        });
        $this->assertSame(0, DB::table('expenses')->count());
    }

    public function test_invalid_amounts_dates_categories_access_and_ownership_are_rejected(): void
    {
        foreach (['0', '0.00', '-1', '1.001', '1e2', '1,000.00', '1000000', '01.20', '12.', '.50', 'NaN', '', 12, null] as $amount) {
            $this->postJson($this->base, $this->content(['amount' => $amount]))->assertUnprocessable()->assertJsonValidationErrors('amount');
        }
        foreach ([['category' => 'invalid'], ['visibility' => 'selected'], ['spent_on' => '2026-02-30'],
            ['payer_membership_id' => $this->otherMember], ['payer_membership_id' => (string) Str::uuid()], ['owner_id' => $this->mihai->id],
            ['family_id' => $this->otherFamily], ['amount_bani' => 1], ['version' => 20], ['note' => str_repeat('a', 241)]] as $change) {
            $this->postJson($this->base, $this->content($change))->assertUnprocessable();
        }
        foreach (['2026-13', '26-10', '2026-00', '2100-01'] as $month) {
            $this->getJson($this->base.'?month='.$month)->assertUnprocessable();
        }
        $this->getJson($this->base.'?visibility=all')->assertUnprocessable();
        $this->getJson($this->base.'?page=0')->assertUnprocessable();
        $this->assertSame(0, DB::connection('admin')->table('expenses')->where('family_id', $this->family)->count());
    }

    public function test_revoked_payer_can_remain_in_existing_history_but_cannot_be_newly_selected(): void
    {
        $row = $this->create(['payer_membership_id' => $this->mihaiMember]);
        DB::connection('admin')->table('family_memberships')->where('id', $this->mihaiMember)->update(['revoked_at' => now()]);
        $this->postJson($this->base, $this->content(['payer_membership_id' => $this->mihaiMember]))->assertUnprocessable();
        $this->getJson($this->base)->assertOk()->assertJsonCount(1, 'payers')->assertJsonPath('items.0.payer_name', 'Mihai Popescu');
        $this->withHeader('If-Match', '"1"')->patchJson($this->base.'/'.$row['id'], $this->content(['visibility' => 'family', 'payer_membership_id' => $this->mihaiMember]))->assertOk();
    }

    public function test_monthly_aggregates_cover_every_page_without_n_plus_one_queries(): void
    {
        $this->create(['amount' => '0,01']);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->getJson($this->base)->assertOk();
        $small = count(DB::getQueryLog());
        $rows = [];
        for ($index = 0; $index < 52; $index++) {
            $rows[] = ['id' => (string) Str::uuid(), 'family_id' => $this->family, 'owner_id' => $this->ana->id,
                'payer_membership_id' => $this->anaMember, 'amount_bani' => 1, 'category' => 'food', 'spent_on' => '2026-10-24', 'created_at' => now(), 'updated_at' => now()];
        }
        DB::connection('admin')->table('expenses')->insert($rows);
        DB::flushQueryLog();
        $first = $this->getJson($this->base)->assertOk()->assertJsonCount(50, 'items')->assertJsonPath('count', 53)
            ->assertJsonPath('total_bani', 53)->assertJsonPath('categories.0.total_bani', 53)->assertJsonPath('next_page', 2);
        $this->assertSame($small, count(DB::getQueryLog()));
        $second = $this->getJson($this->base.'?page=2')->assertOk()->assertJsonCount(3, 'items')->assertJsonPath('next_page', null)->assertJsonPath('total_bani', 53);
        $ids = array_merge(array_column($first->json('items'), 'id'), array_column($second->json('items'), 'id'));
        $this->assertCount(53, array_unique($ids));
    }
}
