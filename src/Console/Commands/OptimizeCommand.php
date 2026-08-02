<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA CONSOLE SOURCE
File: src\Console\Commands\OptimizeCommand.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

Purpose:
- Builds bootstrap caches used by production-style deployments.
*/

namespace Fnlla\Php\Console\Commands;

use Fnlla\Php\Console\Command;

final class OptimizeCommand extends Command
{
    public function name(): string
    {
        return "optimize";
    }

    public function description(): string
    {
        return "Build configuration and route bootstrap caches.";
    }

    public function handle(array $arguments): int
    {
        $configExit = (new ConfigCacheCommand($this->container))->handle([]);
        $routeExit = (new RouteCacheCommand($this->container))->handle([]);

        return $configExit === 0 && $routeExit === 0 ? 0 : 1;
    }
}
