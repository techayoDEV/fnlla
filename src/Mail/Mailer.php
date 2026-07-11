<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA MAIL SOURCE
File: src\Mail\Mailer.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Implements maintained mail delivery helpers for framework flows and notifications.
*/

namespace Fnlla\Php\Mail;

use RuntimeException;

final class Mailer
{
    public function to(array|string $recipients): PendingMail
    {
        return new PendingMail($this, $recipients);
    }

    public function send(array|string $recipients, string $subject, string $html, string $text = ""): void
    {
        $driver = (string) config("mail.default", "log");

        if ($driver === "log") {
            $directory = storage_path((string) config("mail.log_path", "mail"));

            if (!is_dir($directory)) {
                mkdir($directory, 0777, true);
            }

            $path = $directory . DIRECTORY_SEPARATOR . gmdate("Ymd") . ".log";
            $payload = [
                "sent_at" => gmdate(DATE_ATOM),
                "to" => is_array($recipients) ? array_values($recipients) : [$recipients],
                "subject" => $subject,
                "html" => $html,
                "text" => $text,
            ];

            file_put_contents($path, json_encode($payload, JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND | LOCK_EX);

            return;
        }

        if ($driver === "native") {
            $to = is_array($recipients) ? implode(", ", $recipients) : $recipients;
            $headers = [
                "MIME-Version: 1.0",
                "Content-type: text/html; charset=UTF-8",
                "From: " . (string) config("mail.from.address", "no-reply@example.com"),
            ];

            if (!mail((string) $to, $subject, $html, implode("\r\n", $headers))) {
                throw new RuntimeException("Native mail delivery failed.");
            }

            return;
        }

        throw new RuntimeException("Unsupported mail driver: " . $driver);
    }
}
