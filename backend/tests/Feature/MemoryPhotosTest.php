<?php

namespace Tests\Feature;

use App\Models\User;
use App\Platform\Media\Scanner;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

final class MemoryPhotosTest extends TestCase
{
    private User $owner;

    private User $reader;

    private string $family;

    private string $readerMember;

    private string $base;

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
        $this->owner = User::factory()->make();
        $this->owner->setConnection('admin')->save();
        $this->reader = User::factory()->make();
        $this->reader->setConnection('admin')->save();
        $this->family = (string) Str::uuid();
        $this->readerMember = (string) Str::uuid();
        DB::connection('admin')->table('families')->insert(['id' => $this->family, 'name' => 'Familia test']);
        DB::connection('admin')->table('family_memberships')->insert([
            ['id' => (string) Str::uuid(), 'family_id' => $this->family, 'user_id' => $this->owner->id, 'role' => 'owner'],
            ['id' => $this->readerMember, 'family_id' => $this->family, 'user_id' => $this->reader->id, 'role' => 'adult'],
        ]);
        $this->base = '/api/v1/families/'.$this->family.'/moments';
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
        DB::disableQueryLog();
        try {
            if (isset($this->family)) {
                foreach (['outbox_deliveries', 'outbox_events', 'audit_events', 'memory_moments', 'media_assets', 'family_memberships'] as $table) {
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

    private function content(array $changes = []): array
    {
        return array_replace(['kind' => 'moment', 'title' => 'Vacanță', 'body' => 'La munte', 'occurred_on' => '2026-10-24',
            'visibility' => 'family', 'photos_mode' => 'edit'], $changes);
    }

    private function image(string $name = 'photo.png'): UploadedFile
    {
        return UploadedFile::fake()->image($name, 80 + crc32($name) % 40, 60);
    }

    private function create(array $changes = []): array
    {
        return $this->post($this->base, $this->content($changes + ['photos' => [$this->image('first.png'), $this->image('second.png'), $this->image('third.png')]]))
            ->assertCreated()->json();
    }

    private function update(array $moment, array $changes = []): TestResponse
    {
        return $this->withHeader('If-Match', '"'.$moment['version'].'"')->post($this->base.'/'.$moment['id'],
            $this->content($changes + ['_method' => 'PATCH', 'photo_ids' => array_column($moment['photos'], 'id')]));
    }

    private function photoUrl(array $moment, int $index = 0): string
    {
        return $this->base.'/'.$moment['id'].'/photos/'.$moment['photos'][$index]['id'];
    }

    public function test_multiple_photos_have_stable_order_private_bytes_and_a_compatible_cover(): void
    {
        $moment = $this->create();
        $this->assertCount(3, $moment['photos']);
        $this->assertSame(['ready', 'ready', 'ready'], array_column($moment['photos'], 'status'));
        $this->assertArrayNotHasKey('media_asset_id', $moment['photos'][0]);
        $this->assertArrayNotHasKey('photo_asset_id', $moment);
        $this->getJson($this->base)->assertOk()->assertJsonPath('items.0.photos', $moment['photos']);
        $this->getJson($this->base.'/'.$moment['id'])->assertOk()->assertJsonPath('photos', $moment['photos']);
        foreach (array_keys($moment['photos']) as $index) {
            $this->get($this->photoUrl($moment, $index))->assertOk()->assertHeader('Content-Type', 'image/png')->assertHeader('Cache-Control', 'max-age=0, no-store, private');
        }
        $this->assertSame($this->get($this->photoUrl($moment))->streamedContent(), $this->get($this->base.'/'.$moment['id'].'/photo')->streamedContent());
        $this->assertSame(3, DB::connection('admin')->table('memory_moment_photos')->where('family_id', $this->family)->count());
    }

    public function test_append_remove_promotes_cover_and_old_links_stop_working(): void
    {
        $moment = $this->create();
        $retained = [$moment['photos'][2]['id'], $moment['photos'][1]['id']];
        $updated = $this->update($moment, ['photo_ids' => [7 => $retained[0], 11 => $retained[1]], 'photos' => [$this->image('new.png')]])
            ->assertOk()->assertJsonPath('version', 2)->json();
        $this->assertSame($retained, array_slice(array_column($updated['photos'], 'id'), 0, 2));
        $this->assertCount(3, $updated['photos']);
        $this->get($this->photoUrl($moment))->assertNotFound();
        $this->assertSame($this->get($this->photoUrl($updated))->streamedContent(), $this->get($this->base.'/'.$moment['id'].'/photo')->streamedContent());
        $this->update($updated, ['photo_ids' => []])->assertOk()->assertJsonCount(0, 'photos')->assertJsonPath('photo_status', null);
        $this->get($this->base.'/'.$moment['id'].'/photo')->assertNotFound();
        $this->get($this->photoUrl($updated, 2))->assertNotFound();
        $this->assertCount(4, Storage::disk('local')->allFiles($this->family));
    }

    public function test_legacy_clients_keep_the_whole_gallery_and_can_replace_it(): void
    {
        $moment = $this->create();
        $legacy = $this->content();
        unset($legacy['photos_mode']);
        $url = $this->base.'/'.$moment['id'];
        $this->withHeader('If-Match', '"1"')->patchJson($url, $legacy)->assertOk()->assertJsonPath('photos', $moment['photos']);
        $replaced = $this->withHeader('If-Match', '"2"')->post($url, $legacy + ['_method' => 'PATCH', 'photo_action' => 'replace', 'photo' => $this->image()])
            ->assertOk()->assertJsonCount(1, 'photos')->json();
        $this->get($this->photoUrl($moment, 1))->assertNotFound();
        $this->get($this->photoUrl($replaced))->assertOk();
    }

    public function test_access_changes_revocation_and_archiving_apply_to_every_photo(): void
    {
        $moment = $this->create(['visibility' => 'selected', 'grant_membership_ids' => [$this->readerMember]]);
        $this->actingAs($this->reader);
        foreach (array_keys($moment['photos']) as $index) {
            $this->get($this->photoUrl($moment, $index))->assertOk();
        }
        $this->getJson($this->base)->assertJsonPath('items.0.grants', [])->assertJsonPath('items.0.can_edit', false);
        $this->update($moment)->assertForbidden();
        $this->actingAs($this->owner);
        $private = $this->update($moment, ['visibility' => 'private'])->assertOk()->json();
        $this->actingAs($this->reader);
        foreach (array_keys($moment['photos']) as $index) {
            $this->get($this->photoUrl($moment, $index))->assertNotFound();
        }
        $this->actingAs($this->owner);
        $shared = $this->update($private)->assertOk()->json();
        $archived = $this->withHeader('If-Match', '"'.$shared['version'].'"')->postJson($this->base.'/'.$moment['id'].'/archive')->assertOk()->assertJsonCount(3, 'photos')->json();
        $this->actingAs($this->reader)->get($this->photoUrl($moment, 2))->assertNotFound();
        $this->actingAs($this->owner)->get($this->photoUrl($moment, 2))->assertOk();
        $this->withHeader('If-Match', '"'.$archived['version'].'"')->postJson($this->base.'/'.$moment['id'].'/restore')->assertOk()->assertJsonPath('visibility', 'private')->assertJsonCount(3, 'photos');
        DB::connection('admin')->table('family_memberships')->where('id', $this->readerMember)->update(['revoked_at' => now()]);
        $this->actingAs($this->reader)->get($this->photoUrl($moment, 1))->assertNotFound();
    }

    public function test_photo_ids_cannot_cross_moments_or_families_or_be_duplicated(): void
    {
        $moment = $this->create();
        $other = $this->create();
        $this->update($moment, ['photo_ids' => [$other['photos'][0]['id']]])->assertUnprocessable();
        $this->get($this->base.'/'.$moment['id'].'/photos/'.$other['photos'][0]['id'])->assertNotFound();
        $this->get('/api/v1/families/'.Str::uuid().'/moments/'.$moment['id'].'/photos/'.$moment['photos'][0]['id'])->assertNotFound();
        $this->update($moment, ['photo_ids' => [$moment['photos'][0]['id'], $moment['photos'][0]['id']]])->assertUnprocessable();
        $this->getJson($this->base.'/'.$moment['id'])->assertJsonPath('version', 1)->assertJsonPath('photos', $moment['photos']);
    }

    public function test_limited_members_can_browse_shared_photos_but_cannot_edit_or_scan(): void
    {
        $moment = $this->create();
        DB::connection('admin')->table('family_memberships')->where('id', $this->readerMember)->update(['role' => 'limited']);
        $this->actingAs($this->reader);
        $this->getJson($this->base)->assertOk()->assertJsonCount(3, 'items.0.photos')->assertJsonPath('items.0.can_manage', false);
        $this->get($this->photoUrl($moment, 2))->assertOk();
        $this->update($moment, ['photo_ids' => []])->assertForbidden();
        $this->postJson($this->photoUrl($moment, 2).'/scan')->assertForbidden();
        $this->post($this->base, $this->content(['photos' => [$this->image()]]))->assertForbidden();
    }

    public function test_scan_and_retry_are_per_photo_and_require_the_author_and_version(): void
    {
        $this->scanner->result = 'unavailable';
        $moment = $this->create();
        $this->getJson($this->photoUrl($moment, 1))->assertConflict();
        $this->actingAs($this->reader)->withHeader('If-Match', '"1"')->postJson($this->photoUrl($moment, 1).'/scan')->assertForbidden();
        $this->actingAs($this->owner)->withHeader('If-Match', '"0"')->postJson($this->photoUrl($moment, 1).'/scan')->assertStatus(412);
        $this->scanner->result = 'clean';
        $this->withHeader('If-Match', '"1"')->postJson($this->photoUrl($moment, 1).'/scan')->assertOk()->assertJsonPath('version', 1)
            ->assertJsonPath('photos.0.status', 'quarantine')->assertJsonPath('photos.1.status', 'ready');
        $this->get($this->photoUrl($moment, 1))->assertOk();
        $this->scanner->result = 'infected';
        $this->postJson($this->photoUrl($moment, 2).'/scan')->assertOk()->assertJsonPath('photos.2.status', 'rejected');
        $this->getJson($this->photoUrl($moment, 2))->assertConflict();
    }

    public function test_stale_updates_do_not_upload_or_detach_any_photos(): void
    {
        $moment = $this->create();
        $saved = $this->update($moment, ['title' => 'Titlu nou'])->assertOk()->json();
        $this->update($moment, ['photo_ids' => [], 'photos' => [$this->image()]])->assertStatus(412);
        $this->assertSame(3, DB::connection('admin')->table('media_assets')->where('family_id', $this->family)->count());
        $this->getJson($this->base.'/'.$moment['id'])->assertJsonPath('photos', $saved['photos'])->assertJsonPath('version', 2);
    }

    public function test_invalid_second_upload_rolls_back_metadata_and_files_in_create_and_update(): void
    {
        $invalid = fn () => UploadedFile::fake()->createWithContent('wrong.png', 'not an image');
        $this->post($this->base, $this->content(['photos' => [$this->image(), $invalid()]]))->assertUnprocessable();
        $this->assertSame(0, DB::connection('admin')->table('memory_moments')->where('family_id', $this->family)->count());
        $this->assertSame(0, DB::connection('admin')->table('media_assets')->where('family_id', $this->family)->count());
        $this->assertSame([], Storage::disk('local')->allFiles($this->family));
        $moment = $this->create();
        $this->update($moment, ['photo_ids' => [], 'photos' => [$this->image(), $invalid()]])->assertUnprocessable();
        $this->getJson($this->base.'/'.$moment['id'])->assertJsonPath('version', 1)->assertJsonPath('photos', $moment['photos']);
        $this->assertCount(3, Storage::disk('local')->allFiles($this->family));
    }

    public function test_count_batch_size_and_family_quota_prevent_partial_saves(): void
    {
        $files = array_map(fn () => $this->image(), range(1, 21));
        $this->post($this->base, $this->content(['photos' => $files]))->assertUnprocessable();
        $large = UploadedFile::fake()->image('large.png')->size(6000);
        $this->post($this->base, $this->content(['photos' => [$large, $large]]))->assertUnprocessable();
        $this->assertSame(0, DB::connection('admin')->table('media_assets')->where('family_id', $this->family)->count());
        $moment = $this->create();
        $this->update($moment, ['photos' => array_slice($files, 0, 18)])->assertUnprocessable();
        config(['memoria.family_quota_bytes' => 1]);
        $this->update($moment, ['photos' => [$this->image()]])->assertUnprocessable();
        $this->getJson($this->base.'/'.$moment['id'])->assertJsonPath('version', 1)->assertJsonPath('photos', $moment['photos']);
    }

    public function test_gallery_payload_uses_constant_queries_and_enforces_tenant_rls(): void
    {
        $this->create();
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->getJson($this->base)->assertJsonCount(3, 'items.0.photos');
        $small = count(DB::getQueryLog());
        DB::disableQueryLog();
        for ($index = 0; $index < 12; $index++) {
            $this->create();
        }
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->getJson($this->base)->assertOk()->assertJsonCount(12, 'items')->assertJsonPath('has_more', true);
        $this->assertSame($small, count(DB::getQueryLog()));
        DB::disableQueryLog();
        $this->assertSame(0, DB::table('memory_moment_photos')->count());
        $policy = DB::connection('admin')->selectOne("SELECT relrowsecurity, relforcerowsecurity FROM pg_class WHERE relname = 'memory_moment_photos'");
        $this->assertTrue($policy->relrowsecurity);
        $this->assertTrue($policy->relforcerowsecurity);
    }
}
