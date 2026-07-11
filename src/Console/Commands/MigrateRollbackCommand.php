<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA CONSOLE SOURCE
File: src\Console\Commands\MigrateRollbackCommand.php
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
use Fnlla\Php\Database\Migrations\Migrator;

final class MigrateRollbackCommand extends Command
{
    public function name(): string
    {
        return "migrate:rollback";
    }

    public function description(): string
    {
        return "Rollback the most recent migration batch.";
    }

    public function handle(array $arguments): int
    {
        $steps = isset($arguments[0]) ? max(1, (int) $arguments[0]) : 1;
        $migrator = $this->container->make(Migrator::class);
        $rolledBack = $migrator->rollback($steps);

        if ($rolledBack === []) {
            $this->line("Nothing to rollback.");

            return 0;
        }

        foreach ($rolledBack as $migration) {
            $this->line("Rolled back: " . $migration);
        }

        return 0;
    }
}
