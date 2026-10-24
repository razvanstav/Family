<?php

namespace Tests\Feature;

use App\Models\User;
use App\Platform\Administration\SmtpReminderEmailSender;
use App\Platform\Contracts\NotificationPreferences;
use App\Platform\Contracts\ReminderEmailSender;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Tests\TestCase;

final class NotificationSettingsTest extends TestCase
{
    private User $actor;

    private User $other;

    private string $url = '/api/v1/account/notification-settings';

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('memoria_test', config('database.connections.pgsql.database'));
        $admin = config('database.connections.pgsql');
        $admin['username'] = 'memoria_admin';
        $admin['password'] = env('DB_ADMIN_PASSWORD');
        config(['database.connections.admin' => $admin]);
        foreach (['actor', 'other'] as $property) {
            $this->{$property} = User::factory()->make();
            $this->{$property}->setConnection('admin')->save();
        }
        $this->actingAs($this->actor)->withHeader('Accept', 'application/json');
    }

    protected function tearDown(): void
    {
        try {
            foreach ([$this->actor ?? null, $this->other ?? null] as $user) {
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

    private function items(bool $inApp = true, bool $email = false): array
    {
        return array_map(fn (string $category): array => ['category' => $category, 'in_app' => $inApp, 'email' => $email],
            ['calendar', 'tasks', 'birthdays', 'deadlines', 'memories']);
    }

    public function test_defaults_preserve_current_in_app_behavior_without_opting_into_email_or_new_sources(): void
    {
        $result = $this->getJson($this->url)->assertOk()->assertHeader('ETag', '"0"')->assertJsonPath('version', 0)->assertJsonCount(5, 'items')->json();
        $this->assertSame([true, false, true, true, false], array_column($result['items'], 'in_app'));
        $this->assertSame([false, false, false, false, false], array_column($result['items'], 'email'));
        $this->assertFalse(app(NotificationPreferences::class)->configured($this->actor->id));
    }

    public function test_save_preserves_independent_channels_and_is_personal_to_the_authenticated_account(): void
    {
        $items = $this->items(false, true);
        $items[1] = ['category' => 'tasks', 'in_app' => true, 'email' => false];
        $this->withHeader('If-Match', '"0"')->putJson($this->url, ['items' => $items])->assertOk()->assertHeader('ETag', '"1"')->assertJsonPath('version', 1)->assertJsonPath('items', $items);
        $this->getJson($this->url)->assertJsonPath('items', $items);
        $this->assertTrue(app(NotificationPreferences::class)->configured($this->actor->id));
        $this->assertSame(['in_app' => true, 'email' => false], app(NotificationPreferences::class)->forUser($this->actor->id)['tasks']);
        $this->actingAs($this->other)->getJson($this->url)->assertJsonPath('version', 0)->assertJsonPath('items.0.email', false);
    }

    public function test_identical_save_and_different_category_order_do_not_increment_version(): void
    {
        $items = $this->items();
        $this->withHeader('If-Match', '"0"')->putJson($this->url, ['items' => $items])->assertOk()->assertJsonPath('version', 1);
        $this->withHeader('If-Match', '"1"')->putJson($this->url, ['items' => array_reverse($items)])->assertOk()->assertJsonPath('version', 1)->assertJsonPath('items', $items);
    }

    public function test_missing_or_stale_version_cannot_overwrite_saved_settings(): void
    {
        $this->putJson($this->url, ['items' => $this->items()])->assertStatus(428);
        $this->withHeader('If-Match', '"0"')->putJson($this->url, ['items' => $this->items()])->assertOk();
        $this->putJson($this->url, ['items' => $this->items(false, true)])->assertStatus(412);
        $this->getJson($this->url)->assertJsonPath('version', 1)->assertJsonPath('items', $this->items());
    }

    public function test_every_category_must_be_known_unique_complete_and_boolean(): void
    {
        $valid = $this->items();
        $duplicate = $valid;
        $duplicate[1]['category'] = 'calendar';
        $unknown = $valid;
        $unknown[1]['category'] = 'admin';
        $badBoolean = $valid;
        $badBoolean[0]['email'] = 'yes';
        $extra = $valid;
        $extra[0]['recipient'] = $this->other->id;
        foreach ([$duplicate, $unknown, $badBoolean, $extra, array_slice($valid, 0, 4)] as $items) {
            $this->withHeader('If-Match', '"0"')->putJson($this->url, ['items' => $items])->assertUnprocessable();
        }
        $this->putJson($this->url, ['items' => $valid, 'user_id' => $this->other->id])->assertUnprocessable();
        $this->getJson($this->url)->assertJsonPath('version', 0);
    }

    public function test_categories_match_real_sources_and_sender_uses_the_configured_amora_mailer(): void
    {
        $preferences = app(NotificationPreferences::class);
        foreach ([['birthday', 'birthdays'], ['task', 'tasks'], ['vehicle_entry', 'deadlines'], ['pet_entry', 'deadlines'], ['document_expiry', 'deadlines'], ['memory_anniversary', 'memories'], [null, 'calendar']] as [$source, $category]) {
            $this->assertSame($category, $preferences->category($source));
        }
        $this->assertInstanceOf(SmtpReminderEmailSender::class, app(ReminderEmailSender::class));
        $mailer = Mockery::mock();
        $message = Mockery::mock();
        $message->shouldReceive('to')->once()->with('recipient@example.test')->andReturnSelf();
        $message->shouldReceive('subject')->once()->with('Reminder Amora')->andReturnSelf();
        $mailer->shouldReceive('raw')->once()->withArgs(function (string $body, $callback) use ($message): bool {
            $this->assertSame('Text de test fără SMTP', $body);
            $callback($message);

            return true;
        });
        Mail::shouldReceive('mailer')->once()->with('amora')->andReturn($mailer);
        app(ReminderEmailSender::class)->send('recipient@example.test', 'Reminder Amora', 'Text de test fără SMTP');
    }

    public function test_unauthenticated_reads_and_writes_are_rejected(): void
    {
        $this->app['auth']->forgetGuards();
        $this->getJson($this->url)->assertUnauthorized();
        $this->putJson($this->url, ['items' => $this->items()])->assertUnauthorized();
    }
}
