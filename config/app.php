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
$appLogPath = trim((string) env("APP_LOG_PATH", "logs/app.log"));
$sessionPath = trim((string) env("SESSION_PATH", "framework/sessions"));

return [
    "name" => (string) env("APP_NAME", "FNLLA"),
    "tagline" => (string) env("APP_TAGLINE", ""),
    "brand_logo" => trim((string) env("APP_BRAND_LOGO", "auto")),
    "environment" => $environment,
    "debug" => (bool) env("APP_DEBUG", $isDevelopment),
    "base_url" => rtrim((string) env("APP_URL", ""), "/"),
    "asset_url" => rtrim((string) env("ASSET_URL", ""), "/"),
    "project_leadership" => [
        "organization" => trim((string) env("PROJECT_LEADERSHIP_ORGANIZATION", "")),
        "person_name" => trim((string) env("PROJECT_LEADERSHIP_PERSON_NAME", "")),
        "person_email" => strtolower(trim((string) env("PROJECT_LEADERSHIP_PERSON_EMAIL", ""))),
        "person_role" => trim((string) env("PROJECT_LEADERSHIP_PERSON_ROLE", "")),
        "responsibility" => trim((string) env("PROJECT_LEADERSHIP_RESPONSIBILITY", "")),
        "profile_url" => trim((string) env("PROJECT_LEADERSHIP_PROFILE_URL", "")),
        "visibility" => strtolower(trim((string) env("PROJECT_LEADERSHIP_VISIBILITY", "disabled"))),
        "status" => strtolower(trim((string) env("PROJECT_LEADERSHIP_STATUS", "pending"))),
        "confirmed_by" => trim((string) env("PROJECT_LEADERSHIP_CONFIRMED_BY", "")),
        "confirmed_at" => trim((string) env("PROJECT_LEADERSHIP_CONFIRMED_AT", "")),
    ],
    "timezone" => (string) env("APP_TIMEZONE", "UTC"),
    "locale" => (string) env("APP_LOCALE", "en"),
    "fallback_locale" => (string) env("APP_FALLBACK_LOCALE", "en"),
    "log_path" => storage_path($appLogPath !== "" ? $appLogPath : "logs/app.log"),
    "session_path" => storage_path($sessionPath !== "" ? $sessionPath : "framework/sessions"),
    "providers" => [
        \Fnlla\Php\Providers\FrameworkServiceProvider::class,
        \Fnlla\Php\Providers\AuthServiceProvider::class,
    ],
];
