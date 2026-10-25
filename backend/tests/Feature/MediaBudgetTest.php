<?php

namespace Tests\Feature;

use App\Models\User;
use App\Platform\Media\Scanner;
use App\Platform\Media\UploadBudget;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

final class MediaBudgetTest extends TestCase
{
    private array $families = [];

    private array $users = [];

    private object $scanner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('memoria_test', config('database.connections.pgsql.database'));
        $admin = config('database.connections.pgsql');
        $admin['username'] = 'memoria_admin';
        $admin['password'] = env('DB_ADMIN_PASSWORD');
        config(['database.connections.admin' => $admin, 'cache.default' => 'array', 'cache.limiter' => 'array',
            'memoria.media_budget_cache_store' => 'array', 'memoria.media_min_free_bytes' => 0]);
        Cache::store('array')->flush();
        Storage::fake('local');
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.110']);
        $this->withHeader('Accept', 'application/json');
        $this->freezeTime();
        $this->scanner = new class implements Scanner
        {
            public string $result = 'unavailable';

            public function scan(string $path): string
            {
                return $this->result;
            }
        };
        $this->app->instance(Scanner::class, $this->scanner);
        for ($index = 0; $index < 2; $index++) {
            $family = (string) Str::uuid();
            DB::connection('admin')->table('families')->insert(['id' => $family, 'name' => 'SR2 media fixture']);
            $this->families[] = $family;
            $user = User::factory()->make(['email' => 'media-budget-'.$family.'@example.test']);
            $user->setConnection('admin')->save();
            $this->users[] = $user;
            DB::connection('admin')->table('family_memberships')->insert(['id' => (string) Str::uuid(),
                'family_id' => $family, 'user_id' => $user->id, 'role' => 'owner']);
        }
    }

    protected function tearDown(): void
    {
        try {
            foreach ($this->families as $family) {
                foreach (['calendar_items', 'calendar_reminder_syncs', 'audit_events', 'documents', 'media_assets', 'member_profiles', 'family_memberships'] as $table) {
                    DB::connection('admin')->table($table)->where('family_id', $family)->delete();
                }
                DB::connection('admin')->table('families')->where('id', $family)->delete();
            }
            DB::connection('admin')->table('users')->whereIn('id', array_map(fn (User $user): int => $user->id, $this->users))->delete();
        } finally {
            $this->travelBack();
            DB::disconnect('pgsql');
            DB::disconnect('admin');
            parent::tearDown();
        }
    }

    private function file(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('fixture.pdf', "%PDF-1.4\nSR2 isolated test document\n%%EOF\n");
    }

    private function endpoint(int $index): string
    {
        return '/api/v1/families/'.$this->families[$index].'/documents';
    }

    private function upload(int $index, ?UploadedFile $file = null)
    {
        return $this->actingAs($this->users[$index])->post($this->endpoint($index), [
            'title' => 'SR2 document', 'category' => 'personal', 'file' => $file ?? $this->file(),
        ]);
    }

    public function test_changing_account_and_family_does_not_reset_ip_quota_even_with_unavailable_scanner(): void
    {
        config(['memoria.upload_daily_ip_bytes' => $this->file()->getSize()]);
        $this->upload(0)->assertCreated()->assertJsonPath('status', 'quarantine');
        $this->upload(1)->assertStatus(429);
        $this->assertSame(1, DB::connection('admin')->table('media_assets')->whereIn('family_id', $this->families)->count());
        $this->assertCount(1, Storage::disk('local')->allFiles());
        $this->actingAs($this->users[1])->postJson($this->endpoint(1), ['title' => 'Metadate', 'category' => 'personal'])
            ->assertCreated()->assertJsonPath('status', null);
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.111']);
        $this->upload(1)->assertCreated()->assertJsonPath('status', 'quarantine');
    }

    public function test_low_disk_space_refuses_file_before_persistence_but_keeps_metadata_available(): void
    {
        $this->app->bind(UploadBudget::class, fn () => new class extends UploadBudget
        {
            protected function availableBytes(string $path): float|false
            {
                return 0;
            }
        });
        $this->upload(0)->assertStatus(503);
        $this->assertSame(0, DB::connection('admin')->table('media_assets')->whereIn('family_id', $this->families)->count());
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->actingAs($this->users[0])->postJson($this->endpoint(0), ['title' => 'Metadate', 'category' => 'personal'])->assertCreated();
    }

    public function test_transaction_rollback_removes_file_without_resetting_committed_abuse_budget(): void
    {
        config(['cache.default' => 'database', 'memoria.upload_daily_ip_bytes' => $this->file()->getSize()]);
        DB::beginTransaction();
        try {
            $this->upload(0)->assertCreated();
        } finally {
            DB::rollBack();
        }
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertSame(0, DB::connection('admin')->table('media_assets')->whereIn('family_id', $this->families)->count());
        $this->upload(1)->assertStatus(429);
    }

    public function test_manual_scan_rejects_and_removes_a_file_when_normalization_growth_is_not_allowed(): void
    {
        $response = $this->upload(0, UploadedFile::fake()->image('fixture.png', 30, 30))->assertCreated()->assertJsonPath('status', 'quarantine');
        $document = $response->json('id');
        $this->app->bind(UploadBudget::class, fn () => new class extends UploadBudget
        {
            public function accountGrowth(FilesystemAdapter $disk, int $bytes): bool
            {
                return false;
            }
        });
        $this->scanner->result = 'clean';
        $this->actingAs($this->users[0])->postJson($this->endpoint(0).'/'.$document.'/scan')->assertOk()->assertJsonPath('status', 'rejected');
        $asset = DB::connection('admin')->table('media_assets')->where('family_id', $this->families[0])->firstOrFail();
        $this->assertSame('rejected', $asset->status);
        $this->assertSame(0, $asset->bytes);
        Storage::disk('local')->assertMissing($asset->storage_key);

    }
}
