<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA PHP ROUTE DEFINITION
File: routes\console.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA PHP is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA PHP framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Registers maintained HTTP or console routes for the framework runtime.
*/

use Fnlla\Php\Console\Scheduling\Schedule;

if (!isset($schedule) || !$schedule instanceof Schedule) {
    return;
}

$schedule->command("cache:clear")->dailyAt("03:00");
