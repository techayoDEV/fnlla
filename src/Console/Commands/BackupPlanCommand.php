<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA CONSOLE SOURCE
File: src\Console\Commands\BackupPlanCommand.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

Purpose:
- Emits the production backup and restore plan for FNLLA deployments.
*/

namespace Fnlla\Php\Console\Commands;

use Fnlla\Php\Console\Command;
use Fnlla\Php\Support\BackupPlanBuilder;

final class BackupPlanCommand extends Command
{
    public function name(): string
    {
        return "ops:backup-plan";
    }

    public function description(): string
    {
        return "Generate a redacted backup and restore plan for production operations.";
    }

    public function handle(array $arguments): int
    {
        $plan = $this->container->make(BackupPlanBuilder::class)->build();
        $json = json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $output = $this->optionValue($arguments, "--output");

        if ($output !== null && trim($output) !== "") {
            $path = $this->resolveStorageOutput(trim($output));
            $directory = dirname($path);

            if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
                $this->error("Unable to create backup-plan output directory: " . $directory);

                return 1;
            }

            file_put_contents($path, $json . PHP_EOL, LOCK_EX);
            $this->line("Backup plan written: " . $path);

            return 0;
        }

        $this->line($json);

        return 0;
    }

    private function optionValue(array $arguments, string $name): ?string
    {
        foreach ($arguments as $index => $argument) {
            $argument = (string) $argument;

            if ($argument === $name && isset($arguments[$index + 1])) {
                return (string) $arguments[$index + 1];
            }

            if (str_starts_with($argument, $name . "=")) {
                return substr($argument, strlen($name) + 1);
            }
        }

        return null;
    }

    private function resolveStorageOutput(string $relativePath): string
    {
        $relativePath = ltrim(str_replace(["/", "\\"], DIRECTORY_SEPARATOR, $relativePath), DIRECTORY_SEPARATOR);

        if ($relativePath === "" || in_array("..", explode(DIRECTORY_SEPARATOR, $relativePath), true)) {
            throw new \RuntimeException("Backup plan output path must stay inside storage.");
        }

        return storage_path($relativePath);
    }
}
