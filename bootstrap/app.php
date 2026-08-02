<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA BOOTSTRAP FILE
File: bootstrap\app.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Bootstraps a framework runtime stage or shared application environment boundary.
*/

use Fnlla\Php\Application;
use Fnlla\Php\Exceptions\ExceptionHandler;
use Fnlla\Php\Session\SessionStore;

$container = require __DIR__ . DIRECTORY_SEPARATOR . "common.php";

$container->instance(SessionStore::class, new SessionStore());
$router = require APP_ROOT . DIRECTORY_SEPARATOR . "bootstrap" . DIRECTORY_SEPARATOR . "router.php";

$application = new Application(
    $router,
    $container,
    $container->make(ExceptionHandler::class)
);
/*
Keep the HTTP edge deliberately small. Service registration belongs in
FrameworkServiceProvider; this bootstrap file only wires the global middleware
that must run before route matching or controller execution.
*/
$application->middleware(["trusted-hosts", "cors", "maintenance"]);

return $application;
