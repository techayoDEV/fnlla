<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA CONSOLE SOURCE
File: src\Console\Commands\PerfCompareCommand.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

Purpose:
- Compares current performance against an explicit baseline profile.
*/

namespace Fnlla\Php\Console\Commands;

use Fnlla\Php\Console\Command;
use Fnlla\Php\Support\PerformanceProfiler;

final class PerfCompareCommand extends Command
{
    public function name(): string
    {
        return "perf:compare";
    }

    public function description(): string
    {
        return "Compare current performance against a baseline file.";
    }

    public function handle(array $arguments): int
    {
        $iterations = max(1, (int) ($this->optionValue($arguments, "--iterations") ?? "5"));
        $against = $this->optionValue($arguments, "--against") ?? framework_performance_baseline_path();
        $maxRegression = max(0, (float) ($this->optionValue($arguments, "--max-regression") ?? "20"));
        $maxRegressionMs = max(0, (float) ($this->optionValue($arguments, "--max-regression-ms") ?? "1000"));
        $json = in_array("--json", $arguments, true);
        $profiler = $this->container->make(PerformanceProfiler::class);
        $baseline = $profiler->readBaseline($against);

        if ($baseline === null) {
            $this->error("Baseline does not exist or is invalid: " . $against);
            return 1;
        }

        $current = $profiler->profile($iterations);
        $comparisons = $profiler->compare($baseline, $current, $maxRegression, $maxRegressionMs);
        $ok = count(array_filter($comparisons, static fn (array $row): bool => $row["status"] === "fail")) === 0;
        $payload = [
            "schema" => "fnlla.performance_compare.v1",
            "ok" => $ok,
            "against" => $against,
            "max_regression_percent" => $maxRegression,
            "max_regression_ms" => $maxRegressionMs,
            "comparisons" => $comparisons,
        ];

        if ($json) {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            return $ok ? 0 : 1;
        }

        foreach ($comparisons as $comparison) {
            $this->line(sprintf(
                "[%s] %s p95 baseline=%sms current=%sms delta=%sms/%s%%",
                strtoupper((string) $comparison["status"]),
                (string) $comparison["name"],
                (string) $comparison["baseline_p95_ms"],
                (string) $comparison["current_p95_ms"],
                (string) $comparison["delta_ms"],
                (string) $comparison["delta_percent"]
            ));
        }

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
