<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA CONFIGURATION FILE
File: config\license.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Defines local FNLLA commercial licence verification settings.
*/

return [
    "issuer" => (string) env("FNLLA_LICENSE_ISSUER", "fnlla.com"),
    "secret" => (string) env("FNLLA_LICENSE_SECRET", "fnlla-local-license-secret-change-before-production"),
    "token_prefix" => "fnlla_license_v1",
    "installed_path" => (string) env("FNLLA_LICENSE_PATH", "framework/license.json"),
];
