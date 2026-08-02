<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA CONSOLE SOURCE
File: src\Console\Commands\OptimizeClearCommand.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

Purpose:
- Removes bootstrap cache files created by `optimize`, `config:cache` and
  `route:cache`.
*/

namespace Fnlla\Php\Console\Commands;

use Fnlla\Php\Console\Command;

final class OptimizeClearCommand extends Command
{
    public function name(): string
    {
        return "optimize:clear";
    }

    public function description(): string
    {
        return "Remove bootstrap caches, asset manifest and preload file.";
    }

    public function handle(array $arguments): int
    {
        foreach ([framework_config_cache_path(), framework_route_cache_path(), framework_asset_manifest_path(), framework_preload_path()] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        $this->line("Bootstrap and warm caches cleared.");

        return 0;
    }
}
