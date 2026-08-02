<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA CONFIGURATION FILE
File: config\health.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

Purpose:
- Controls health endpoint caching and operator-facing readiness payload costs.
*/

return [
    /*
    The health endpoint still adds request-specific fields on every hit, but
    expensive filesystem/release/version checks can be reused briefly. This keeps
    probes cheap without hiding state long enough to surprise operators.
    */
    "cache_ttl_seconds" => max(0, (int) env("HEALTH_CACHE_TTL_SECONDS", 10)),
];
