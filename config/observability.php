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
];
