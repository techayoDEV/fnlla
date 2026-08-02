<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA CONSOLE SOURCE
File: src\Console\Commands\UpgradeApplyCommand.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

Purpose:
- Applies only safe, generated upgrade-plan actions; defaults to dry-run.
*/

namespace Fnlla\Php\Console\Commands;

use Fnlla\Php\Console\Command;
use Fnlla\Php\Support\UpgradeAnalyzer;

final class UpgradeApplyCommand extends Command
{
    public function name(): string
    {
        return "upgrade:apply";
    }

    public function description(): string
    {
        return "Dry-run or apply safe major-release upgrade actions.";
    }

    public function handle(array $arguments): int
    {
        $json = in_array("--json", $arguments, true);
        $target = $this->optionValue($arguments, "--target") ?? "2.0.0";
        $dryRun = !in_array("--yes", $arguments, true);
        $analyzer = $this->container->make(UpgradeAnalyzer::class);
        $report = $analyzer->report($target);
        $result = $analyzer->applySafePlan($report, $dryRun);

        if ($json) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            return 0;
        }

        $this->line($dryRun ? "Upgrade dry-run" : "Upgrade safe actions applied");

        foreach ((array) $result["actions"] as $action) {
            $this->line(sprintf("[%s] %s - %s", strtoupper((string) $action["status"]), (string) $action["id"], (string) $action["detail"]));
        }

        if ($dryRun) {
            $this->line("Pass --yes to apply only actions marked safe_to_apply.");
        }

        return 0;
    }

    private function optionValue(array $arguments, string $name): ?string
    {
        foreach ($arguments as $index => $argument) {
            if ($argument === $name && isset($arguments[$index + 1])) {
                return (string) $arguments[$index + 1];
            }

            if (str_starts_with((string) $argument, $name . "=")) {
                return substr((string) $argument, strlen($name) + 1);
            }
        }

        return null;
    }
}
