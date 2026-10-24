<?php

namespace App\Modules\Documents\Application;

use App\Modules\Calendar\Contracts\DeadlineSource;
use Illuminate\Database\Query\Builder;

final class DocumentExpirySource implements DeadlineSource
{
    public function __construct(private readonly DocumentAccess $access) {}

    public function type(): string
    {
        return 'document_expiry';
    }

    public function documents(string $family): Builder
    {
        return $this->access->recipients($family)->whereNotNull('d.expires_on');
    }

    public function references(string $family): Builder
    {
        return $this->documents($family)->select('d.id as parent_id', 'd.version')
            ->selectRaw("md5('document-expiry:' || d.id::text || ':' || reader.id::text)::uuid as id, true as active");
    }
}
