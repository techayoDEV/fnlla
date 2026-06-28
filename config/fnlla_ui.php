<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA PHP CONFIGURATION FILE
File: config\fnlla_ui.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA PHP is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA PHP framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Defines maintained application or framework configuration for the official FNLLA PHP stack.
*/

$explicitEnvironment = $_ENV["APP_ENV"] ?? $_SERVER["APP_ENV"] ?? getenv("APP_ENV");
$hasExplicitEnvironment = is_string($explicitEnvironment) && trim($explicitEnvironment) !== "";
$environment = $hasExplicitEnvironment
    ? trim((string) $explicitEnvironment)
    : (is_file(base_path(".env")) ? "production" : "development");
$isDevelopmentLike = $environment === "development" || (!$hasExplicitEnvironment && !is_file(base_path(".env")));

return [
    "enforce" => (bool) env("FNLLA_UI_ENFORCE", $isDevelopmentLike),
    "auto_sync" => (bool) env("FNLLA_UI_AUTO_SYNC", $isDevelopmentLike),
    "check_interval_seconds" => max(0, (int) env("FNLLA_UI_SYNC_INTERVAL_SECONDS", 900)),
    "sync_script" => (string) env("FNLLA_UI_SYNC_SCRIPT", "scripts/sync-fnlla-ui.ps1"),
    "state_path" => storage_path((string) env("FNLLA_UI_STATE_PATH", "framework/fnlla-ui-guard.json")),
    "version_file" => public_path("vendor/fnlla-ui/VERSION"),
    "layout_path" => base_path("views/layouts/app.php"),
    "page_view_glob" => base_path("views/pages/*.php"),
    "required_runtime_files" => [
        public_path("vendor/fnlla-ui/assets/css/fnlla-ui.css"),
        public_path("vendor/fnlla-ui/assets/js/fnlla-ui.js"),
        public_path("vendor/fnlla-ui/assets/icons"),
        public_path("vendor/fnlla-ui/VERSION"),
    ],
    "required_layout_markers" => [
        '<div class="wrapper">',
        "<main",
        "<footer",
        'asset("vendor/fnlla-ui/assets/css/fnlla-ui.css")',
        'asset("vendor/fnlla-ui/assets/js/fnlla-ui.js")',
    ],
    "required_page_markers" => [
        'class="section',
        'class="container',
    ],
    "scan_paths" => [
        base_path("views/layouts/*.php"),
        base_path("views/pages/*.php"),
        public_path("assets/*.css"),
    ],
    "forbidden_markers" => [
        '/cdn\.tailwindcss\.com/i',
        '/(?:href|src|@import)[^\\r\\n]*(?:tailwind|tailwindcss)/i',
        '/(?:href|src|@import)[^\\r\\n]*bootstrap(?:\.min)?\.(?:css|js)/i',
        '/(?:href|src|@import)[^\\r\\n]*bulma/i',
        '/(?:href|src|@import)[^\\r\\n]*foundation/i',
        '/(?:href|src|@import)[^\\r\\n]*uikit/i',
        '/(?:href|src|@import)[^\\r\\n]*materialize/i',
        '/(?:href|src|@import)[^\\r\\n]*semantic-ui/i',
    ],
];
