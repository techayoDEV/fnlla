<?php

declare(strict_types=1);

use App\Controllers\HomeController as PlainHomeController;

$router->get("/", [PlainHomeController::class, "index"])->name("home");
$router->get("/api/health", [PlainHomeController::class, "health"])->name("api.health");
