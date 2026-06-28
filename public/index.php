<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA PHP PUBLIC ENTRYPOINT
File: public\index.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA PHP is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA PHP framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Handles a public web request or static file routing boundary for the maintained framework.
*/

$application = require dirname(__DIR__) . DIRECTORY_SEPARATOR . "bootstrap" . DIRECTORY_SEPARATOR . "app.php";
$application->run();
