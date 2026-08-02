<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA SUPPORT SOURCE
File: src\Support\PerformanceProfiler.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

Purpose:
- Measures local CLI and repository performance in a machine-readable format.
*/

namespace Fnlla\Php\Support;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class PerformanceProfiler
{
    public function profile(int $iterations = 5): array
    {
        $iterations = max(1, $iterations);

        return [
            "schema" => "fnlla.performance_profile.v1",
            "generated_at_utc" => gmdate(DATE_ATOM),
            "php_version" => PHP_VERSION,
            "environment" => app_environment(),
            "iterations" => $iterations,
            "footprint" => $this->footprint(),
            "cli" => $this->cli($iterations),
            "memory" => [
                "peak_bytes" => memory_get_peak_usage(true),
            ],
        ];
    }

    public function writeBaseline(array $profile, string $path): void
    {
        $directory = dirname($path);

        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        file_put_contents($path, json_encode($profile, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL, LOCK_EX);
    }

    public function readBaseline(string $path): ?array
    {
        if (!is_file($path)) {
            return null;
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : null;
    }

    public function compare(array $baseline, array $current, float $maxRegression, float $maxRegressionMs): array
    {
        $rows = [];

        foreach ((array) ($current["cli"] ?? []) as $name => $currentRow) {
            $baselineRow = (array) (($baseline["cli"] ?? [])[$name] ?? []);
            $baselineP95 = (float) ($baselineRow["p95_ms"] ?? 0);
            $currentP95 = (float) ($currentRow["p95_ms"] ?? 0);
            $deltaMs = $currentP95 - $baselineP95;
            $delta = $baselineP95 > 0 ? (($currentP95 - $baselineP95) / $baselineP95) * 100 : 0.0;

            $rows[] = [
                "name" => (string) $name,
                "baseline_p95_ms" => round($baselineP95, 3),
                "current_p95_ms" => round($currentP95, 3),
                "delta_ms" => round($deltaMs, 3),
                "delta_percent" => round($delta, 2),
                "status" => $delta > $maxRegression && $deltaMs > $maxRegressionMs ? "fail" : "pass",
            ];
        }

        return $rows;
    }

    private function cli(int $iterations): array
    {
        $commands = [
            "list" => [PHP_BINARY, base_path("fnlla"), "list"],
            "route:list" => [PHP_BINARY, base_path("fnlla"), "route:list"],
            "version:status" => [PHP_BINARY, base_path("fnlla"), "version:status"],
        ];
        $rows = [];

        foreach ($commands as $name => $command) {
            $times = [];

            for ($index = 0; $index < $iterations; $index++) {
                $started = microtime(true);
                $result = ProcessRunner::run($command, base_path(), 120);
                $times[] = (microtime(true) - $started) * 1000;

                if ($result["exit_code"] !== 0) {
                    $rows[$name] = [
                        "ok" => false,
                        "error" => $result["output"],
                    ];
                    continue 2;
                }
            }

            sort($times);
            $rows[$name] = [
                "ok" => true,
                "avg_ms" => round(array_sum($times) / count($times), 3),
                "p50_ms" => round($this->percentile($times, 50), 3),
                "p95_ms" => round($this->percentile($times, 95), 3),
                "min_ms" => round(min($times), 3),
                "max_ms" => round(max($times), 3),
            ];
        }

        return $rows;
    }

    private function footprint(): array
    {
        $rows = [];

        foreach (["src", "bootstrap", "config", "routes", "views", "public", "tests", "docs", "scripts"] as $path) {
            $absolute = base_path($path);

            if (!is_dir($absolute)) {
                continue;
            }

            [$files, $bytes] = $this->countFiles($absolute);
            $rows[$path] = [
                "files" => $files,
                "bytes" => $bytes,
            ];
        }

        return $rows;
    }

    private function countFiles(string $directory): array
    {
        $files = 0;
        $bytes = 0;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $item) {
            if (!$item->isFile()) {
                continue;
            }

            $files++;
            $bytes += $item->getSize();
        }

        return [$files, $bytes];
    }

    private function percentile(array $sortedTimes, int $percentile): float
    {
        if ($sortedTimes === []) {
            return 0.0;
        }

        $index = (int) ceil(($percentile / 100) * count($sortedTimes)) - 1;
        $index = max(0, min(count($sortedTimes) - 1, $index));

        return (float) $sortedTimes[$index];
    }
}
