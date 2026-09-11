<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA CONFIGURATION FILE
File: config\developer_control.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

Purpose:
- Defines the local and remote developer control contract used to disable a
  project surface without coupling FNLLA to a private TechAyo admin product.
*/

return [
    "policy_profile" => trim((string) env("FNLLA_POLICY_PROFILE", "standard")),
    "regulated_mode" => (bool) env("FNLLA_REGULATED_MODE", false),
    "activity_log_driver" => trim((string) env("DEVELOPER_ACTIVITY_LOG_DRIVER", "file")),
    "local_state_path" => trim((string) env("DEVELOPER_CONTROL_STATE_PATH", "framework/developer/control.json")),
    "activity_log_path" => trim((string) env("DEVELOPER_ACTIVITY_LOG_PATH", "framework/developer/activity.jsonl")),
    "activity_log_table" => trim((string) env("DEVELOPER_ACTIVITY_LOG_TABLE", "fnlla_developer_activity_log")),
    "disabled_title" => (string) env("DEVELOPER_CONTROL_DISABLED_TITLE", "Service disabled by developer"),
    "disabled_message" => (string) env("DEVELOPER_CONTROL_DISABLED_MESSAGE", "This service is temporarily disabled by the developer team. Please contact the project developer for assistance."),
    "disabled_contact" => (string) env("DEVELOPER_CONTROL_DISABLED_CONTACT", "developer@example.com"),
    "service_provider" => (string) env("DEVELOPER_CONTROL_SERVICE_PROVIDER", "TechAyo Limited"),
    "suspended_title" => (string) env("DEVELOPER_CONTROL_SUSPENDED_TITLE", "Services suspended"),
    "suspended_message" => (string) env("DEVELOPER_CONTROL_SUSPENDED_MESSAGE", "Your services have been suspended. Please contact your service provider."),
    "remote" => [
        "enabled" => (bool) env("DEVELOPER_CONTROL_REMOTE_ENABLED", false),
        "endpoint" => trim((string) env("DEVELOPER_CONTROL_REMOTE_ENDPOINT", "")),
        "token" => trim((string) env("DEVELOPER_CONTROL_REMOTE_TOKEN", "")),
        "project_id" => trim((string) env("DEVELOPER_CONTROL_REMOTE_PROJECT_ID", env("PROJECT_ID", ""))),
        "tenant" => trim((string) env("DEVELOPER_CONTROL_REMOTE_TENANT", "techayo")),
        "signature_secret" => trim((string) env("DEVELOPER_CONTROL_REMOTE_SIGNATURE_SECRET", "")),
        "schema" => "fnlla.techayo_remote_control.v1",
        "cache_path" => trim((string) env("DEVELOPER_CONTROL_REMOTE_CACHE_PATH", "framework/developer/remote-control.json")),
        "cache_ttl_seconds" => max(5, (int) env("DEVELOPER_CONTROL_REMOTE_CACHE_TTL_SECONDS", 60)),
        "timeout_seconds" => max(1, (int) env("DEVELOPER_CONTROL_REMOTE_TIMEOUT_SECONDS", 5)),
        "fail_closed" => (bool) env("DEVELOPER_CONTROL_REMOTE_FAIL_CLOSED", false),
        "allowed_hosts" => array_values(array_filter(array_map("trim", explode(",", (string) env("DEVELOPER_CONTROL_REMOTE_ALLOWED_HOSTS", "techayo.co.uk,www.techayo.co.uk"))))),
    ],
];
