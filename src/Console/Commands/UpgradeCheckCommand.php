<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA CONSOLE SOURCE
File: src\Console\Commands\UpgradeCheckCommand.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

Purpose:
- Reports whether a FNLLA workspace is ready for a target major release.
*/

namespace Fnlla\Php\Console\Commands;

use Fnlla\Php\Console\Command;
use Fnlla\Php\Support\UpgradeAnalyzer;

final class UpgradeCheckCommand extends Command
{
    public function name(): string
    {
        return "upgrade:check";
    }

    public function description(): string
    {
        return "Check major-release upgrade readiness.";
    }

    public function handle(array $arguments): int
    {
        $json = in_array("--json", $arguments, true);
        $target = $this->optionValue($arguments, "--target") ?? "2.0.0";
        $report = $this->container->make(UpgradeAnalyzer::class)->report($target);
        $summary = (array) ($report["summary"] ?? []);
        $ok = (int) ($summary["failures"] ?? 0) === 0;

        if ($json) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            return $ok ? 0 : 1;
        }

        $this->line("FNLLA upgrade readiness");
        $this->line("Target: " . $target);

        foreach ((array) $report["checks"] as $check) {
            $this->line(sprintf("[%s] %s - %s", strtoupper((string) $check["status"]), (string) $check["id"], (string) $check["detail"]));
        }

        $this->line(sprintf(
            "Summary: %d passed, %d warnings, %d failures",
            (int) ($summary["passed"] ?? 0),
            (int) ($summary["warnings"] ?? 0),
            (int) ($summary["failures"] ?? 0)
        ));

        return $ok ? 0 : 1;
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
