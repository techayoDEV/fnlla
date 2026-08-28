<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA SUPPORT SOURCE
File: src\Support\FrameworkUpdateAuditLogger.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Writes a small JSONL audit trail for official GitHub-backed framework update
  checks, dry-runs, apply runs and rejected update sources.
*/

namespace Fnlla\Php\Support;

use RuntimeException;

final class FrameworkUpdateAuditLogger
{
    public static function write(string $event, array $context = []): void
    {
        if ((bool) config("framework_update.audit_log_enabled", true) !== true) {
            return;
        }

        $path = self::configuredPath();
        $directory = dirname($path);

        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new RuntimeException("Unable to create framework update audit log directory: " . $directory);
        }

        $entry = [
            "timestamp" => gmdate(DATE_ATOM),
            "event" => $event,
            "official_repository" => FrameworkReleaseChannel::OFFICIAL_REPOSITORY,
            "context" => self::redact($context),
        ];

        $encoded = json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if (!is_string($encoded)) {
            throw new RuntimeException("Unable to encode framework update audit log entry.");
        }

        file_put_contents($path, $encoded . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    public static function configuredPath(): string
    {
        $relativePath = trim((string) config("framework_update.audit_log_path", "logs/framework-update.log"));

        if ($relativePath === "" || self::isAbsolutePath($relativePath)) {
            throw new RuntimeException("Framework update audit log path must stay inside storage.");
        }

        $relativePath = str_replace(["/", "\\"], DIRECTORY_SEPARATOR, $relativePath);
        $relativePath = ltrim($relativePath, DIRECTORY_SEPARATOR);

        if (in_array("..", explode(DIRECTORY_SEPARATOR, $relativePath), true)) {
            throw new RuntimeException("Framework update audit log path must stay inside storage.");
        }

        return storage_path($relativePath);
    }

    private static function redact(mixed $value, ?string $key = null): mixed
    {
        $redactKeys = (array) config("logging.redact_keys", []);
        $normalizedKey = is_string($key) ? strtolower($key) : "";

        foreach ($redactKeys as $redactKey) {
            if (is_string($redactKey) && $redactKey !== "" && str_contains($normalizedKey, strtolower($redactKey))) {
                return "[redacted]";
            }
        }

        if (is_array($value)) {
            $redacted = [];

            foreach ($value as $childKey => $childValue) {
                $redacted[$childKey] = self::redact($childValue, is_string($childKey) ? $childKey : null);
            }

            return $redacted;
        }

        return $value;
    }

    private static function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, DIRECTORY_SEPARATOR)
            || preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1;
    }
}
