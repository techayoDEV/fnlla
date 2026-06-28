<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA PHP TEST CASE
File: tests\bootstrap.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA PHP is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA PHP framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Validates maintained framework behavior inside the repository-local test harness.
*/

$_ENV["APP_ENV"] = "testing";
$_SERVER["APP_ENV"] = "testing";
$_ENV["APP_DEBUG"] = "false";
$_SERVER["APP_DEBUG"] = "false";
$_SESSION = [];

$container = require dirname(__DIR__) . DIRECTORY_SEPARATOR . "bootstrap" . DIRECTORY_SEPARATOR . "common.php";
$GLOBALS["fnlla_php_config"]["app"]["environment"] = "testing";
$GLOBALS["fnlla_php_config"]["app"]["debug"] = false;
$GLOBALS["fnlla_php_config"]["app"]["log_path"] = storage_path("logs/test.log");
