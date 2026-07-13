<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA CONFIGURATION FILE
File: config\maintenance.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Defines password-protected maintenance access for downstream FNLLA projects.
*/

return [
    "enabled" => (bool) env("MAINTENANCE_MODE_ENABLED", false),
    "username" => trim((string) env("MAINTENANCE_ACCESS_USERNAME", "")),
    "password" => (string) env("MAINTENANCE_ACCESS_PASSWORD", ""),
    "setup_ui_enabled" => (bool) env("MAINTENANCE_SETUP_UI_ENABLED", framework_detect_environment() !== "production"),
    "setup_ui_local_only" => (bool) env("MAINTENANCE_SETUP_UI_LOCAL_ONLY", true),
    "unlock_ttl_minutes" => max(1, (int) env("MAINTENANCE_ACCESS_TTL_MINUTES", 10)),
    "max_attempts" => max(1, (int) env("MAINTENANCE_ACCESS_MAX_ATTEMPTS", 5)),
    "attempt_window_minutes" => max(1, (int) env("MAINTENANCE_ACCESS_WINDOW_MINUTES", 15)),
    "lockout_minutes" => max(1, (int) env("MAINTENANCE_ACCESS_LOCKOUT_MINUTES", 15)),
    "session_key" => "maintenance.access_unlocked",
    "unlocked_at_key" => "maintenance.access_unlocked_at",
    "expires_at_key" => "maintenance.access_expires_at",
    "env_path" => base_path(".env"),
    "env_example_path" => base_path(".env.example"),
];
