<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA CONFIGURATION FILE
File: config\integrations.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

Purpose:
- Defines optional Developer Panel integration adapters. They are disabled by
  default and only describe FNLLA-side connection settings.
*/

return [
    "ga4" => [
        "enabled" => (bool) env("FNLLA_INTEGRATION_GA4_ENABLED", false),
        "measurement_id" => trim((string) env("FNLLA_INTEGRATION_GA4_MEASUREMENT_ID", "")),
    ],
    "clarity" => [
        "enabled" => (bool) env("FNLLA_INTEGRATION_CLARITY_ENABLED", false),
        "project_id" => trim((string) env("FNLLA_INTEGRATION_CLARITY_PROJECT_ID", "")),
    ],
    "sentry" => [
        "enabled" => (bool) env("FNLLA_INTEGRATION_SENTRY_ENABLED", false),
        "dsn" => trim((string) env("FNLLA_INTEGRATION_SENTRY_DSN", "")),
        "environment" => trim((string) env("FNLLA_INTEGRATION_SENTRY_ENVIRONMENT", env("APP_ENV", "development"))),
    ],
    "api_hooks" => [
        "enabled" => (bool) env("FNLLA_INTEGRATION_API_HOOKS_ENABLED", false),
        "endpoint" => trim((string) env("FNLLA_INTEGRATION_API_HOOKS_ENDPOINT", "")),
    ],
    "heatmaps" => [
        "enabled" => (bool) env("FNLLA_INTEGRATION_HEATMAPS_ENABLED", false),
        "provider" => trim((string) env("FNLLA_INTEGRATION_HEATMAPS_PROVIDER", "")),
    ],
];
