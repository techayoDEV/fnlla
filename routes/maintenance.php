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

use Fnlla\Php\Controllers\DeveloperAccessController;
use Fnlla\Php\Controllers\FrameworkUpdateController;
use Fnlla\Php\Controllers\HomeController;

$router->get("/maintenance", [HomeController::class, "maintenanceHome"])->name("maintenance.home");
$router->post("/maintenance/setup-access", [HomeController::class, "setupMaintenanceAccess"])->middleware("csrf")->name("maintenance.setup_access");
$router->post("/maintenance/setup-developer-access", [HomeController::class, "setupDeveloperAccess"])->middleware(["csrf", "developer-operations"])->name("maintenance.setup_developer_access");
$router->post("/maintenance/unlock", [HomeController::class, "unlockMaintenance"])->middleware("csrf")->name("maintenance.unlock");
$router->post("/maintenance/lock", [HomeController::class, "lockMaintenance"])->middleware(["csrf", "developer-operations"])->name("maintenance.lock");
$router->get("/maintenance/health", [HomeController::class, "healthPage"])->middleware("developer-operations")->name("health");
$router->get("/maintenance/framework-update", [FrameworkUpdateController::class, "show"])->middleware("developer-operations")->name("maintenance.framework_update");
$router->post("/maintenance/framework-update/run", [FrameworkUpdateController::class, "run"])->middleware(["csrf", "developer-operations"])->throttle(5, 1)->name("maintenance.framework_update.run");

$developerAccessPath = developer_access()->path();

if ($developerAccessPath !== "") {
    $router->get($developerAccessPath, [DeveloperAccessController::class, "entry"])->name("developer.entry");
    $router->post($developerAccessPath . "/unlock", [DeveloperAccessController::class, "unlock"])->middleware("csrf")->name("developer.unlock");
    $router->get($developerAccessPath . "/panel", [DeveloperAccessController::class, "show"])->middleware("developer-session")->name("developer.panel");
    $router->post($developerAccessPath . "/panel/lock", [DeveloperAccessController::class, "lock"])->middleware(["csrf", "developer-session"])->name("developer.lock");
    $router->post($developerAccessPath . "/panel/settings/maintenance", [DeveloperAccessController::class, "updateMaintenanceCredentials"])->middleware(["csrf", "developer-session"])->name("developer.settings.maintenance");
    $router->post($developerAccessPath . "/panel/settings/password", [DeveloperAccessController::class, "updateDeveloperPassword"])->middleware(["csrf", "developer-session"])->name("developer.settings.password");
    $router->post($developerAccessPath . "/panel/settings/nav-mode", [DeveloperAccessController::class, "updateNavigationMode"])->middleware(["csrf", "developer-session"])->name("developer.settings.nav_mode");
    $router->post($developerAccessPath . "/panel/settings/rotate-path", [DeveloperAccessController::class, "rotatePath"])->middleware(["csrf", "developer-session"])->name("developer.settings.rotate_path");
}
