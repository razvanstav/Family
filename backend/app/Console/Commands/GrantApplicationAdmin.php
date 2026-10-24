<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

#[Signature('app:grant-admin {email : Email of the existing account authorized to administer Amora}')]
#[Description('Grant platform administration to an explicitly authorized existing account using a privileged database connection')]
final class GrantApplicationAdmin extends Command
{
    public function handle(): int
    {
        $email = Str::lower(trim((string) $this->argument('email')));
        if (! in_array($email, config('memoria.admin_emails', []), true)) {
            $this->error('Adresa trebuie autorizată explicit în APP_ADMIN_EMAILS.');

            return self::FAILURE;
        }
        $users = User::query()->whereRaw('lower(email) = ?', [$email])->get();
        if ($users->count() !== 1) {
            $this->error('Este necesar un singur cont existent cu această adresă. Comanda nu creează conturi.');

            return self::FAILURE;
        }
        try {
            $users->sole()->forceFill(['is_platform_admin' => true])->save();
        } catch (QueryException) {
            $this->error('Rulează comanda cu o conexiune de administrare a bazei de date, nu cu utilizatorul aplicației.');

            return self::FAILURE;
        }
        $this->info('Accesul de administrare a fost acordat contului existent.');

        return self::SUCCESS;
    }
}
