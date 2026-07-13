<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA CONFIGURATION FILE
File: config\fnlla_runtime.php
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
$isDevelopmentLike = $environment === "development";

return [
    "enforce" => (bool) env("FNLLA_RUNTIME_ENFORCE", $isDevelopmentLike),
    "auto_sync" => (bool) env("FNLLA_RUNTIME_AUTO_SYNC", $isDevelopmentLike),
    "check_interval_seconds" => max(0, (int) env("FNLLA_RUNTIME_SYNC_INTERVAL_SECONDS", 900)),
    "sync_script" => (string) env("FNLLA_RUNTIME_SYNC_SCRIPT", "scripts/sync-fnlla-runtime.ps1"),
    "state_path" => storage_path((string) env("FNLLA_RUNTIME_STATE_PATH", "framework/fnlla-runtime-guard.json")),
    "version_file" => public_path("vendor/fnlla-runtime/VERSION"),
    "layout_path" => base_path("views/layouts/app.php"),
    "page_view_glob" => base_path("views/pages/*.php"),
    "required_runtime_files" => [
        public_path("vendor/fnlla-runtime/assets/css/fnlla-runtime.css"),
        public_path("vendor/fnlla-runtime/assets/js/fnlla-runtime.js"),
        public_path("vendor/fnlla-runtime/assets/icons"),
        public_path("vendor/fnlla-runtime/VERSION"),
    ],
    "required_layout_markers" => [
        "<header",
        "<main",
        "<footer",
        'asset("vendor/fnlla-runtime/assets/css/fnlla-runtime.css")',
        'asset("vendor/fnlla-runtime/assets/js/fnlla-runtime.js")',
    ],
    "required_page_markers" => [
        'class="section',
        'class="container',
    ],
    "required_text_markers" => [
        public_path("vendor/fnlla-runtime/README.md") => [
            "integrated FNLLA UI surface handoff for downstream projects",
            "public/vendor/fnlla-runtime/",
            "scripts/sync-fnlla-runtime.ps1",
        ],
        public_path("vendor/fnlla-runtime/MANIFEST.json") => [
            '"distribution_root": "."',
        ],
        public_path("vendor/fnlla-runtime/assets/css/fnlla-runtime.css") => [
            "Integrated vendored runtime stylesheet shipped from public/vendor/fnlla-runtime/assets/css/fnlla-runtime.css.",
        ],
    ],
    "forbidden_text_markers" => [
        public_path("vendor/fnlla-runtime/README.md") => [
            '/publish-fnlla-runtime\.mjs/i',
            '/dist\/fnlla-runtime/i',
            '/repository root `assets\/` tree/i',
        ],
        public_path("vendor/fnlla-runtime/MANIFEST.json") => [
            '/"distribution_root": "dist\/fnlla-runtime"/',
        ],
        public_path("vendor/fnlla-runtime/assets/css/fnlla-runtime.css") => [
            '/src\/css\//i',
            '/publish-fnlla-runtime\.mjs/i',
        ],
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
