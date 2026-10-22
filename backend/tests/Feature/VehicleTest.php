<?php

namespace Tests\Feature;

use App\Models\User;
use App\Platform\Media\Scanner;
use Database\Seeders\DemoSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class VehicleTest extends TestCase
{
    private User $ana;

    private User $mihai;

    private string $base;

    private string $vehicle;

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
        $this->ana = User::where('email', 'ana@example.test')->firstOrFail();
        $this->mihai = User::where('email', 'mihai@example.test')->firstOrFail();
        $this->base = '/api/v1/families/'.DemoSeeder::POPESCU.'/vehicles';
        $this->vehicle = $this->actingAs($this->ana)->postJson($this->base, $this->profile())->assertCreated()->json('id');
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
        DB::disconnect('pgsql');
        DB::disconnect('admin');
        parent::tearDown();
    }

    private function profile(): array
    {
        return ['name' => 'Mașina familiei', 'make' => 'Dacia', 'model' => 'Duster', 'registration' => 'B 123 ABC', 'year' => 2022];
    }

    private function entry(): array
    {
        return ['kind' => 'service', 'title' => 'Schimb ulei și filtre', 'state' => 'completed', 'event_date' => '2026-10-01', 'cost' => '620.50', 'odometer_km' => 42000, 'notes' => null];
    }

    private function path(): string
    {
        return $this->base.'/'.$this->vehicle;
    }

    public function test_family_boundary_roles_and_revocation_apply_to_every_vehicle_surface(): void
    {
        $entry = $this->postJson($this->path().'/entries', $this->entry())->assertCreated()->json('id');
        $foreign = '/api/v1/families/'.DemoSeeder::IONESCU.'/vehicles/'.$this->vehicle;
        $this->getJson($foreign)->assertNotFound();
        $this->getJson($foreign.'/entries')->assertNotFound();
        $this->getJson($foreign.'/entries/'.$entry)->assertNotFound();
        $this->getJson($foreign.'/documents')->assertNotFound();
        DB::connection('admin')->table('family_memberships')->where('family_id', DemoSeeder::POPESCU)->where('user_id', $this->mihai->id)->update(['role' => 'limited']);
        $this->actingAs($this->mihai)->getJson($this->base)->assertOk()->assertJsonPath('0.can_manage', false);
        $this->getJson($this->path().'/entries')->assertOk()->assertJsonCount(1, 'items');
        $this->postJson($this->base, $this->profile())->assertForbidden();
        $this->withHeader('If-Match', '"1"')->patchJson($this->path(), $this->profile())->assertForbidden();
        $this->postJson($this->path().'/entries', $this->entry())->assertForbidden();
        $this->patchJson($this->path().'/entries/'.$entry, $this->entry())->assertForbidden();
        $this->postJson($this->path().'/photo', ['action' => 'remove'])->assertForbidden();
        $this->postJson($this->path().'/documents', ['document_id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa'])->assertForbidden();
        DB::connection('admin')->table('family_memberships')->where('family_id', DemoSeeder::POPESCU)->where('user_id', $this->mihai->id)->update(['revoked_at' => now()]);
        $this->getJson($this->base)->assertNotFound();
        $this->getJson($this->path().'/entries/'.$entry)->assertNotFound();
        $this->getJson($this->path().'/documents')->assertNotFound();
        $this->getJson($this->path().'/photo')->assertNotFound();
    }

    public function test_profile_versions_archive_restore_and_list_payload_are_consistent(): void
    {
        $this->getJson($this->base)->assertJsonPath('0.registration', 'B 123 ABC')->assertJsonPath('0.version', 1);
        $this->patchJson($this->path(), $this->profile())->assertStatus(428);
        $this->withHeader('If-Match', '"1"')->patchJson($this->path(), array_replace($this->profile(), ['name' => 'Mașina nouă']))->assertOk()->assertJsonPath('version', 2);
        $this->patchJson($this->path(), $this->profile())->assertStatus(412);
        $this->withHeader('If-Match', '"2"')->postJson($this->path().'/archive', ['archived' => true])->assertOk()->assertJsonPath('version', 3);
        $this->getJson($this->base)->assertExactJson([]);
        $this->getJson($this->base.'?archived=1')->assertJsonCount(1);
        $this->postJson($this->path().'/entries', $this->entry())->assertConflict();
        $this->withHeader('If-Match', '"3"')->postJson($this->path().'/archive', ['archived' => false])->assertOk()->assertJsonPath('version', 4);
        $this->getJson($this->base)->assertJsonPath('0.name', 'Mașina nouă');
        $audit = DB::connection('admin')->table('audit_events')->where('action', 'like', 'vehicle.%')->get();
        $this->assertStringNotContainsString('B 123 ABC', $audit->toJson());
    }

    public function test_records_validate_dates_and_amounts_and_protect_edits_and_archives(): void
    {
        $url = $this->path().'/entries';
        foreach (['-1', '1.234', '1e4', '10000000000'] as $invalid) {
            $this->postJson($url, array_replace($this->entry(), ['cost' => $invalid]))->assertUnprocessable();
        }
        $this->postJson($url, array_replace($this->entry(), ['event_date' => '2100-01-01']))->assertUnprocessable();
        $this->postJson($url, array_replace($this->entry(), ['event_date' => '2026-02-30']))->assertUnprocessable();
        $this->postJson($url, array_replace($this->entry(), ['odometer_km' => -1]))->assertUnprocessable();
        $this->postJson($url, array_replace($this->entry(), ['state' => 'planned']))->assertUnprocessable();
        $this->postJson($url, array_replace($this->entry(), ['state' => 'planned', 'kind' => 'fuel', 'cost' => null, 'odometer_km' => null]))->assertUnprocessable();
        $id = $this->postJson($url, $this->entry())->assertCreated()->assertJsonPath('cost', '620.50')->json('id');
        $this->withHeader('If-Match', '"1"')->patchJson($url.'/'.$id, array_replace($this->entry(), ['cost' => '850']))->assertOk()->assertJsonPath('version', 2);
        $this->patchJson($url.'/'.$id, $this->entry())->assertStatus(412);
        $this->withHeader('If-Match', '"2"')->postJson($url.'/'.$id.'/archive', ['archived' => true])->assertOk()->assertJsonPath('version', 3);
        $this->getJson($url)->assertJsonCount(0, 'items');
        $this->getJson($url.'?archived=1')->assertJsonCount(1, 'items');
        $this->withHeader('If-Match', '"3"')->postJson($url.'/'.$id.'/archive', ['archived' => false])->assertOk();
        $this->getJson($url.'?group=costs')->assertJsonPath('items.0.cost', '850.00');
        $this->postJson($url, array_replace($this->entry(), ['cost' => null, 'odometer_km' => null]))->assertCreated();
        $this->getJson($url.'?group=costs')->assertJsonCount(2, 'items');
    }

    public function test_documents_keep_their_own_access_after_linking_and_sharing_changes(): void
    {
        $document = DB::connection('admin')->table('documents')->where('family_id', DemoSeeder::POPESCU)->where('owner_id', $this->ana->id)->where('category', 'personal')->firstOrFail();
        $url = $this->path().'/documents';
        $this->postJson($url, ['document_id' => $document->id])->assertOk()->assertJsonCount(1);
        $this->postJson($url, ['document_id' => $document->id])->assertOk()->assertJsonCount(1);
        $this->actingAs($this->mihai)->getJson($url)->assertExactJson([]);
        $this->postJson($url, ['document_id' => $document->id])->assertNotFound();
        DB::connection('admin')->table('documents')->where('id', $document->id)->update(['visibility' => 'family']);
        $this->getJson($url)->assertJsonPath('0.id', $document->id)->assertJsonMissingPath('0.asset_id');
        DB::connection('admin')->table('documents')->where('id', $document->id)->update(['visibility' => 'private']);
        $this->getJson($url)->assertExactJson([]);
        $foreign = DB::connection('admin')->table('documents')->where('family_id', DemoSeeder::IONESCU)->value('id');
        $this->actingAs($this->ana)->postJson($url, ['document_id' => $foreign])->assertNotFound();
        $this->deleteJson($url.'/'.$document->id)->assertOk();
        $this->assertTrue(DB::connection('admin')->table('documents')->where('id', $document->id)->exists());
        $this->getJson($url)->assertExactJson([]);
    }

    public function test_photo_bytes_follow_family_access_and_versions(): void
    {
        $this->withHeader('If-Match', '"1"')->post($this->path().'/photo', ['action' => 'replace', 'photo' => UploadedFile::fake()->image('car.png', 400, 240)])
            ->assertOk()->assertJsonPath('photo_status', 'ready')->assertJsonPath('version', 2);
        $this->get($this->path().'/photo')->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->actingAs($this->mihai)->get($this->path().'/photo')->assertOk();
        $this->actingAs($this->ana)->withHeader('If-Match', '"1"')->postJson($this->path().'/photo', ['action' => 'remove'])->assertStatus(412);
        $this->withHeader('If-Match', '"2"')->postJson($this->path().'/photo', ['action' => 'remove'])->assertOk()->assertJsonPath('photo_status', null);
        $this->getJson($this->path().'/photo')->assertNotFound();
    }

    public function test_planned_order_pagination_and_maintenance_filters(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $url = $this->path().'/entries';
        $planned = array_replace($this->entry(), ['state' => 'planned', 'cost' => null, 'odometer_km' => null]);
        for ($day = 23; $day >= 1; $day--) {
            $kind = $day === 1 ? 'itp' : ($day === 2 ? 'rca' : 'service');
            $title = $day === 1 ? 'ITP' : ($day === 2 ? 'Asigurare RCA' : 'Revizie service');
            $this->postJson($url, array_replace($planned, ['kind' => $kind, 'title' => $title, 'event_date' => '2027-01-'.str_pad((string) $day, 2, '0', STR_PAD_LEFT)]))->assertCreated();
        }
        $this->getJson($url.'?state=planned')->assertJsonCount(20, 'items')->assertJsonPath('has_more', true)->assertJsonPath('items.0.event_date', '2027-01-01');
        $this->getJson($url.'?state=planned&page=2')->assertJsonCount(3, 'items')->assertJsonPath('has_more', false);
        $this->postJson($url, array_replace($this->entry(), ['kind' => 'fuel', 'title' => 'Alimentare', 'cost' => '320']))->assertCreated();
        $this->getJson($url.'?group=maintenance&state=completed')->assertJsonCount(0, 'items');
        $this->getJson($url.'?group=costs')->assertJsonCount(1, 'items');
        $this->postJson($url, $this->entry())->assertCreated();
        $this->postJson($url, array_replace($this->entry(), ['kind' => 'itp', 'title' => 'ITP', 'cost' => '150', 'event_date' => '2026-06-10']))->assertCreated();
        $this->getJson($url.'?group=maintenance&state=completed')->assertJsonCount(2, 'items');
        $this->getJson($url.'?group=costs')->assertJsonCount(3, 'items');
    }
}
