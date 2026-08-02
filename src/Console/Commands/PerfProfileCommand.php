<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA CONSOLE SOURCE
File: src\Console\Commands\PerfProfileCommand.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

Purpose:
- Captures a local performance profile for regression tracking.
*/

namespace Fnlla\Php\Console\Commands;

use Fnlla\Php\Console\Command;
use Fnlla\Php\Support\PerformanceProfiler;

final class PerfProfileCommand extends Command
{
    public function name(): string
    {
        return "perf:profile";
    }

    public function description(): string
    {
        return "Measure FNLLA CLI and footprint performance.";
    }

    public function handle(array $arguments): int
    {
        $iterations = max(1, (int) ($this->optionValue($arguments, "--iterations") ?? "5"));
        $writeBaseline = in_array("--write-baseline", $arguments, true);
        $json = in_array("--json", $arguments, true);
        $profiler = $this->container->make(PerformanceProfiler::class);
        $profile = $profiler->profile($iterations);

        if ($writeBaseline) {
            $profiler->writeBaseline($profile, framework_performance_baseline_path());
            $profile["baseline_path"] = framework_performance_baseline_path();
        }

        if ($json) {
            $this->line(json_encode($profile, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            return 0;
        }

        $this->line("FNLLA performance profile");
        $this->line("Iterations: " . (string) $iterations);

        foreach ((array) $profile["cli"] as $name => $row) {
            $this->line(sprintf(
                "%s avg=%sms p50=%sms p95=%sms",
                (string) $name,
                (string) ($row["avg_ms"] ?? "n/a"),
                (string) ($row["p50_ms"] ?? "n/a"),
                (string) ($row["p95_ms"] ?? "n/a")
            ));
        }

        if ($writeBaseline) {
            $this->line("Baseline written: " . framework_performance_baseline_path());
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
