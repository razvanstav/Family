<?php

namespace App\Platform\Media;

use Illuminate\Cache\Repository;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Cache;
use Throwable;

class UploadBudget
{
    /** @param callable():void $write */
    public function write(FilesystemAdapter $disk, int $bytes, callable $write): void
    {
        try {
            $this->cache()->lock('media-upload-write:v1', 30)->block(3, function () use ($disk, $bytes, $write): void {
                $this->ensureDirectory($disk);
                abort_unless($this->hasSpace($disk, $bytes), 503, 'Încărcările sunt oprite temporar deoarece spațiul disponibil este insuficient. Reîncearcă mai târziu.');
                $budget = $this->budget();
                $retryAfter = max(1, $budget['expires_at'] - now()->getTimestamp());
                $minutes = (int) ceil($retryAfter / 60);
                $delay = $minutes === 1 ? 'un minut' : $minutes.' minute';
                abort_unless($this->fits($budget, $bytes), 429, 'Ai atins limita de încărcare pentru această conexiune. Reîncearcă peste '.$delay.'.', ['Retry-After' => (string) $retryAfter]);
                $write();
                $this->charge($budget, $bytes);
            });
        } catch (LockTimeoutException) {
            abort(503, 'O altă încărcare este în curs. Reîncearcă în câteva secunde.');
        }
    }

    /** A failed growth reservation rejects the normalized file, including a manual retry. */
    public function accountGrowth(FilesystemAdapter $disk, int $bytes): bool
    {
        if ($bytes <= 0) {
            return true;
        }
        try {
            return $this->cache()->lock('media-upload-write:v1', 30)->block(3, function () use ($disk, $bytes): bool {
                $budget = $this->budget();
                if (! $this->hasSpace($disk, 0) || ! $this->fits($budget, $bytes)) {
                    return false;
                }
                $this->charge($budget, $bytes);

                return true;
            });
        } catch (Throwable $error) {
            report($error);

            return false;
        }
    }

    protected function availableBytes(string $path): float|false
    {
        return @disk_free_space($path);
    }

    private function ensureDirectory(FilesystemAdapter $disk): void
    {
        if (! is_dir($disk->path(''))) {
            $disk->makeDirectory('');
        }
        abort_unless(is_dir($disk->path('')), 503, 'Spațiul pentru încărcare nu este disponibil. Reîncearcă mai târziu.');
    }

    private function hasSpace(FilesystemAdapter $disk, int $bytes): bool
    {
        $available = $this->availableBytes($disk->path(''));

        return $available !== false && $available >= max(0, (int) config('memoria.media_min_free_bytes')) + $bytes;
    }

    /** @return array{bytes:int,expires_at:int} */
    private function budget(): array
    {
        $now = now()->getTimestamp();
        $budget = $this->cache()->get($this->key());
        if (! is_array($budget) || ! isset($budget['bytes'], $budget['expires_at']) || $budget['expires_at'] <= $now) {
            return ['bytes' => 0, 'expires_at' => $now + 86400];
        }

        return ['bytes' => (int) $budget['bytes'], 'expires_at' => (int) $budget['expires_at']];
    }

    /** @param array{bytes:int,expires_at:int} $budget */
    private function fits(array $budget, int $bytes): bool
    {
        return max(0, (int) config('memoria.upload_daily_ip_bytes')) >= $budget['bytes'] + $bytes;
    }

    /** @param array{bytes:int,expires_at:int} $budget */
    private function charge(array $budget, int $bytes): void
    {
        // Count writes even if a later scan or transaction rejects them, so retries cannot reset the abuse budget.
        $budget['bytes'] += $bytes;
        abort_unless($this->cache()->put($this->key(), $budget, max(1, $budget['expires_at'] - now()->getTimestamp())), 503, 'Limita de încărcare nu poate fi verificată. Reîncearcă mai târziu.');
    }

    private function cache(): Repository
    {
        return Cache::store((string) config('memoria.media_budget_cache_store'));
    }

    private function key(): string
    {
        return 'media-upload-ip:v1:'.hash_hmac('sha256', request()->ip() ?? 'unknown', (string) config('app.key'));
    }
}
