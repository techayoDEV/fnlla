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

use Fnlla\Php\Support\FrameworkIdentity;

$isDevelopment = framework_detect_environment() === "development";

return [
    "ui_enabled" => (bool) env("FRAMEWORK_UPDATE_UI_ENABLED", $isDevelopment),
    "ui_local_only" => (bool) env("FRAMEWORK_UPDATE_UI_LOCAL_ONLY", true),
    "ui_apply_enabled" => (bool) env("FRAMEWORK_UPDATE_UI_APPLY_ENABLED", $isDevelopment),
    "github_enabled" => (bool) env("FRAMEWORK_UPDATE_GITHUB_ENABLED", true),
    "official_repository" => FrameworkIdentity::REPOSITORY,
    "github_repository" => FrameworkIdentity::REPOSITORY,
    "github_clone_url" => FrameworkIdentity::REPOSITORY_URL,
    "github_api_base_url" => FrameworkIdentity::GITHUB_API_BASE_URL,
    "github_timeout_seconds" => max(5, (int) env("FRAMEWORK_UPDATE_GITHUB_TIMEOUT_SECONDS", 20)),
    "download_cache_path" => trim((string) env("FRAMEWORK_UPDATE_DOWNLOAD_CACHE_PATH", "framework/updates/fnlla")),
    "dry_run_report_enabled" => (bool) env("FRAMEWORK_UPDATE_DRY_RUN_REPORT_ENABLED", true),
    "dry_run_report_path" => trim((string) env("FRAMEWORK_UPDATE_DRY_RUN_REPORT_PATH", "framework/updates/fnlla/dry-run-report.json")),
    "audit_log_enabled" => (bool) env("FRAMEWORK_UPDATE_AUDIT_LOG_ENABLED", true),
    "audit_log_path" => trim((string) env("FRAMEWORK_UPDATE_AUDIT_LOG_PATH", "logs/framework-update.log")),
    "source_path" => "",
    "allow_local_sources" => false,
    "allow_repository_override" => false,
    "post_install_checks" => (bool) env("FRAMEWORK_UPDATE_POST_INSTALL_CHECKS", true),
    "apply_policy" => [
        "backup_confirmed" => (bool) env("FRAMEWORK_UPDATE_APPLY_BACKUP_CONFIRMED", false),
        "signed_artifact_confirmed" => (bool) env("FRAMEWORK_UPDATE_APPLY_SIGNED_ARTIFACT_CONFIRMED", false),
        "maintenance_window_confirmed" => (bool) env("FRAMEWORK_UPDATE_APPLY_MAINTENANCE_WINDOW_CONFIRMED", false),
        "ci_approval_confirmed" => (bool) env("FRAMEWORK_UPDATE_APPLY_CI_APPROVAL_CONFIRMED", false),
    ],
    "lock_file" => trim((string) env("FNLLA_FRAMEWORK_LOCK_FILE", ".fnlla/framework-lock.json")),
    "migration_lock_file" => trim((string) env("FNLLA_MIGRATION_LOCK_FILE", ".fnlla/legacy-framework-lock.json")),
];
