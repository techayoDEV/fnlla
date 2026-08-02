<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA CONSOLE SOURCE
File: src\Console\Commands\UpgradePlanCommand.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

Purpose:
- Writes a machine-readable major-release upgrade plan.
*/

namespace Fnlla\Php\Console\Commands;

use Fnlla\Php\Console\Command;
use Fnlla\Php\Support\UpgradeAnalyzer;

final class UpgradePlanCommand extends Command
{
    public function name(): string
    {
        return "upgrade:plan";
    }

    public function description(): string
    {
        return "Write a machine-readable major-release upgrade plan.";
    }

    public function handle(array $arguments): int
    {
        $json = in_array("--json", $arguments, true);
        $target = $this->optionValue($arguments, "--target") ?? "2.0.0";
        $output = $this->optionValue($arguments, "--output") ?? framework_upgrade_plan_path();
        $analyzer = $this->container->make(UpgradeAnalyzer::class);
        $report = $analyzer->report($target);

        $analyzer->write($report, $output);

        if ($json) {
            $report["output_path"] = $output;
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            return 0;
        }

        $this->line("Upgrade plan written: " . $output);
        $this->line("Actions: " . (string) count((array) ($report["plan"]["actions"] ?? [])));

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
