<?php

namespace App\Platform\Administration;

use App\Models\User;
use Illuminate\Support\Str;

final class AdministratorAccess
{
    public static function allows(?User $user): bool
    {
        if ($user === null || ! $user->is_platform_admin) {
            return false;
        }

        return in_array(Str::lower(trim($user->email)), config('memoria.admin_emails', []), true);
    }
}
