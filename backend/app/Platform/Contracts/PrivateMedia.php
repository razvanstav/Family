<?php

namespace App\Platform\Contracts;

use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\StreamedResponse;

interface PrivateMedia
{
    /** @return array{id:string,status:string} */
    public function receive(string $familyId, int $actorId, UploadedFile $file): array;

    public function retry(string $familyId, string $assetId): string;

    public function download(string $familyId, string $assetId): StreamedResponse;
}
