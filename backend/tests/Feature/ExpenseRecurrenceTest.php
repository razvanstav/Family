<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

final class ExpenseRecurrenceTest extends TestCase
{
    private string $family;

    private string $otherFamily;

    private string $base;

    private User $ana;

    private User $mihai;

    private string $anaMember;

    private string $mihaiMember;

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
            DB::connection('admin')->table('families')->insert(['id' => $family, 'name' => 'Expense recurrence fixture']);
        }
        foreach (['ana' => 'Ana Popescu', 'mihai' => 'Mihai Popescu'] as $key => $name) {
            $user = User::factory()->make(['name' => $name, 'email' => 'expense-recurrence-'.$this->family.'-'.$key.'@example.test']);
            $user->setConnection('admin')->save();
            $this->{$key} = $user;
        }
        $this->anaMember = (string) Str::uuid();
        $this->mihaiMember = (string) Str::uuid();
        DB::connection('admin')->table('family_memberships')->insert([
            ['id' => $this->anaMember, 'family_id' => $this->family, 'user_id' => $this->ana->id, 'role' => 'owner'],
            ['id' => $this->mihaiMember, 'family_id' => $this->family, 'user_id' => $this->mihai->id, 'role' => 'adult'],
            ['id' => (string) Str::uuid(), 'family_id' => $this->otherFamily, 'user_id' => $this->ana->id, 'role' => 'owner'],
        ]);
        $this->base = '/api/v1/families/'.$this->family;
        $this->actingAs($this->ana);
        $this->travelTo(CarbonImmutable::parse('2026-10-24T10:00:00Z'));
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
        return array_replace(['amount' => '120,50', 'category' => 'home', 'spent_on' => '2026-10-24',
            'payer_membership_id' => $this->anaMember, 'note' => 'Internet', 'visibility' => 'family',
            'payment_status' => 'planned', 'recurrence' => 'monthly'], $changes);
    }

    private function create(array $changes = []): array
    {
        return $this->postJson($this->base.'/expenses', $this->content($changes))->assertCreated()->json();
    }

    private function pay(array $expense): array
    {
        return $this->withHeader('If-Match', '"'.$expense['version'].'"')->patchJson($this->base.'/expenses/'.$expense['id'].'/payment', ['payment_status' => 'paid'])->assertOk()->json();
    }

    private function edit(array $expense, array $changes = []): array
    {
        return $this->withHeader('If-Match', '"'.$expense['version'].'"')->patchJson($this->base.'/expenses/'.$expense['id'], $this->content(array_replace([
            'spent_on' => $expense['spent_on'], 'payment_status' => $expense['payment_status'], 'recurrence' => $expense['recurrence'],
        ], $changes)))->assertOk()->json();
    }

    private function planned(string $month = '2026-10', string $visibility = 'family'): array
    {
        return $this->getJson($this->base.'/expenses?payment_status=planned&month='.$month.'&visibility='.$visibility)->assertOk()->json('items');
    }

    private function home(): array
    {
        return $this->getJson($this->base.'/home?month=2026-10')->assertOk()->json('upcoming_payments');
    }

    public function test_planned_rows_do_not_count_as_paid_and_payment_creates_exactly_one_successor(): void
    {
        $expense = $this->create();
        $this->assertFalse($expense['has_next_payment']);
        $this->assertCount(1, $this->planned());
        $this->getJson($this->base.'/expenses')->assertJsonPath('total_bani', 0)->assertJsonPath('count', 0);
        $paid = $this->pay($expense);
        $this->assertSame('2026-11-24', $paid['next_due_on']);
        $this->assertSame('2026-10-24', $paid['spent_on']);
        $this->assertTrue($paid['has_next_payment']);
        $this->assertSame('paid', $paid['payment_status']);
        $next = $this->planned('2026-11')[0];
        $this->assertSame('monthly', $next['recurrence']);
        $this->assertSame(12050, $next['amount_bani']);
        $this->getJson($this->base.'/expenses')->assertJsonPath('total_bani', 12050)->assertJsonPath('count', 1);
        $again = $this->pay($paid);
        $this->assertSame($paid['version'], $again['version']);
        $this->assertCount(1, $this->planned('2026-11'));
        $this->withHeader('If-Match', '"1"')->patchJson($this->base.'/expenses/'.$expense['id'].'/payment', ['payment_status' => 'paid'])->assertStatus(412);
    }

    public function test_paid_creation_makes_future_payment_without_adding_it_to_paid_totals_or_recreating_deleted_child(): void
    {
        $expense = $this->create(['payment_status' => 'paid', 'recurrence' => 'daily']);
        $this->assertTrue($expense['has_next_payment']);
        $this->assertSame('2026-10-25', $expense['next_due_on']);
        $next = $this->planned()[0];
        $this->withHeader('If-Match', '"1"')->deleteJson($this->base.'/expenses/'.$next['id'])->assertNoContent();
        $edited = $this->edit($expense, ['amount' => '130', 'spent_on' => '2026-10-20']);
        $this->assertTrue($edited['has_next_payment']);
        $this->assertCount(0, $this->planned());
        $this->withHeader('If-Match', '"'.$edited['version'].'"')->patchJson($this->base.'/expenses/'.$expense['id'], $this->content(['payment_status' => 'paid', 'recurrence' => null]))
            ->assertUnprocessable()->assertJsonValidationErrors('recurrence');
    }

    #[TestWith(['monthly', '2026-01-31', '2026-02-28', '2026-03-31'])]
    #[TestWith(['yearly', '2028-02-29', '2029-02-28', '2030-02-28'])]
    public function test_successive_payments_keep_monthly_and_leap_year_anchors(string $frequency, string $first, string $second, string $third): void
    {
        $this->travelTo(CarbonImmutable::parse($first.'T10:00:00Z'));
        $expense = $this->create(['spent_on' => $first, 'recurrence' => $frequency, 'payment_status' => 'paid']);
        $this->assertSame($second, $expense['next_due_on']);
        $next = $this->planned(substr($second, 0, 7))[0];
        $this->travelTo(CarbonImmutable::parse($second.'T10:00:00Z'));
        $this->assertSame($third, $this->pay($next)['next_due_on']);
    }

    public function test_late_payment_skips_missed_cycles_and_keeps_original_date(): void
    {
        $expense = $this->create(['spent_on' => '2026-01-31']);
        $paid = $this->pay($expense);
        $this->assertSame('2026-01-31', $paid['spent_on']);
        $this->assertSame('2026-10-31', $paid['next_due_on']);
        $this->assertCount(1, $this->planned());
    }

    public function test_editing_current_planned_payment_changes_or_stops_the_next_recurrence_only(): void
    {
        $parent = $this->create(['payment_status' => 'paid']);
        $next = $this->planned('2026-11')[0];
        $next = $this->edit($next, ['recurrence' => null, 'amount' => '40']);
        $this->pay($next);
        $this->assertCount(0, $this->planned('2026-11'));
        $this->getJson($this->base.'/expenses/'.$parent['id'])->assertOk()->assertJsonPath('recurrence', 'monthly')->assertJsonPath('amount_bani', 12050);
        $this->withHeader('If-Match', '"1"')->patchJson($this->base.'/expenses/'.$parent['id'], $this->content(['payment_status' => 'planned']))
            ->assertUnprocessable()->assertJsonValidationErrors('payment_status');
    }

    public function test_home_window_is_inclusive_bounded_and_hides_private_paid_deleted_and_other_family_rows(): void
    {
        $this->create(['spent_on' => '2026-10-23', 'recurrence' => null]);
        $today = $this->create(['spent_on' => '2026-10-24', 'recurrence' => null]);
        $this->create(['spent_on' => '2026-11-01', 'recurrence' => null]);
        $last = $this->create(['spent_on' => '2026-10-31', 'recurrence' => null]);
        $this->create(['payment_status' => 'paid', 'recurrence' => null]);
        $deleted = $this->create(['recurrence' => null]);
        $this->withHeader('If-Match', '"1"')->deleteJson($this->base.'/expenses/'.$deleted['id'])->assertNoContent();
        $this->actingAs($this->mihai);
        $this->create(['visibility' => 'private', 'recurrence' => null]);
        $this->actingAs($this->ana);
        $rows = $this->home();
        $this->assertSame([$today['id'], $last['id']], array_column($rows, 'id'));
        $this->assertSame(1, $rows[0]['version']);
        $this->assertTrue($rows[0]['can_edit']);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->home();
        $small = count(DB::getQueryLog());
        for ($index = 0; $index < 12; $index++) {
            $this->create(['spent_on' => '2026-10-25', 'recurrence' => null]);
        }
        DB::flushQueryLog();
        $this->assertCount(5, $this->home());
        $this->assertSame($small, count(DB::getQueryLog()));
        $this->getJson('/api/v1/families/'.$this->otherFamily.'/home?month=2026-10')->assertOk()->assertJsonCount(0, 'upcoming_payments');
    }

    public function test_permissions_version_validation_and_revocation_protect_payment_mutations(): void
    {
        $shared = $this->create();
        $private = $this->create(['visibility' => 'private']);
        $path = $this->base.'/expenses/'.$shared['id'].'/payment';
        $this->patchJson($path, ['payment_status' => 'paid'])->assertStatus(428);
        $this->withHeader('If-Match', '"1"')->patchJson($path, ['payment_status' => 'planned'])->assertUnprocessable();
        $this->actingAs($this->mihai);
        $this->patchJson($path, ['payment_status' => 'paid'])->assertForbidden();
        $this->patchJson($this->base.'/expenses/'.$private['id'].'/payment', ['payment_status' => 'paid'])->assertNotFound();
        $this->assertFalse($this->home()[0]['can_edit']);
        $this->actingAs($this->ana);
        DB::connection('admin')->table('family_memberships')->where('id', $this->anaMember)->update(['role' => 'limited']);
        $this->patchJson($path, ['payment_status' => 'paid'])->assertForbidden();
        $this->assertFalse($this->home()[0]['can_edit']);
        DB::connection('admin')->table('family_memberships')->where('id', $this->anaMember)->update(['revoked_at' => now()]);
        $this->getJson($this->base.'/home?month=2026-10')->assertNotFound();
    }

    public function test_revoked_payer_and_supported_date_limit_stop_recurrence_without_losing_paid_record(): void
    {
        $expense = $this->create(['payer_membership_id' => $this->mihaiMember]);
        DB::connection('admin')->table('family_memberships')->where('id', $this->mihaiMember)->update(['revoked_at' => now()]);
        $paid = $this->pay($expense);
        $this->assertNull($paid['next_due_on']);
        $this->assertStringContainsString('plătitorul', $paid['recurrence_notice']);
        $edge = $this->create(['spent_on' => '2099-12-31', 'recurrence' => 'daily']);
        $paid = $this->pay($edge);
        $this->assertNull($paid['next_due_on']);
        $this->assertSame('paid', $paid['payment_status']);
        $this->assertCount(0, $this->planned());
    }

    public function test_planned_and_paid_dates_stay_in_calendar_without_activating_reminders(): void
    {
        $expense = $this->create(['recurrence' => 'weekly']);
        $calendar = $this->getJson($this->base.'/calendar-items?month=2026-10')->assertOk()->json();
        $this->assertCount(1, $calendar);
        $this->assertSame($expense['id'], $calendar[0]['source']['id']);
        $this->assertNull($calendar[0]['reminder']);
        $this->pay($expense);
        $this->getJson($this->base.'/calendar-items?month=2026-10')->assertOk()->assertJsonCount(2);
        $this->assertSame(0, DB::connection('admin')->table('calendar_items')->where('family_id', $this->family)->count());
    }

    public function test_invalid_recurrence_metadata_and_status_are_rejected(): void
    {
        foreach ([['recurrence' => 'hourly'], ['payment_status' => 'pending'], ['recurrence_anchor_on' => '2026-10-01'],
            ['recurrence_parent_id' => (string) Str::uuid()], ['has_next_payment' => true]] as $change) {
            $this->postJson($this->base.'/expenses', $this->content($change))->assertUnprocessable();
        }
        $this->getJson($this->base.'/expenses?payment_status=pending')->assertUnprocessable();
    }

    public function test_database_prevents_duplicate_successors_even_after_soft_delete(): void
    {
        $parent = $this->create(['payment_status' => 'paid']);
        $child = DB::connection('admin')->table('expenses')->where('family_id', $this->family)->where('recurrence_parent_id', $parent['id'])->first();
        DB::connection('admin')->table('expenses')->where('id', $child->id)->update(['deleted_at' => now()]);
        $copy = (array) $child;
        $copy['id'] = (string) Str::uuid();
        try {
            DB::connection('admin')->table('expenses')->insert($copy);
            $this->fail('A duplicate successor must be rejected.');
        } catch (QueryException $exception) {
            $this->assertSame('23505', $exception->errorInfo[0]);
        }
    }

    public function test_home_uses_romanian_today_and_includes_the_following_month(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-24T21:30:00Z'));
        $this->create(['spent_on' => '2026-10-24', 'recurrence' => null]);
        $today = $this->create(['spent_on' => '2026-10-25', 'recurrence' => null]);
        $last = $this->create(['spent_on' => '2026-11-01', 'recurrence' => null]);
        $this->create(['spent_on' => '2026-11-02', 'recurrence' => null]);
        $this->assertSame([$today['id'], $last['id']], array_column($this->home(), 'id'));
    }

    public function test_database_rejects_a_recurrence_parent_from_another_family(): void
    {
        $parent = $this->create();
        $copy = (array) DB::connection('admin')->table('expenses')->where('id', $parent['id'])->first();
        $copy['id'] = (string) Str::uuid();
        $copy['family_id'] = $this->otherFamily;
        $copy['payer_membership_id'] = DB::connection('admin')->table('family_memberships')->where('family_id', $this->otherFamily)->value('id');
        $copy['recurrence_parent_id'] = $parent['id'];
        try {
            DB::connection('admin')->table('expenses')->insert($copy);
            $this->fail('A recurrence parent from another family must be rejected.');
        } catch (QueryException $exception) {
            $this->assertSame('23503', $exception->errorInfo[0]);
        }
    }
}
