<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Documents\Contracts\DocumentLinks;
use App\Modules\Documents\Contracts\DocumentSummaries;
use App\Platform\Media\Scanner;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

final class DocumentMetadataTest extends TestCase
{
    private string $family;

    private string $ownerMember;

    private string $readerMember;

    private string $base;

    private User $owner;

    private User $reader;

    private object $scanner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('memoria_test', config('database.connections.pgsql.database'));
        $admin = config('database.connections.pgsql');
        $admin['username'] = 'memoria_admin';
        $admin['password'] = env('DB_ADMIN_PASSWORD');
        config(['database.connections.admin' => $admin]);
        Storage::fake('local');
        foreach (['owner', 'reader'] as $name) {
            $this->{$name} = User::factory()->make();
            $this->{$name}->setConnection('admin')->save();
        }
        $this->family = (string) Str::uuid();
        $this->ownerMember = (string) Str::uuid();
        $this->readerMember = (string) Str::uuid();
        DB::connection('admin')->table('families')->insert(['id' => $this->family, 'name' => 'Documente fără fișier test']);
        DB::connection('admin')->table('family_memberships')->insert([
            ['id' => $this->ownerMember, 'family_id' => $this->family, 'user_id' => $this->owner->id, 'role' => 'owner'],
            ['id' => $this->readerMember, 'family_id' => $this->family, 'user_id' => $this->reader->id, 'role' => 'adult'],
        ]);
        $this->base = '/api/v1/families/'.$this->family.'/documents';
        $this->actingAs($this->owner)->withHeader('Accept', 'application/json');
        $this->scanner = new class implements Scanner
        {
            public string $result = 'clean';

            public function scan(string $path): string
            {
                return $this->result;
            }
        };
        $this->app->instance(Scanner::class, $this->scanner);
    }

    protected function tearDown(): void
    {
        try {
            if (isset($this->family)) {
                foreach (['calendar_items', 'audit_events', 'documents', 'media_assets', 'member_profiles', 'family_memberships'] as $table) {
                    DB::connection('admin')->table($table)->where('family_id', $this->family)->delete();
                }
                DB::connection('admin')->table('families')->where('id', $this->family)->delete();
            }
            foreach ([$this->owner ?? null, $this->reader ?? null] as $user) {
                if ($user) {
                    DB::connection('admin')->table('users')->where('id', $user->id)->delete();
                }
            }
        } finally {
            DB::disconnect('pgsql');
            DB::disconnect('admin');
            parent::tearDown();
        }
    }

    private function create(array $changes = []): array
    {
        return $this->postJson($this->base, array_replace(['title' => 'Pașaport', 'category' => 'personal'], $changes))
            ->assertCreated()->json();
    }

    private function image(): UploadedFile
    {
        return UploadedFile::fake()->image('document.png', 80, 60);
    }

    public function test_a_name_only_document_is_saved_listed_and_read_without_an_asset(): void
    {
        $doc = $this->create(['expires_on' => '2028-03-10']);
        $this->assertSame('Pașaport', $doc['title']);
        $this->assertSame('private', $doc['visibility']);
        $this->assertSame($this->ownerMember, $doc['subject_membership_id']);
        foreach (['mime', 'bytes', 'status', 'original_name'] as $key) {
            $this->assertNull($doc[$key]);
        }
        $this->assertFalse(array_key_exists('asset_id', $doc));
        $this->assertSame(0, DB::connection('admin')->table('media_assets')->where('family_id', $this->family)->count());
        $this->getJson($this->base)->assertOk()->assertJsonCount(1)->assertJsonPath('0.id', $doc['id'])->assertJsonPath('0.status', null);
        $this->getJson($this->base.'/'.$doc['id'])->assertOk()->assertHeader('ETag', '"1"')->assertJsonPath('expires_on', '2028-03-10');
        $this->getJson($this->base.'/'.$doc['id'].'/content')->assertNotFound();
        $this->postJson($this->base.'/'.$doc['id'].'/scan')->assertNotFound();
    }

    public function test_metadata_keeps_category_access_grants_and_public_contracts_without_a_file(): void
    {
        $personal = $this->create();
        $home = $this->create(['category' => 'home', 'title' => 'Garanție']);
        $this->assertSame('adults', $home['visibility']);
        $this->actingAs($this->reader)->getJson($this->base.'/'.$personal['id'])->assertNotFound();
        $this->getJson($this->base.'/'.$home['id'])->assertOk()->assertJsonPath('status', null);
        $this->actingAs($this->owner)->withHeader('If-Match', '"1"')->putJson($this->base.'/'.$personal['id'].'/sharing', [
            'visibility' => 'selected', 'membership_ids' => [$this->readerMember],
        ])->assertOk()->assertJsonPath('version', 2);
        $this->actingAs($this->reader)->getJson($this->base.'/'.$personal['id'])->assertOk();
        DB::transaction(function () use ($personal): void {
            DB::select("SELECT set_config('app.family_id', ?, true)", [$this->family]);
            $references = app(DocumentLinks::class)->references($this->family, $this->reader->id, $this->readerMember)->get()->keyBy('id');
            $this->assertNull($references[$personal['id']]->mime);
            $this->assertNull($references[$personal['id']]->status);
            $this->assertCount(2, app(DocumentSummaries::class)->recent($this->family, $this->reader->id, $this->readerMember));
        });
        $this->actingAs($this->owner)->withHeader('If-Match', '"2"')->putJson($this->base.'/'.$personal['id'].'/sharing', [
            'visibility' => 'private', 'membership_ids' => [],
        ])->assertOk();
        $this->actingAs($this->reader)->getJson($this->base.'/'.$personal['id'])->assertNotFound();
    }

    public function test_the_owner_can_attach_a_file_later_without_changing_metadata_or_access(): void
    {
        $doc = $this->create(['expires_on' => '2029-05-06']);
        $url = $this->base.'/'.$doc['id'];
        $result = $this->withHeader('If-Match', '"1"')->post($url.'/file', ['file' => $this->image()])
            ->assertOk()->assertHeader('ETag', '"2"')->assertJsonPath('status', 'ready')->assertJsonPath('mime', 'image/png')->json();
        foreach (['id', 'title', 'category', 'owner_id', 'subject_membership_id', 'visibility', 'expires_on'] as $key) {
            $this->assertSame($doc[$key], $result[$key]);
        }
        $this->get($url.'/content')->assertOk();
        $this->assertSame(1, DB::connection('admin')->table('documents')->where('family_id', $this->family)->count());
        $this->assertSame(1, DB::connection('admin')->table('media_assets')->where('family_id', $this->family)->count());
        $this->withHeader('If-Match', '"2"')->post($url.'/file', ['file' => $this->image()])->assertStatus(409);
        $this->withHeader('If-Match', '"2"')->patchJson($url, ['expires_on' => '2030-05-06'])->assertOk()->assertJsonPath('version', 3);
    }

    public function test_attachment_requires_the_current_version_valid_file_and_owner_permission(): void
    {
        $doc = $this->create(['category' => 'memories']);
        $url = $this->base.'/'.$doc['id'].'/file';
        $this->post($url, ['file' => $this->image()])->assertStatus(428);
        $this->withHeader('If-Match', '"0"')->post($url, ['file' => $this->image()])->assertStatus(412);
        $this->withHeader('If-Match', '"1"')->post($url, [])->assertUnprocessable();
        $this->post($url, ['file' => UploadedFile::fake()->createWithContent('bad.png', 'plain text')])->assertUnprocessable();
        $this->post($url, ['file' => $this->image(), 'visibility' => 'private'])->assertUnprocessable();
        $this->actingAs($this->reader)->post($url, ['file' => $this->image()])->assertForbidden();
        DB::connection('admin')->table('family_memberships')->where('id', $this->ownerMember)->update(['role' => 'limited']);
        $this->actingAs($this->owner)->post($url, ['file' => $this->image()])->assertForbidden();
        $this->postJson($this->base, ['title' => 'Fără acces', 'category' => 'memories'])->assertForbidden();
        $this->assertSame(0, DB::connection('admin')->table('media_assets')->where('family_id', $this->family)->count());
    }

    public function test_existing_file_upload_and_later_attachment_keep_scanning_states(): void
    {
        $this->scanner->result = 'unavailable';
        $created = $this->post($this->base, ['title' => 'Cu fișier', 'category' => 'personal', 'file' => $this->image()])
            ->assertCreated()->assertJsonPath('status', 'quarantine')->json();
        $this->getJson($this->base.'/'.$created['id'].'/content')->assertStatus(409);
        $doc = $this->create();
        $this->scanner->result = 'infected';
        $this->withHeader('If-Match', '"1"')->post($this->base.'/'.$doc['id'].'/file', ['file' => $this->image()])
            ->assertOk()->assertJsonPath('status', 'rejected');
        $this->getJson($this->base.'/'.$doc['id'].'/content')->assertStatus(409);
        $this->getJson($this->base.'/'.$created['id'])->assertOk()->assertJsonPath('status', 'quarantine');
    }

    public function test_metadata_validation_and_isolation_still_apply(): void
    {
        $this->postJson($this->base, ['title' => '', 'category' => 'personal'])->assertUnprocessable();
        $this->postJson($this->base, ['title' => 'Test', 'category' => 'anything'])->assertUnprocessable();
        $this->postJson($this->base, ['title' => 'Test', 'category' => 'personal', 'expires_on' => '2028-02-31'])->assertUnprocessable();
        $this->postJson($this->base, ['title' => 'Test', 'category' => 'home', 'subject_membership_id' => $this->ownerMember])->assertUnprocessable();
        $doc = $this->create();
        $otherFamily = (string) Str::uuid();
        $this->getJson('/api/v1/families/'.$otherFamily.'/documents/'.$doc['id'])->assertNotFound();
        $this->post('/api/v1/families/'.$otherFamily.'/documents/'.$doc['id'].'/file', ['file' => $this->image()])->assertNotFound();
        $this->assertSame(0, DB::connection('admin')->table('media_assets')->where('family_id', $this->family)->count());
    }
}
