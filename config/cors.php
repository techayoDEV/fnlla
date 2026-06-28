<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA PHP CONFIGURATION FILE
File: config\cors.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA PHP is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA PHP framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Defines maintained application or framework configuration for the official FNLLA PHP stack.
*/

return [
    "allowed_origins" => ["*"],
    "allowed_methods" => ["GET", "POST", "PUT", "PATCH", "DELETE", "OPTIONS"],
    "allowed_headers" => ["Content-Type", "Authorization", "X-Requested-With", "X-Request-Id", "X-CSRF-TOKEN"],
    "supports_credentials" => false,
    "max_age" => 3600,
];
