<?php

namespace App\Modules\Memory\Application;

use App\Platform\Contracts\PrivateMedia;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class MomentPhotos
{
    public const LIMIT = 20;

    public const UPLOAD_BYTES = 10 * 1024 * 1024;

    public function __construct(private readonly PrivateMedia $media) {}

    public function summaries(string $family): Builder
    {
        return DB::table('memory_moment_photos as mp')->join('media_assets as asset', function ($join) {
            $join->on('asset.family_id', '=', 'mp.family_id')->on('asset.id', '=', 'mp.media_asset_id');
        })->where('mp.family_id', $family)
            ->selectRaw("mp.moment_id, json_agg(json_build_object('id', mp.id, 'status', asset.status) ORDER BY mp.position) as photos_json")
            ->groupBy('mp.moment_id');
    }

    /** @param list<string> $retainedIds
     * @param  list<UploadedFile>  $uploads
     */
    public function sync(string $family, string $moment, int $actor, array $retainedIds, array $uploads): ?string
    {
        $query = DB::table('memory_moment_photos')->where('family_id', $family)->where('moment_id', $moment);
        $existing = (clone $query)->get()->keyBy('id');
        if (count($retainedIds) + count($uploads) > self::LIMIT) {
            throw ValidationException::withMessages(['photos' => 'O amintire poate avea cel mult 20 de fotografii.']);
        }
        foreach ($retainedIds as $id) {
            if (! $existing->has($id)) {
                throw ValidationException::withMessages(['photo_ids' => 'Alege numai fotografii din această amintire.']);
            }
        }
        $rows = array_map(fn (string $id): array => ['id' => $id, 'media_asset_id' => $existing[$id]->media_asset_id], $retainedIds);
        foreach ($uploads as $upload) {
            $asset = $this->media->receiveImage($family, $actor, $upload);
            $rows[] = ['id' => (string) Str::uuid(), 'media_asset_id' => $asset['id']];
        }
        $query->delete();
        if ($rows !== []) {
            DB::table('memory_moment_photos')->insert(array_map(fn (array $row, int $position): array => $row + [
                'family_id' => $family, 'moment_id' => $moment, 'position' => $position,
            ], $rows, array_keys($rows)));
        }

        return $rows[0]['media_asset_id'] ?? null;
    }

    public function asset(string $family, string $moment, string $photo): string
    {
        return DB::table('memory_moment_photos')->where('family_id', $family)->where('moment_id', $moment)->where('id', $photo)->firstOrFail()->media_asset_id;
    }
}
