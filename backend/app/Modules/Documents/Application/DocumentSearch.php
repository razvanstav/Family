<?php

namespace App\Modules\Documents\Application;

use App\Platform\Contracts\SearchProvider;
use App\Platform\Search\SearchTerm;
use Illuminate\Database\Query\Builder;

final class DocumentSearch implements SearchProvider
{
    public function __construct(private readonly DocumentAccess $access) {}

    public function type(): string
    {
        return 'document';
    }

    public function query(string $family, int $actor, string $membership, SearchTerm $term): Builder
    {
        $query = $this->access->visible($family, $actor, $membership)
            ->leftJoin('family_memberships as subject', function ($join) {
                $join->on('subject.id', '=', 'd.subject_membership_id')->on('subject.family_id', '=', 'd.family_id')->whereNull('subject.revoked_at');
            })->leftJoin('family_memberships as owner', function ($join) {
                $join->on('owner.user_id', '=', 'd.owner_id')->on('owner.family_id', '=', 'd.family_id')->whereNull('owner.revoked_at');
            });

        return $term->constrain($query, ['d.title'])->selectRaw("'document'::text as type, d.id, d.title, NULL::text as excerpt, d.category, d.visibility, coalesce(subject.id, owner.id) as member_id, d.created_at::date as date");
    }
}
