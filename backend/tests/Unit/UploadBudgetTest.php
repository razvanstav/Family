<?php

namespace Tests\Unit;

use App\Models\User;
use App\Platform\Media\UploadBudget;
use Illuminate\Cache\Repository;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

final class UploadBudgetTest extends TestCase
{
    private FilesystemAdapter $disk;

    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array', 'memoria.media_budget_cache_store' => 'array', 'memoria.upload_daily_ip_bytes' => 100, 'memoria.media_min_free_bytes' => 40]);
        Cache::store('array')->flush();
        $this->disk = Storage::fake('local');
        $this->freezeTime();
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        parent::tearDown();
    }

    private function budget(string $ip = '198.51.100.100', int $user = 1): UploadBudget
    {
        $request = Request::create('/api/v1/families/fixture/documents', 'POST', server: ['REMOTE_ADDR' => $ip]);
        $request->setUserResolver(fn () => (new User)->forceFill(['id' => $user]));
        $this->app->instance('request', $request);

        return new class extends UploadBudget
        {
            public float|false $space = 1000000;

            protected function availableBytes(string $path): float|false
            {
                return $this->space;
            }
        };
    }

    private function write(UploadBudget $budget, string $path, int $bytes): void
    {
        $budget->write($this->disk, $bytes, fn () => $this->disk->put($path, str_repeat('a', $bytes)));
    }

    private function blocked(callable $operation, int $status): void
    {
        try {
            $operation();
            $this->fail('Expected the storage protection to refuse the operation.');
        } catch (HttpException $error) {
            $this->assertSame($status, $error->getStatusCode());
        }
    }

    public function test_daily_budget_spans_new_accounts_and_families_on_the_same_ip(): void
    {
        $this->write($this->budget(user: 1), 'family-a/photo', 60);
        $this->write($this->budget(user: 2), 'family-b/photo', 40);
        $this->blocked(fn () => $this->write($this->budget(user: 3), 'family-c/photo', 1), 429);
        $this->disk->assertExists(['family-a/photo', 'family-b/photo']);
        $this->disk->assertMissing('family-c/photo');
    }

    public function test_other_networks_have_an_independent_budget(): void
    {
        $this->write($this->budget(), 'first/photo', 100);
        $this->write($this->budget('198.51.100.101'), 'second/photo', 100);
        $this->disk->assertExists(['first/photo', 'second/photo']);
    }

    public function test_budget_expires_from_first_write_instead_of_extending_on_every_upload(): void
    {
        $this->write($this->budget(), 'first/photo', 60);
        $this->travel(23)->hours();
        $this->write($this->budget(), 'second/photo', 40);
        $this->blocked(fn () => $this->write($this->budget(), 'blocked/photo', 1), 429);
        $this->travel(3601)->seconds();
        $this->write($this->budget(), 'new-window/photo', 100);
        $this->disk->assertExists('new-window/photo');
    }

    public function test_failed_write_does_not_consume_the_budget(): void
    {
        $budget = $this->budget();
        try {
            $budget->write($this->disk, 100, function (): void {
                throw new RuntimeException('Fixture write failed.');
            });
            $this->fail('The write failure must propagate.');
        } catch (RuntimeException $error) {
            $this->assertSame('Fixture write failed.', $error->getMessage());
        }
        $this->write($this->budget(), 'retry/photo', 100);
        $this->disk->assertExists('retry/photo');
    }

    public function test_free_space_reserve_includes_the_incoming_file_and_refuses_before_write(): void
    {
        $budget = $this->budget();
        $budget->space = 50;
        $this->blocked(fn () => $this->write($budget, 'blocked/photo', 11), 503);
        $this->disk->assertMissing('blocked/photo');
        $this->write($budget, 'boundary/photo', 10);
        $this->disk->assertExists('boundary/photo');
    }

    public function test_unknown_free_space_fails_closed_without_consuming_budget(): void
    {
        $budget = $this->budget();
        $budget->space = false;
        $this->blocked(fn () => $this->write($budget, 'blocked/photo', 100), 503);
        $this->disk->assertMissing('blocked/photo');
        $this->write($this->budget(), 'retry/photo', 100);
        $this->disk->assertExists('retry/photo');
    }

    public function test_lock_contention_fails_closed_before_the_storage_callback(): void
    {
        $lock = Mockery::mock(Lock::class);
        $lock->shouldReceive('block')->once()->andThrow(new LockTimeoutException);
        $cache = Mockery::mock(Repository::class);
        $cache->shouldReceive('lock')->once()->andReturn($lock);
        Cache::shouldReceive('store')->with('array')->once()->andReturn($cache);
        $this->blocked(fn () => $this->write($this->budget(), 'blocked/photo', 1), 503);
        $this->disk->assertMissing('blocked/photo');
    }

    public function test_normalized_image_growth_is_charged_and_cannot_exceed_budget_or_reserve(): void
    {
        $budget = $this->budget();
        $this->write($budget, 'image/photo', 60);
        $this->assertTrue($budget->accountGrowth($this->disk, 40));
        $this->assertFalse($budget->accountGrowth($this->disk, 1));
        $other = $this->budget('198.51.100.102');
        $other->space = 39;
        $this->assertFalse($other->accountGrowth($this->disk, 1));
        $this->write($this->budget('198.51.100.102'), 'retry/photo', 100);
        $this->disk->assertExists('retry/photo');
    }
}
