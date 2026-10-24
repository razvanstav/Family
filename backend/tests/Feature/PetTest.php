<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Calendar\Application\CalendarReminders;
use App\Modules\Calendar\Contracts\SourceItems;
use App\Platform\Media\Scanner;
use Carbon\CarbonImmutable;
use Database\Seeders\DemoSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery;
use RuntimeException;
use Tests\TestCase;

final class PetTest extends TestCase
{
    private User $ana;

    private User $mihai;

    private string $anaMember;

    private string $mihaiMember;

    private string $base;

    private string $pet;

    private object $scannerStub;

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
        Storage::fake('local');
        (new DemoSeeder)->run();
        DB::setDefaultConnection('pgsql');
        $this->ana = User::where('email', 'ana@example.test')->firstOrFail();
        $this->mihai = User::where('email', 'mihai@example.test')->firstOrFail();
        $members = DB::table('family_memberships')->where('family_id', DemoSeeder::POPESCU)->pluck('id', 'user_id');
        $this->anaMember = $members[$this->ana->id];
        $this->mihaiMember = $members[$this->mihai->id];
        $this->base = '/api/v1/families/'.DemoSeeder::POPESCU.'/pets';
        $this->actingAs($this->ana);
        $this->withHeader('Accept', 'application/json');
        $this->travelTo(CarbonImmutable::parse('2026-10-24T06:00:00Z'));
        $this->pet = $this->postJson($this->base, $this->profile())->assertCreated()->json('id');
        $this->scanner('clean');
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        DB::disableQueryLog();
        DB::disconnect('pgsql');
        DB::disconnect('admin');
        parent::tearDown();
    }

    private function scanner(string $result): void
    {
        if (! isset($this->scannerStub)) {
            $this->scannerStub = new class implements Scanner
            {
                public string $result = 'clean';

                public function scan(string $path): string
                {
                    return $this->result;
                }
            };
            $this->app->instance(Scanner::class, $this->scannerStub);
        }
        $this->scannerStub->result = $result;
    }

    private function profile(array $changes = []): array
    {
        return array_replace(['name' => 'Milo', 'species' => 'Pisică', 'breed' => null, 'birth_date' => null], $changes);
    }

    private function path(string $suffix = ''): string
    {
        return $this->base.'/'.$this->pet.$suffix;
    }

    private function content(array $changes = []): array
    {
        return array_replace(['kind' => 'vaccination', 'title' => 'Vaccinare', 'event_date' => '2026-10-01',
            'weight_kg' => null, 'cost' => '120.50', 'notes' => null, 'next_due_on' => null], $changes);
    }

    private function createEntry(array $changes = []): array
    {
        return $this->postJson($this->path('/entries'), $this->content($changes))->assertCreated()->json();
    }

    private function updateEntry(array $entry, array $changes): array
    {
        $data = array_intersect_key($entry, array_flip(['kind', 'title', 'event_date', 'weight_kg', 'cost', 'notes', 'next_due_on', 'reminder_offset_minutes', 'reminder_recipients']));

        return $this->withHeader('If-Match', '"'.$entry['version'].'"')->patchJson($this->path('/entries/'.$entry['id']), array_replace($data, $changes))->assertOk()->json();
    }

    private function completeEntry(array $entry, bool $completed): array
    {
        return $this->withHeader('If-Match', '"'.$entry['version'].'"')->patchJson($this->path('/entries/'.$entry['id'].'/completion'), ['completed' => $completed])->assertOk()->json();
    }

    private function calendar(string $month = '2026-11', bool $archived = false): array
    {
        $items = $this->getJson('/api/v1/families/'.DemoSeeder::POPESCU.'/calendar-items?month='.$month.'&archived='.(int) $archived)->assertOk()->json();

        return array_values(array_filter($items, fn (array $row) => ($row['source']['type'] ?? null) === 'pet_entry'));
    }

    public function test_profile_payload_versions_archive_restore_and_normal_adult_management(): void
    {
        $this->getJson($this->base)->assertOk()->assertJsonCount(1)->assertJsonPath('0.name', 'Milo')->assertJsonPath('0.photo_status', null)->assertJsonPath('0.birth_date', null)->assertJsonPath('0.version', 1);
        $this->patchJson($this->path(), $this->profile())->assertStatus(428);
        $this->actingAs($this->mihai)->withHeader('If-Match', '"1"')->patchJson($this->path(), $this->profile(['name' => 'Milo al familiei', 'birth_date' => '2021-03-09']))->assertOk()->assertJsonPath('version', 2);
        $this->patchJson($this->path(), $this->profile())->assertStatus(412);
        $this->withHeader('If-Match', '"2"')->postJson($this->path('/archive'), ['archived' => true])->assertOk()->assertJsonPath('version', 3);
        $this->getJson($this->base)->assertExactJson([]);
        $this->getJson($this->base.'?archived=1')->assertJsonCount(1);
        $this->postJson($this->path('/entries'), $this->content())->assertConflict();
        $this->withHeader('If-Match', '"3"')->patchJson($this->path(), $this->profile())->assertConflict();
        $this->postJson($this->path('/archive'), ['archived' => false])->assertOk()->assertJsonPath('version', 4);
        $this->getJson($this->path())->assertJsonPath('name', 'Milo al familiei')->assertJsonPath('can_manage', true);
    }

    public function test_profiles_validate_real_fields_without_creating_human_identities(): void
    {
        $users = DB::connection('admin')->table('users')->count();
        $members = DB::connection('admin')->table('family_memberships')->count();
        foreach ([['name' => '  '], ['species' => ''], ['name' => str_repeat('a', 101)], ['species' => str_repeat('a', 61)],
            ['breed' => str_repeat('a', 101)], ['birth_date' => '2026-02-30'], ['birth_date' => '2026-10-25'], ['birth_date' => '1899-01-01'],
            ['family_id' => DemoSeeder::IONESCU], ['photo_asset_id' => (string) Str::uuid()], ['created_by' => $this->mihai->id]] as $change) {
            $this->postJson($this->base, $this->profile($change))->assertUnprocessable();
        }
        $this->postJson($this->base, ['name' => 'Bruno', 'species' => 'Câine'])->assertCreated()->assertJsonPath('breed', null);
        $this->assertSame($users, DB::connection('admin')->table('users')->count());
        $this->assertSame($members, DB::connection('admin')->table('family_memberships')->count());
    }

    public function test_family_isolation_limited_access_revocation_and_database_rls(): void
    {
        $entry = $this->createEntry(['next_due_on' => '2026-11-02']);
        $foreign = '/api/v1/families/'.DemoSeeder::IONESCU.'/pets/'.$this->pet;
        foreach (['', '/entries', '/entries/'.$entry['id'], '/documents', '/photo'] as $suffix) {
            $this->getJson($foreign.$suffix)->assertNotFound();
        }
        DB::connection('admin')->table('family_memberships')->where('id', $this->mihaiMember)->update(['role' => 'limited']);
        $this->actingAs($this->mihai);
        $this->getJson($this->base)->assertOk()->assertJsonPath('0.can_manage', false);
        $this->getJson($this->path('/entries'))->assertOk()->assertJsonCount(1, 'items');
        $this->getJson($this->path('/documents'))->assertOk();
        $this->postJson($this->base, $this->profile())->assertForbidden();
        $this->withHeader('If-Match', '"1"')->patchJson($this->path(), $this->profile())->assertForbidden();
        $this->postJson($this->path('/entries'), $this->content())->assertForbidden();
        $this->patchJson($this->path('/entries/'.$entry['id'].'/completion'), ['completed' => true])->assertForbidden();
        $this->postJson($this->path('/archive'), ['archived' => true])->assertForbidden();
        $this->postJson($this->path('/photo'), ['action' => 'remove'])->assertForbidden();
        $this->postJson($this->path('/documents'), ['document_id' => (string) Str::uuid()])->assertForbidden();
        DB::connection('admin')->table('family_memberships')->where('id', $this->mihaiMember)->update(['revoked_at' => now()]);
        foreach ([$this->base, $this->path(), $this->path('/entries'), $this->path('/documents'), $this->path('/photo')] as $url) {
            $this->getJson($url)->assertNotFound();
        }
        DB::transaction(function (): void {
            DB::select("SELECT set_config('app.family_id', ?, true)", [DemoSeeder::IONESCU]);
            $this->assertSame(0, DB::table('pets')->count());
            $this->assertSame(0, DB::table('pet_entries')->count());
        });
        $this->assertSame(0, DB::table('pets')->count());
    }

    public function test_care_payload_validates_decimals_dates_kinds_and_versioned_archive(): void
    {
        $weight = $this->createEntry(['kind' => 'weight', 'weight_kg' => '12.345', 'cost' => '0']);
        $this->assertSame('12.345', $weight['weight_kg']);
        $this->assertSame('0.00', $weight['cost']);
        foreach ([['kind' => 'weight', 'weight_kg' => null], ['kind' => 'weight', 'weight_kg' => '0'],
            ['kind' => 'weight', 'weight_kg' => '0.0001'], ['kind' => 'weight', 'weight_kg' => '10000'],
            ['kind' => 'weight', 'weight_kg' => '1e3'], ['weight_kg' => '5'], ['cost' => '-1'], ['cost' => '1.234'], ['cost' => '1e3'], ['cost' => '10000000'],
            ['event_date' => '2026-02-30'], ['event_date' => '2026-10-25'], ['next_due_on' => '2026-09-30'], ['next_due_on' => '2101-01-01'],
            ['kind' => 'unknown'], ['next_due_completed_at' => now()->toIso8601String()]] as $change) {
            $this->postJson($this->path('/entries'), $this->content($change))->assertUnprocessable();
        }
        $url = $this->path('/entries/'.$weight['id']);
        $this->withHeaders(['If-Match' => ''])->patchJson($url, $this->content())->assertStatus(428);
        $weight = $this->updateEntry($weight, ['weight_kg' => '13.500', 'notes' => 'Măsurare nouă']);
        $this->assertSame(2, $weight['version']);
        $this->withHeader('If-Match', '"1"')->patchJson($url, $this->content())->assertStatus(412);
        $this->withHeader('If-Match', '"2"')->postJson($url.'/archive', ['archived' => true])->assertOk()->assertJsonPath('version', 3);
        $this->getJson($this->path('/entries'))->assertJsonCount(0, 'items');
        $this->getJson($this->path('/entries?archived=1'))->assertJsonCount(1, 'items')->assertJsonPath('items.0.notes', 'Măsurare nouă');
        $this->withHeader('If-Match', '"3"')->patchJson($url, $this->content())->assertConflict();
        $this->postJson($url.'/archive', ['archived' => false])->assertOk()->assertJsonPath('version', 4);
        $this->withHeader('If-Match', '"4"')->patchJson($url.'/completion', ['completed' => true])->assertConflict();
    }

    public function test_document_links_preserve_independent_access_and_hide_unauthorized_metadata(): void
    {
        $document = DB::connection('admin')->table('documents')->where('family_id', DemoSeeder::POPESCU)->where('owner_id', $this->ana->id)->where('category', 'personal')->firstOrFail();
        $url = $this->path('/documents');
        $this->postJson($url, ['document_id' => $document->id])->assertOk()->assertJsonCount(1)->assertJsonMissingPath('0.asset_id');
        $this->postJson($url, ['document_id' => $document->id])->assertOk()->assertJsonCount(1);
        $this->actingAs($this->mihai)->getJson($url)->assertExactJson([]);
        $this->postJson($url, ['document_id' => $document->id])->assertNotFound();
        $this->deleteJson($url.'/'.$document->id)->assertNotFound();
        DB::connection('admin')->table('documents')->where('id', $document->id)->update(['visibility' => 'family']);
        $this->getJson($url)->assertJsonPath('0.id', $document->id);
        DB::connection('admin')->table('documents')->where('id', $document->id)->update(['visibility' => 'private']);
        $this->getJson($url)->assertExactJson([]);
        $foreign = DB::connection('admin')->table('documents')->where('family_id', DemoSeeder::IONESCU)->value('id');
        $this->actingAs($this->ana)->postJson($url, ['document_id' => $foreign])->assertNotFound();
        $this->deleteJson($url.'/'.$document->id)->assertOk();
        $this->assertTrue(DB::connection('admin')->table('documents')->where('id', $document->id)->exists());
        $this->getJson($url)->assertExactJson([]);
    }

    public function test_photo_bytes_remain_family_authorized_and_uploads_use_versions_and_scanning(): void
    {
        $url = $this->path('/photo');
        $this->withHeader('If-Match', '"1"')->post($url, ['action' => 'replace', 'photo' => UploadedFile::fake()->image('milo.png', 400, 240)])->assertOk()->assertJsonPath('photo_status', 'ready')->assertJsonPath('version', 2);
        $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->actingAs($this->mihai)->get($url)->assertOk();
        $this->get('/api/v1/families/'.DemoSeeder::IONESCU.'/pets/'.$this->pet.'/photo')->assertNotFound();
        $this->actingAs($this->ana)->withHeader('If-Match', '"1"')->postJson($url, ['action' => 'remove'])->assertStatus(412);
        $this->withHeader('If-Match', '"2"')->post($url, ['action' => 'replace', 'photo' => UploadedFile::fake()->createWithContent('wrong.pdf', '%PDF-1.4 invalid')])->assertUnprocessable();
        $this->postJson($url, ['action' => 'remove'])->assertOk()->assertJsonPath('photo_status', null)->assertJsonPath('version', 3);
        $this->getJson($url)->assertNotFound();
        $this->scanner('unavailable');
        $this->withHeader('If-Match', '"3"')->post($url, ['action' => 'replace', 'photo' => UploadedFile::fake()->image('waiting.png', 40, 40)])->assertOk()->assertJsonPath('photo_status', 'quarantine')->assertJsonPath('version', 4);
        $this->getJson($url)->assertConflict();
        $this->scanner('clean');
        $this->withHeader('If-Match', '"4"')->postJson($url.'/retry')->assertOk()->assertJsonPath('photo_status', 'ready');
        $this->get($url)->assertOk();
    }

    public function test_list_queries_are_constant_with_more_profiles_and_care_entries_and_paginate(): void
    {
        $this->createEntry();
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->getJson($this->path('/entries'))->assertOk();
        $smallEntries = count(DB::getQueryLog());
        DB::flushQueryLog();
        $this->getJson($this->base)->assertOk();
        $smallPets = count(DB::getQueryLog());
        for ($index = 0; $index < 22; $index++) {
            DB::connection('admin')->table('pet_entries')->insert(['id' => (string) Str::uuid(), 'family_id' => DemoSeeder::POPESCU,
                'pet_id' => $this->pet, 'author_id' => $this->ana->id, 'kind' => 'care', 'title' => 'Îngrijire '.$index,
                'event_date' => '2026-10-01', 'created_at' => now(), 'updated_at' => now()]);
            DB::connection('admin')->table('pets')->insert(['id' => (string) Str::uuid(), 'family_id' => DemoSeeder::POPESCU,
                'created_by' => $this->ana->id, 'name' => 'Animal '.$index, 'species' => 'Pisică', 'created_at' => now(), 'updated_at' => now()]);
        }
        DB::flushQueryLog();
        $this->getJson($this->path('/entries'))->assertJsonCount(20, 'items')->assertJsonPath('has_more', true);
        $this->assertSame($smallEntries, count(DB::getQueryLog()));
        $this->getJson($this->path('/entries?page=2'))->assertJsonCount(3, 'items')->assertJsonPath('has_more', false);
        $this->getJson($this->path('/entries?kind=vaccination'))->assertJsonCount(1, 'items');
        DB::flushQueryLog();
        $this->getJson($this->base)->assertJsonCount(23);
        $this->assertSame($smallPets, count(DB::getQueryLog()));
    }

    public function test_next_deadline_is_separate_from_care_history_and_editing_date_resets_completion(): void
    {
        $entry = $this->createEntry(['next_due_on' => '2026-11-02']);
        $eventId = $entry['calendar_event_id'];
        $this->assertSame(['type' => 'pet_entry', 'id' => $entry['id'], 'parent_id' => $this->pet], $this->calendar()[0]['source']);
        $this->assertSame('Vaccinare · Milo', $this->calendar()[0]['title']);
        $entry = $this->completeEntry($entry, true);
        $this->assertNotNull($entry['next_due_completed_at']);
        $this->assertSame('2026-10-01', $entry['event_date']);
        $this->assertSame('120.50', $entry['cost']);
        $this->assertSame([], $this->calendar());
        $entry = $this->updateEntry($entry, ['title' => 'Titlu corectat']);
        $this->assertNotNull($entry['next_due_completed_at']);
        $this->assertSame([], $this->calendar());
        $entry = $this->updateEntry($entry, ['next_due_on' => '2026-11-03']);
        $this->assertNull($entry['next_due_completed_at']);
        $this->assertSame($eventId, $this->calendar()[0]['id']);
        $this->assertSame('2026-11-03', $this->calendar()[0]['event_date']);
        $entry = $this->completeEntry($entry, true);
        $entry = $this->completeEntry($entry, false);
        $this->assertNull($entry['next_due_completed_at']);
        $this->assertCount(1, $this->calendar());
        $entry = $this->updateEntry($entry, ['next_due_on' => null, 'reminder_offset_minutes' => null, 'reminder_recipients' => []]);
        $this->assertSame([], $this->calendar());
        $this->assertSame([], $this->calendar(archived: true));
        $this->getJson('/api/v1/families/'.DemoSeeder::POPESCU.'/calendar-items/'.$eventId)->assertNotFound();
    }

    public function test_inapp_reminders_are_delivered_once_and_date_edit_cancels_old_schedule(): void
    {
        $entry = $this->createEntry(['next_due_on' => '2026-11-02', 'reminder_offset_minutes' => 1440,
            'reminder_recipients' => [$this->anaMember, $this->mihaiMember]]);
        $eventId = $entry['calendar_event_id'];
        $this->assertSame(2, DB::connection('admin')->table('calendar_reminder_deliveries')->where('calendar_item_id', $eventId)->where('state', 'pending')->count());
        $this->travelTo(CarbonImmutable::parse('2026-11-01T07:00:00Z'));
        $reminders = app(CalendarReminders::class);
        $this->assertSame(2, $reminders->deliverFamily(DemoSeeder::POPESCU));
        $this->assertSame(0, $reminders->deliverFamily(DemoSeeder::POPESCU));
        $entry = $this->updateEntry($entry, ['next_due_on' => '2026-11-03']);
        $this->assertSame(2, DB::connection('admin')->table('calendar_reminder_deliveries')->where('calendar_item_id', $eventId)->where('state', 'cancelled')->count());
        $this->travelTo(CarbonImmutable::parse('2026-11-02T07:00:00Z'));
        $this->assertSame(2, $reminders->deliverFamily(DemoSeeder::POPESCU));
        $this->assertSame(0, $reminders->deliverFamily(DemoSeeder::POPESCU));
    }

    public function test_completed_deadline_stays_completed_through_archives_and_reminders_remain_cancelled(): void
    {
        $entry = $this->createEntry(['next_due_on' => '2026-11-02', 'reminder_offset_minutes' => 1440, 'reminder_recipients' => [$this->anaMember]]);
        $entry = $this->completeEntry($entry, true);
        $url = $this->path('/entries/'.$entry['id'].'/archive');
        $entry = $this->withHeader('If-Match', '"'.$entry['version'].'"')->postJson($url, ['archived' => true])->assertOk()->json();
        $entry = $this->withHeader('If-Match', '"'.$entry['version'].'"')->postJson($url, ['archived' => false])->assertOk()->json();
        $this->assertNotNull($entry['next_due_completed_at']);
        $pet = $this->withHeader('If-Match', '"1"')->postJson($this->path('/archive'), ['archived' => true])->assertOk()->json();
        $this->withHeader('If-Match', '"'.$pet['version'].'"')->postJson($this->path('/archive'), ['archived' => false])->assertOk();
        $this->assertSame([], $this->calendar());
        $this->assertSame(0, DB::connection('admin')->table('calendar_reminder_deliveries')->where('calendar_item_id', $entry['calendar_event_id'])->whereIn('state', ['pending', 'delivered'])->count());
        $this->travelTo(CarbonImmutable::parse('2026-11-01T07:00:00Z'));
        $this->assertSame(0, app(CalendarReminders::class)->deliverFamily(DemoSeeder::POPESCU));
    }

    public function test_invalid_reminder_recipients_and_revocation_cannot_deliver_or_leave_partial_records(): void
    {
        $foreign = DB::connection('admin')->table('family_memberships')->where('family_id', DemoSeeder::IONESCU)->value('id');
        $child = (string) Str::uuid();
        DB::connection('admin')->table('family_memberships')->insert(['id' => $child, 'family_id' => DemoSeeder::POPESCU, 'user_id' => null, 'child_name' => 'Copil', 'role' => 'limited']);
        foreach ([['reminder_recipients' => []], ['reminder_recipients' => [$foreign]], ['reminder_recipients' => [$child]],
            ['reminder_recipients' => [$this->anaMember, $this->anaMember]], ['reminder_offset_minutes' => 10080, 'reminder_recipients' => [$this->anaMember]]] as $change) {
            $this->postJson($this->path('/entries'), array_replace($this->content(['next_due_on' => '2026-11-02', 'reminder_offset_minutes' => 0]), $change))->assertUnprocessable();
        }
        $this->assertSame(0, DB::connection('admin')->table('pet_entries')->count());
        $this->assertSame([], $this->calendar());
        $entry = $this->createEntry(['next_due_on' => '2026-11-02', 'reminder_offset_minutes' => 1440, 'reminder_recipients' => [$this->mihaiMember]]);
        $this->withHeader('If-Match', '"'.$entry['version'].'"')->patchJson($this->path('/entries/'.$entry['id']), $this->content([
            'title' => 'Schimbare anulată', 'next_due_on' => '2026-11-03', 'reminder_offset_minutes' => 0, 'reminder_recipients' => [$foreign],
        ]))->assertUnprocessable();
        $this->getJson($this->path('/entries/'.$entry['id']))->assertOk()->assertJsonPath('version', 1)
            ->assertJsonPath('title', 'Vaccinare')->assertJsonPath('next_due_on', '2026-11-02');
        $this->assertSame('2026-11-02', $this->calendar()[0]['event_date']);
        DB::connection('admin')->table('family_memberships')->where('id', $this->mihaiMember)->update(['revoked_at' => now()]);
        $this->travelTo(CarbonImmutable::parse('2026-11-01T07:00:00Z'));
        $this->assertSame(0, app(CalendarReminders::class)->deliverFamily(DemoSeeder::POPESCU));
        $this->assertSame(0, DB::connection('admin')->table('calendar_reminder_deliveries')->where('calendar_item_id', $entry['calendar_event_id'])->where('state', 'delivered')->count());
    }

    public function test_parent_archive_cancels_pending_reminders_and_restore_reactivates_only_open_deadlines(): void
    {
        $open = $this->createEntry(['next_due_on' => '2026-11-02', 'reminder_offset_minutes' => 1440, 'reminder_recipients' => [$this->anaMember]]);
        $closed = $this->createEntry(['title' => 'Scadență finalizată', 'next_due_on' => '2026-11-02', 'reminder_offset_minutes' => 1440, 'reminder_recipients' => [$this->anaMember]]);
        $this->completeEntry($closed, true);
        $this->assertSame(1, DB::connection('admin')->table('calendar_reminder_deliveries')->where('state', 'pending')->count());
        $pet = $this->withHeader('If-Match', '"1"')->postJson($this->path('/archive'), ['archived' => true])->assertOk()->json();
        $this->assertSame([], $this->calendar());
        $this->assertSame(0, DB::connection('admin')->table('calendar_reminder_deliveries')->whereIn('state', ['pending', 'delivered'])->count());
        $this->travelTo(CarbonImmutable::parse('2026-11-01T07:00:00Z'));
        $this->assertSame(0, app(CalendarReminders::class)->deliverFamily(DemoSeeder::POPESCU));
        $this->withHeader('If-Match', '"'.$pet['version'].'"')->postJson($this->path('/archive'), ['archived' => false])->assertOk();
        $events = $this->calendar();
        $this->assertCount(1, $events);
        $this->assertSame($open['calendar_event_id'], $events[0]['id']);
        $this->assertSame($open['id'], $events[0]['source']['id']);
        $this->assertSame(1, app(CalendarReminders::class)->deliverFamily(DemoSeeder::POPESCU));
        $this->assertSame(0, app(CalendarReminders::class)->deliverFamily(DemoSeeder::POPESCU));
        $this->assertSame(2, DB::connection('admin')->table('pet_entries')->count());
    }

    public function test_calendar_projection_failure_rolls_back_care_creation_and_audit(): void
    {
        $calendar = Mockery::mock(SourceItems::class);
        $calendar->shouldReceive('settings')->andReturnUsing(fn (string $family, string $type) => DB::table('calendar_items')->where('family_id', $family)->where('source_type', $type)->select('source_id', 'id as calendar_event_id', 'reminder_offset_minutes')->selectRaw('NULL as recipients_json'));
        $calendar->shouldReceive('sync')->once()->andThrow(new RuntimeException('pet projection failed'));
        $this->app->instance(SourceItems::class, $calendar);
        $this->withoutExceptionHandling();
        try {
            $this->postJson($this->path('/entries'), $this->content(['next_due_on' => '2026-11-02']));
            $this->fail('Projection failure must propagate.');
        } catch (RuntimeException $error) {
            $this->assertSame('pet projection failed', $error->getMessage());
        }
        $this->assertSame(0, DB::connection('admin')->table('pet_entries')->count());
        $this->assertSame(0, DB::connection('admin')->table('audit_events')->where('action', 'pet.entry_created')->count());
    }
}
