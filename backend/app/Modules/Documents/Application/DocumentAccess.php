<?php

namespace App\Modules\Documents\Application;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class DocumentAccess
{
    /** @return Collection<int, object> Authorized metadata only; detail and bytes remain canonical operations. */
    public function recent(string $family, int $actor, string $membership): Collection
    {
        return $this->visible($family, $actor, $membership)
            ->join('media_assets as a', function ($join) {
                $join->on('a.id', '=', 'd.asset_id')->on('a.family_id', '=', 'd.family_id');
            })->orderByDesc('d.created_at')->orderByDesc('d.id')->limit(4)
            ->get(['d.id', 'd.title', 'd.category', 'd.visibility', 'd.owner_id', 'a.mime', 'a.status']);
    }

    public function visible(string $family, int $actor, string $membership): Builder
    {
        return DB::table('documents as d')->where('d.family_id', $family)
            ->where(function (Builder $q) use ($actor, $membership) {
                $q->where('d.owner_id', $actor)->orWhere('d.visibility', 'family')
                    ->orWhere(function (Builder $selected) use ($membership) {
                        $selected->where('d.visibility', 'selected')->whereExists(function (Builder $grant) use ($membership) {
                            $grant->selectRaw('1')->from('document_grants as g')
                                ->whereColumn('g.document_id', 'd.id')->whereColumn('g.family_id', 'd.family_id')
                                ->where('g.membership_id', $membership);
                        });
                    });
            });
    }

    public function find(string $family, int $actor, string $membership, string $id): object
    {
        abort_unless(Str::isUuid($id), 404);
        $document = $this->visible($family, $actor, $membership)->where('d.id', $id)->first();
        abort_unless($document, 404);

        return $document;
    }
}
