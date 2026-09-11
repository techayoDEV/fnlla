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

use Fnlla\Php\Routing\RouteDefinition;

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

    private function publicMetricMap(array $map): array
    {
        return array_filter(
            $map,
            fn (mixed $value, mixed $key): bool => !$this->isPrivateMetricKey((string) $key),
            ARRAY_FILTER_USE_BOTH
        );
    }

    private function publicNestedMetricMap(array $map): array
    {
        return array_filter(
            $map,
            fn (mixed $value, mixed $key): bool => is_array($value) && !$this->isPrivateMetricKey((string) $key),
            ARRAY_FILTER_USE_BOTH
        );
    }

    private function publicPageOptions(array $counts = []): array
    {
        $routes = $this->publicRouteCatalog();
        $routeNames = [];
        $options = [];

        foreach ($routes as $route) {
            $path = (string) ($route["path"] ?? "");
            if ($path === "") {
                continue;
            }

            $options[$path] = [
                "path" => $path,
                "label" => (string) ($route["label"] ?? $this->publicPageLabel($path)),
                "route" => (string) ($route["route"] ?? ""),
                "count" => 0,
            ];

            $name = (string) ($route["route"] ?? "");
            if ($name !== "") {
                $routeNames[$name] = $path;
            }
        }

        foreach ($counts as $key => $count) {
            $key = trim((string) $key);
            if ($key === "" || $this->isPrivateMetricKey($key)) {
                continue;
            }

            $path = (string) ($routeNames[$key] ?? $this->publicPagePath($key));
            if ($path === "" || $this->isPrivateMetricKey($path)) {
                continue;
            }

            if (!isset($options[$path])) {
                $options[$path] = [
                    "path" => $path,
                    "label" => $this->publicPageLabel($path, $key),
                    "route" => $key[0] !== "/" ? $key : "",
                    "count" => 0,
                ];
            }

            $options[$path]["count"] += max(0, (int) $count);
        }

        uasort($options, static function (array $first, array $second): int {
            $count = $second["count"] <=> $first["count"];
            if ($count !== 0) {
                return $count;
            }

            $home = ($first["path"] === "/" ? 0 : 1) <=> ($second["path"] === "/" ? 0 : 1);
            if ($home !== 0) {
                return $home;
            }

            return strcmp($first["label"], $second["label"]);
        });

        return array_values($options);
    }

    private function publicRouteCatalog(): array
    {
        try {
            $router = app(\Fnlla\Php\Routing\Router::class);
        } catch (\Throwable) {
            return [];
        }

        $routes = [];
        foreach ((array) $router->getRoutes() as $method => $routesByMethod) {
            if (strtoupper((string) $method) !== "GET") {
                continue;
            }

            foreach ((array) $routesByMethod as $route) {
                $definition = $route["definition"] ?? null;
                if (!$definition instanceof RouteDefinition) {
                    continue;
                }

                $path = $this->publicPagePath($definition->path());
                $name = (string) ($definition->routeName() ?? "");
                if ($path === "" || $path === "/health" || str_contains($path, "{") || str_contains($path, "}") || $this->isPrivateMetricKey($name) || $this->isPrivateMetricKey($path)) {
                    continue;
                }

                $routes[$path] = [
                    "path" => $path,
                    "label" => $this->publicPageLabel($path, $name),
                    "route" => $name,
                ];
            }
        }

        ksort($routes);

        return array_values($routes);
    }

    private function publicPagePath(string $value): string
    {
        $value = trim(str_replace("\\", "/", $value));
        if ($value === "" || $value === "home") {
            return "/";
        }

        $path = parse_url($value, PHP_URL_PATH);
        $path = is_string($path) && $path !== "" ? $path : $value;
        $path = "/" . ltrim($path, "/");
        $path = preg_replace('/[^A-Za-z0-9_\\-\\/\\.{}]/', "", $path) ?? "/";

        return substr($path !== "" ? $path : "/", 0, 180);
    }

    private function publicPageLabel(string $path, string $routeName = ""): string
    {
        $path = $this->publicPagePath($path);
        if ($path === "/") {
            return "Home";
        }

        $source = trim($routeName) !== "" && $routeName[0] !== "/" ? $routeName : $path;
        foreach (["home", "about", "contact", "privacy", "terms", "services"] as $known) {
            if ($source === $known || $path === "/" . $known) {
                return ucfirst($known);
            }
        }

        $label = trim(str_replace([":", ".", "_", "-", "/"], " ", $path));
        $label = preg_replace('/\s+/', " ", $label) ?: $path;

        return ucwords(strtolower($label));
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

    private function isPrivateMetricKey(string $key): bool
    {
        $key = strtolower(trim($key));

        if ($key === "") {
            return false;
        }

        foreach (["developer.", "maintenance.", "customer.", "client.", "api.", "fnlla."] as $prefix) {
            if (str_starts_with($key, $prefix)) {
                return true;
            }
        }

        $path = "/" . trim((string) parse_url($key, PHP_URL_PATH), "/");
        $reserved = array_merge(
            ["/developer", "/maintenance", "/client", "/api", "/fnlla"],
            (array) (DeveloperPanelPolicy::telemetryPolicy()["excluded_paths"] ?? [])
        );

        foreach ($reserved as $reservedPath) {
            $reservedPath = "/" . trim((string) $reservedPath, "/");

            if ($reservedPath === "/") {
                continue;
            }

            if ($path === $reservedPath || str_starts_with($path, $reservedPath . "/")) {
                return true;
            }
        }

        return false;
    }
}
