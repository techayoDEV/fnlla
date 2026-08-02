<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA SUPPORT SOURCE
File: src\Support\Logger.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Implements shared helpers, environment loading, metadata and framework support behaviour.
*/

namespace Fnlla\Php\Support;

use Throwable;

final class Logger
{
    public static function configuredPath(): string
    {
        $configured = trim((string) config("app.log_path", ""));
        $fallback = storage_path("logs/app.log");

        if ($configured === "") {
            return $fallback;
        }

        if (is_dir($configured) || preg_match('/[\\\\\\/]$/', $configured) === 1) {
            return rtrim($configured, "\\/") . DIRECTORY_SEPARATOR . "app.log";
        }

        return $configured;
    }

    public static function write(string $level, string $message, array $context = []): void
    {
        $logPath = self::configuredPath();
        $directory = dirname($logPath);

        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        self::rotateIfNeeded($logPath);

        $entry = [
            "timestamp" => gmdate(DATE_ATOM),
            "level" => strtoupper($level),
            "message" => $message,
            "context" => self::redact($context),
        ];

        $encoded = json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($encoded === false) {
            $encoded = sprintf(
                '{"timestamp":"%s","level":"%s","message":"%s","context":{"encoding_error":"Unable to encode log context"}}',
                gmdate(DATE_ATOM),
                strtoupper($level),
                addslashes($message)
            );
        }

        if (@file_put_contents($logPath, $encoded . PHP_EOL, FILE_APPEND | LOCK_EX) === false) {
            error_log($encoded);
        }
    }

    public static function exception(Throwable $exception, array $context = []): void
    {
        self::write("error", $exception->getMessage(), array_merge($context, [
            "exception" => [
                "type" => $exception::class,
                "message" => $exception->getMessage(),
                "code" => $exception->getCode(),
                "file" => $exception->getFile(),
                "line" => $exception->getLine(),
            ],
            "trace" => $exception->getTraceAsString(),
        ]));
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

    private static function rotateIfNeeded(string $logPath): void
    {
        $maxBytes = max(0, (int) config("logging.max_file_bytes", 5242880));
        $maxFiles = max(0, (int) config("logging.max_rotated_files", 5));

        if ($maxBytes <= 0 || $maxFiles <= 0 || !is_file($logPath) || filesize($logPath) < $maxBytes) {
            return;
        }

        for ($index = $maxFiles; $index >= 1; $index--) {
            $source = $index === 1 ? $logPath : $logPath . "." . ($index - 1);
            $target = $logPath . "." . $index;

            if (!is_file($source)) {
                continue;
            }

            if ($index === $maxFiles && is_file($target)) {
                @unlink($target);
            }

            @rename($source, $target);
        }
    }
}
