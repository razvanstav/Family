<?php

namespace App\Modules\Documents\Application;

use App\Modules\Documents\Contracts\DocumentLinks;
use App\Modules\Documents\Contracts\DocumentSummaries;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class DocumentAccess implements DocumentLinks, DocumentSummaries
{
    public function assertVisible(string $family, int $actor, string $membership, string $id): void
    {
        $this->find($family, $actor, $membership, $id);
    }

    public function revokeMembership(string $family, string $membership): void
    {
        DB::table('document_grants')->where('family_id', $family)->where('membership_id', $membership)->delete();
    }

    /** Authorized metadata for associations; access is evaluated on every read. */
    public function references(string $family, int $actor, string $membership): Builder
    {
        return $this->visible($family, $actor, $membership)->leftJoin('media_assets as a', function ($join) {
            $join->on('a.id', '=', 'd.asset_id')->on('a.family_id', '=', 'd.family_id');
        })->select('d.id', 'd.title', 'd.visibility', 'a.mime', 'a.status');
    }

    /** @return Collection<int, object> Authorized metadata only; detail and bytes remain canonical operations. */
    public function recent(string $family, int $actor, string $membership): Collection
    {
        return $this->visible($family, $actor, $membership)
            ->leftJoin('media_assets as a', function ($join) {
                $join->on('a.id', '=', 'd.asset_id')->on('a.family_id', '=', 'd.family_id');
            })->orderByDesc('d.created_at')->orderByDesc('d.id')->limit(4)
            ->get(['d.id', 'd.title', 'd.category', 'd.visibility', 'd.owner_id', 'd.subject_membership_id', 'a.mime', 'a.status']);
    }

    public function visible(string $family, int $actor, string $membership): Builder
    {
        return $this->authorize(DB::table('documents as d')->where('d.family_id', $family), $family, $actor, $membership);
    }

    public function recipients(string $family): Builder
    {
        $query = DB::table('documents as d')->join('family_memberships as reader', function ($join) {
            $join->on('reader.family_id', '=', 'd.family_id')->whereNull('reader.revoked_at')->whereNotNull('reader.user_id');
        })->where('d.family_id', $family);

        return $this->authorize($query, $family, DB::raw('reader.user_id'), DB::raw('reader.id'));
    }

    private function authorize(Builder $query, string $family, int|Expression $actor, string|Expression $membership): Builder
    {
        return $query->where(function (Builder $q) use ($family, $actor, $membership) {
            $q->where('d.owner_id', $actor)->orWhere('d.visibility', 'family')
                ->orWhere(fn (Builder $adults) => $adults->where('d.visibility', 'adults')
                    ->whereExists($this->adultMembers($family)->where('m.id', $membership)->selectRaw('1')))
                ->orWhere(function (Builder $selected) use ($membership) {
                    $selected->where('d.visibility', 'selected')->whereExists(function (Builder $grant) use ($membership) {
                        $grant->selectRaw('1')->from('document_grants as g')
                            ->whereColumn('g.document_id', 'd.id')->whereColumn('g.family_id', 'd.family_id')
                            ->where('g.membership_id', $membership);
                    });
                });
        });
    }

    public function adultMembers(string $family): Builder
    {
        return DB::table('family_memberships as m')->leftJoin('member_profiles as p', function ($join) {
            $join->on('p.family_id', '=', 'm.family_id')->on('p.membership_id', '=', 'm.id');
        })->where('m.family_id', $family)->whereNull('m.revoked_at')->whereNotNull('m.user_id')
            ->where(fn (Builder $age) => $age->whereNull('p.birth_date')->orWhere('p.birth_date', '<=', now('Europe/Bucharest')->subYearsNoOverflow(18)->toDateString()));
    }

    public function find(string $family, int $actor, string $membership, string $id): object
    {
        abort_unless(Str::isUuid($id), 404);
        $document = $this->visible($family, $actor, $membership)->where('d.id', $id)->first();
        abort_unless($document, 404);

        return $document;
    }
}
