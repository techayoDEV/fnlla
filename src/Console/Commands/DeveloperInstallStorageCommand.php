<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA CONSOLE SOURCE
File: src\Console\Commands\DeveloperInstallStorageCommand.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

Purpose:
- Installs or prints the optional database-backed Developer Panel storage tables.
*/

namespace Fnlla\Php\Console\Commands;

use Fnlla\Php\Console\Command;
use Fnlla\Php\Support\DeveloperPanelStorageInstaller;

final class DeveloperInstallStorageCommand extends Command
{
    public function name(): string
    {
        return "developer:install-storage";
    }

    public function description(): string
    {
        return "Install optional DB tables for Developer Panel workspace, audit, notifications and analytics.";
    }

    public function handle(array $arguments): int
    {
        $installer = new DeveloperPanelStorageInstaller();

        if (in_array("--sql", $arguments, true) || in_array("--dry-run", $arguments, true)) {
            foreach ($installer->statements() as $statement) {
                $this->line($statement . ";");
            }

            return 0;
        }

        $payload = $installer->install();
        $this->line("Developer Panel storage installed.");

        foreach ((array) ($payload["tables"] ?? []) as $name => $table) {
            $this->line("- {$name}: {$table}");
        }

        return 0;
    }
}
