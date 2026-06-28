<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA PHP CONSOLE SOURCE
File: src\Console\Commands\MigrateStatusCommand.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA PHP is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA PHP framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Implements the maintained CLI surface and scheduler-oriented console behavior.
*/

namespace Fnlla\Php\Console\Commands;

use Fnlla\Php\Console\Command;
use Fnlla\Php\Database\Migrations\Migrator;

final class MigrateStatusCommand extends Command
{
    public function name(): string
    {
        return "migrate:status";
    }

    public function description(): string
    {
        return "Show migration status.";
    }

    public function handle(array $arguments): int
    {
        $migrator = $this->container->make(Migrator::class);

        foreach ($migrator->status() as $migration) {
            $this->line(sprintf(
                "[%s] batch=%s %s",
                $migration["ran"] ? "x" : " ",
                $migration["batch"] ?? "-",
                $migration["migration"]
            ));
        }

        return 0;
    }
}
