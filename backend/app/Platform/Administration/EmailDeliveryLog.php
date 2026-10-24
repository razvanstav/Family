<?php

namespace App\Platform\Administration;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;
use Throwable;

final class EmailDeliveryLog
{
    public function record(RawMessage $message, ?Envelope $envelope, bool $sent): void
    {
        if (! $message instanceof Email) {
            return;
        }
        $recipients = $envelope?->getRecipients() ?? [...$message->getTo(), ...$message->getCc(), ...$message->getBcc()];
        $addresses = array_unique(array_map(fn (Address $address): string => $address->getAddress(), $recipients));
        $now = now();
        $rows = array_map(fn (string $address): array => [
            'recipient' => $address,
            'subject' => Str::limit($message->getSubject() ?? '', 255, ''),
            'status' => $sent ? 'sent' : 'failed',
            'sent_at' => $sent ? $now : null,
            'created_at' => $now,
        ], array_values($addresses));
        if ($rows === []) {
            return;
        }
        try {
            DB::transaction(function () use ($rows): void {
                $previous = DB::selectOne("SELECT current_setting('app.email_settings_access', true) AS access")->access;
                DB::select("SELECT set_config('app.email_settings_access', 'delivery', true)");
                try {
                    DB::table('email_deliveries')->insert($rows);
                } finally {
                    DB::select("SELECT set_config('app.email_settings_access', ?, true)", [$previous ?? '']);
                }
            });
        } catch (Throwable) {
            Log::warning('Rezultatul trimiterii unui email nu a putut fi înregistrat în jurnal.');
        }
    }
}
