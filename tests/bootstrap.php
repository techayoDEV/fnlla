<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA TEST CASE
File: tests\bootstrap.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Validates maintained framework behaviour inside the repository-local test harness.
*/

$_ENV["APP_ENV"] = "testing";
$_SERVER["APP_ENV"] = "testing";
$_ENV["APP_DEBUG"] = "false";
$_SERVER["APP_DEBUG"] = "false";
$_SESSION = [];

$container = require dirname(__DIR__) . DIRECTORY_SEPARATOR . "bootstrap" . DIRECTORY_SEPARATOR . "common.php";
$GLOBALS["fnlla_config"]["app"]["environment"] = "testing";
$GLOBALS["fnlla_config"]["app"]["debug"] = false;
$GLOBALS["fnlla_config"]["app"]["log_path"] = storage_path("logs/test.log");
$GLOBALS["fnlla_php_config"] = $GLOBALS["fnlla_config"];

// Source archives omit private storage, including Git's empty-directory placeholders.
foreach (["app", "database", "framework/cache", "framework/queue", "framework/sessions", "logs", "uploads"] as $directory) {
    $path = storage_path($directory);
    if (!is_dir($path) && !mkdir($path, 0700, true) && !is_dir($path)) {
        throw new RuntimeException("Unable to prepare test storage: " . $directory);
    }
}
