<?php

namespace App\Modules\Memory\Application;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class RecentMoments
{
    public function __construct(private readonly MomentAccess $access) {}

    /** @return Collection<int, array<string, mixed>> Authorized summaries; detail and photos require canonical access. */
    public function forHome(string $family, int $actor): Collection
    {
        return $this->access->visible($family, $actor)->whereNull('memory_moments.archived_at')
            ->leftJoin('media_assets as photo', function ($join) {
                $join->on('photo.family_id', '=', 'memory_moments.family_id')->on('photo.id', '=', 'memory_moments.photo_asset_id');
            })->orderByDesc('memory_moments.occurred_on')->orderByDesc('memory_moments.id')->limit(3)
            ->get(['memory_moments.id', 'memory_moments.title', 'memory_moments.kind', 'memory_moments.body',
                'memory_moments.occurred_on', 'memory_moments.visibility', 'photo.status as photo_status'])
            ->map(fn (object $moment): array => [
                'id' => $moment->id, 'title' => $moment->title, 'kind' => $moment->kind,
                'excerpt' => $moment->body ? Str::limit($moment->body, 200) : null,
                'occurred_on' => $moment->occurred_on, 'visibility' => $moment->visibility, 'photo_status' => $moment->photo_status,
            ]);
    }
}
