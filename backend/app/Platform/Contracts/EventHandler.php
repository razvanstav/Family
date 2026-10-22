<?php

namespace App\Platform\Contracts;

interface EventHandler
{
    public function name(): string;

    /** @return list<string> */
    public function events(): array;

    /** Database effects only, on the current connection and tenant transaction. */
    public function handle(StoredEvent $event): void;
}
