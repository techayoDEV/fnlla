<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA CONSOLE SOURCE
File: src\Console\Commands\SeedCommand.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Implements the maintained CLI surface and scheduler-oriented console behavior.
*/

namespace Fnlla\Php\Console\Commands;

use Fnlla\Php\Console\Command;
use Fnlla\Php\Database\Seeders\Seeder;
use RuntimeException;

final class SeedCommand extends Command
{
    public function name(): string
    {
        return "db:seed";
    }

    public function description(): string
    {
        return "Run database seeders.";
    }

    public function handle(array $arguments): int
    {
        $class = $arguments[0] ?? "Database\\Seeders\\DatabaseSeeder";
        $class = str_replace("/", "\\", (string) $class);

        if (!class_exists($class)) {
            throw new RuntimeException("Seeder class not found: " . $class);
        }

        $seeder = $this->container->make($class);

        if (!$seeder instanceof Seeder) {
            throw new RuntimeException("Seeder must extend " . Seeder::class . ": " . $class);
        }

        $seeder->run();
        $this->line("Seeded: " . $class);

        return 0;
    }
}
