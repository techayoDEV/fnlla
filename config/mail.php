<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA PHP CONFIGURATION FILE
File: config\mail.php
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
    "default" => (string) env("MAIL_MAILER", "log"),
    "log_path" => (string) env("MAIL_LOG_PATH", "mail"),
    "from" => [
        "address" => (string) env("MAIL_FROM_ADDRESS", "no-reply@example.com"),
    ],
];
