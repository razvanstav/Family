<?php

namespace App\Modules\Subscriptions\Application;

use App\Modules\Calendar\Contracts\DatedRecords;
use Illuminate\Database\Query\Builder;

final class SubscriptionDatedRecords implements DatedRecords
{
    public function __construct(private readonly SubscriptionAccess $access) {}

    public function records(string $family, int $actor, string $membership): Builder
    {
        return $this->access->query($family, $actor, false)->whereNotNull('s.next_due_on')
            ->select('s.id as source_id', 's.id as source_parent_id', 's.next_due_on as event_date', 's.owner_id', 'author.name as owner_name', 's.version')
            ->selectRaw("md5('subscription:' || s.id::text)::uuid AS id, 'subscription'::text AS source_type, s.service_key::text AS source_kind, s.service_name::text AS title")
            ->selectRaw("CASE WHEN s.family_id = ? THEN 'family'::text ELSE 'selected'::text END AS visibility", [$family]);
    }
}
