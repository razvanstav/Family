<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class SubscriptionTest extends TestCase
{
    private string $family;

    private string $otherFamily;

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
        $this->family = (string) Str::uuid();
        $this->otherFamily = (string) Str::uuid();
        foreach ([$this->family, $this->otherFamily] as $family) {
            DB::connection('admin')->table('families')->insert(['id' => $family, 'name' => 'SB1 subscription fixture']);
        }
        foreach (['owner', 'reader', 'friend', 'outsider'] as $name) {
            $user = User::factory()->make(['name' => 'SB1 '.$name, 'email' => 'sb1-'.$this->family.'-'.$name.'@example.test']);
            $user->setConnection('admin')->save();
            $this->users[$name] = $user;
            $this->members[$name] = (string) Str::uuid();
            DB::connection('admin')->table('family_memberships')->insert(['id' => $this->members[$name],
                'family_id' => in_array($name, ['owner', 'reader'], true) ? $this->family : $this->otherFamily,
                'user_id' => $user->id, 'role' => $name === 'owner' ? 'owner' : 'adult']);
        }
        $this->actingAs($this->users['owner']);
        $this->travelTo(CarbonImmutable::parse('2026-10-24T10:00:00Z'));
    }

    protected function tearDown(): void
    {
        try {
            DB::disableQueryLog();
            foreach ([$this->family, $this->otherFamily] as $family) {
                foreach (['subscription_shares', 'subscriptions', 'calendar_items', 'calendar_reminder_syncs', 'audit_events', 'member_profiles', 'family_memberships'] as $table) {
                    DB::connection('admin')->table($table)->where('family_id', $family)->delete();
                }
                DB::connection('admin')->table('families')->where('id', $family)->delete();
            }
            DB::connection('admin')->table('users')->whereIn('id', array_map(fn (User $user) => $user->id, $this->users))->delete();
        } finally {
            $this->travelBack();
            DB::disconnect('pgsql');
            DB::disconnect('admin');
            parent::tearDown();
        }
    }

    private function base(?string $family = null): string
    {
        return '/api/v1/families/'.($family ?? $this->family).'/subscriptions';
    }

    private function content(array $changes = []): array
    {
        return array_replace(['service_key' => 'netflix', 'service_name' => 'Netflix', 'website_url' => 'https://www.netflix.com/',
            'amount' => '49,99', 'currency' => 'RON', 'recurrence' => 'monthly', 'next_due_on' => '2026-10-30',
            'note' => 'Abonament de test', 'secrets_action' => 'replace', 'login' => 'subscription-login@example.test',
            'password' => '  synthetic-subscription-password  ', 'pin' => '6284'], $changes);
    }

    private function create(array $changes = []): array
    {
        return $this->postJson($this->base(), $this->content($changes))->assertCreated()->json();
    }

    private function match(array $subscription): self
    {
        return $this->withHeader('If-Match', '"'.$subscription['version'].'"');
    }

    private function share(array $subscription): array
    {
        return $this->match($subscription)->postJson($this->base().'/'.$subscription['id'].'/shares',
            ['email' => strtoupper($this->users['friend']->email)])->assertOk()->json();
    }

    private function accept(array $shared): array
    {
        $this->actingAs($this->users['friend']);

        return $this->match($shared['subscription'])->postJson($this->base($this->otherFamily).'/'.$shared['subscription']['id'].'/accept',
            ['token' => $shared['invitation_code']])->assertOk()->json();
    }

    private function stored(array $subscription): object
    {
        return DB::connection('admin')->table('subscriptions')->where('id', $subscription['id'])->sole();
    }

    public function test_credentials_are_encrypted_and_never_in_metadata_audit_or_outbox_and_keep_remove_work(): void
    {
        $subscription = $this->create();
        $this->assertTrue($subscription['has_credentials']);
        $this->assertSame(4999, $subscription['amount_minor']);
        foreach (['password', 'login', 'pin', 'credentials_ciphertext'] as $field) {
            $this->assertArrayNotHasKey($field, $subscription);
        }
        $cipher = $this->stored($subscription)->credentials_ciphertext;
        $this->assertStringNotContainsString('synthetic-subscription-password', $cipher);
        $plain = json_decode(Crypt::decryptString($cipher), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('  synthetic-subscription-password  ', $plain['password']);
        $this->getJson($this->base().'/'.$subscription['id'].'/credentials')->assertOk()
            ->assertJsonPath('password', $plain['password'])->assertHeader('Cache-Control', 'max-age=0, no-store, private');
        $payload = $this->content(['secrets_action' => 'keep', 'amount' => '55']);
        unset($payload['login'], $payload['password'], $payload['pin']);
        $edited = $this->match($subscription)->patchJson($this->base().'/'.$subscription['id'], $payload)->assertOk()->json();
        $this->assertSame($cipher, $this->stored($subscription)->credentials_ciphertext);
        $payload['secrets_action'] = 'remove';
        $removed = $this->match($edited)->patchJson($this->base().'/'.$subscription['id'], $payload)->assertOk()->json();
        $this->assertFalse($removed['has_credentials']);
        $this->assertNull($this->stored($subscription)->credentials_ciphertext);
        $audit = DB::connection('admin')->table('audit_events')->where('family_id', $this->family)->get()->toJson();
        foreach ([$plain['login'], $plain['password'], $plain['pin']] as $secret) {
            $this->assertStringNotContainsString($secret, $audit);
        }
        $this->assertSame(0, DB::connection('admin')->table('outbox_events')->where('family_id', $this->family)->count());
    }

    public function test_family_sees_credentials_automatically_but_only_owner_can_edit_or_share_and_limited_is_readonly(): void
    {
        $subscription = $this->create();
        $this->actingAs($this->users['reader']);
        $this->getJson($this->base())->assertOk()->assertJsonPath('items.0.access', 'family')->assertJsonPath('items.0.can_edit', false);
        $this->getJson($this->base().'/'.$subscription['id'].'/credentials')->assertOk()->assertJsonPath('pin', '6284');
        $this->getJson($this->base().'/'.$subscription['id'].'/shares')->assertForbidden();
        $this->assertContains($this->match($subscription)->patchJson($this->base().'/'.$subscription['id'], $this->content())->status(), [403, 404]);
        DB::connection('admin')->table('family_memberships')->where('id', $this->members['reader'])->update(['role' => 'limited']);
        $this->postJson($this->base(), $this->content())->assertForbidden();
        $this->getJson($this->base().'/'.$subscription['id'].'/credentials')->assertOk();
        $this->actingAs($this->users['friend']);
        $this->getJson($this->base().'/'.$subscription['id'])->assertNotFound();
        $this->getJson($this->base($this->otherFamily).'/'.$subscription['id'])->assertNotFound();
    }

    public function test_invitation_requires_recipient_and_owner_delivered_code_before_any_sensitive_access(): void
    {
        $subscription = $this->create();
        $shared = $this->share($subscription);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $shared['invitation_code']);
        $share = DB::connection('admin')->table('subscription_shares')->where('subscription_id', $subscription['id'])->sole();
        $this->assertSame(hash('sha256', $shared['invitation_code']), $share->token_hash);
        $this->assertNotSame($shared['invitation_code'], $share->token_hash);
        $this->getJson($this->base().'/'.$subscription['id'].'/shares')->assertOk()->assertJsonMissing(['invitation_code' => $shared['invitation_code']]);
        $this->actingAs($this->users['friend']);
        $pending = $this->getJson($this->base($this->otherFamily).'?scope=shared')->assertOk()->json('items.0');
        $this->assertSame('invited', $pending['access']);
        foreach (['amount_minor', 'website_url', 'note', 'next_due_on', 'currency', 'recurrence'] as $field) {
            $this->assertNull($pending[$field]);
        }
        $this->assertFalse($pending['has_credentials']);
        $this->assertArrayNotHasKey('invitation_code', $pending);
        $this->getJson($this->base($this->otherFamily).'/'.$subscription['id'].'/credentials')->assertNotFound();
        $this->match($pending)->postJson($this->base($this->otherFamily).'/'.$subscription['id'].'/accept')->assertUnprocessable();
        $this->match($pending)->postJson($this->base($this->otherFamily).'/'.$subscription['id'].'/accept', ['token' => str_repeat('0', 64)])->assertForbidden();
        $this->actingAs($this->users['outsider']);
        $this->match($pending)->postJson($this->base($this->otherFamily).'/'.$subscription['id'].'/accept', ['token' => $shared['invitation_code']])->assertNotFound();
        $accepted = $this->accept($shared);
        $this->assertSame('shared', $accepted['access']);
        $this->assertFalse($accepted['can_edit']);
        $this->assertSame(3, $accepted['version']);
        $this->getJson($this->base($this->otherFamily).'/'.$subscription['id'].'/credentials')->assertOk()->assertJsonPath('login', 'subscription-login@example.test');
        $this->getJson($this->base($this->otherFamily).'/'.$subscription['id'].'/shares')->assertForbidden();
        $this->assertNull(DB::connection('admin')->table('subscription_shares')->where('id', $share->id)->value('token_hash'));
        $this->match($accepted)->postJson($this->base($this->otherFamily).'/'.$subscription['id'].'/accept', ['token' => $shared['invitation_code']])->assertStatus(409);
        $this->actingAs($this->users['outsider']);
        $this->getJson($this->base($this->otherFamily).'?scope=shared')->assertOk()->assertJsonCount(0, 'items');
        $this->getJson($this->base($this->otherFamily).'/'.$subscription['id'].'/credentials')->assertNotFound();
    }

    public function test_versions_revocation_and_reissued_codes_prevent_stale_accepts_and_secret_access(): void
    {
        $subscription = $this->create();
        $shared = $this->share($subscription);
        $accepted = $this->accept($shared);
        $this->actingAs($this->users['owner']);
        $shareId = $shared['items'][0]['id'];
        $this->match($subscription)->deleteJson($this->base().'/'.$subscription['id'].'/shares/'.$shareId)->assertStatus(412);
        $revoked = $this->match($accepted)->deleteJson($this->base().'/'.$subscription['id'].'/shares/'.$shareId)->assertOk()->json('subscription');
        $this->actingAs($this->users['friend']);
        $this->getJson($this->base($this->otherFamily).'/'.$subscription['id'].'/credentials')->assertNotFound();
        $this->match($accepted)->postJson($this->base($this->otherFamily).'/'.$subscription['id'].'/accept', ['token' => $shared['invitation_code']])->assertNotFound();
        $this->actingAs($this->users['owner']);
        $again = $this->share($revoked);
        $this->assertNotSame($shared['invitation_code'], $again['invitation_code']);
        $this->actingAs($this->users['friend']);
        $this->match($again['subscription'])->postJson($this->base($this->otherFamily).'/'.$subscription['id'].'/accept', ['token' => $shared['invitation_code']])->assertForbidden();
        $this->accept($again);
    }

    public function test_expiration_normalized_duplicates_and_decline_keep_access_closed(): void
    {
        $subscription = $this->create();
        $shared = $this->share($subscription);
        $this->match($shared['subscription'])->postJson($this->base().'/'.$subscription['id'].'/shares', ['email' => strtolower($this->users['friend']->email)])->assertUnprocessable();
        $this->match($shared['subscription'])->postJson($this->base().'/'.$subscription['id'].'/shares', ['email' => $this->users['reader']->email])->assertUnprocessable();
        $this->match($shared['subscription'])->postJson($this->base().'/'.$subscription['id'].'/shares', ['email' => 'missing-'.$this->family.'@example.test'])->assertUnprocessable();
        $this->travelTo(CarbonImmutable::parse('2026-11-01T10:00:00Z'));
        $this->actingAs($this->users['friend']);
        $this->match($shared['subscription'])->postJson($this->base($this->otherFamily).'/'.$subscription['id'].'/accept', ['token' => $shared['invitation_code']])->assertForbidden();
        $this->actingAs($this->users['owner']);
        $new = $this->share($shared['subscription']);
        $this->actingAs($this->users['friend']);
        $this->match($new['subscription'])->postJson($this->base($this->otherFamily).'/'.$subscription['id'].'/decline')->assertNoContent();
        $this->getJson($this->base($this->otherFamily).'?scope=shared')->assertOk()->assertJsonCount(0, 'items');
        $this->getJson($this->base($this->otherFamily).'/'.$subscription['id'].'/credentials')->assertNotFound();
    }

    public function test_calendar_shows_family_and_accepted_individual_dates_but_not_pending_or_other_peoples_shares(): void
    {
        $subscription = $this->create();
        $find = fn (string $family) => array_values(array_filter($this->getJson('/api/v1/families/'.$family.'/calendar-items?month=2026-10')->assertOk()->json(),
            fn ($event) => $event['source'] !== null && $event['source']['type'] === 'subscription'));
        $events = $find($this->family);
        $this->assertCount(1, $events);
        $this->assertSame('2026-10-30', $events[0]['event_date']);
        $this->assertSame('family', $events[0]['visibility']);
        $shared = $this->share($subscription);
        $this->actingAs($this->users['friend']);
        $this->assertCount(0, $find($this->otherFamily));
        $this->accept($shared);
        $events = $find($this->otherFamily);
        $this->assertCount(1, $events);
        $this->assertSame('selected', $events[0]['visibility']);
        $this->assertSame($subscription['id'], $events[0]['source']['id']);
        $this->actingAs($this->users['outsider']);
        $this->assertCount(0, $find($this->otherFamily));
        $this->assertSame(0, DB::connection('admin')->table('calendar_reminder_recipients')->where('family_id', $this->family)->count());
        $this->assertSame(0, DB::connection('admin')->table('expenses')->where('family_id', $this->family)->count());
    }

    public function test_owner_revocation_and_deletion_remove_all_access_and_deleted_credentials_are_wiped(): void
    {
        $subscription = $this->create();
        $shared = $this->share($subscription);
        $this->accept($shared);
        DB::connection('admin')->table('family_memberships')->where('id', $this->members['owner'])->update(['revoked_at' => now()]);
        $this->getJson($this->base($this->otherFamily).'/'.$subscription['id'].'/credentials')->assertNotFound();
        $this->actingAs($this->users['reader']);
        $this->getJson($this->base())->assertOk()->assertJsonCount(0, 'items');
        DB::connection('admin')->table('family_memberships')->where('id', $this->members['owner'])->update(['revoked_at' => null]);
        $this->actingAs($this->users['owner']);
        $current = $this->getJson($this->base().'/'.$subscription['id'])->assertOk()->json();
        $this->match($current)->deleteJson($this->base().'/'.$subscription['id'])->assertNoContent();
        $this->assertNull($this->stored($subscription)->credentials_ciphertext);
        $this->actingAs($this->users['friend']);
        $this->getJson($this->base($this->otherFamily).'/'.$subscription['id'].'/credentials')->assertNotFound();
    }

    public function test_invalid_amount_dates_identity_and_credential_links_are_rejected_without_echoing_secrets(): void
    {
        foreach ([['amount' => '12.999'], ['amount' => '-5'], ['next_due_on' => '2101-01-01'], ['currency' => 'XXX'],
            ['website_url' => 'javascript:alert(1)'], ['website_url' => 'https://user:password@example.test/'],
            ['owner_id' => $this->users['reader']->id], ['service_name' => '   '], ['secrets_action' => 'keep']] as $change) {
            $response = $this->postJson($this->base(), $this->content($change))->assertUnprocessable();
            $this->assertStringNotContainsString('synthetic-subscription-password', $response->getContent());
        }
        $subscription = $this->create(['amount' => null, 'next_due_on' => null, 'website_url' => null]);
        $this->assertNull($subscription['amount_minor']);
        $this->assertNull($subscription['next_due_on']);
        $this->assertNull($subscription['website_url']);
        $this->flushHeaders()->patchJson($this->base().'/'.$subscription['id'], $this->content())->assertStatus(428);
    }

    public function test_forced_rls_blocks_unrelated_read_and_recipient_metadata_or_grant_identity_writes(): void
    {
        $subscription = $this->create();
        $shared = $this->share($subscription);
        $this->accept($shared);
        DB::transaction(function () use ($subscription) {
            DB::select("SELECT set_config('app.family_id', ?, true), set_config('app.actor_id', ?, true)", [$this->otherFamily, (string) $this->users['outsider']->id]);
            $this->assertSame(0, DB::table('subscriptions')->where('id', $subscription['id'])->count());
        });
        foreach ([
            fn () => DB::table('subscriptions')->where('id', $subscription['id'])->update(['service_name' => 'Changed by recipient', 'version' => 4]),
            fn () => DB::table('subscription_shares')->where('subscription_id', $subscription['id'])->update(['recipient_id' => $this->users['outsider']->id]),
        ] as $write) {
            try {
                DB::transaction(function () use ($write) {
                    DB::select("SELECT set_config('app.family_id', ?, true), set_config('app.actor_id', ?, true)", [$this->otherFamily, (string) $this->users['friend']->id]);
                    $write();
                });
                $this->fail('RLS/trigger must reject recipient content writes.');
            } catch (QueryException $error) {
                $this->assertSame('42501', $error->getCode());
            }
        }
        $this->assertSame('Netflix', $this->stored($subscription)->service_name);
    }

    public function test_list_has_constant_query_count_and_omits_secrets_for_multiple_records(): void
    {
        $subscription = $this->create();
        DB::enableQueryLog();
        $this->getJson($this->base())->assertOk()->assertJsonCount(1, 'items');
        $single = count(DB::getQueryLog());
        DB::disableQueryLog();
        for ($i = 0; $i < 5; $i++) {
            $this->create(['service_key' => 'custom-'.$i, 'service_name' => 'Serviciu '.$i]);
        }
        DB::flushQueryLog();
        DB::enableQueryLog();
        $response = $this->getJson($this->base())->assertOk()->assertJsonCount(6, 'items');
        $many = count(DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertSame($single, $many);
        $this->assertStringNotContainsString('synthetic-subscription-password', $response->getContent());
        $this->assertStringNotContainsString($this->stored($subscription)->credentials_ciphertext, $response->getContent());
    }
}
