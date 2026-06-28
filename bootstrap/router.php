<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA PHP BOOTSTRAP FILE
File: bootstrap\router.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA PHP is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA PHP framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Bootstraps a framework runtime stage or shared application environment boundary.
*/

use Fnlla\Php\Auth\Middleware\Authorize;
use Fnlla\Php\Auth\Middleware\Authenticate;
use Fnlla\Php\Container\Container;
use Fnlla\Php\Middleware\HandleCors;
use Fnlla\Php\Middleware\ThrottleRequests;
use Fnlla\Php\Middleware\VerifyCsrfToken;
use Fnlla\Php\Routing\Router;

if (!isset($container) || !$container instanceof Container) {
    throw new RuntimeException("Container must be available before loading routes.");
}

$router = $container->make(Router::class);
$router->middleware("csrf", VerifyCsrfToken::class);
$router->middleware("auth", Authenticate::class);
$router->middleware("authorize", Authorize::class);
$router->middleware("cors", HandleCors::class);
$router->middleware("throttle", ThrottleRequests::class);

require APP_ROOT . DIRECTORY_SEPARATOR . "routes" . DIRECTORY_SEPARATOR . "web.php";

return $router;
