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

use Fnlla\Php\Controllers\AuthController;
use Fnlla\Php\Controllers\HomeController;
use Fnlla\Php\Http\Resources\JsonResource;
use Fnlla\Php\Http\Request;
use Fnlla\Php\Http\Response;

$router->get("/", [HomeController::class, "home"])->name("home");
$router->get("/about", [HomeController::class, "about"])->name("about");
$router->get("/contact", [HomeController::class, "contact"])->name("contact");
$router->post("/contact", [HomeController::class, "sendContact"])->middleware("csrf")->throttle(5, 1)->name("contact.submit");
$router->get("/login", [AuthController::class, "loginForm"])->name("login");
$router->post("/login", [AuthController::class, "login"])->middleware("csrf")->throttle(5, 1)->name("login.submit");
$router->get("/dashboard", [AuthController::class, "dashboard"])->middleware("auth")->authorize("view-dashboard")->name("dashboard");
$router->get("/admin", [AuthController::class, "admin"])->middleware("auth")->authorize("manage-admin-area")->name("admin");
$router->post("/logout", [AuthController::class, "logout"])->middleware(["csrf", "auth"])->name("logout");
$router->get("/projects/{project}", static fn (string $project): array => [
    "project" => $project,
    "status" => "ok",
])->name("projects.show");

$router->group([
    "prefix" => "api",
    "as" => "api.",
    "middleware" => "throttle",
], static function ($router): void {
    $router->get("/health", static fn (Request $request): Response => Response::json([
        "name" => config("app.name"),
        "status" => "ok",
        "timestamp" => gmdate(DATE_ATOM),
        "request_path" => $request->path(),
    ]))->name("health");

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
