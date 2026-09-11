<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA ROUTE DEFINITION
File: routes\web.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Registers maintained HTTP or console routes for the framework runtime.
*/

use Fnlla\Php\Controllers\HomeController;
use Fnlla\Php\Controllers\ConsentController;
use Fnlla\Php\Controllers\PageController;

/*
Public route contract:
- project-owned pages stay small and controller-backed
- FNLLA telemetry endpoints are first-party, throttled and consent-aware
- developer/customer/maintenance routes live in routes/maintenance.php so the
  public surface remains easy to scan during handover
*/
$router->get("/", [HomeController::class, "projectHome"])->name("home");
$router->get("/about", [PageController::class, "about"])->name("about");
$router->get("/contact", [PageController::class, "contact"])->name("contact");
$router->post("/contact", [PageController::class, "submitContact"])->middleware(["csrf", "throttle"])->name("contact.submit");
$router->post("/fnlla/consent", [ConsentController::class, "store"])->middleware("throttle")->name("fnlla.consent");
$router->post("/fnlla/analytics/event", [ConsentController::class, "analyticsEvent"])->middleware("throttle")->name("fnlla.analytics.event");
$router->get("/terms", [PageController::class, "terms"])->name("terms");
$router->get("/privacy", [PageController::class, "privacy"])->name("privacy");
$router->get("/health", [HomeController::class, "redirectHealthToMaintenance"]);

$router->group([
    "prefix" => "api",
    "as" => "api.",
    "middleware" => "throttle",
], static function ($router): void {
    $router->get("/health", [HomeController::class, "healthApi"])->name("health");

    $router->get("/profile", [HomeController::class, "profileApi"])->name("profile");
});
