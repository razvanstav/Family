<?php

namespace App\Platform\Administration;

use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\TransportInterface;

class BrevoTransportFactory
{
    public function make(string $username, string $password): TransportInterface
    {
        $transport = new EsmtpTransport(EmailSettings::HOST, EmailSettings::PORT, false);
        $transport->setAutoTls(true);
        $transport->setRequireTls(true);
        $transport->setUsername($username);
        $transport->setPassword($password);
        $transport->getStream()->setTimeout(10);

        return $transport;
    }
}
