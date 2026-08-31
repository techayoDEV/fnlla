<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA CONFIGURATION FILE
File: config\observability.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

Purpose:
- Controls access logging, request timing headers and lightweight local metrics.
*/

return [
    "access_log" => [
        /*
        Access logs are structured JSON entries written through the existing
        redacting logger. They are meant for local files today and can later be
        forwarded to a log collector without changing the request lifecycle.
        */
        "enabled" => (bool) env("OBSERVABILITY_ACCESS_LOG_ENABLED", true),
    ],
    "response_time_header" => [
        /*
        This header is useful for staging and local diagnosis. Production teams
        that consider timing headers too chatty can disable it without losing
        server-side access logs or metrics.
        */
        "enabled" => (bool) env("OBSERVABILITY_RESPONSE_TIME_HEADER_ENABLED", true),
        "name" => (string) env("OBSERVABILITY_RESPONSE_TIME_HEADER", "X-Response-Time"),
    ],
    "metrics" => [
        /*
        The file recorder is intentionally simple and dependency-free. It gives
        small deployments a useful heartbeat while keeping the interface ready
        for a future OpenTelemetry/Prometheus adapter.
        */
        "enabled" => (bool) env("OBSERVABILITY_METRICS_ENABLED", true),
        "path" => (string) env("OBSERVABILITY_METRICS_PATH", "framework/metrics.json"),
    ],
    "analytics" => [
        /*
        Developer Panel analytics are first-party, aggregate-only and local by
        default. They avoid raw IP addresses, raw user agents and visitor
        fingerprinting while still giving teams enough signal to debug traffic,
        forms, conversion goals and slow routes without external analytics.
        */
        "enabled" => (bool) env("OBSERVABILITY_ANALYTICS_ENABLED", true),
        "retention_days" => max(1, (int) env("OBSERVABILITY_ANALYTICS_RETENTION_DAYS", 90)),
        "sample_rate" => max(1, min(100, (int) env("OBSERVABILITY_ANALYTICS_SAMPLE_RATE", 100))),
        "bot_filtering" => (bool) env("OBSERVABILITY_ANALYTICS_BOT_FILTERING", true),
        "device_detection" => (bool) env("OBSERVABILITY_ANALYTICS_DEVICE_DETECTION", true),
        "track_query_strings" => (bool) env("OBSERVABILITY_ANALYTICS_TRACK_QUERY_STRINGS", false),
        "goals" => [
            [
                "key" => "form_submission",
                "label" => "Form submissions",
                "metric" => "form_submissions",
            ],
            [
                "key" => "healthy_runtime",
                "label" => "Healthy runtime checks",
                "route" => "api.health",
            ],
        ],
    ],
    "heatmap" => [
        /*
        First-party behavior telemetry is the local alternative to Clarity-style
        click and scroll maps. It records aggregate page zones and depth buckets
        after analytics consent, without session replay, raw cursor trails,
        keystrokes, IP addresses, user agents or visitor fingerprints.
        */
        "enabled" => (bool) env("OBSERVABILITY_HEATMAP_ENABLED", true),
        "sample_rate" => max(1, min(100, (int) env("OBSERVABILITY_HEATMAP_SAMPLE_RATE", 100))),
        "click_grid_columns" => max(1, min(12, (int) env("OBSERVABILITY_HEATMAP_GRID_COLUMNS", 5))),
        "click_grid_rows" => max(1, min(12, (int) env("OBSERVABILITY_HEATMAP_GRID_ROWS", 5))),
    ],
    "slow_route_threshold_ms" => max(1, (int) env("OBSERVABILITY_SLOW_ROUTE_THRESHOLD_MS", 750)),
];
