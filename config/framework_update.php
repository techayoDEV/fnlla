<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA CONFIGURATION FILE
File: config\framework_update.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Defines framework-update controls that downstream applications can keep
  enabled locally without exposing a production-wide command runner.
*/

$isDevelopment = framework_detect_environment() === "development";

return [
    "ui_enabled" => (bool) env("FRAMEWORK_UPDATE_UI_ENABLED", env("STARTER_UPDATE_UI_ENABLED", $isDevelopment)),
    "ui_local_only" => (bool) env("FRAMEWORK_UPDATE_UI_LOCAL_ONLY", env("STARTER_UPDATE_UI_LOCAL_ONLY", true)),
    "ui_apply_enabled" => (bool) env("FRAMEWORK_UPDATE_UI_APPLY_ENABLED", env("STARTER_UPDATE_UI_APPLY_ENABLED", $isDevelopment)),
    "github_enabled" => (bool) env("FRAMEWORK_UPDATE_GITHUB_ENABLED", true),
    "github_repository" => trim((string) env("FRAMEWORK_UPDATE_GITHUB_REPOSITORY", "techayoDEV/fnlla")),
    "github_clone_url" => trim((string) env("FRAMEWORK_UPDATE_GITHUB_CLONE_URL", "")),
    "github_api_base_url" => rtrim((string) env("FRAMEWORK_UPDATE_GITHUB_API_BASE_URL", "https://api.github.com"), "/"),
    "github_timeout_seconds" => max(5, (int) env("FRAMEWORK_UPDATE_GITHUB_TIMEOUT_SECONDS", 20)),
    "download_cache_path" => trim((string) env("FRAMEWORK_UPDATE_DOWNLOAD_CACHE_PATH", "framework/updates/fnlla")),
    "source_path" => trim((string) env("FRAMEWORK_UPDATE_SOURCE_PATH", env("STARTER_UPDATE_SOURCE_PATH", ""))),
];
