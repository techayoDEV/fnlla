<?php

declare(strict_types=1);

namespace Fnlla\Php\Maintenance;

use Fnlla\Php\Mail\Mailer;
use RuntimeException;

final class DeveloperRecoveryMailJob
{
    public function __construct(private string $email, private int $requestedAt)
    {
    }

    public function handle(): void
    {
        if ($this->requestedAt < time() - 3600) {
            return;
        }
        $recovery = app(DeveloperPasswordRecovery::class);
        if (!$recovery->enabled()) {
            return;
        }
        try {
            if (!$recovery->mailAllowed()) {
                throw new RuntimeException("Production recovery requires a delivery transport.");
            }
            $url = $recovery->issue($this->email);
            if ($url !== null) {
                $minutes = max(5, min(60, (int) config("developer_access.recovery_ttl_minutes", 30)));
                app(Mailer::class)->send($this->email, "Reset your developer password",
                    '<p>A password reset was requested for your developer account.</p><p><a href="' . h($url) . '">Reset password</a></p><p>This link expires in ' . $minutes . ' minutes and works once. Ignore this email if you did not request it. Two-factor authentication remains enabled.</p>',
                    "Reset your developer password: " . $url . "\nExpires in " . $minutes . " minutes. Ignore this email if you did not request it.");
            }
        } catch (\Throwable) {
            // Do not place transport messages, bearer URLs or recipients in failed-job logs.
            throw new RuntimeException("Developer recovery delivery failed. Check APP_URL, mail transport and writable private storage.");
        }
    }
}
