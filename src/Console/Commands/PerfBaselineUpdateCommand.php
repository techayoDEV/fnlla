<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA CONSOLE SOURCE
File: src\Console\Commands\PerfBaselineUpdateCommand.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

Purpose:
- Captures and stores a local performance baseline for later comparisons.
*/

namespace Fnlla\Php\Console\Commands;

use Fnlla\Php\Console\Command;
use Fnlla\Php\Support\PerformanceProfiler;

final class PerfBaselineUpdateCommand extends Command
{
    public function name(): string
    {
        return "perf:baseline:update";
    }

    public function description(): string
    {
        return "Capture and store the local performance baseline.";
    }

    public function handle(array $arguments): int
    {
        $iterations = max(1, (int) ($this->optionValue($arguments, "--iterations") ?? "7"));
        $output = $this->optionValue($arguments, "--output") ?? framework_performance_baseline_path();
        $json = in_array("--json", $arguments, true);
        $profiler = $this->container->make(PerformanceProfiler::class);
        $profile = $profiler->profile($iterations);

        $profiler->writeBaseline($profile, $output);

        if ($json) {
            $profile["baseline_path"] = $output;
            $this->line(json_encode($profile, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            return 0;
        }

        $this->line("Performance baseline updated: " . $output);
        $this->line("Iterations: " . (string) $iterations);

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
