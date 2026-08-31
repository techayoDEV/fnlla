<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA SUPPORT SOURCE
File: src\Support\DeveloperMetricsReportHelpers.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

Purpose:
- Shares aggregate metrics shaping helpers across Developer Panel reports.
*/

namespace Fnlla\Php\Support;

trait DeveloperMetricsReportHelpers
{
    private function readMetrics(): array
    {
        $path = storage_path(ltrim((string) config("observability.metrics.path", "framework/metrics.json"), "\\/"));

        if (!is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    private function averageDuration(array $metrics): float
    {
        $requests = max(1, (int) ($metrics["total_requests"] ?? 0));

        return round((float) ($metrics["total_duration_ms"] ?? 0.0) / $requests, 3);
    }

    private function topMap(array $map, int $limit, bool $includePercent = true): array
    {
        $normalized = [];

        foreach ($map as $key => $value) {
            $label = trim((string) $key);

            if ($label === "") {
                continue;
            }

            $normalized[$this->safeLabel($label)] = max(0, (int) $value);
        }

        arsort($normalized);
        $max = max(1, max($normalized ?: [1]));
        $items = [];

        foreach (array_slice($normalized, 0, max(1, $limit), true) as $label => $count) {
            $item = [
                "label" => $label,
                "count" => $count,
            ];

            if ($includePercent) {
                $item["percent"] = (int) round(($count / $max) * 100);
            }

            $items[] = $item;
        }

        return $items;
    }

    private function series(array $map, int $limit, string $kind = "day"): array
    {
        ksort($map);
        $slice = array_slice($map, -max(1, $limit), null, true);
        $max = max(1, max(array_map(static fn (mixed $value): int => max(0, (int) $value), $slice ?: [1])));
        $items = [];

        foreach ($slice as $label => $count) {
            $count = max(0, (int) $count);
            $items[] = [
                "label" => $kind === "hour" ? (string) $label : substr((string) $label, 5),
                "full_label" => (string) $label,
                "count" => $count,
                "percent" => (int) round(($count / $max) * 100),
            ];
        }

        return $items;
    }

    private function safeLabel(string $value): string
    {
        $value = trim((string) preg_replace('/\s+/', ' ', strip_tags($value)));

        return substr($value, 0, 180);
    }
}
