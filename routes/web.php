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

use Fnlla\Php\Controllers\DocsController;
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
if (has_local_docs_workspace()) {
    $router->get("/docs", [DocsController::class, "index"])->name("docs.home");
    $router->get("/docs/assets/docs.css", [DocsController::class, "stylesheet"])->name("docs.asset.stylesheet");
    $router->get("/docs/assets/docs.js", [DocsController::class, "script"])->name("docs.asset.script");
    $router->get("/docs/assets/brand/fnlla-runtime.svg", [DocsController::class, "runtimeBrandIcon"])->name("docs.asset.runtime_brand");
    $router->get("/docs/assets/brand/fnlla.svg", [DocsController::class, "brandIcon"])->name("docs.asset.brand");
    $router->get("/docs/{page}", [DocsController::class, "page"])->name("docs.page");
}

$router->get("/", [HomeController::class, "projectHome"])->name("home");
$router->get("/about", [PageController::class, "about"])->name("about");
$router->get("/services", [PageController::class, "services"])->name("services");
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
