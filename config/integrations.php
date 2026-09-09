<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA CONFIGURATION FILE
File: config\integrations.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

Purpose:
- Defines optional project-owned integration adapters. Third-party analytics,
  heatmap replay and error-reporting vendors are intentionally not part of the
  core FNLLA integration contract.
*/

return [
    "api_hooks" => [
        "enabled" => (bool) env("FNLLA_INTEGRATION_API_HOOKS_ENABLED", false),
        "endpoint" => trim((string) env("FNLLA_INTEGRATION_API_HOOKS_ENDPOINT", "")),
    ],
];
