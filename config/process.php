<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA CONFIGURATION FILE
File: config\process.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Defines bounded process execution defaults for maintainer-side tooling.
*/

return [
    "default_timeout_seconds" => max(1, (int) env("PROCESS_TIMEOUT_SECONDS", 300)),
    "output_limit_bytes" => max(1024, (int) env("PROCESS_OUTPUT_LIMIT_BYTES", 1048576)),
];
