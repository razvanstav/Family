<?php

namespace App\Modules\Documents\Application;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class DocumentAccess
{
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
