<?php

namespace App\Platform\Contracts;

interface NotificationPreferences
{
    /** @return array<string, array{in_app: bool, email: bool}> */
    public function forUser(int $user): array;

    public function configured(int $user): bool;

    public function category(?string $sourceType): string;
}
