<?php

declare(strict_types=1);

namespace Fnlla\Smtp;

use Fnlla\Php\Mail\MailTransportInterface;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

final class SmtpTransport implements MailTransportInterface
{
    public function __construct(private TransportInterface $transport) {}

    public static function fromDsn(string $dsn): self
    {
        if (!str_starts_with($dsn, "smtp://") && !str_starts_with($dsn, "smtps://")) {
            throw new \InvalidArgumentException("Expected an SMTP or SMTPS DSN.");
        }
        return new self(Transport::fromDsn($dsn));
    }

    public function send(array $message): void
    {
        $email = (new Email())->from(new Address($message["from"]["address"], $message["from"]["name"]))
            ->to(...$message["to"])->subject($message["subject"])
            ->html($message["html"])->text($message["text"]);
        if ($message["reply_to"] !== null) { $email->replyTo($message["reply_to"]); }
        $this->transport->send($email);
    }
}
