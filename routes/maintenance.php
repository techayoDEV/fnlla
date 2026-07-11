<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA ROUTE DEFINITION
File: routes\maintenance.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Registers the framework-maintenance page kept as a framework-managed surface
  so downstream applications can update their framework base without reopening
  the full maintainer workspace.
*/

use Fnlla\Php\Controllers\FrameworkUpdateController;
use Fnlla\Php\Controllers\HomeController;

$router->get("/maintenance", [HomeController::class, "maintenanceHome"])->name("maintenance.home");
$router->get("/maintenance/health", [HomeController::class, "healthPage"])->name("health");
$router->get("/maintenance/framework-update", [FrameworkUpdateController::class, "show"])->name("maintenance.framework_update");
$router->post("/maintenance/framework-update/run", [FrameworkUpdateController::class, "run"])->middleware("csrf")->throttle(5, 1)->name("maintenance.framework_update.run");
