<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA CONSOLE SOURCE
File: src\Console\Commands\RouteCacheCommand.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Compiles route definitions into a bootstrap cache file.
*/

namespace Fnlla\Php\Console\Commands;

use Fnlla\Php\Console\Command;

final class RouteCacheCommand extends Command
{
    public function name(): string
    {
        return "route:cache";
    }

    public function description(): string
    {
        return "Compile route definitions into the bootstrap cache.";
    }

    public function handle(array $arguments): int
    {
        $path = framework_route_cache_path();

        if (is_file($path)) {
            unlink($path);
        }

        $container = $this->container;
        $router = require base_path("bootstrap/router.php");
        $routes = $router->exportCache();
        $directory = dirname($path);

        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        file_put_contents($path, "<?php\n\nreturn " . var_export($routes, true) . ";\n", LOCK_EX);
        $this->line("Routes cached: " . $path);

        return 0;
    }
}
