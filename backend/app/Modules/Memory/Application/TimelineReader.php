<?php

namespace App\Modules\Memory\Application;

use App\Modules\Kitchen\Contracts\Activity;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

final class TimelineReader
{
    public function __construct(private readonly MomentAccess $moments, private readonly Activity $kitchen) {}

    private function momentCards(string $family, int $actor, array $ids): array
    {
        return $this->moments->visible($family, $actor)->whereNull('memory_moments.archived_at')->whereIn('memory_moments.id', $ids)
            ->join('users as author', 'author.id', '=', 'memory_moments.owner_id')
            ->leftJoin('media_assets as photo', function ($join) {
                $join->on('photo.family_id', '=', 'memory_moments.family_id')->on('photo.id', '=', 'memory_moments.photo_asset_id');
            })->select(['memory_moments.id', 'memory_moments.kind as moment_kind', 'memory_moments.title', 'memory_moments.visibility',
                'memory_moments.version as source_version', 'memory_moments.occurred_on', 'author.name as owner_name', 'photo.status as photo_status',
                DB::raw('LEFT(memory_moments.body, 240) AS excerpt')])
            ->get()->mapWithKeys(fn (object $row): array => [$row->id => ['kind' => 'memory.moment', 'photo_is_example' => false] + (array) $row])->all();
    }

    /** @return array{items:array,has_more:bool,next_cursor:?string} */
    public function listing(string $family, int $actor, string $membership, string $filter, ?string $cursor, int $limit = 20): array
    {
        abort_unless(in_array($filter, ['all', 'memory', 'meals'], true) && $limit >= 1 && $limit <= 50, 422);
        $query = DB::table('memory_timeline_sources')->where('family_id', $family)->where('removed', false);
        if ($filter !== 'all') {
            $query->where('source_kind', $filter === 'memory' ? 'memory.moment' : 'kitchen.meal');
        }
        if ($cursor !== null) {
            try {
                $position = json_decode(Crypt::decryptString($cursor), true, flags: JSON_THROW_ON_ERROR);
            } catch (DecryptException|\JsonException) {
                abort(422, 'Pagina cronologiei nu mai este validă. Actualizează cronologia.');
            }
            $position = Validator::make(['position' => $position], ['position' => 'required|array|size:5',
                'position.0' => 'required|date_format:Y-m-d', 'position.1' => 'required|in:memory.moment,kitchen.meal',
                'position.2' => 'required|uuid', 'position.3' => 'required|in:'.$family, 'position.4' => 'required|in:'.$filter])->validate()['position'];
            $query->where(fn (Builder $after) => $after->where('occurred_on', '<', $position[0])
                ->orWhere(fn (Builder $sameDate) => $sameDate->where('occurred_on', $position[0])
                    ->where(fn (Builder $tie) => $tie->where('source_kind', '>', $position[1])
                        ->orWhere(fn (Builder $sameKind) => $sameKind->where('source_kind', $position[1])->where('source_id', '<', $position[2])))));
        }
        $rows = $query->orderByDesc('occurred_on')->orderBy('source_kind')->orderByDesc('source_id')->limit(201)->get();
        $scan = $rows->take(200);
        $momentIds = $scan->where('source_kind', 'memory.moment')->pluck('source_id')->all();
        $mealIds = $scan->where('source_kind', 'kitchen.meal')->pluck('source_id')->all();
        $cards = ['memory.moment' => $momentIds ? $this->momentCards($family, $actor, $momentIds) : [],
            'kitchen.meal' => $mealIds ? $this->kitchen->mealCards($family, $actor, $membership, $mealIds) : []];
        $items = [];
        $scanned = 0;
        $last = null;
        foreach ($scan as $row) {
            $scanned++;
            $last = $row;
            $card = $cards[$row->source_kind][$row->source_id] ?? null;
            if ($card && $card['occurred_on'] === $row->occurred_on) {
                $items[] = $card;
            }
            if (count($items) === $limit) {
                break;
            }
        }
        $more = $scanned < $rows->count();

        return ['items' => $items, 'has_more' => $more, 'next_cursor' => $more && $last
            ? Crypt::encryptString(json_encode([$last->occurred_on, $last->source_kind, $last->source_id, $family, $filter], JSON_THROW_ON_ERROR)) : null];
    }
}
