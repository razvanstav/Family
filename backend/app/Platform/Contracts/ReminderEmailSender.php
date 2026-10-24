<?php

namespace App\Platform\Contracts;

interface ReminderEmailSender
{
    public function ready(): bool;

    public function send(string $address, string $subject, string $body): void;
}
