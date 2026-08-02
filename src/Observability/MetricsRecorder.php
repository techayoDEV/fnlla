<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA OBSERVABILITY SOURCE
File: src\Observability\MetricsRecorder.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

Purpose:
- Records lightweight request metrics without requiring external services.
*/

namespace Fnlla\Php\Observability;

use Fnlla\Php\Http\Request;
use Fnlla\Php\Http\Response;

final class MetricsRecorder
{
    public function record(Request $request, Response $response, float $durationMs): void
    {
        if (!$this->enabled()) {
            return;
        }

        $this->locked(function () use ($request, $response, $durationMs): void {
            $metrics = $this->read();
            $routeName = $this->routeName();
            $status = (string) $response->status();
            $method = $request->method();

            $metrics["schema"] = "fnlla.metrics.v1";
            $metrics["updated_at_utc"] = gmdate(DATE_ATOM);
            $metrics["total_requests"] = (int) ($metrics["total_requests"] ?? 0) + 1;
            $metrics["total_duration_ms"] = round((float) ($metrics["total_duration_ms"] ?? 0.0) + $durationMs, 3);
            $metrics["max_duration_ms"] = round(max((float) ($metrics["max_duration_ms"] ?? 0.0), $durationMs), 3);
            $metrics["status_counts"] = $this->incrementMap((array) ($metrics["status_counts"] ?? []), $status);
            $metrics["method_counts"] = $this->incrementMap((array) ($metrics["method_counts"] ?? []), $method);

            if ($routeName !== "") {
                $metrics["route_counts"] = $this->incrementMap((array) ($metrics["route_counts"] ?? []), $routeName);
            }

            $metrics["last_request"] = [
                "request_id" => $request->requestId(),
                "method" => $method,
                "path" => $request->path(),
                "route" => $routeName,
                "status" => $response->status(),
                "duration_ms" => round($durationMs, 3),
                "recorded_at_utc" => gmdate(DATE_ATOM),
            ];

            $this->write($metrics);
        });
    }

    public function snapshot(): array
    {
        return $this->read();
    }

    public function clear(): void
    {
        $path = $this->path();

        if (is_file($path)) {
            unlink($path);
        }
    }

    private function enabled(): bool
    {
        return (bool) config("observability.metrics.enabled", true);
    }

    private function incrementMap(array $map, string $key): array
    {
        $map[$key] = (int) ($map[$key] ?? 0) + 1;
        ksort($map);

        return $map;
    }

    private function routeName(): string
    {
        return trim((string) ($_SERVER["FNLLA_ROUTE_NAME"] ?? ""));
    }

    private function read(): array
    {
        $path = $this->path();

        if (!is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    private function write(array $metrics): void
    {
        $path = $this->path();
        $directory = dirname($path);

        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        file_put_contents(
            $path,
            json_encode($metrics, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL,
            LOCK_EX
        );
    }

    private function locked(callable $callback): void
    {
        $path = $this->path();
        $directory = dirname($path);

        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $handle = fopen($path . ".lock", "c");

        if (!is_resource($handle)) {
            $callback();
            return;
        }

        try {
            flock($handle, LOCK_EX);
            $callback();
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    private function path(): string
    {
        $configured = (string) config("observability.metrics.path", "framework/metrics.json");

        return storage_path(ltrim($configured, "\\/"));
    }
}
