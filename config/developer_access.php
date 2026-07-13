<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA CONFIGURATION FILE
File: config\developer_access.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Defines the hidden developer access panel used after the public operations
  navigation is no longer meant to stay visible to the client.
*/

return [
    "enabled" => (bool) env("DEVELOPER_ACCESS_ENABLED", true),
    "path" => trim((string) env("DEVELOPER_ACCESS_PATH", "")),
    "password" => (string) env("DEVELOPER_ACCESS_PASSWORD", ""),
    "setup_ui_enabled" => (bool) env("DEVELOPER_ACCESS_SETUP_UI_ENABLED", framework_detect_environment() !== "production"),
    "setup_ui_local_only" => (bool) env("DEVELOPER_ACCESS_SETUP_UI_LOCAL_ONLY", true),
    "operations_nav_mode" => trim((string) env("DEVELOPER_OPERATIONS_NAV_MODE", "hidden")),
    "unlock_ttl_minutes" => max(1, (int) env("DEVELOPER_ACCESS_TTL_MINUTES", 120)),
    "max_attempts" => max(1, (int) env("DEVELOPER_ACCESS_MAX_ATTEMPTS", 5)),
    "attempt_window_minutes" => max(1, (int) env("DEVELOPER_ACCESS_WINDOW_MINUTES", 15)),
    "lockout_minutes" => max(1, (int) env("DEVELOPER_ACCESS_LOCKOUT_MINUTES", 15)),
    "path_prefix" => trim((string) env("DEVELOPER_ACCESS_PATH_PREFIX", "/_dev-")),
    "session_key" => "developer.access_unlocked",
    "unlocked_at_key" => "developer.access_unlocked_at",
    "expires_at_key" => "developer.access_expires_at",
];
