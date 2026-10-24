<?php

namespace App\Platform\Administration;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Mailer\Exception\TransportException;

final class EmailSettings
{
    public const HOST = 'smtp-relay.brevo.com';

    public const PORT = 587;

    /** @return array{enabled: bool, host: string, port: int, username: string, has_password: bool, from_address: string, from_name: string, reply_to: ?string, version: int} */
    public function publicSettings(): array
    {
        return $this->payload(DB::table('application_email_settings')->where('id', 1)->firstOrFail());
    }

    /**
     * @param  array{enabled: bool, username: ?string, password?: ?string, from_address: ?string, from_name: ?string, reply_to?: ?string, version: int}  $data
     * @return array{enabled: bool, host: string, port: int, username: string, has_password: bool, from_address: string, from_name: string, reply_to: ?string, version: int}
     */
    public function update(array $data, int $actorId): array
    {
        $current = DB::table('application_email_settings')->where('id', 1)->lockForUpdate()->firstOrFail();
        abort_unless($current->version === (int) $data['version'], 412, 'Setările au fost modificate. Reîncarcă înainte de a salva.');
        $password = $data['password'] ?? '';
        $encryptedPassword = trim($password) === '' ? $current->password_encrypted : Crypt::encryptString($password);
        $values = [
            'enabled' => $data['enabled'],
            'username' => $data['username'] ?? '',
            'password_encrypted' => $encryptedPassword,
            'from_address' => $data['from_address'] ?? '',
            'from_name' => $data['from_name'] ?? '',
            'reply_to' => $data['reply_to'] ?? null,
        ];
        if ($data['enabled']) {
            $errors = [];
            foreach (['username' => 'Completează utilizatorul SMTP din Brevo.', 'from_address' => 'Completează adresa expeditorului.', 'from_name' => 'Completează numele expeditorului.'] as $field => $message) {
                if ($values[$field] === '') {
                    $errors[$field] = $message;
                }
            }
            if ($encryptedPassword === null) {
                $errors['password'] = 'Completează cheia SMTP din Brevo.';
            }
            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }
        }
        DB::table('application_email_settings')->where('id', 1)->update([
            ...$values, 'version' => $current->version + 1, 'updated_by' => $actorId, 'updated_at' => now(),
        ]);

        return $this->publicSettings();
    }

    /** @return array{username: string, password: string, from_address: string, from_name: string, reply_to: ?string} */
    public function deliverySettings(): array
    {
        $settings = DB::transaction(function (): object {
            $previous = DB::selectOne("SELECT current_setting('app.email_settings_access', true) AS access")->access;
            DB::select("SELECT set_config('app.email_settings_access', 'delivery', true)");
            try {
                return DB::table('application_email_settings')->where('id', 1)->firstOrFail();
            } finally {
                DB::select("SELECT set_config('app.email_settings_access', ?, true)", [$previous ?? '']);
            }
        });
        if (! $settings->enabled || $settings->username === '' || $settings->password_encrypted === null || $settings->from_address === '' || $settings->from_name === '') {
            throw new TransportException('Trimiterea emailurilor nu este configurată sau este dezactivată.');
        }

        return [
            'username' => $settings->username,
            'password' => Crypt::decryptString($settings->password_encrypted),
            'from_address' => $settings->from_address,
            'from_name' => $settings->from_name,
            'reply_to' => $settings->reply_to,
        ];
    }

    /** @return array{enabled: bool, host: string, port: int, username: string, has_password: bool, from_address: string, from_name: string, reply_to: ?string, version: int} */
    private function payload(object $settings): array
    {
        return [
            'enabled' => (bool) $settings->enabled, 'host' => self::HOST, 'port' => self::PORT,
            'username' => $settings->username, 'has_password' => $settings->password_encrypted !== null,
            'from_address' => $settings->from_address, 'from_name' => $settings->from_name,
            'reply_to' => $settings->reply_to, 'version' => (int) $settings->version,
        ];
    }
}
