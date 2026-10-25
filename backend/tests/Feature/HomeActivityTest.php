<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class HomeActivityTest extends TestCase
{
    private User $ana;

    private User $mihai;

    private string $family;

    private string $otherFamily;

    private string $anaMember;

    private string $mihaiMember;

    private string $otherMember;

    private string $base;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('memoria_test', config('database.connections.pgsql.database'));
        $admin = config('database.connections.pgsql');
        $admin['username'] = 'memoria_admin';
        $admin['password'] = env('DB_ADMIN_PASSWORD');
        config(['database.connections.admin' => $admin]);
        $this->travelTo(CarbonImmutable::parse('2026-10-25T12:00:00Z'));
        $this->family = (string) Str::uuid();
        $this->otherFamily = (string) Str::uuid();
        foreach ([$this->family, $this->otherFamily] as $family) {
            DB::connection('admin')->table('families')->insert(['id' => $family, 'name' => 'H6 activity fixture']);
        }
        foreach (['ana' => 'Ana Test', 'mihai' => 'Mihai Test'] as $key => $name) {
            $user = User::factory()->make(['name' => $name, 'email' => 'h6-'.$this->family.'-'.$key.'@example.test']);
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
        $this->base = '/api/v1/families/'.$this->family;
    }

    protected function tearDown(): void
    {
        try {
            DB::disableQueryLog();
            foreach ([$this->family, $this->otherFamily] as $family) {
                foreach (['calendar_reminder_deliveries', 'calendar_reminder_recipients', 'calendar_items',
                    'subscription_shares', 'subscriptions', 'pet_documents', 'pet_entries', 'pets',
                    'vehicle_documents', 'vehicle_entries', 'vehicles', 'kitchen_meal_votes', 'kitchen_meals',
                    'recipe_grants', 'recipe_versions', 'recipes', 'memory_moment_photos', 'memory_moment_grants',
                    'memory_moment_people', 'memory_timeline_sources', 'memory_moments', 'family_tasks', 'expenses',
                    'outbox_deliveries', 'outbox_events', 'audit_events', 'member_profiles', 'family_memberships'] as $table) {
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

    private function home(): string
    {
        return $this->base.'/home?month=2026-10';
    }

    private function insert(string $table, array $data): string
    {
        $id = (string) Str::uuid();
        DB::connection('admin')->table($table)->insert(['id' => $id] + array_replace(['family_id' => $this->family], $data));

        return $id;
    }

    private function audit(string $action, string $resource, array $changes = []): string
    {
        return $this->insert('audit_events', array_replace(['actor_id' => $this->ana->id, 'action' => $action,
            'resource_id' => $resource, 'created_at' => now()], $changes));
    }

    private function task(array $changes = []): string
    {
        return $this->insert('family_tasks', array_replace(['owner_id' => $this->ana->id,
            'assignee_membership_id' => $this->anaMember, 'title' => 'Sarcină H6', 'due_on' => '2026-11-20',
            'visibility' => 'family', 'created_at' => now(), 'updated_at' => now()], $changes));
    }

    private function targetIds(array $items): array
    {
        return array_column(array_column($items, 'target'), 'id');
    }

    public function test_activity_requires_membership_and_records_the_member_who_completed_the_task(): void
    {
        $this->getJson($this->home())->assertUnauthorized();
        $this->actingAs($this->mihai)->getJson('/api/v1/families/'.$this->otherFamily.'/home?month=2026-10')->assertNotFound();
        $task = $this->task(['assignee_membership_id' => $this->mihaiMember]);
        $this->audit('task.created', $task, ['created_at' => now()->subMinute()]);
        DB::connection('admin')->table('family_memberships')->where('id', $this->mihaiMember)->update(['role' => 'limited']);
        $this->withHeader('If-Match', '"1"')->patchJson($this->base.'/tasks/'.$task.'/completion', ['completed' => true])->assertOk();
        $response = $this->actingAs($this->ana)->getJson($this->home())->assertOk()->assertJsonCount(2, 'activity')
            ->assertJsonPath('activity.0.action', 'task.completed')->assertJsonPath('activity.0.actor_name', 'Mihai Test')
            ->assertJsonPath('activity.1.actor_name', 'Ana Test')->assertJsonPath('activity.0.target.id', $task)
            ->assertJsonPath('activity.0.title', 'Sarcină H6')->assertJsonCount(0, 'tasks');
        $this->assertSame(['id', 'action', 'actor_name', 'occurred_at', 'title', 'target'], array_keys($response->json('activity.0')));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertSame(0, DB::table('audit_events')->count());
        DB::connection('admin')->table('family_memberships')->where('id', $this->mihaiMember)->update(['revoked_at' => now()]);
        $this->actingAs($this->mihai)->getJson($this->home())->assertNotFound();
    }

    public function test_activity_uses_current_private_access_and_never_crosses_families(): void
    {
        $shared = $this->task(['owner_id' => $this->mihai->id]);
        $mine = $this->task(['visibility' => 'private']);
        $hidden = $this->task(['owner_id' => $this->mihai->id, 'visibility' => 'private']);
        $other = $this->task(['family_id' => $this->otherFamily, 'assignee_membership_id' => $this->otherMember]);
        foreach ([$shared, $mine, $hidden] as $task) {
            $this->audit('task.created', $task);
        }
        $this->audit('task.created', $other, ['family_id' => $this->otherFamily]);
        $expense = $this->insert('expenses', ['owner_id' => $this->mihai->id, 'payer_membership_id' => $this->mihaiMember,
            'amount_bani' => 1000, 'category' => 'food', 'spent_on' => '2025-01-01', 'visibility' => 'private']);
        $this->audit('expense.created', $expense, ['actor_id' => $this->mihai->id]);
        $items = $this->actingAs($this->ana)->getJson($this->home())->assertOk()->assertJsonCount(2, 'activity')->json('activity');
        $this->assertEqualsCanonicalizing([$shared, $mine], $this->targetIds($items));
        DB::connection('admin')->table('family_tasks')->where('id', $shared)->update(['visibility' => 'private']);
        DB::connection('admin')->table('family_tasks')->where('id', $mine)->update(['deleted_at' => now()]);
        $this->getJson($this->home())->assertOk()->assertJsonPath('activity', []);
        $items = $this->actingAs($this->mihai)->getJson($this->home())->assertOk()->assertJsonCount(3, 'activity')->json('activity');
        $this->assertContains($expense, $this->targetIds($items));
        $expenseItem = collect($items)->firstWhere('target.id', $expense);
        $this->assertSame('Alimente', $expenseItem['title']);
        $this->assertSame('2025-01-01', $expenseItem['target']['date']);
    }

    public function test_today_uses_romanian_midnights_across_the_25_hour_autumn_day(): void
    {
        $task = $this->task();
        $before = $this->audit('task.created', $task, ['created_at' => '2026-10-24 20:59:59']);
        $start = $this->audit('task.created', $task, ['created_at' => '2026-10-24 21:00:00']);
        $last = $this->audit('task.completed', $task, ['created_at' => '2026-10-25 21:59:59']);
        $after = $this->audit('task.reopened', $task, ['created_at' => '2026-10-25 22:00:00']);
        $items = $this->actingAs($this->ana)->getJson($this->home())->assertOk()->assertJsonPath('today', '2026-10-25')->json('activity');
        $this->assertSame([$last, $start], array_column($items, 'id'));
        $this->assertSame('2026-10-24T21:00:00+00:00', $items[1]['occurred_at']);
        $this->travelTo(CarbonImmutable::parse('2026-10-25T22:01:00Z'));
        $this->getJson($this->home())->assertOk()->assertJsonPath('today', '2026-10-26')->assertJsonCount(1, 'activity')
            ->assertJsonPath('activity.0.id', $after);
        $this->assertNotSame($before, $after);
    }

    public function test_memory_grants_and_archiving_apply_immediately_to_activity(): void
    {
        $moment = $this->insert('memory_moments', ['owner_id' => $this->mihai->id, 'kind' => 'moment', 'title' => 'Amintire selectată',
            'occurred_on' => '2000-01-01', 'visibility' => 'selected', 'created_at' => now(), 'updated_at' => now()]);
        $this->audit('memory.created', $moment, ['actor_id' => $this->mihai->id]);
        $this->actingAs($this->ana)->getJson($this->home())->assertOk()->assertJsonPath('activity', []);
        DB::connection('admin')->table('memory_moment_grants')->insert(['family_id' => $this->family, 'moment_id' => $moment, 'membership_id' => $this->anaMember]);
        $this->getJson($this->home())->assertOk()->assertJsonCount(1, 'activity')->assertJsonPath('activity.0.target.kind', 'memory')
            ->assertJsonPath('activity.0.title', 'Amintire selectată');
        DB::connection('admin')->table('memory_moment_grants')->where('moment_id', $moment)->delete();
        $this->getJson($this->home())->assertOk()->assertJsonPath('activity', []);
        DB::connection('admin')->table('memory_moments')->where('id', $moment)->update(['visibility' => 'family', 'archived_at' => now()]);
        $this->actingAs($this->mihai)->getJson($this->home())->assertOk()->assertJsonPath('activity', []);
    }

    public function test_kitchen_and_manual_calendar_activity_respect_source_access_and_exclude_derived_events(): void
    {
        $recipe = $this->insert('recipes', ['owner_id' => $this->mihai->id, 'visibility' => 'private', 'created_at' => now(), 'updated_at' => now()]);
        $version = $this->insert('recipe_versions', ['recipe_id' => $recipe, 'version_no' => 1, 'title' => 'Supă H6', 'category' => 'soup',
            'ingredients' => '[]', 'instructions' => 'Fierbe', 'duration_minutes' => 20, 'servings' => 2, 'difficulty' => 'easy']);
        $this->audit('recipe.created', $recipe, ['actor_id' => $this->mihai->id]);
        $meal = $this->insert('kitchen_meals', ['owner_id' => $this->mihai->id, 'recipe_id' => $recipe, 'recipe_version_id' => $version,
            'dish_title' => 'Supă H6', 'meal_date' => '2026-10-20', 'meal_type' => 'lunch', 'visibility' => 'family']);
        $this->audit('meal.created', $meal, ['actor_id' => $this->mihai->id]);
        $event = $this->insert('calendar_items', ['owner_id' => $this->mihai->id, 'title' => 'Eveniment H6', 'event_date' => '2026-11-01', 'all_day' => true, 'visibility' => 'family']);
        $this->audit('calendar.created', $event, ['actor_id' => $this->mihai->id]);
        $task = $this->task();
        $derived = $this->insert('calendar_items', ['owner_id' => $this->ana->id, 'title' => 'Sarcină derivată', 'event_date' => '2026-11-20',
            'all_day' => true, 'visibility' => 'family', 'source_type' => 'task', 'source_id' => $task, 'source_parent_id' => $task, 'source_version' => 1]);
        $this->audit('calendar.created', $derived);
        $this->audit('meal.vote_saved', $meal);
        $items = $this->actingAs($this->ana)->getJson($this->home())->assertOk()->assertJsonCount(2, 'activity')->json('activity');
        $this->assertEqualsCanonicalizing([$meal, $event], $this->targetIds($items));
        $this->assertSame('2026-10-20', collect($items)->firstWhere('target.id', $meal)['target']['date']);
        DB::connection('admin')->table('recipe_grants')->insert(['family_id' => $this->family, 'recipe_id' => $recipe, 'membership_id' => $this->anaMember]);
        DB::connection('admin')->table('recipes')->where('id', $recipe)->update(['visibility' => 'selected']);
        $this->getJson($this->home())->assertOk()->assertJsonCount(3, 'activity');
        DB::connection('admin')->table('recipe_grants')->where('recipe_id', $recipe)->delete();
        DB::connection('admin')->table('kitchen_meals')->where('id', $meal)->update(['visibility' => 'private']);
        DB::connection('admin')->table('calendar_items')->where('id', $event)->update(['visibility' => 'private']);
        $this->getJson($this->home())->assertOk()->assertJsonPath('activity', []);
    }

    public function test_subscriptions_exclude_other_source_families_and_credential_audit_events(): void
    {
        $subscription = $this->insert('subscriptions', ['owner_id' => $this->ana->id, 'service_key' => 'custom', 'service_name' => 'Abonament H6',
            'credentials_ciphertext' => Crypt::encryptString('{"password":"H6-only-synthetic-secret"}')]);
        $this->audit('subscription.created', $subscription);
        $this->audit('subscription.credentials_viewed', $subscription);
        $external = $this->insert('subscriptions', ['family_id' => $this->otherFamily, 'owner_id' => $this->ana->id,
            'service_key' => 'custom', 'service_name' => 'Extern H6']);
        $this->audit('subscription.created', $external, ['family_id' => $this->otherFamily]);
        $this->insert('subscription_shares', ['family_id' => $this->otherFamily, 'subscription_id' => $external,
            'recipient_id' => $this->mihai->id, 'status' => 'accepted']);
        $this->actingAs($this->mihai)->getJson($this->base.'/subscriptions/'.$external)->assertOk();
        $response = $this->getJson($this->home())->assertOk()->assertJsonCount(1, 'activity')->assertJsonPath('activity.0.target.id', $subscription);
        $this->assertStringNotContainsString('H6-only-synthetic-secret', $response->getContent());
        $this->assertStringNotContainsString('credentials', json_encode($response->json('activity')));
        $this->actingAs($this->ana)->withHeader('If-Match', '"1"')->deleteJson($this->base.'/subscriptions/'.$subscription)->assertNoContent();
        $this->actingAs($this->mihai)->getJson($this->home())->assertOk()->assertJsonPath('activity', []);
    }

    public function test_vehicle_and_pet_entries_keep_parent_targets_and_disappear_with_archived_parents(): void
    {
        $vehicle = $this->insert('vehicles', ['created_by' => $this->ana->id, 'name' => 'Mașină H6', 'make' => 'Dacia', 'model' => 'Duster']);
        $carEntry = $this->insert('vehicle_entries', ['vehicle_id' => $vehicle, 'author_id' => $this->mihai->id, 'title' => 'Service H6',
            'kind' => 'service', 'state' => 'completed', 'event_date' => '2026-10-20']);
        $pet = $this->insert('pets', ['created_by' => $this->ana->id, 'name' => 'Cățel H6', 'species' => 'Câine']);
        $petEntry = $this->insert('pet_entries', ['pet_id' => $pet, 'author_id' => $this->mihai->id, 'title' => 'Vizită H6', 'kind' => 'visit', 'event_date' => '2026-10-20']);
        foreach (['vehicle.created' => $vehicle, 'vehicle.entry_created' => $carEntry, 'pet.created' => $pet, 'pet.entry_created' => $petEntry] as $action => $id) {
            $this->audit($action, $id, ['actor_id' => $this->mihai->id]);
        }
        DB::connection('admin')->table('family_memberships')->where('id', $this->mihaiMember)->update(['role' => 'limited']);
        $items = $this->actingAs($this->mihai)->getJson($this->home())->assertOk()->assertJsonCount(4, 'activity')->json('activity');
        $this->assertSame(['kind' => 'vehicle_entry', 'id' => $carEntry, 'parent_id' => $vehicle], collect($items)->firstWhere('target.id', $carEntry)['target']);
        $this->assertSame(['kind' => 'pet_entry', 'id' => $petEntry, 'parent_id' => $pet], collect($items)->firstWhere('target.id', $petEntry)['target']);
        DB::connection('admin')->table('vehicles')->where('id', $vehicle)->update(['archived_at' => now()]);
        DB::connection('admin')->table('pets')->where('id', $pet)->update(['archived_at' => now()]);
        $this->getJson($this->home())->assertOk()->assertJsonPath('activity', []);
    }

    public function test_limit_and_order_apply_after_authorization_without_per_item_queries(): void
    {
        $this->actingAs($this->ana)->getJson($this->home())->assertOk();
        DB::enableQueryLog();
        $this->getJson($this->home())->assertOk()->assertJsonPath('activity', []);
        $emptyQueries = count(DB::getQueryLog());
        DB::flushQueryLog();
        $expected = [];
        for ($i = 0; $i < 24; $i++) {
            $task = $this->task(['title' => 'Visible '.$i]);
            $expected[] = $this->audit('task.created', $task, ['created_at' => '2026-10-25 10:00:00']);
            $private = $this->task(['owner_id' => $this->mihai->id, 'visibility' => 'private']);
            $this->audit('task.created', $private, ['created_at' => '2026-10-25 11:00:00']);
        }
        rsort($expected, SORT_STRING);
        $items = $this->getJson($this->home())->assertOk()->assertJsonCount(20, 'activity')->json('activity');
        $this->assertSame(array_slice($expected, 0, 20), array_column($items, 'id'));
        $this->assertSame($emptyQueries, count(DB::getQueryLog()));
    }
}
