<?php

namespace App\Modules\Memory\Application;

use App\Modules\Memory\Contracts\MomentSummaries;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class RecentMoments implements MomentSummaries
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

    /** @param ?array{0:string,1:string,2:string,3:string,4:string} $position */
    public function forMember(string $family, int $actor, string $member, ?array $position): Collection
    {
        $query = $this->access->visible($family, $actor)->whereNull('memory_moments.archived_at')
            ->whereExists(fn (Builder $people) => $people->selectRaw('1')->from('memory_moment_people as p')
                ->whereColumn('p.family_id', 'memory_moments.family_id')->whereColumn('p.moment_id', 'memory_moments.id')->where('p.membership_id', $member))
            ->join('users as author', 'author.id', '=', 'memory_moments.owner_id')
            ->leftJoin('media_assets as photo', function ($join) {
                $join->on('photo.family_id', '=', 'memory_moments.family_id')->on('photo.id', '=', 'memory_moments.photo_asset_id');
            });
        if ($position !== null) {
            if ($position[1] === 'entry') {
                $query->where('memory_moments.occurred_on', '<=', $position[0]);
            } else {
                $query->where(fn (Builder $after) => $after->where('memory_moments.occurred_on', '<', $position[0])
                    ->orWhere(fn (Builder $same) => $same->where('memory_moments.occurred_on', $position[0])->where('memory_moments.id', '<', $position[2])));
            }
        }

        return $query->orderByDesc('memory_moments.occurred_on')->orderByDesc('memory_moments.id')->limit(13)
            ->get(['memory_moments.id', 'memory_moments.title', 'memory_moments.kind', 'memory_moments.occurred_on', 'memory_moments.visibility',
                'memory_moments.version', 'author.name as owner_name', 'photo.status as photo_status', DB::raw('LEFT(memory_moments.body, 240) as excerpt')])
            ->map(fn (object $row): array => ['source' => 'moment'] + (array) $row);
    }
}
