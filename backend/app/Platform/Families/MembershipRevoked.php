<?php

namespace App\Platform\Families;

final readonly class MembershipRevoked
{
    public function __construct(public string $familyId, public string $membershipId) {}
}
