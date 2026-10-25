<?php

namespace App\Platform\Media;

use App\Platform\Contracts\PrivateMedia;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class LocalPrivateMedia implements PrivateMedia
{
    public function __construct(private Scanner $scanner, private UploadBudget $budget) {}

    public function receive(string $familyId, int $actorId, UploadedFile $file): array
    {
        return $this->receiveFile($familyId, $actorId, $file, false);
    }

    public function receiveImage(string $familyId, int $actorId, UploadedFile $file): array
    {
        return $this->receiveFile($familyId, $actorId, $file, true);
    }

    private function receiveFile(string $familyId, int $actorId, UploadedFile $file, bool $imageOnly): array
    {
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file->getRealPath());
        $allowed = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if ($imageOnly) {
            unset($allowed['application/pdf']);
        }
        if (! isset($allowed[$mime]) || ! $file->isValid() || $file->getSize() < 1 || $file->getSize() > config('memoria.max_upload_bytes')) {
            throw ValidationException::withMessages(['file' => $imageOnly ? 'Alege un JPG, PNG sau WebP de cel mult 10 MB.' : 'Alege un PDF, JPG, PNG sau WebP de cel mult 10 MB.']);
        }
        if (DB::table('media_assets')->where('family_id', $familyId)->sum('bytes') + $file->getSize() > config('memoria.family_quota_bytes')) {
            throw ValidationException::withMessages(['file' => 'Spațiul familiei este plin (100 MB în această etapă).']);
        }
        $id = (string) Str::uuid();
        $key = $familyId.'/'.$id;
        $disk = Storage::disk('local');
        try {
            $this->budget->write($disk, $file->getSize(), function () use ($disk, $familyId, $file, $id, $key): void {
                $disk->putFileAs($familyId, $file, $id);
                abort_unless($disk->exists($key), 503, 'Fișierul nu a putut fi salvat.');
            });
            DB::afterRollBack(fn () => $disk->delete($key));
            // Keep the client name for display only; it is never a storage path or HTTP header.
            $name = Str::limit(preg_replace('/[\x00-\x1F\x7F]/u', '', basename(str_replace('\\', '/', $file->getClientOriginalName()))) ?: 'document', 200, '');
            DB::table('media_assets')->insert([
                'id' => $id, 'family_id' => $familyId, 'created_by' => $actorId,
                'storage_key' => $key, 'original_name' => $name, 'mime' => $mime,
                'bytes' => $disk->size($key), 'sha256' => hash_file('sha256', $disk->path($key)),
                'status' => 'quarantine', 'created_at' => now(), 'updated_at' => now(),
            ]);

            return ['id' => $id, 'status' => $this->retry($familyId, $id)];
        } catch (\Throwable $error) {
            $disk->delete($key);
            throw $error;
        }
    }

    public function retry(string $familyId, string $assetId): string
    {
        $asset = DB::table('media_assets')->where('family_id', $familyId)->where('id', $assetId)->firstOrFail();
        if ($asset->status !== 'quarantine') {
            return $asset->status;
        }
        $disk = Storage::disk('local');
        $path = $disk->path($asset->storage_key);
        if (! $disk->exists($asset->storage_key) || ! hash_equals($asset->sha256, hash_file('sha256', $path))) {
            $status = 'rejected';
        } else {
            $result = $this->scanner->scan($path);
            $status = match ($result) {
                'clean' => 'ready', 'infected' => 'rejected', default => 'quarantine'
            };
            if ($status === 'ready' && str_starts_with($asset->mime, 'image/')) {
                $status = $this->normalizeImage($path, $asset->mime) ? 'ready' : 'rejected';
            }
        }
        clearstatcache(true, $path);
        if ($status === 'ready') {
            $normalizedBytes = filesize($path);
            $growthAllowed = $this->budget->accountGrowth($disk, max(0, $normalizedBytes - $asset->bytes));
            $otherBytes = DB::table('media_assets')->where('family_id', $familyId)->where('id', '!=', $assetId)->sum('bytes');
            if (! $growthAllowed || $normalizedBytes > config('memoria.max_upload_bytes') || $otherBytes + $normalizedBytes > config('memoria.family_quota_bytes')) {
                $status = 'rejected';
            }
        }
        if ($status === 'rejected') {
            $disk->delete($asset->storage_key);
        }
        DB::table('media_assets')->where('id', $assetId)->where('family_id', $familyId)->update([
            'status' => $status, 'updated_at' => now(),
            'bytes' => $status === 'rejected' ? 0 : filesize($path),
            'sha256' => $status === 'rejected' ? $asset->sha256 : hash_file('sha256', $path),
        ]);

        return $status;
    }

    private function normalizeImage(string $path, string $mime): bool
    {
        if (! extension_loaded('gd')) {
            return false;
        }
        $dimensions = @getimagesize($path);
        if (! $dimensions || $dimensions[0] * $dimensions[1] > 16000000) {
            return false;
        }
        $image = @imagecreatefromstring(file_get_contents($path));
        if (! $image) {
            return false;
        }
        imagealphablending($image, false);
        imagesavealpha($image, true);
        $ok = match ($mime) {
            'image/jpeg' => imagejpeg($image, $path, 90),
            'image/png' => imagepng($image, $path),
            'image/webp' => imagewebp($image, $path, 90),
            default => false,
        };
        imagedestroy($image);

        return $ok;
    }

    public function image(string $familyId, string $assetId): StreamedResponse
    {
        $asset = DB::table('media_assets')->where('family_id', $familyId)->where('id', $assetId)->firstOrFail();
        abort_unless($asset->status === 'ready', 409, 'Fotografia nu este disponibilă până la finalizarea verificării.');
        abort_unless(in_array($asset->mime, ['image/jpeg', 'image/png', 'image/webp'], true), 404);
        $disk = Storage::disk('local');
        abort_unless($disk->exists($asset->storage_key), 404);

        return $disk->response($asset->storage_key, 'fotografie', [
            'Content-Type' => $asset->mime, 'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff', 'Content-Security-Policy' => "default-src 'none'; sandbox",
        ], 'inline');
    }

    public function download(string $familyId, string $assetId): StreamedResponse
    {
        $asset = DB::table('media_assets')->where('family_id', $familyId)->where('id', $assetId)->firstOrFail();
        abort_unless($asset->status === 'ready', 409, 'Fișierul nu este disponibil până la finalizarea verificării.');
        $disk = Storage::disk('local');
        abort_unless($disk->exists($asset->storage_key), 404);
        $extension = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$asset->mime];

        return $disk->download($asset->storage_key, 'document.'.$extension, [
            'Content-Type' => 'application/octet-stream',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]);
    }
}
