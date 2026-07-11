<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA CONFIGURATION FILE
File: config\app.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Defines maintained application or framework configuration for the official FNLLA stack.
*/

$environment = framework_detect_environment();
$isDevelopment = $environment === "development";

return [
    "name" => "FNLLA",
    "environment" => $environment,
    "debug" => (bool) env("APP_DEBUG", $isDevelopment),
    "base_url" => rtrim((string) env("APP_URL", ""), "/"),
    "timezone" => (string) env("APP_TIMEZONE", "UTC"),
    "locale" => (string) env("APP_LOCALE", "en"),
    "fallback_locale" => (string) env("APP_FALLBACK_LOCALE", "en"),
    "log_path" => storage_path((string) env("APP_LOG_PATH", "logs/app.log")),
    "session_path" => storage_path((string) env("SESSION_PATH", "framework/sessions")),
    "providers" => [
        \Fnlla\Php\Providers\FrameworkServiceProvider::class,
        \Fnlla\Php\Providers\AuthServiceProvider::class,
    ],
];
