<?php

namespace Tests\Feature;

use App\Models\User;
use App\Platform\Administration\BrevoTransportFactory;
use Illuminate\Database\QueryException;
use Illuminate\Mail\Message;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Mockery;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

final class AdminOverviewTest extends TestCase
{
    private User $administrator;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 24)->setTime(12, 0));
        $this->assertSame('memoria_test', config('database.connections.pgsql.database'));
        $admin = config('database.connections.pgsql');
        $admin['username'] = 'memoria_admin';
        $admin['password'] = env('DB_ADMIN_PASSWORD');
        config(['database.connections.admin' => $admin, 'memoria.admin_emails' => ['admin@example.test']]);
        DB::connection('admin')->statement('TRUNCATE families, users, email_deliveries RESTART IDENTITY CASCADE');
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
        $this->travelBack();
        DB::disconnect('pgsql');
        DB::disconnect('admin');
        parent::tearDown();
    }

    public function test_overview_requires_both_platform_grant_and_allowlisted_email(): void
    {
        $this->getJson('/api/v1/admin/overview')->assertUnauthorized();
        $this->actingAs($this->owner)->getJson('/api/v1/admin/overview')->assertForbidden();
        config(['memoria.admin_emails' => ['owner@example.test']]);
        $this->getJson('/api/v1/admin/overview')->assertForbidden();
        $this->actingAs($this->administrator)->getJson('/api/v1/admin/overview')->assertForbidden();
        config(['memoria.admin_emails' => ['admin@example.test']]);
        $this->getJson('/api/v1/admin/overview')->assertOk()
            ->assertJsonPath('users.total', 2)
            ->assertJsonPath('families.total', 1)
            ->assertJsonPath('email.total', 0)
            ->assertJsonPath('email.sent', 0)
            ->assertJsonPath('email.failed', 0)
            ->assertJsonPath('email.last_30_days', 0)
            ->assertJsonPath('recent_emails', [])
            ->assertHeader('Cache-Control', 'max-age=0, no-store, private');
    }

    public function test_overview_counts_all_users_families_and_delivery_attempts_with_inclusive_thirty_day_cutoff(): void
    {
        $cutoff = now()->subDays(30);
        DB::setDefaultConnection('admin');
        User::factory()->create(['created_at' => $cutoff, 'updated_at' => $cutoff]);
        User::factory()->create(['created_at' => $cutoff->copy()->subSecond(), 'updated_at' => $cutoff]);
        User::factory()->create(['created_at' => $cutoff->copy()->subYear(), 'updated_at' => $cutoff]);
        DB::table('families')->insert(['id' => (string) Str::uuid(), 'name' => 'Altă familie']);
        $expectedIds = [];
        for ($index = 0; $index < 11; $index++) {
            $expectedIds[] = DB::table('email_deliveries')->insertGetId($this->delivery(['recipient' => "recipient{$index}@example.test"]));
        }
        DB::table('email_deliveries')->insert([
            $this->delivery(['created_at' => $cutoff->copy()->subSecond()]),
            $this->delivery(['recipient' => 'cutoff@example.test', 'status' => 'failed', 'sent_at' => null, 'created_at' => $cutoff]),
        ]);
        DB::setDefaultConnection('pgsql');

        $response = $this->actingAs($this->administrator)->getJson('/api/v1/admin/overview')->assertOk()
            ->assertJsonPath('users.total', 5)
            ->assertJsonPath('users.last_30_days', 3)
            ->assertJsonPath('families.total', 2)
            ->assertJsonPath('email.total', 13)
            ->assertJsonPath('email.sent', 12)
            ->assertJsonPath('email.failed', 1)
            ->assertJsonPath('email.last_30_days', 12)
            ->assertJsonCount(10, 'recent_emails');
        $this->assertSame(array_slice(array_reverse($expectedIds), 0, 10), array_column($response->json('recent_emails'), 'id'));
        foreach ($response->json('recent_emails') as $delivery) {
            $this->assertEqualsCanonicalizing(['id', 'recipient', 'subject', 'status', 'sent_at', 'created_at'], array_keys($delivery));
            $this->assertSame('sent', $delivery['status']);
            $this->assertNotNull($delivery['sent_at']);
        }
    }

    public function test_delivery_rows_are_visible_only_to_admin_and_delivery_context_can_only_append(): void
    {
        $id = DB::connection('admin')->table('email_deliveries')->insertGetId($this->delivery());
        $this->assertSame(0, DB::table('email_deliveries')->count());
        $this->assertSame(0, DB::table('email_deliveries')->where('id', $id)->update(['status' => 'failed']));
        $this->assertSame(0, DB::table('email_deliveries')->where('id', $id)->delete());
        $this->assertInsertRejectedForContext(null);

        DB::transaction(function () use ($id): void {
            DB::select("SELECT set_config('app.email_settings_access', 'delivery', true)");
            $this->assertTrue(DB::table('email_deliveries')->insert($this->delivery(['recipient' => 'appended@example.test'])));
            $this->assertSame(0, DB::table('email_deliveries')->count());
            $this->assertSame(0, DB::table('email_deliveries')->where('id', $id)->update(['status' => 'failed']));
            $this->assertSame(0, DB::table('email_deliveries')->where('id', $id)->delete());
        });
        DB::transaction(function () use ($id): void {
            DB::select("SELECT set_config('app.email_settings_access', 'admin', true)");
            $this->assertSame(2, DB::table('email_deliveries')->count());
            $this->assertSame(0, DB::table('email_deliveries')->where('id', $id)->update(['status' => 'failed']));
            $this->assertSame(0, DB::table('email_deliveries')->where('id', $id)->delete());
        });
        $this->assertInsertRejectedForContext('admin');
        $this->assertSame(0, DB::table('email_deliveries')->count());
        $this->assertSame(2, DB::connection('admin')->table('email_deliveries')->count());
    }

    public function test_test_email_logs_transport_acceptance_failure_and_disabled_settings_without_exposing_secrets(): void
    {
        $transport = new ArrayTransport;
        $factory = Mockery::mock(BrevoTransportFactory::class);
        $factory->shouldReceive('make')->once()->with('brevo-login', 'smtp-secret-one')->andReturn($transport);
        $factory->shouldReceive('make')->once()->with('brevo-login', 'smtp-secret-one')
            ->andThrow(new TransportException('SMTP authentication failed: smtp-secret-one brevo-login secret-error-marker'));
        $this->app->instance(BrevoTransportFactory::class, $factory);
        $this->actingAs($this->administrator)->putJson('/api/v1/admin/email-settings', $this->settings())->assertOk();
        $this->postJson('/api/v1/admin/email-test', ['recipient' => 'accepted@example.test'])->assertOk();
        $failure = $this->postJson('/api/v1/admin/email-test', ['recipient' => 'failed@example.test'])->assertUnprocessable();
        $this->putJson('/api/v1/admin/email-settings', $this->settings(['version' => 2, 'enabled' => false]))->assertOk();
        $disabled = $this->postJson('/api/v1/admin/email-test', ['recipient' => 'disabled@example.test'])->assertUnprocessable();

        $this->assertCount(1, $transport->messages());
        $rows = DB::connection('admin')->table('email_deliveries')->orderBy('id')->get();
        $this->assertCount(3, $rows);
        $this->assertSame(['accepted@example.test', 'failed@example.test', 'disabled@example.test'], $rows->pluck('recipient')->all());
        $this->assertSame(['sent', 'failed', 'failed'], $rows->pluck('status')->all());
        $this->assertNotNull($rows[0]->sent_at);
        $this->assertNull($rows[1]->sent_at);
        $this->assertNull($rows[2]->sent_at);
        foreach ($rows as $delivery) {
            $this->assertEqualsCanonicalizing(['id', 'recipient', 'subject', 'status', 'sent_at', 'created_at'], array_keys((array) $delivery));
        }
        $overview = $this->getJson('/api/v1/admin/overview')->assertOk()
            ->assertJsonPath('email.total', 3)->assertJsonPath('email.sent', 1)->assertJsonPath('email.failed', 2)
            ->assertJsonPath('recent_emails.0.sent_at', null);
        foreach (['smtp-secret-one', 'brevo-login', 'secret-error-marker', 'Acesta este un email de test trimis din Amora.'] as $secret) {
            $this->assertStringNotContainsString($secret, $rows->toJson());
            $this->assertStringNotContainsString($secret, $overview->getContent());
            $this->assertStringNotContainsString($secret, $failure->getContent());
            $this->assertStringNotContainsString($secret, $disabled->getContent());
        }
    }

    public function test_mail_logs_each_unique_to_cc_and_bcc_recipient_without_storing_message_body(): void
    {
        $transport = new ArrayTransport;
        $factory = Mockery::mock(BrevoTransportFactory::class);
        $factory->shouldReceive('make')->once()->with('brevo-login', 'smtp-secret-one')->andReturn($transport);
        $this->app->instance(BrevoTransportFactory::class, $factory);
        $this->actingAs($this->administrator)->putJson('/api/v1/admin/email-settings', $this->settings())->assertOk();

        Mail::raw('private-body-marker', function (Message $message): void {
            $message->to(['to@example.test', 'duplicate@example.test'])
                ->cc(['cc@example.test', 'duplicate@example.test'])
                ->bcc(['bcc@example.test', 'to@example.test'])
                ->subject('Eveniment de familie');
        });

        $rows = DB::connection('admin')->table('email_deliveries')->orderBy('recipient')->get();
        $this->assertSame(['bcc@example.test', 'cc@example.test', 'duplicate@example.test', 'to@example.test'], $rows->pluck('recipient')->all());
        $this->assertSame(['sent'], $rows->pluck('status')->unique()->values()->all());
        $this->assertSame(['Eveniment de familie'], $rows->pluck('subject')->unique()->values()->all());
        $this->assertStringNotContainsString('private-body-marker', $rows->toJson());
        $this->assertCount(1, $transport->messages());
    }

    public function test_explicit_envelope_logs_only_actual_smtp_recipients_once(): void
    {
        $transport = new ArrayTransport;
        $factory = Mockery::mock(BrevoTransportFactory::class);
        $factory->shouldReceive('make')->once()->with('brevo-login', 'smtp-secret-one')->andReturn($transport);
        $this->app->instance(BrevoTransportFactory::class, $factory);
        $this->actingAs($this->administrator)->putJson('/api/v1/admin/email-settings', $this->settings())->assertOk();
        $email = (new Email)->from('original@example.test')->to('header-only@example.test')
            ->cc('recipient@example.test')->bcc('hidden-header-only@example.test')
            ->subject('Expediere explicită')->text('private-envelope-body-marker');
        $envelope = new Envelope(new Address('original@example.test'), [
            new Address('recipient@example.test'), new Address('envelope-only@example.test'), new Address('recipient@example.test'),
        ]);

        Mail::mailer('amora')->getSymfonyTransport()->send($email, $envelope);

        $rows = DB::connection('admin')->table('email_deliveries')->orderBy('recipient')->get();
        $this->assertSame(['envelope-only@example.test', 'recipient@example.test'], $rows->pluck('recipient')->all());
        $this->assertSame(['sent'], $rows->pluck('status')->unique()->values()->all());
        $this->assertStringNotContainsString('private-envelope-body-marker', $rows->toJson());
        $this->assertCount(1, $transport->messages());
    }

    private function assertInsertRejectedForContext(?string $context): void
    {
        try {
            DB::transaction(function () use ($context): void {
                if ($context !== null) {
                    DB::select("SELECT set_config('app.email_settings_access', ?, true)", [$context]);
                }
                DB::table('email_deliveries')->insert($this->delivery());
            });
            $this->fail('Insertul în jurnal necesită contextul delivery.');
        } catch (QueryException $exception) {
            $this->assertSame('42501', (string) $exception->getCode());
        }
    }

    private function delivery(array $changes = []): array
    {
        return array_replace([
            'recipient' => 'recipient@example.test', 'subject' => 'Test SMTP', 'status' => 'sent',
            'sent_at' => now(), 'created_at' => now(),
        ], $changes);
    }

    private function settings(array $changes = []): array
    {
        return array_replace([
            'enabled' => true, 'username' => 'brevo-login', 'password' => 'smtp-secret-one',
            'from_address' => 'sender@example.test', 'from_name' => 'Amora', 'reply_to' => 'reply@example.test', 'version' => 1,
        ], $changes);
    }
}
