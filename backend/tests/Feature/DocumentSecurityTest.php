<?php

namespace Tests\Feature;

use App\Models\User;
use App\Platform\Contracts\PrivateMedia;
use App\Platform\Families\MembershipRevoked;
use App\Platform\Media\ClamAvScanner;
use App\Platform\Media\Scanner;
use Database\Seeders\DemoSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

final class DocumentSecurityTest extends TestCase
{
    private User $ana;

    private User $mihai;

    private User $elena;

    private string $family = DemoSeeder::POPESCU;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('memoria_test', config('database.connections.pgsql.database'), 'Tests must use the dedicated database.');
        $admin = config('database.connections.pgsql');
        $admin['username'] = 'memoria_admin';
        $admin['password'] = env('DB_ADMIN_PASSWORD');
        config(['database.connections.admin' => $admin, 'memoria.demo_enabled' => true]);
        DB::connection('admin')->statement('TRUNCATE audit_events, document_grants, documents, media_assets, family_memberships, families, users RESTART IDENTITY CASCADE');
        Storage::fake('local');
        DB::setDefaultConnection('admin');
        (new DemoSeeder)->run();
        DB::setDefaultConnection('pgsql');
        $this->ana = User::where('email', 'ana@example.test')->firstOrFail();
        $this->mihai = User::where('email', 'mihai@example.test')->firstOrFail();
        $this->elena = User::where('email', 'elena@example.test')->firstOrFail();
        $this->scanner('clean');
    }

    private function scanner(string $result): void
    {
        $this->app->instance(Scanner::class, new class($result) implements Scanner
        {
            public function __construct(private string $result) {}

            public function scan(string $path): string
            {
                return $this->result;
            }
        });
    }

    protected function tearDown(): void
    {
        DB::disconnect('pgsql');
        DB::disconnect('admin');
        parent::tearDown();
    }

    public function test_home_documents_follow_current_adult_membership_including_limited_adults(): void
    {
        $this->actingAs($this->ana);
        $id = $this->postJson($this->path(), ['title' => 'Act casă', 'category' => 'home', 'file' => UploadedFile::fake()->createWithContent('casa.pdf', DemoSeeder::samplePdf())])
            ->assertCreated()->assertJsonPath('visibility', 'adults')->assertJsonPath('subject_membership_id', null)->json('id');
        $member = (string) Str::uuid();
        DB::connection('admin')->table('family_memberships')->insert(['id' => $member, 'family_id' => $this->family, 'user_id' => $this->elena->id, 'role' => 'limited']);
        DB::connection('admin')->table('member_profiles')->insert(['membership_id' => $member, 'family_id' => $this->family, 'birth_date' => '1980-01-01']);
        $this->actingAs($this->elena)->getJson($this->path('/'.$id))->assertOk();
        $this->getJson($this->path('/'.$id.'/content'))->assertOk();
        $this->getJson($this->path())->assertJsonFragment(['id' => $id]);
        DB::connection('admin')->table('member_profiles')->where('membership_id', $member)->update(['birth_date' => now('Europe/Bucharest')->subYearsNoOverflow(18)->addDay()->toDateString()]);
        $this->getJson($this->path('/'.$id))->assertNotFound();
        $this->getJson($this->path('/'.$id.'/content'))->assertNotFound();
        $this->getJson($this->path())->assertJsonMissing(['id' => $id]);
        $this->getJson('/api/v1/families/'.$this->family.'/home?month='.now('Europe/Bucharest')->format('Y-m'))->assertOk()->assertJsonMissing(['id' => $id]);
        DB::connection('admin')->table('member_profiles')->where('membership_id', $member)->update(['birth_date' => now('Europe/Bucharest')->subYearsNoOverflow(18)->toDateString()]);
        $this->getJson($this->path('/'.$id))->assertOk();
        DB::connection('admin')->table('family_memberships')->where('id', $member)->update(['revoked_at' => now()]);
        $this->getJson($this->path('/'.$id))->assertNotFound();
    }

    public function test_family_categories_set_access_and_cannot_be_made_private(): void
    {
        $this->actingAs($this->ana);
        foreach (['home' => 'adults', 'memories' => 'family'] as $category => $access) {
            $id = $this->postJson($this->path(), ['title' => 'Document comun '.$category, 'category' => $category, 'file' => UploadedFile::fake()->createWithContent('test.pdf', DemoSeeder::samplePdf())])
                ->assertCreated()->assertJsonPath('visibility', $access)->assertJsonPath('subject_membership_id', null)->json('id');
            $this->withHeader('If-Match', '"1"')->putJson($this->path('/'.$id.'/sharing'), ['visibility' => 'private', 'membership_ids' => []])->assertUnprocessable();
            $this->withHeader('If-Match', '"1"')->putJson($this->path('/'.$id.'/sharing'), ['visibility' => $access, 'membership_ids' => []])->assertOk();
        }
    }

    public function test_personal_documents_can_belong_to_an_accountless_child_without_transferring_ownership(): void
    {
        $this->actingAs($this->ana);
        $own = DB::table('family_memberships')->where('family_id', $this->family)->where('user_id', $this->ana->id)->value('id');
        $child = $this->postJson('/api/v1/families/'.$this->family.'/members/children', ['name' => 'Sofia', 'birth_date' => now('Europe/Bucharest')->subYears(8)->toDateString(), 'parent_ids' => [$own]])->assertCreated()->json('id');
        $payload = ['title' => 'Document copil', 'category' => 'health', 'subject_membership_id' => $child, 'file' => UploadedFile::fake()->createWithContent('test.pdf', DemoSeeder::samplePdf())];
        $id = $this->postJson($this->path(), $payload)->assertCreated()->assertJsonPath('visibility', 'private')->assertJsonPath('subject_membership_id', $child)->assertJsonPath('owner_id', $this->ana->id)->json('id');
        $this->actingAs($this->mihai)->postJson($this->path(), $payload)->assertForbidden();
        $this->getJson($this->path('/'.$id))->assertNotFound();
        $foreign = DB::connection('admin')->table('family_memberships')->where('family_id', DemoSeeder::IONESCU)->value('id');
        $this->actingAs($this->ana)->postJson($this->path(), array_replace($payload, ['subject_membership_id' => $foreign]))->assertNotFound();
        $this->withHeader('If-Match', '"1"')->putJson($this->path('/'.$id.'/sharing'), ['visibility' => 'family', 'membership_ids' => []])->assertOk();
        $this->actingAs($this->mihai)->getJson($this->path('/'.$id))->assertOk()->assertJsonPath('subject_membership_id', $child);
    }

    private function path(string $suffix = '', ?string $family = null): string
    {
        return '/api/v1/families/'.($family ?? $this->family).'/documents'.$suffix;
    }

    private function doc(string $title): object
    {
        return DB::connection('admin')->table('documents')->where('title', $title)->firstOrFail();
    }

    private function upload(): TestResponse
    {
        return $this->postJson($this->path(), [
            'title' => 'Fișier de test', 'category' => 'personal',
            'file' => UploadedFile::fake()->createWithContent('test.pdf', DemoSeeder::samplePdf()),
        ]);
    }

    public function test_requests_require_authentication_and_families_are_actor_scoped(): void
    {
        $this->getJson($this->path())->assertUnauthorized();
        $this->actingAs($this->mihai)->getJson('/api/v1/session')->assertJsonCount(1, 'families');
        $this->getJson($this->path('', DemoSeeder::IONESCU))->assertNotFound();
    }

    public function test_family_owner_cannot_read_other_adults_private_metadata_or_bytes(): void
    {
        $doc = $this->doc('Documentul privat al lui Mihai');
        $this->actingAs($this->ana)->getJson($this->path())->assertOk()->assertJsonMissing(['id' => $doc->id])->assertJsonCount(4);
        $this->getJson($this->path('/'.$doc->id))->assertNotFound();
        $this->getJson($this->path('/'.$doc->id.'/content'))->assertNotFound();
        $this->putJson($this->path('/'.$doc->id.'/sharing'), ['visibility' => 'family', 'membership_ids' => []])->assertNotFound();
    }

    public function test_foreign_document_identifiers_and_wrong_parent_are_hidden(): void
    {
        $doc = $this->doc('Amintiri din casa bunicilor');
        $this->actingAs($this->ana)->getJson($this->path('/'.$doc->id))->assertNotFound();
        $this->getJson($this->path('/'.$doc->id.'/content'))->assertNotFound();
        $this->getJson($this->path('/not-a-uuid'))->assertNotFound();
        $this->actingAs($this->mihai)->getJson($this->path('/'.$doc->id, DemoSeeder::IONESCU))->assertNotFound();
    }

    public function test_explicit_grant_and_revocation_apply_to_metadata_and_each_download(): void
    {
        $this->actingAs($this->ana);
        $doc = (object) $this->upload()->assertCreated()->json();
        $member = DB::table('family_memberships')->where('family_id', $this->family)->where('user_id', $this->mihai->id)->value('id');
        $this->actingAs($this->ana)->withHeader('If-Match', '"1"')->putJson($this->path('/'.$doc->id.'/sharing'), [
            'visibility' => 'selected', 'membership_ids' => [$member],
        ])->assertOk()->assertJsonPath('visibility', 'selected')->assertJsonPath('version', 2);
        $this->actingAs($this->mihai)->getJson($this->path('/'.$doc->id))->assertOk();
        $this->getJson($this->path('/'.$doc->id.'/content'))->assertOk()->assertHeader('Content-Type', 'application/octet-stream');
        $this->actingAs($this->ana)->withHeader('If-Match', '"2"')->putJson($this->path('/'.$doc->id.'/sharing'), [
            'visibility' => 'private', 'membership_ids' => [],
        ])->assertOk();
        $this->actingAs($this->mihai)->getJson($this->path('/'.$doc->id))->assertNotFound();
        $this->getJson($this->path('/'.$doc->id.'/content'))->assertNotFound();
        $this->getJson($this->path())->assertJsonMissing(['id' => $doc->id]);
    }

    public function test_stale_versions_and_foreign_grants_cannot_change_access(): void
    {
        $this->actingAs($this->ana);
        $doc = (object) $this->upload()->assertCreated()->json();
        $member = DB::table('family_memberships')->where('family_id', DemoSeeder::IONESCU)->where('user_id', $this->elena->id)->value('id');
        $this->actingAs($this->ana)->putJson($this->path('/'.$doc->id.'/sharing'), [
            'visibility' => 'family', 'membership_ids' => [],
        ])->assertStatus(428);
        $this->withHeader('If-Match', '"9"')->putJson($this->path('/'.$doc->id.'/sharing'), [
            'visibility' => 'family', 'membership_ids' => [],
        ])->assertStatus(412);
        $this->withHeader('If-Match', '"1"')->putJson($this->path('/'.$doc->id.'/sharing'), [
            'visibility' => 'selected', 'membership_ids' => [$member],
        ])->assertUnprocessable();
        $this->assertSame('private', $this->doc('Fișier de test')->visibility);
    }

    public function test_revoked_membership_blocks_existing_session_and_direct_file_links(): void
    {
        $doc = $this->doc('Povestea casei noastre');
        $member = DB::table('family_memberships')->where('family_id', $this->family)->where('user_id', $this->mihai->id)->value('id');
        $this->actingAs($this->mihai)->getJson($this->path('/'.$doc->id.'/content'))->assertOk();
        $this->actingAs($this->ana)->deleteJson('/api/v1/families/'.$this->family.'/members/'.$member)->assertNoContent();
        $this->actingAs($this->mihai)->getJson($this->path())->assertNotFound();
        $this->getJson($this->path('/'.$doc->id.'/content'))->assertNotFound();
        $this->getJson('/api/v1/session')->assertJsonCount(0, 'families');
    }

    public function test_document_grants_are_removed_by_revocation_and_do_not_return_on_readmission(): void
    {
        $id = $this->actingAs($this->ana)->upload()->assertCreated()->json('id');
        $member = DB::table('family_memberships')->where('family_id', $this->family)->where('user_id', $this->mihai->id)->value('id');
        $this->withHeader('If-Match', '"1"')->putJson($this->path('/'.$id.'/sharing'), [
            'visibility' => 'selected', 'membership_ids' => [$member],
        ])->assertOk();
        $this->actingAs($this->mihai)->getJson($this->path('/'.$id))->assertOk();
        $this->actingAs($this->ana)->deleteJson('/api/v1/families/'.$this->family.'/members/'.$member)->assertNoContent();
        $this->assertSame(0, DB::connection('admin')->table('document_grants')->where('membership_id', $member)->count());
        DB::connection('admin')->table('family_memberships')->where('id', $member)->update(['revoked_at' => null]);
        $this->actingAs($this->mihai)->getJson($this->path())->assertOk()->assertJsonMissing(['id' => $id]);
        $this->getJson($this->path('/'.$id))->assertNotFound();
        $this->getJson($this->path('/'.$id.'/content'))->assertNotFound();
        $this->actingAs($this->ana)->getJson($this->path('/'.$id))->assertOk();
    }

    public function test_revocation_rolls_back_membership_and_document_grants_when_an_event_consumer_fails(): void
    {
        $id = $this->actingAs($this->ana)->upload()->assertCreated()->json('id');
        $member = DB::table('family_memberships')->where('family_id', $this->family)->where('user_id', $this->mihai->id)->value('id');
        $this->withHeader('If-Match', '"1"')->putJson($this->path('/'.$id.'/sharing'), [
            'visibility' => 'selected', 'membership_ids' => [$member],
        ])->assertOk();
        $revision = DB::connection('admin')->table('families')->where('id', $this->family)->value('authz_revision');
        Event::listen(MembershipRevoked::class, function (): void {
            throw new \RuntimeException('Simulated consumer failure');
        });
        $this->withoutExceptionHandling();
        try {
            $this->deleteJson('/api/v1/families/'.$this->family.'/members/'.$member);
            $this->fail('Revocation must propagate the consumer failure.');
        } catch (\RuntimeException $error) {
            $this->assertSame('Simulated consumer failure', $error->getMessage());
        }
        $this->assertNull(DB::connection('admin')->table('family_memberships')->where('id', $member)->value('revoked_at'));
        $this->assertSame($revision, DB::connection('admin')->table('families')->where('id', $this->family)->value('authz_revision'));
        $this->assertSame(1, DB::connection('admin')->table('document_grants')->where('document_id', $id)->where('membership_id', $member)->count());
        $this->actingAs($this->mihai)->getJson($this->path('/'.$id))->assertOk();
    }

    public function test_adult_cannot_revoke_members_or_change_someone_elses_sharing(): void
    {
        $doc = $this->doc('Povestea casei noastre');
        $owner = DB::table('family_memberships')->where('family_id', $this->family)->where('user_id', $this->ana->id)->value('id');
        $this->actingAs($this->mihai)->deleteJson('/api/v1/families/'.$this->family.'/members/'.$owner)->assertForbidden();
        $this->withHeader('If-Match', '"1"')->putJson($this->path('/'.$doc->id.'/sharing'), ['visibility' => 'family', 'membership_ids' => []])->assertForbidden();
    }

    public function test_clean_upload_is_private_and_checks_real_mime(): void
    {
        $this->actingAs($this->ana);
        $uploaded = $this->upload()->assertCreated()->assertJsonPath('status', 'ready')->assertJsonPath('visibility', 'private');
        $id = $uploaded->json('id');
        $this->getJson($this->path('/'.$id.'/content'))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->actingAs($this->mihai)->getJson($this->path('/'.$id.'/content'))->assertNotFound();
        $this->actingAs($this->ana)->postJson($this->path(), [
            'title' => 'Spoof', 'category' => 'personal',
            'file' => UploadedFile::fake()->createWithContent('renamed.pdf', '<script>alert(1)</script>'),
        ])->assertUnprocessable();
    }

    public function test_scanner_failure_quarantines_and_retry_can_release_clean_bytes(): void
    {
        $this->scanner('unavailable');
        $this->actingAs($this->ana);
        $id = $this->upload()->assertCreated()->assertJsonPath('status', 'quarantine')->json('id');
        $this->getJson($this->path('/'.$id.'/content'))->assertStatus(409);
        $this->scanner('clean');
        $this->postJson($this->path('/'.$id.'/scan'))->assertOk()->assertJsonPath('status', 'ready');
        $this->getJson($this->path('/'.$id.'/content'))->assertOk();
    }

    public function test_infected_bytes_are_rejected_and_deleted(): void
    {
        $this->scanner('infected');
        $this->actingAs($this->ana);
        $id = $this->upload()->assertCreated()->assertJsonPath('status', 'rejected')->json('id');
        $asset = DB::connection('admin')->table('documents as d')->join('media_assets as a', 'a.id', '=', 'd.asset_id')->where('d.id', $id)->first();
        Storage::disk('local')->assertMissing($asset->storage_key);
        $this->getJson($this->path('/'.$id.'/content'))->assertStatus(409);
    }

    public function test_oversized_files_tenant_override_and_quota_are_rejected(): void
    {
        $this->actingAs($this->ana)->postJson($this->path(), [
            'title' => 'Too large', 'category' => 'personal', 'file' => UploadedFile::fake()->create('huge.pdf', 10241, 'application/pdf'),
        ])->assertUnprocessable();
        $this->postJson($this->path(), [
            'title' => 'Wrong tenant', 'category' => 'personal', 'family_id' => DemoSeeder::IONESCU,
            'file' => UploadedFile::fake()->createWithContent('test.pdf', DemoSeeder::samplePdf()),
        ])->assertUnprocessable();
        config(['memoria.family_quota_bytes' => 1]);
        $this->upload()->assertUnprocessable();
    }

    public function test_image_reencoding_removes_extra_payload(): void
    {
        $image = imagecreatetruecolor(2, 2);
        ob_start();
        imagepng($image);
        $bytes = ob_get_clean();
        imagedestroy($image);
        $this->actingAs($this->ana);
        $id = $this->postJson($this->path(), [
            'title' => 'Image', 'category' => 'memories', 'file' => UploadedFile::fake()->createWithContent('image.png', $bytes.'UNTRUSTED_METADATA'),
        ])->assertCreated()->assertJsonPath('status', 'ready')->json('id');
        $asset = DB::connection('admin')->table('documents as d')->join('media_assets as a', 'a.id', '=', 'd.asset_id')->where('d.id', $id)->first();
        $this->assertStringNotContainsString('UNTRUSTED_METADATA', Storage::disk('local')->get($asset->storage_key));
    }

    public function test_real_postgres_runtime_role_has_rls_and_context_does_not_leak(): void
    {
        $role = DB::selectOne('SELECT current_user AS name, rolsuper, rolbypassrls FROM pg_roles WHERE rolname=current_user');
        $this->assertSame('memoria_app', $role->name);
        $this->assertFalse($role->rolsuper);
        $this->assertFalse($role->rolbypassrls);
        $this->assertSame(0, DB::table('documents')->count());
        DB::transaction(function () {
            DB::select("SELECT set_config('app.family_id', ?, true)", [$this->family]);
            $this->assertSame(5, DB::table('documents')->count());
            $this->assertSame(0, DB::table('documents')->where('family_id', DemoSeeder::IONESCU)->count());
        });
        $this->assertSame(0, DB::table('documents')->count(), 'Transaction-local tenant must be cleared.');
        $this->actingAs($this->ana)->getJson($this->path())->assertOk();
        $this->assertSame(0, DB::table('documents')->count(), 'HTTP context must also be cleared.');
    }

    public function test_database_composite_foreign_key_rejects_cross_family_asset(): void
    {
        $source = $this->doc('Amintiri din casa bunicilor');
        $foreign = (array) DB::connection('admin')->table('media_assets')->where('id', $source->asset_id)->first();
        $foreign['id'] = (string) Str::uuid();
        $foreign['storage_key'] = 'unused-test-key';
        DB::connection('admin')->table('media_assets')->insert($foreign);
        try {
            // Admin bypasses RLS here so this specifically tests the composite FK.
            DB::connection('admin')->table('documents')->where('title', 'Jurnal de călătorie')->update(['asset_id' => $foreign['id']]);
            $this->fail('Cross-tenant asset must not be accepted.');
        } catch (QueryException $e) {
            $this->assertSame('23503', $e->getCode());
        }
    }

    public function test_rls_blocks_writes_to_another_family_even_without_application_policy(): void
    {
        try {
            DB::transaction(function () {
                DB::select("SELECT set_config('app.family_id', ?, true)", [$this->family]);
                DB::table('audit_events')->insert(['id' => (string) Str::uuid(), 'family_id' => DemoSeeder::IONESCU,
                    'actor_id' => $this->ana->id, 'action' => 'forged', 'resource_id' => (string) Str::uuid(), 'created_at' => now()]);
            });
            $this->fail('RLS must reject cross-family writes.');
        } catch (QueryException $e) {
            $this->assertSame('42501', $e->getCode());
        }
    }

    public function test_csrf_protects_cookie_authenticated_mutations(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        $this->actingAs($this->ana)->postJson($this->path(), [])->assertStatus(419);
    }

    public function test_audit_does_not_capture_private_document_titles_or_filenames(): void
    {
        $this->actingAs($this->ana);
        $this->upload()->assertCreated();
        $events = DB::connection('admin')->table('audit_events')->get()->toJson();
        $this->assertStringContainsString('document.created', $events);
        $this->assertStringNotContainsString('Fișier de test', $events);
        $this->assertStringNotContainsString('test.pdf', $events);
    }

    public function test_live_clamav_releases_an_actual_clean_upload(): void
    {
        if (! getenv('CLAMAV_INTEGRATION')) {
            $this->markTestSkipped('Set CLAMAV_INTEGRATION=1 with a running, updated ClamAV.');
        }
        $this->app->instance(Scanner::class, new ClamAvScanner);
        $this->actingAs($this->ana);
        $id = $this->upload()->assertCreated()->assertJsonPath('status', 'ready')->json('id');
        $this->getJson($this->path('/'.$id.'/content'))->assertOk();
    }

    public function test_failed_database_transaction_removes_new_private_bytes(): void
    {
        $assetId = null;
        try {
            DB::transaction(function () use (&$assetId) {
                DB::select("SELECT set_config('app.family_id', ?, true)", [$this->family]);
                $asset = $this->app->make(PrivateMedia::class)->receive(
                    $this->family, $this->ana->id,
                    UploadedFile::fake()->createWithContent('rollback.pdf', DemoSeeder::samplePdf())
                );
                $assetId = $asset['id'];
                throw new \RuntimeException('Simulated failure after file write.');
            });
        } catch (\RuntimeException $e) {
            $this->assertSame('Simulated failure after file write.', $e->getMessage());
        }
        $this->assertNotNull($assetId);
        Storage::disk('local')->assertMissing($this->family.'/'.$assetId);
        $this->assertFalse(DB::connection('admin')->table('media_assets')->where('id', $assetId)->exists());
    }

    public function test_live_clamav_detects_harmless_eicar_in_memory(): void
    {
        if (! getenv('CLAMAV_INTEGRATION')) {
            $this->markTestSkipped('Set CLAMAV_INTEGRATION=1 with a running, updated ClamAV.');
        }
        // Standard harmless antivirus test signature, transmitted only in RAM to local ClamAV.
        $signature = 'X5O!P%@AP[4'.'\\PZX54(P^)7CC)7}$EICAR-STANDARD-ANTIVIRUS-TEST-FILE!$H+H*';
        $socket = stream_socket_client('tcp://127.0.0.1:3310', $code, $message, 2);
        $this->assertIsResource($socket);
        stream_set_timeout($socket, 10);
        fwrite($socket, "zINSTREAM\0".pack('N', strlen($signature)).$signature.pack('N', 0));
        $result = stream_get_contents($socket, 1024);
        fclose($socket);
        $this->assertStringContainsString(' FOUND', $result);
    }
}
