<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class SupportRequestTest extends TestCase
{
    private User $administrator;

    private User $owner;

    private string $family;

    /** @var list<string> */
    private array $references = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('memoria_test', config('database.connections.pgsql.database'));
        $admin = config('database.connections.pgsql');
        $admin['username'] = 'memoria_admin';
        $admin['password'] = env('DB_ADMIN_PASSWORD');
        config(['database.connections.admin' => $admin]);
        DB::setDefaultConnection('admin');
        $suffix = Str::lower(Str::random(12));
        $this->owner = User::factory()->create(['email' => 'support-owner-'.$suffix.'@example.test']);
        $this->administrator = User::factory()->create(['email' => 'support-admin-'.$suffix.'@example.test', 'is_platform_admin' => true]);
        $this->family = (string) Str::uuid();
        DB::table('families')->insert(['id' => $this->family, 'name' => 'Familie solicitări']);
        DB::table('family_memberships')->insert(['id' => (string) Str::uuid(), 'family_id' => $this->family, 'user_id' => $this->owner->id, 'role' => 'owner']);
        DB::setDefaultConnection('pgsql');
        config(['memoria.admin_emails' => [$this->administrator->email]]);
        Mail::fake();
        Notification::fake();
    }

    protected function tearDown(): void
    {
        DB::connection('admin')->table('support_requests')->whereIn('reference', $this->references)->delete();
        DB::connection('admin')->table('family_memberships')->where('family_id', $this->family)->delete();
        DB::connection('admin')->table('families')->where('id', $this->family)->delete();
        DB::connection('admin')->table('users')->whereIn('id', [$this->owner->id, $this->administrator->id])->delete();
        DB::disconnect('pgsql');
        DB::disconnect('admin');
        parent::tearDown();
    }

    /** @return array<string, mixed> */
    private function content(array $changes = []): array
    {
        return array_replace([
            'type' => 'privacy', 'name' => 'Solicitant confidențial', 'email' => 'Solicitant@Example.Test',
            'subject' => 'Acces la date private', 'message' => 'Mesaj confidențial pentru verificarea solicitării.',
        ], $changes);
    }

    private function submit(array $changes = []): string
    {
        $response = $this->postJson('/api/v1/public/requests', $this->content($changes))->assertCreated();
        $reference = $response->json('reference');
        $this->references[] = $reference;
        $response->assertExactJson(['reference' => $reference, 'status' => 'received', 'created_at' => $response->json('created_at')])
            ->assertHeader('Cache-Control', 'max-age=0, no-store, private');
        $this->assertMatchesRegularExpression('/^AMR-[A-Z0-9]{12}$/', $reference);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.000000Z$/', $response->json('created_at'));

        return $reference;
    }

    private function insertRequest(string $status = 'received'): string
    {
        $id = (string) Str::uuid();
        $reference = 'AMR-'.Str::upper(Str::random(12));
        $this->references[] = $reference;
        DB::connection('admin')->table('support_requests')->insert([
            'id' => $id, 'reference' => $reference, 'purpose' => 'support', 'status' => $status,
            'payload_ciphertext' => Crypt::encryptString(json_encode(array_diff_key($this->content(), ['type' => true]), JSON_THROW_ON_ERROR)),
            'version' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $id;
    }

    public function test_public_requests_are_encrypted_and_receipts_never_return_content_or_send_email(): void
    {
        foreach (['support', 'privacy', 'withdrawal', 'illegal-content'] as $type) {
            $reference = $this->submit(['type' => $type]);
            $row = DB::connection('admin')->table('support_requests')->where('reference', $reference)->firstOrFail();
            $this->assertSame($type, $row->purpose);
            $this->assertSame('received', $row->status);
            $this->assertSame(1, $row->version);
            $decoded = json_decode(Crypt::decryptString($row->payload_ciphertext), true, flags: JSON_THROW_ON_ERROR);
            $this->assertSame('solicitant@example.test', $decoded['email']);
            foreach (['name', 'subject', 'message'] as $field) {
                $this->assertSame($this->content()[$field], $decoded[$field]);
                $this->assertStringNotContainsString($this->content()[$field], json_encode($row, JSON_THROW_ON_ERROR));
            }
            $this->assertStringNotContainsString('solicitant@example.test', $row->payload_ciphertext);
        }
        $this->assertGuest();
        $this->assertSame(0, DB::table('support_requests')->count());
        Mail::assertNothingSent();
        Mail::assertNothingQueued();
        Notification::assertNothingSent();
    }

    public function test_only_granted_and_allowlisted_application_admin_can_read_requests(): void
    {
        $id = $this->insertRequest();
        $this->getJson('/api/v1/admin/requests')->assertUnauthorized();
        $this->patchJson('/api/v1/admin/requests/'.$id, ['status' => 'closed', 'version' => 1])->assertUnauthorized();
        $this->actingAs($this->owner)->getJson('/api/v1/admin/requests')->assertForbidden();
        $this->patchJson('/api/v1/admin/requests/'.$id, ['status' => 'closed', 'version' => 1])->assertForbidden();
        config(['memoria.admin_emails' => [$this->owner->email]]);
        $this->getJson('/api/v1/admin/requests')->assertForbidden();
        $this->actingAs($this->administrator)->getJson('/api/v1/admin/requests')->assertForbidden();
        config(['memoria.admin_emails' => [$this->administrator->email]]);
        $this->getJson('/api/v1/admin/requests')->assertOk()->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.name', 'Solicitant confidențial')
            ->assertJsonPath('items.0.email', 'Solicitant@Example.Test')
            ->assertJsonPath('items.0.subject', 'Acces la date private')
            ->assertJsonPath('items.0.message', $this->content()['message'])
            ->assertJsonMissingPath('items.0.payload_ciphertext')
            ->assertHeader('Cache-Control', 'max-age=0, no-store, private');
        $this->assertSame(0, DB::table('support_requests')->count());
    }

    public function test_administration_lists_are_bounded_and_filter_by_status(): void
    {
        for ($index = 0; $index < 26; $index++) {
            $this->insertRequest($index === 0 ? 'closed' : 'received');
        }
        $this->actingAs($this->administrator)->getJson('/api/v1/admin/requests')
            ->assertOk()->assertJsonCount(25, 'items')->assertJsonPath('page', 1)->assertJsonPath('has_more', true);
        $this->getJson('/api/v1/admin/requests?page=2')->assertOk()->assertJsonCount(1, 'items')->assertJsonPath('has_more', false);
        $this->getJson('/api/v1/admin/requests?status=closed')->assertOk()->assertJsonCount(1, 'items')->assertJsonPath('has_more', false);
        $this->getJson('/api/v1/admin/requests?status=unknown')->assertUnprocessable();
        $this->getJson('/api/v1/admin/requests?page=0')->assertUnprocessable();
    }

    public function test_status_updates_preserve_content_and_reject_stale_or_invalid_changes(): void
    {
        $id = $this->insertRequest();
        $ciphertext = DB::connection('admin')->table('support_requests')->where('id', $id)->value('payload_ciphertext');
        $this->actingAs($this->administrator)->patchJson('/api/v1/admin/requests/'.$id, ['status' => 'in_progress', 'version' => 1])
            ->assertOk()->assertJsonPath('status', 'in_progress')->assertJsonPath('version', 2);
        $this->patchJson('/api/v1/admin/requests/'.$id, ['status' => 'closed', 'version' => 1])->assertStatus(412);
        $this->patchJson('/api/v1/admin/requests/'.$id, ['status' => 'sent', 'version' => 2])->assertUnprocessable();
        $this->patchJson('/api/v1/admin/requests/'.Str::uuid(), ['status' => 'closed', 'version' => 1])->assertNotFound();
        $this->patchJson('/api/v1/admin/requests/'.$id, ['status' => 'closed', 'version' => 2])
            ->assertOk()->assertJsonPath('status', 'closed')->assertJsonPath('version', 3);
        $this->assertSame($ciphertext, DB::connection('admin')->table('support_requests')->where('id', $id)->value('payload_ciphertext'));
        Mail::assertNothingSent();
    }

    #[DataProvider('invalidRequests')]
    public function test_invalid_requests_are_not_persisted(string $field, mixed $value): void
    {
        $before = DB::connection('admin')->table('support_requests')->count();
        $this->postJson('/api/v1/public/requests', $this->content([$field => $value]))->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertSame($before, DB::connection('admin')->table('support_requests')->count());
    }

    /** @return iterable<string, array{string, mixed}> */
    public static function invalidRequests(): iterable
    {
        yield 'unknown purpose' => ['type', 'other'];
        yield 'name required' => ['name', ''];
        yield 'name length' => ['name', str_repeat('a', 101)];
        yield 'email format' => ['email', 'invalid'];
        yield 'subject required' => ['subject', ''];
        yield 'subject length' => ['subject', str_repeat('a', 161)];
        yield 'message required' => ['message', ''];
        yield 'message length' => ['message', str_repeat('a', 8001)];
        yield 'cannot forge status' => ['status', 'closed'];
        yield 'no attachments' => ['attachments', ['file.pdf']];
    }

    public function test_public_submission_rejects_files_and_is_rate_limited(): void
    {
        $this->withHeader('Accept', 'application/json')->post('/api/v1/public/requests', $this->content() + ['file' => UploadedFile::fake()->create('private.pdf', 1, 'application/pdf')])
            ->assertUnprocessable()->assertJsonValidationErrors('file');
        for ($index = 0; $index < 5; $index++) {
            $this->submit();
        }
        $this->postJson('/api/v1/public/requests', $this->content())->assertStatus(429);
    }

    public function test_csrf_protects_public_and_admin_mutations(): void
    {
        $id = $this->insertRequest();
        $this->app->detectEnvironment(fn () => 'production');
        $this->postJson('/api/v1/public/requests', $this->content())->assertStatus(419);
        $this->actingAs($this->administrator)->patchJson('/api/v1/admin/requests/'.$id, ['status' => 'closed', 'version' => 1])->assertStatus(419);
    }

    public function test_rls_is_append_only_for_submission_and_admin_cannot_replace_personal_content(): void
    {
        $id = $this->insertRequest();
        $this->assertSame(0, DB::table('support_requests')->count());
        $this->assertSame(0, DB::table('support_requests')->where('id', $id)->update(['status' => 'closed']));
        $row = (array) DB::connection('admin')->table('support_requests')->where('id', $id)->firstOrFail();
        $row['id'] = (string) Str::uuid();
        $row['reference'] = 'AMR-'.Str::upper(Str::random(12));
        $this->assertRejected(fn () => DB::table('support_requests')->insert($row));
        DB::transaction(function (): void {
            DB::select("SELECT set_config('app.support_requests_access', 'submission', true)");
            $this->assertSame(0, DB::table('support_requests')->count());
        });
        $this->assertRejected(function () use ($id): void {
            DB::select("SELECT set_config('app.support_requests_access', 'admin', true)");
            DB::table('support_requests')->where('id', $id)->update(['payload_ciphertext' => 'replacement']);
        });
        $this->assertRejected(fn () => DB::table('support_requests')->where('id', $id)->delete());
    }

    private function assertRejected(callable $operation): void
    {
        try {
            DB::transaction($operation);
            $this->fail('Runtime access must reject this operation.');
        } catch (QueryException $exception) {
            $this->assertSame('42501', $exception->getCode());
        }
    }
}
