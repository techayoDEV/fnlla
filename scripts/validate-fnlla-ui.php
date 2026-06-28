<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA PHP MAINTAINER SCRIPT
File: scripts\validate-fnlla-ui.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA PHP is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA PHP framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Supports framework maintenance, validation, release hygiene or repository hardening.
*/

define("FNLLA_UI_SKIP_AUTO_GUARD", true);

require dirname(__DIR__) . DIRECTORY_SEPARATOR . "bootstrap" . DIRECTORY_SEPARATOR . "common.php";

\Fnlla\Php\Support\FnllaUiGuard::validateOnly();

fwrite(STDOUT, "FNLLA UI contract passed." . PHP_EOL);
