<?php

namespace App\Platform\Administration;

use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;
use Throwable;

final class BrevoTransport implements TransportInterface
{
    public function __construct(private EmailSettings $settings, private BrevoTransportFactory $factory, private EmailDeliveryLog $deliveries) {}

    public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
    {
        try {
            $settings = $this->settings->deliverySettings();
            if (! $message instanceof Email) {
                throw new TransportException('Formatul emailului nu este acceptat.');
            }
            $message = clone $message;
            $sender = new Address($settings['from_address'], $settings['from_name']);
            $message->from($sender)->sender($sender)->returnPath($sender);
            if ($settings['reply_to'] !== null) {
                $message->replyTo($settings['reply_to']);
            }
            $envelope = $envelope === null ? Envelope::create($message) : clone $envelope;
            $envelope->setSender($sender);

            $sent = $this->factory->make($settings['username'], $settings['password'])->send($message, $envelope);
            if ($sent === null) {
                throw new TransportException('Emailul nu a fost acceptat de transport.');
            }
        } catch (Throwable) {
            $this->deliveries->record($message, $envelope, false);
            throw new TransportException('Emailul nu a putut fi trimis. Verifică setările SMTP, expeditorul validat în Brevo și încearcă din nou.');
        }
        $this->deliveries->record($message, $envelope, true);

        return $sent;
    }

    public function __toString(): string
    {
        return 'amora-brevo';
    }
}
