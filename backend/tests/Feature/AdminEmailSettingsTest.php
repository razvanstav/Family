<?php

namespace Tests\Feature;

use App\Models\User;
use App\Platform\Administration\BrevoTransportFactory;
use Illuminate\Mail\Message;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Mockery;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

final class AdminEmailSettingsTest extends TestCase
{
    private User $administrator;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('memoria_test', config('database.connections.pgsql.database'));
        $admin = config('database.connections.pgsql');
        $admin['username'] = 'memoria_admin';
        $admin['password'] = env('DB_ADMIN_PASSWORD');
        config(['database.connections.admin' => $admin, 'memoria.admin_emails' => ['admin@example.test']]);
        DB::connection('admin')->statement('TRUNCATE families, users RESTART IDENTITY CASCADE');
        DB::setDefaultConnection('admin');
        $this->owner = User::factory()->create(['email' => 'owner@example.test']);
        $this->administrator = User::factory()->create(['email' => 'admin@example.test', 'is_platform_admin' => true]);
        $familyId = (string) Str::uuid();
        DB::table('families')->insert(['id' => $familyId, 'name' => 'Familie test']);
        DB::table('family_memberships')->insert(['id' => (string) Str::uuid(), 'family_id' => $familyId, 'user_id' => $this->owner->id, 'role' => 'owner']);
        DB::table('application_email_settings')->insert(['id' => 1, 'created_at' => now(), 'updated_at' => now()]);
        DB::setDefaultConnection('pgsql');
    }

    protected function tearDown(): void
    {
        DB::disconnect('pgsql');
        DB::disconnect('admin');
        parent::tearDown();
    }

    public function test_only_explicitly_granted_administrators_can_access_global_settings(): void
    {
        $this->getJson('/api/v1/admin/email-settings')->assertUnauthorized();
        $this->putJson('/api/v1/admin/email-settings', $this->settings())->assertUnauthorized();
        $this->postJson('/api/v1/admin/email-test', ['recipient' => 'recipient@example.test'])->assertUnauthorized();
        $this->actingAs($this->owner)->getJson('/api/v1/session')->assertJsonPath('user.is_admin', false);
        $this->getJson('/api/v1/admin/email-settings')->assertForbidden();
        $this->putJson('/api/v1/admin/email-settings', $this->settings())->assertForbidden();
        $this->postJson('/api/v1/admin/email-test', ['recipient' => 'recipient@example.test'])->assertForbidden();

        $this->actingAs($this->administrator)->getJson('/api/v1/session')->assertJsonPath('user.is_admin', true);
        $this->getJson('/api/v1/admin/email-settings')->assertOk()->assertJsonPath('enabled', false)
            ->assertJsonPath('host', 'smtp-relay.brevo.com')->assertJsonPath('port', 587)
            ->assertJsonPath('has_password', false)->assertJsonPath('version', 1)
            ->assertHeader('Cache-Control', 'max-age=0, no-store, private');
        config(['memoria.admin_emails' => []]);
        $this->getJson('/api/v1/admin/email-settings')->assertForbidden();
        $this->getJson('/api/v1/session')->assertJsonPath('user.is_admin', false);
    }

    public function test_public_signup_with_allowlisted_email_never_grants_admin_and_only_privileged_cli_can_grant_existing_account(): void
    {
        config(['memoria.admin_emails' => ['new-admin@example.test']]);
        $this->postJson('/api/v1/register', [
            'name' => 'New Admin', 'email' => 'new-admin@example.test', 'password' => 'Test-Password-2026!',
            'family_name' => 'Familie nouă', 'is_platform_admin' => true,
        ])->assertCreated()->assertJsonPath('user.is_admin', false);
        $newUser = User::where('email', 'new-admin@example.test')->firstOrFail();
        $this->assertFalse($newUser->is_platform_admin);
        $this->getJson('/api/v1/admin/email-settings')->assertForbidden();
        $this->artisan('app:grant-admin', ['email' => 'new-admin@example.test'])->assertExitCode(1);
        $this->assertFalse($newUser->fresh()->is_platform_admin);
        DB::setDefaultConnection('admin');
        $this->artisan('app:grant-admin', ['email' => ' NEW-ADMIN@EXAMPLE.TEST '])->assertSuccessful();
        DB::setDefaultConnection('pgsql');
        $this->actingAs($newUser->fresh())->getJson('/api/v1/admin/email-settings')->assertOk();
        $count = User::count();
        config(['memoria.admin_emails' => ['missing@example.test']]);
        $this->artisan('app:grant-admin', ['email' => 'missing@example.test'])->assertExitCode(1);
        $this->assertSame($count, User::count());
    }

    public function test_secret_is_encrypted_never_returned_and_preserved_until_replaced(): void
    {
        $this->actingAs($this->administrator);
        $response = $this->putJson('/api/v1/admin/email-settings', $this->settings())->assertOk()
            ->assertJsonPath('version', 2)->assertJsonPath('has_password', true);
        $this->assertArrayNotHasKey('password', $response->json());
        $this->assertStringNotContainsString('smtp-secret-one', $response->getContent());
        $encrypted = DB::connection('admin')->table('application_email_settings')->value('password_encrypted');
        $this->assertNotSame('smtp-secret-one', $encrypted);
        $this->assertSame('smtp-secret-one', Crypt::decryptString($encrypted));

        foreach (['', null, 'omitted'] as $index => $password) {
            $data = $this->settings(['version' => $index + 2, 'password' => $password]);
            if ($password === 'omitted') {
                unset($data['password']);
            }
            $this->putJson('/api/v1/admin/email-settings', $data)->assertOk()->assertJsonPath('version', $index + 3);
            $this->assertSame($encrypted, DB::connection('admin')->table('application_email_settings')->value('password_encrypted'));
        }
        $this->putJson('/api/v1/admin/email-settings', $this->settings(['version' => 5, 'password' => 'smtp-secret-two']))->assertOk();
        $this->assertSame('smtp-secret-two', Crypt::decryptString(DB::connection('admin')->table('application_email_settings')->value('password_encrypted')));
        $this->getJson('/api/v1/admin/email-settings')->assertJsonMissingPath('password')->assertJsonMissingPath('password_encrypted');
    }

    public function test_conflicts_and_invalid_settings_do_not_overwrite_saved_credentials(): void
    {
        $this->actingAs($this->administrator);
        foreach ([['host' => '127.0.0.1'], ['port' => 25], ['url' => 'smtp://127.0.0.1'], ['from_address' => 'invalid'], ['reply_to' => 'invalid'], ['username' => "user\r\nother"], ['password' => "key\r\nother"], ['from_name' => "Amora\r\nBcc: hidden@example.test"], ['version' => 0], ['password' => ''], ['username' => '']] as $changes) {
            $this->putJson('/api/v1/admin/email-settings', $this->settings($changes))->assertUnprocessable();
        }
        $this->putJson('/api/v1/admin/email-settings', $this->settings())->assertOk();
        $this->putJson('/api/v1/admin/email-settings', $this->settings(['password' => 'should-never-save']))->assertStatus(412);
        $this->assertSame('smtp-secret-one', Crypt::decryptString(DB::connection('admin')->table('application_email_settings')->value('password_encrypted')));
        $this->assertSame(2, DB::connection('admin')->table('application_email_settings')->value('version'));
    }

    public function test_email_test_uses_saved_credentials_and_explicit_recipient_without_exposing_transport_errors(): void
    {
        $transport = new ArrayTransport;
        $factory = Mockery::mock(BrevoTransportFactory::class);
        $factory->shouldReceive('make')->once()->with('brevo-login', 'smtp-secret-one')->andReturn($transport);
        $factory->shouldReceive('make')->once()->with('brevo-login', 'smtp-secret-one')
            ->andThrow(new TransportException('Authentication failed: smtp-secret-one brevo-login'));
        $this->app->instance(BrevoTransportFactory::class, $factory);
        $this->actingAs($this->administrator)->putJson('/api/v1/admin/email-settings', $this->settings())->assertOk();

        $this->postJson('/api/v1/admin/email-test', ['recipient' => 'recipient@example.test', 'password' => 'unsaved-key', 'username' => 'unsaved-login'])
            ->assertOk()->assertJsonPath('message', 'Emailul de test a fost trimis.');
        $sent = $transport->messages()->sole();
        $email = $sent->getOriginalMessage();
        $this->assertSame('recipient@example.test', $email->getTo()[0]->getAddress());
        $this->assertSame('sender@example.test', $email->getFrom()[0]->getAddress());
        $this->assertSame('Amora', $email->getFrom()[0]->getName());
        $this->assertSame('reply@example.test', $email->getReplyTo()[0]->getAddress());
        $this->assertSame('sender@example.test', $sent->getEnvelope()->getSender()->getAddress());
        $failure = $this->postJson('/api/v1/admin/email-test', ['recipient' => 'recipient@example.test'])->assertUnprocessable();
        $this->assertStringNotContainsString('smtp-secret-one', $failure->getContent());
        $this->assertStringNotContainsString('brevo-login', $failure->getContent());
    }

    public function test_test_email_requires_valid_recipient_enabled_saved_settings_and_is_rate_limited(): void
    {
        $factory = Mockery::mock(BrevoTransportFactory::class);
        $factory->shouldNotReceive('make');
        $this->app->instance(BrevoTransportFactory::class, $factory);
        $this->actingAs($this->administrator);
        $this->postJson('/api/v1/admin/email-test', ['recipient' => 'invalid'])->assertUnprocessable()->assertJsonValidationErrors('recipient');
        $this->postJson('/api/v1/admin/email-test', ['recipient' => 'recipient@example.test', ...$this->settings()])->assertUnprocessable();
        $this->postJson('/api/v1/admin/email-test', ['recipient' => 'recipient@example.test'])->assertUnprocessable();
        $this->postJson('/api/v1/admin/email-test', ['recipient' => 'recipient@example.test'])->assertTooManyRequests();
    }

    public function test_default_mailer_reloads_settings_for_each_send_even_when_already_resolved(): void
    {
        $this->assertSame('amora', config('mail.default'));
        $transport = new ArrayTransport;
        $factory = Mockery::mock(BrevoTransportFactory::class);
        $factory->shouldReceive('make')->once()->with('brevo-login', 'smtp-secret-one')->andReturn($transport);
        $factory->shouldReceive('make')->once()->with('next-login', 'smtp-secret-two')->andReturn($transport);
        $this->app->instance(BrevoTransportFactory::class, $factory);
        $this->actingAs($this->administrator)->putJson('/api/v1/admin/email-settings', $this->settings())->assertOk();
        $mailer = Mail::mailer();
        $mailer->raw('Primul email', fn (Message $message) => $message->to('recipient@example.test')->subject('Test'));
        $this->putJson('/api/v1/admin/email-settings', $this->settings(['version' => 2, 'username' => 'next-login', 'password' => 'smtp-secret-two', 'from_address' => 'next@example.test']))->assertOk();
        $mailer->raw('Al doilea email', fn (Message $message) => $message->to('recipient@example.test')->subject('Test'));
        $this->assertSame('sender@example.test', $transport->messages()[0]->getOriginalMessage()->getFrom()[0]->getAddress());
        $this->assertSame('next@example.test', $transport->messages()[1]->getOriginalMessage()->getFrom()[0]->getAddress());
        $this->putJson('/api/v1/admin/email-settings', $this->settings(['version' => 3, 'enabled' => false]))->assertOk();
        $this->expectException(TransportException::class);
        $mailer->raw('Blocat', fn (Message $message) => $message->to('recipient@example.test')->subject('Test'));
    }

    public function test_database_settings_require_admin_or_delivery_context_and_normal_sessions_do_not_read_them(): void
    {
        $this->assertSame(0, DB::table('application_email_settings')->count());
        $this->assertSame(0, DB::table('application_email_settings')->where('id', 1)->update(['enabled' => true]));
        DB::transaction(function (): void {
            DB::select("SELECT set_config('app.email_settings_access', 'delivery', true)");
            $this->assertSame(1, DB::table('application_email_settings')->count());
            $this->assertSame(0, DB::table('application_email_settings')->where('id', 1)->update(['enabled' => true]));
        });
        $this->actingAs($this->administrator)->getJson('/api/v1/admin/email-settings')->assertOk();
        $this->assertSame(0, DB::table('application_email_settings')->count());
        DB::enableQueryLog();
        $this->getJson('/api/v1/session')->assertOk();
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        foreach ($queries as $query) {
            $this->assertStringNotContainsString('application_email_settings', $query['query']);
        }
    }

    public function test_smtp_transport_requires_tls_and_uses_only_brevo_with_a_bounded_timeout(): void
    {
        $transport = (new BrevoTransportFactory)->make('brevo-login', 'smtp-key');
        $this->assertSame('smtp-relay.brevo.com', $transport->getStream()->getHost());
        $this->assertSame(587, $transport->getStream()->getPort());
        $this->assertTrue($transport->isTlsRequired());
        $this->assertTrue($transport->isAutoTls());
        $this->assertSame(10.0, $transport->getStream()->getTimeout());
    }

    private function settings(array $changes = []): array
    {
        return array_replace([
            'enabled' => true, 'username' => 'brevo-login', 'password' => 'smtp-secret-one',
            'from_address' => 'sender@example.test', 'from_name' => 'Amora', 'reply_to' => 'reply@example.test', 'version' => 1,
        ], $changes);
    }
}
