# Optional SMTP transport

Local package, not yet published to a public registry. Add a Composer path
repository pointing at packages/fnlla-smtp and require techayodev/fnlla-smtp:@dev
in a Core or packaged Complete application. Neither starter installs it by default.
Pin a tested revision for distribution. SMTP/TLS is handled by Symfony Mailer.

Register in your application service provider:

```php
$this->container->singleton(\Fnlla\Php\Mail\MailTransportInterface::class,
    static fn () => \Fnlla\Smtp\SmtpTransport::fromDsn((string) env('MAILER_DSN')));
```

Set MAIL_MAILER=adapter and MAILER_DSN to the SMTP DSN. Keep credentials in the
environment; URL-encode DSN credentials. Use TLS and certificate verification in
production. Transport errors propagate; acknowledgement is not final mailbox
delivery. Retrying ambiguous failures can produce duplicates.

Tests use a real loopback SMTP socket with no relay and cover success and 550
recipient rejection. Production authentication, TLS certificates, DNS,
SPF/DKIM/DMARC and provider limits remain deployment responsibilities.
