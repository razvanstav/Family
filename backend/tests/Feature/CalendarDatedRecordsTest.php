<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Documents\Application\DocumentExpiryReminders;
use App\Modules\Memory\Application\MemoryAnniversaryReminders;
use App\Modules\Pets\Application\PetCalendar;
use App\Modules\Tasks\Application\TaskCalendar;
use App\Modules\Vehicles\Application\VehicleCalendar;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class CalendarDatedRecordsTest extends TestCase
{
    private string $family;

    private array $families = [];

    private array $users = [];

    private array $members = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('memoria_test', config('database.connections.pgsql.database'));
        $admin = config('database.connections.pgsql');
        $admin['username'] = 'memoria_admin';
        $admin['password'] = env('DB_ADMIN_PASSWORD');
        config(['database.connections.admin' => $admin]);
        $this->travelTo(CarbonImmutable::parse('2026-10-24 08:00', 'Europe/Bucharest'));
        $this->family = $this->newFamily();
        foreach (['owner', 'reader', 'minor'] as $name) {
            $user = User::factory()->make(['email' => 'dated-'.$this->family.'-'.$name.'@example.test']);
            $user->setConnection('admin')->save();
            $this->users[$name] = $user;
            $this->members[$name] = (string) Str::uuid();
            DB::connection('admin')->table('family_memberships')->insert(['id' => $this->members[$name], 'family_id' => $this->family, 'user_id' => $user->id, 'role' => $name === 'owner' ? 'owner' : 'adult']);
        }
        $this->members['child'] = (string) Str::uuid();
        DB::connection('admin')->table('family_memberships')->insert(['id' => $this->members['child'], 'family_id' => $this->family, 'user_id' => null, 'role' => 'limited', 'child_name' => 'Copil test']);
        foreach (['owner' => '1980-10-12', 'reader' => '1990-10-13', 'minor' => '2015-10-14', 'child' => '2020-10-15'] as $name => $birth) {
            DB::connection('admin')->table('member_profiles')->insert(['family_id' => $this->family, 'membership_id' => $this->members[$name], 'birth_date' => $birth]);
        }
        $this->actingAs($this->users['owner'])->withHeader('Accept', 'application/json');
    }

    protected function tearDown(): void
    {
        try {
            foreach ($this->families as $family) {
                foreach (['calendar_items', 'documents', 'memory_moments', 'kitchen_meals', 'recipe_versions', 'recipes', 'family_tasks', 'expenses', 'vehicle_entries', 'vehicles', 'pet_entries', 'pets', 'member_profile_entries', 'member_profiles', 'media_assets', 'audit_events', 'family_memberships'] as $table) {
                    DB::connection('admin')->table($table)->where('family_id', $family)->delete();
                }
                DB::connection('admin')->table('families')->where('id', $family)->delete();
            }
            foreach ($this->users as $user) {
                DB::connection('admin')->table('users')->where('id', $user->id)->delete();
            }
        } finally {
            $this->travelBack();
            DB::disconnect('pgsql');
            DB::disconnect('admin');
            parent::tearDown();
        }
    }

    private function newFamily(): string
    {
        $family = (string) Str::uuid();
        $this->families[] = $family;
        DB::connection('admin')->table('families')->insert(['id' => $family, 'name' => 'Date Calendar test']);

        return $family;
    }

    private function record(string $table, array $values = []): string
    {
        $id = (string) Str::uuid();
        $defaults = match ($table) {
            'documents' => ['owner_id' => $this->users['owner']->id, 'asset_id' => null, 'title' => 'Document fără fișier', 'category' => 'personal', 'subject_membership_id' => $this->members['owner'], 'visibility' => 'private', 'expires_on' => '2026-10-30'],
            'member_profile_entries' => ['membership_id' => $this->members['child'], 'author_id' => $this->users['owner']->id, 'kind' => 'visit', 'title' => 'Vizită medic', 'notes' => 'Detaliu în sursă.', 'occurred_on' => '2026-10-26', 'visibility' => 'private'],
            'expenses' => ['owner_id' => $this->users['owner']->id, 'payer_membership_id' => $this->members['owner'], 'amount_bani' => 1200, 'category' => 'health', 'spent_on' => '2026-10-23', 'note' => 'Cheltuială test', 'visibility' => 'family'],
            'family_tasks' => ['owner_id' => $this->users['owner']->id, 'assignee_membership_id' => $this->members['reader'], 'title' => 'Sarcină test', 'due_on' => '2026-10-27', 'visibility' => 'family'],
            'memory_moments' => ['owner_id' => $this->users['owner']->id, 'title' => 'Amintire test', 'kind' => 'note', 'occurred_on' => '2026-10-20', 'visibility' => 'family'],
            default => [],
        };
        DB::connection('admin')->table($table)->insert($values + ['id' => $id, 'family_id' => $this->family] + $defaults);

        return $id;
    }

    private function tenant(Closure $callback): mixed
    {
        return DB::transaction(function () use ($callback) {
            DB::table('families')->where('id', $this->family)->lockForUpdate()->firstOrFail();
            DB::select("SELECT set_config('app.family_id', ?, true)", [$this->family]);

            return $callback();
        });
    }

    private function calendar(string $month = '2026-10'): array
    {
        return $this->getJson('/api/v1/families/'.$this->family.'/calendar-items?month='.$month)->assertOk()->json();
    }

    private function find(array $events, string $type, string $id): array
    {
        $matches = array_values(array_filter($events, fn (array $event) => $event['source'] !== null && $event['source']['type'] === $type && $event['source']['id'] === $id));
        $this->assertCount(1, $matches);

        return $matches[0];
    }

    private function meal(string $visibility = 'family'): string
    {
        $recipe = $this->record('recipes', ['owner_id' => $this->users['owner']->id]);
        $version = $this->record('recipe_versions', ['recipe_id' => $recipe, 'version_no' => 1, 'title' => 'Supă test', 'category' => 'soup', 'ingredients' => '[]', 'instructions' => 'Instrucțiuni test', 'duration_minutes' => 15, 'servings' => 2, 'difficulty' => 'easy']);

        return $this->record('kitchen_meals', ['owner_id' => $this->users['owner']->id, 'recipe_id' => $recipe, 'recipe_version_id' => $version, 'dish_title' => 'Supă test', 'meal_date' => '2026-10-24', 'meal_type' => 'lunch', 'visibility' => $visibility]);
    }

    private function vehicleEntry(string $state): string
    {
        $vehicle = $this->record('vehicles', ['created_by' => $this->users['owner']->id, 'name' => 'Mașină test', 'make' => 'Marcă', 'model' => 'Model']);

        return $this->record('vehicle_entries', ['vehicle_id' => $vehicle, 'author_id' => $this->users['owner']->id, 'kind' => 'service', 'title' => 'Revizie test', 'event_date' => $state === 'planned' ? '2026-10-29' : '2026-10-21', 'state' => $state]);
    }

    private function petEntry(bool $completed = false): array
    {
        $pet = $this->record('pets', ['created_by' => $this->users['owner']->id, 'name' => 'Animal test', 'species' => 'Câine', 'birth_date' => '2026-10-01']);
        $entry = $this->record('pet_entries', ['pet_id' => $pet, 'author_id' => $this->users['owner']->id, 'kind' => 'visit', 'title' => 'Vizită veterinar', 'event_date' => '2026-10-20', 'next_due_on' => '2026-10-28', 'next_due_completed_at' => $completed ? now() : null]);

        return [$pet, $entry];
    }

    public function test_metadata_only_expiry_and_medical_visit_are_readonly_calendar_events_without_notification_opt_in(): void
    {
        $document = $this->record('documents');
        $visit = $this->record('member_profile_entries');
        $events = $this->calendar();
        $doc = $this->find($events, 'document_date', $document);
        $entry = $this->find($events, 'profile_entry', $visit);
        foreach ([$doc, $entry] as $event) {
            $this->assertTrue(Str::isUuid($event['id']));
            $this->assertTrue($event['source']['virtual']);
            $this->assertTrue($event['all_day']);
            $this->assertFalse($event['can_edit']);
            $this->assertNull($event['reminder']);
            $this->assertNull($event['time']);
            $this->getJson('/api/v1/families/'.$this->family.'/calendar-items/'.$event['id'])->assertOk()->assertHeader('ETag', '"1"')->assertJsonPath('id', $event['id']);
        }
        $this->assertSame('visit', $entry['source']['kind']);
        $this->getJson('/api/v1/families/'.$this->family.'/members/'.$this->members['child'].'/profile/entries/'.$visit)->assertOk()->assertJsonPath('notes', 'Detaliu în sursă.')->assertJsonPath('can_manage', false);
        $this->assertSame(0, DB::connection('admin')->table('calendar_items')->where('family_id', $this->family)->count());
        $this->assertSame(0, DB::connection('admin')->table('calendar_reminder_deliveries')->where('family_id', $this->family)->count());
        $this->assertFalse(DB::connection('admin')->table('user_notification_preferences')->where('user_id', $this->users['owner']->id)->exists());
    }

    public function test_virtual_dates_preserve_private_selected_adult_and_family_access_and_grant_revocation(): void
    {
        $private = [$this->record('documents'), $this->record('member_profile_entries'), $this->record('expenses', ['visibility' => 'private']), $this->record('family_tasks', ['visibility' => 'private']), $this->meal('private'), $this->record('memory_moments', ['visibility' => 'private'])];
        $selected = $this->record('documents', ['visibility' => 'selected']);
        $adults = $this->record('documents', ['visibility' => 'adults']);
        $memory = $this->record('memory_moments', ['visibility' => 'selected']);
        $visit = $this->record('member_profile_entries', ['visibility' => 'family']);
        DB::connection('admin')->table('document_grants')->insert(['family_id' => $this->family, 'document_id' => $selected, 'membership_id' => $this->members['reader']]);
        DB::connection('admin')->table('memory_moment_grants')->insert(['family_id' => $this->family, 'moment_id' => $memory, 'membership_id' => $this->members['reader']]);
        $this->actingAs($this->users['reader']);
        $events = $this->calendar();
        $ids = array_column(array_column($events, 'source'), 'id');
        foreach ($private as $id) {
            $this->assertNotContains($id, $ids);
        }
        $selectedEvent = $this->find($events, 'document_date', $selected);
        $memoryEvent = $this->find($events, 'memory_date', $memory);
        $this->assertSame('selected', $selectedEvent['visibility']);
        $this->find($events, 'document_date', $adults);
        $this->find($events, 'profile_entry', $visit);
        DB::connection('admin')->table('document_grants')->where('document_id', $selected)->delete();
        DB::connection('admin')->table('memory_moment_grants')->where('moment_id', $memory)->delete();
        foreach ([$selectedEvent, $memoryEvent] as $event) {
            $this->getJson('/api/v1/families/'.$this->family.'/calendar-items/'.$event['id'])->assertNotFound();
        }
        $this->actingAs($this->users['minor']);
        $this->assertNotContains($adults, array_column(array_column($this->calendar(), 'source'), 'id'));
        $this->getJson('/api/v1/families/'.$this->family.'/members/'.$this->members['child'].'/profile/entries/'.$private[1])->assertNotFound();
    }

    public function test_all_dates_include_completed_history_and_deduplicate_existing_projections(): void
    {
        $document = $this->record('documents');
        $completedTask = $this->record('family_tasks', ['completed_at' => now()]);
        $pendingTask = $this->record('family_tasks');
        $expense = $this->record('expenses');
        $meal = $this->meal();
        $moment = $this->record('memory_moments');
        $oldMoment = $this->record('memory_moments', ['visibility' => 'private', 'occurred_on' => '2020-10-26']);
        $completedVehicle = $this->vehicleEntry('completed');
        $plannedVehicle = $this->vehicleEntry('planned');
        [$pet, $completedPet] = $this->petEntry(true);
        [$pendingPet, $pendingEntry] = $this->petEntry();
        $preferences = array_fill_keys(['calendar', 'tasks', 'birthdays', 'deadlines', 'memories'], ['in_app' => true, 'email' => false]);
        DB::connection('admin')->table('user_notification_preferences')->insert(['user_id' => $this->users['owner']->id, 'preferences' => json_encode($preferences)]);
        $this->tenant(function () use ($pendingTask, $plannedVehicle, $pendingEntry): void {
            app(TaskCalendar::class)->syncTask($this->family, $pendingTask);
            app(VehicleCalendar::class)->syncEntry($this->family, $plannedVehicle);
            app(PetCalendar::class)->syncEntry($this->family, $pendingEntry);
            app(DocumentExpiryReminders::class)->syncFamily($this->family);
            app(MemoryAnniversaryReminders::class)->syncFamily($this->family);
        });
        $events = $this->calendar();
        foreach ([['task_date', $completedTask], ['expense', $expense], ['meal', $meal], ['memory_date', $moment], ['vehicle_date', $completedVehicle], ['pet_birth', $pet], ['pet_birth', $pendingPet], ['pet_record', $completedPet], ['pet_due', $completedPet], ['pet_record', $pendingEntry]] as [$type, $id]) {
            $this->find($events, $type, $id);
        }
        foreach ([['task', $pendingTask, 'task_date'], ['vehicle_entry', $plannedVehicle, 'vehicle_date'], ['pet_entry', $pendingEntry, 'pet_due']] as [$stored, $id, $virtual]) {
            $this->find($events, $stored, $id);
            $this->assertCount(0, array_filter($events, fn ($event) => $event['source']['type'] === $virtual && $event['source']['id'] === $id));
        }
        $docEvents = array_values(array_filter($events, fn ($event) => in_array($event['source']['type'], ['document_expiry', 'document_date'], true) && $event['source']['parent_id'] === $document));
        $this->assertCount(1, $docEvents);
        $this->assertSame('document_expiry', $docEvents[0]['source']['type']);
        $this->assertCount(1, array_filter($events, fn ($event) => $event['source']['type'] === 'memory_anniversary' && $event['source']['parent_id'] === $oldMoment));
        $this->find($this->calendar('2020-10'), 'memory_date', $oldMoment);
    }

    public function test_date_changes_deletion_archiving_and_subject_revocation_apply_without_calendar_writes(): void
    {
        $document = $this->record('documents');
        $visit = $this->record('member_profile_entries');
        $expense = $this->record('expenses');
        $task = $this->record('family_tasks', ['completed_at' => now()]);
        $memory = $this->record('memory_moments');
        $meal = $this->meal();
        $docEvent = $this->find($this->calendar(), 'document_date', $document);
        DB::connection('admin')->table('documents')->where('id', $document)->update(['expires_on' => '2026-11-02', 'version' => 2]);
        $this->getJson('/api/v1/families/'.$this->family.'/calendar-items/'.$docEvent['id'])->assertOk()->assertHeader('ETag', '"2"')->assertJsonPath('event_date', '2026-11-02');
        $this->assertSame($docEvent['id'], $this->find($this->calendar('2026-11'), 'document_date', $document)['id']);
        DB::connection('admin')->table('expenses')->where('id', $expense)->update(['deleted_at' => now()]);
        DB::connection('admin')->table('family_tasks')->where('id', $task)->update(['deleted_at' => now()]);
        DB::connection('admin')->table('memory_moments')->where('id', $memory)->update(['archived_at' => now()]);
        DB::connection('admin')->table('kitchen_meals')->where('id', $meal)->update(['archived_at' => now()]);
        DB::connection('admin')->table('family_memberships')->where('id', $this->members['child'])->update(['revoked_at' => now()]);
        $remaining = array_column(array_column($this->calendar(), 'source'), 'id');
        foreach ([$document, $visit, $expense, $task, $memory, $meal] as $id) {
            $this->assertNotContains($id, $remaining);
        }
        $this->assertSame(0, DB::connection('admin')->table('calendar_items')->where('family_id', $this->family)->count());
    }

    public function test_raw_birth_dates_remain_subject_or_owner_only_without_enabling_shared_birthdays(): void
    {
        $this->find($this->calendar('1990-10'), 'member_birth', $this->members['reader']);
        $this->find($this->calendar('2020-10'), 'member_birth', $this->members['child']);
        $this->actingAs($this->users['reader']);
        $own = $this->find($this->calendar('1990-10'), 'member_birth', $this->members['reader']);
        $this->assertSame('private', $own['visibility']);
        $this->assertSame([], $this->calendar('1980-10'));
        $this->assertSame([], $this->calendar('2020-10'));
        $this->assertSame([], $this->calendar());
        $this->assertSame(0, DB::connection('admin')->table('calendar_items')->where('family_id', $this->family)->count());
    }

    public function test_queries_are_batched_and_canonical_dates_remain_isolated_to_the_active_family(): void
    {
        $visit = $this->record('member_profile_entries', ['visibility' => 'family']);
        $queries = 0;
        DB::listen(function (QueryExecuted $query) use (&$queries): void {
            if ($query->connectionName === 'pgsql' && str_contains($query->sql, 'as "dated_record"')) {
                $queries++;
            }
        });
        $before = $this->calendar();
        $this->assertSame(8, $queries);
        for ($i = 0; $i < 20; $i++) {
            $this->record('member_profile_entries', ['visibility' => 'family']);
        }
        $queries = 0;
        $this->assertCount(count($before) + 20, $this->calendar());
        $this->assertSame(8, $queries);
        $other = $this->newFamily();
        DB::connection('admin')->table('family_memberships')->insert(['id' => (string) Str::uuid(), 'family_id' => $other, 'user_id' => $this->users['owner']->id, 'role' => 'owner']);
        $this->getJson('/api/v1/families/'.$other.'/calendar-items?month=2026-10')->assertOk()->assertExactJson([]);
        $virtual = $this->find($before, 'profile_entry', $visit);
        $this->getJson('/api/v1/families/'.$other.'/calendar-items/'.$virtual['id'])->assertNotFound();
        $this->withHeader('If-Match', '"1"')->patchJson('/api/v1/families/'.$this->family.'/calendar-items/'.$virtual['id'], ['title' => 'Alt titlu'])->assertNotFound();
    }

    public function test_home_calendar_days_include_authorized_medical_dates_with_channels_off_without_changing_events_or_reminders(): void
    {
        $preferences = array_fill_keys(['calendar', 'tasks', 'birthdays', 'deadlines', 'memories'], ['in_app' => false, 'email' => false]);
        foreach ($this->users as $user) {
            DB::connection('admin')->table('user_notification_preferences')->insert(['user_id' => $user->id, 'preferences' => json_encode($preferences)]);
        }
        $this->postJson('/api/v1/families/'.$this->family.'/calendar-items', ['title' => 'Eveniment existent', 'event_date' => '2026-10-25',
            'all_day' => true, 'visibility' => 'family', 'reminder_offset_minutes' => 0, 'reminder_recipients' => [$this->members['owner']]])->assertCreated();
        $url = '/api/v1/families/'.$this->family.'/home?month=2026-10';
        $before = $this->getJson($url)->assertOk()->assertJsonPath('days', ['2026-10-25'])->json();
        $intents = DB::connection('admin')->table('calendar_reminder_deliveries')->where('family_id', $this->family)->count();
        $this->record('member_profile_entries', ['occurred_on' => '2026-10-27']);
        $this->record('member_profile_entries', ['occurred_on' => '2026-10-26', 'visibility' => 'family']);
        $this->record('member_profile_entries', ['occurred_on' => '2026-10-26', 'visibility' => 'family']);
        $this->record('documents');
        $after = $this->getJson($url)->assertOk()->assertJsonPath('days', ['2026-10-25', '2026-10-26', '2026-10-27', '2026-10-30'])->json();
        $this->assertSame($before['events'], $after['events']);
        $this->assertSame($before['reminders'], $after['reminders']);
        $this->assertSame([], $after['reminders']);
        $this->assertSame($intents, DB::connection('admin')->table('calendar_reminder_deliveries')->where('family_id', $this->family)->count());
        $this->actingAs($this->users['reader']);
        $reader = $this->getJson($url)->assertOk()->assertJsonPath('days', ['2026-10-25', '2026-10-26'])->json();
        $this->assertSame($before['events'], $reader['events']);
        $this->assertSame([], $reader['reminders']);
    }
}
