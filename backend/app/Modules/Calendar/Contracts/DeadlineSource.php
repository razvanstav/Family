<?php

namespace App\Modules\Calendar\Contracts;

use Illuminate\Database\Query\Builder;

interface DeadlineSource
{
    public function type(): string;

    /** Returns id, parent_id, version and active from the canonical tenant source. */
    public function references(string $family): Builder;
}
