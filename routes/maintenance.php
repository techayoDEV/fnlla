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
$router->get("/developer-panel-setup", [HomeController::class, "developerSetupAlias"])->name("developer.setup");
$router->post("/maintenance/setup-access", [HomeController::class, "setupMaintenanceAccess"])->middleware("csrf")->name("maintenance.setup_access");
$router->post("/maintenance/setup-developer-access", [HomeController::class, "setupDeveloperAccess"])->middleware(["csrf", "developer-operations"])->name("maintenance.setup_developer_access");
$router->post("/maintenance/unlock", [HomeController::class, "unlockMaintenance"])->middleware("csrf")->name("maintenance.unlock");
$router->post("/maintenance/lock", [HomeController::class, "lockMaintenance"])->middleware(["csrf", "developer-operations"])->name("maintenance.lock");
$router->get("/maintenance/health", [HomeController::class, "healthPage"])->middleware("developer-operations")->name("health");
$router->get("/maintenance/framework-update", [FrameworkUpdateController::class, "show"])->middleware("developer-operations")->name("maintenance.framework_update");
$router->post("/maintenance/framework-update/run", [FrameworkUpdateController::class, "run"])->middleware(["csrf", "developer-operations"])->throttle(5, 1)->name("maintenance.framework_update.run");

if (developer_access()->enabled()) {
    $router->get("/developer", [DeveloperAccessController::class, "entry"])->name("developer.login");
    $router->post("/developer/unlock", [DeveloperAccessController::class, "unlock"])->middleware("csrf")->name("developer.login.unlock");
    $router->get("/developer/panel", [DeveloperAccessController::class, "show"])->middleware("developer-session")->name("developer.panel");
    $router->post("/developer/panel/lock", [DeveloperAccessController::class, "lock"])->middleware(["csrf", "developer-session"])->name("developer.lock");
    $router->post("/developer/panel/settings/maintenance", [DeveloperAccessController::class, "updateMaintenanceCredentials"])->middleware(["csrf", "developer-session"])->name("developer.settings.maintenance");
    $router->post("/developer/panel/settings/password", [DeveloperAccessController::class, "updateDeveloperPassword"])->middleware(["csrf", "developer-session"])->name("developer.settings.password");
    $router->post("/developer/panel/settings/nav-mode", [DeveloperAccessController::class, "updateNavigationMode"])->middleware(["csrf", "developer-session"])->name("developer.settings.nav_mode");
}
