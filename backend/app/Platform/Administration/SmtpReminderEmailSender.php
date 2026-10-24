<?php

namespace App\Platform\Administration;

use App\Platform\Contracts\ReminderEmailSender;
use Illuminate\Support\Facades\Mail;
use Throwable;

final class SmtpReminderEmailSender implements ReminderEmailSender
{
    public function __construct(private readonly EmailSettings $settings) {}

    public function ready(): bool
    {
        try {
            $this->settings->deliverySettings();

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    public function send(string $address, string $subject, string $body): void
    {
        Mail::mailer('amora')->raw($body, fn ($message) => $message->to($address)->subject($subject));
    }
}
