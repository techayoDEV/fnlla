<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA PHP ROUTE DEFINITION
File: routes\web.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA PHP is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA PHP framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Registers maintained HTTP or console routes for the framework runtime.
*/

use Fnlla\Php\Controllers\DocsController;
use Fnlla\Php\Controllers\HomeController;
use Fnlla\Php\Http\Resources\JsonResource;
use Fnlla\Php\Http\Request;
use Fnlla\Php\Http\Response;

if (has_local_docs_workspace()) {
    $router->get("/docs", [DocsController::class, "index"])->name("docs.home");
    $router->get("/docs/assets/docs.css", [DocsController::class, "stylesheet"])->name("docs.asset.stylesheet");
    $router->get("/docs/assets/docs.js", [DocsController::class, "script"])->name("docs.asset.script");
    $router->get("/docs/assets/brand/fnlla-web.svg", [DocsController::class, "brandIcon"]);
    $router->get("/docs/assets/brand/fnlla-php.svg", [DocsController::class, "brandIcon"])->name("docs.asset.brand");
    $router->get("/docs/{page}", [DocsController::class, "page"])->name("docs.page");
}

$router->get("/", [HomeController::class, "home"])->name("home");
$router->get("/project/launch", [HomeController::class, "projectLaunch"])->name("project.launch");
$router->get("/health", [HomeController::class, "redirectHealthToMaintenance"]);
$router->get("/contact", [HomeController::class, "contact"])->name("contact");
$router->post("/contact", [HomeController::class, "sendContact"])->middleware("csrf")->throttle(5, 1)->name("contact.submit");

$router->group([
    "prefix" => "api",
    "as" => "api.",
    "middleware" => "throttle",
], static function ($router): void {
    $router->get("/health", [HomeController::class, "healthApi"])->name("health");

    $router->get("/profile", static fn (): JsonResource => new class([
        "name" => config("app.name"),
        "version" => "1.0",
        "supports" => ["routing", "middleware", "auth", "queues"],
    ]) extends JsonResource {
        public function toArray(): array
        {
            return [
                "meta" => $this->resource,
            ];
        }
    })->name("profile");
});
