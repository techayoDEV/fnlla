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
- Implements shared helpers, environment loading, metadata and framework support behavior.
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

        $entry = [
            "timestamp" => gmdate(DATE_ATOM),
            "level" => strtoupper($level),
            "message" => $message,
            "context" => $context,
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
}
