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
            $recordedAt = gmdate(DATE_ATOM);
            $today = gmdate("Y-m-d");
            $hour = gmdate("H:00");
            $routeName = $this->routeName();
            $status = (string) $response->status();
            $method = $request->method();

            $metrics["schema"] = "fnlla.metrics.v1";
            $metrics["updated_at_utc"] = $recordedAt;
            $metrics["total_requests"] = (int) ($metrics["total_requests"] ?? 0) + 1;
            $metrics["total_duration_ms"] = round((float) ($metrics["total_duration_ms"] ?? 0.0) + $durationMs, 3);
            $metrics["max_duration_ms"] = round(max((float) ($metrics["max_duration_ms"] ?? 0.0), $durationMs), 3);
            $metrics["status_counts"] = $this->incrementMap((array) ($metrics["status_counts"] ?? []), $status);
            $metrics["method_counts"] = $this->incrementMap((array) ($metrics["method_counts"] ?? []), $method);
            $metrics["daily_requests"] = $this->incrementMap((array) ($metrics["daily_requests"] ?? []), $today);

            if ($routeName !== "") {
                $metrics["route_counts"] = $this->incrementMap((array) ($metrics["route_counts"] ?? []), $routeName);
            }

            $analyticsEnabled = \Fnlla\Php\Support\DeveloperModules::enabled("analytics")
                && (bool) config("observability.analytics.enabled", true);
            $analyticsSampled = $analyticsEnabled && $this->withinSampleRate();
            $routeKey = $this->routeKey($request, $routeName);

            if ($analyticsSampled) {
                $metrics["route_duration_totals"] = $this->addToMap((array) ($metrics["route_duration_totals"] ?? []), $routeKey, $durationMs);
                $metrics["route_duration_counts"] = $this->incrementMap((array) ($metrics["route_duration_counts"] ?? []), $routeKey);

                if ($durationMs > (float) config("observability.slow_route_threshold_ms", 750)) {
                    $metrics["slow_route_counts"] = $this->incrementMap((array) ($metrics["slow_route_counts"] ?? []), $routeKey);
                }
            }

            if ($analyticsSampled && $this->isFormSubmission($request, $response)) {
                $metrics["form_submissions"] = (int) ($metrics["form_submissions"] ?? 0) + 1;
                $metrics["form_route_counts"] = $this->incrementMap((array) ($metrics["form_route_counts"] ?? []), $routeKey);
            }

            if ($analyticsSampled && $this->isPageView($request, $response)) {
                $pageViewKey = $routeName !== "" ? $routeName : $request->path();
                $referrerHost = $this->referrerHost($request);
                $userAgent = (string) $request->header("User-Agent", "");

                if ((bool) config("observability.analytics.bot_filtering", true) && $this->isLikelyBot($userAgent)) {
                    $metrics["bot_page_view_requests"] = (int) ($metrics["bot_page_view_requests"] ?? 0) + 1;
                } else {
                    $metrics["page_views"] = (int) ($metrics["page_views"] ?? 0) + 1;
                    $metrics["page_route_counts"] = $this->incrementMap((array) ($metrics["page_route_counts"] ?? []), $pageViewKey);
                    $metrics["referrer_counts"] = $this->incrementMap(
                        (array) ($metrics["referrer_counts"] ?? []),
                        $referrerHost !== "" ? $referrerHost : "direct"
                    );
                    $metrics["source_counts"] = $this->incrementMap((array) ($metrics["source_counts"] ?? []), $this->sourceType($referrerHost));
                    $metrics["daily_page_views"] = $this->incrementMap((array) ($metrics["daily_page_views"] ?? []), $today);
                    $metrics["hourly_page_views"] = $this->incrementMap((array) ($metrics["hourly_page_views"] ?? []), $hour);

                    if ((bool) config("observability.analytics.device_detection", true)) {
                        $metrics["device_counts"] = $this->incrementMap((array) ($metrics["device_counts"] ?? []), $this->deviceType($userAgent));
                    }
                }
            }

            $metrics = $this->trimTimeBuckets($metrics);

            $metrics["last_request"] = [
                "request_id" => $request->requestId(),
                "method" => $method,
                "path" => $request->path(),
                "route" => $routeName,
                "status" => $response->status(),
                "duration_ms" => round($durationMs, 3),
                "recorded_at_utc" => $recordedAt,
            ];

            $this->write($metrics);
        });
    }

    public function snapshot(): array
    {
        return $this->read();
    }

    public function recordConsent(array $preferences): void
    {
        if (!$this->enabled()) {
            return;
        }

        $this->locked(function () use ($preferences): void {
            $metrics = $this->read();
            $recordedAt = gmdate(DATE_ATOM);
            $today = gmdate("Y-m-d");
            $analytics = (bool) ($preferences["analytics"] ?? false);
            $marketing = (bool) ($preferences["marketing"] ?? false);
            $state = $this->consentState($analytics, $marketing);

            $metrics["schema"] = "fnlla.metrics.v1";
            $metrics["updated_at_utc"] = $recordedAt;
            $metrics["consent_events_total"] = (int) ($metrics["consent_events_total"] ?? 0) + 1;
            $metrics["consent_counts"] = $this->incrementMap((array) ($metrics["consent_counts"] ?? []), $state);
            $metrics["consent_analytics_allowed"] = (int) ($metrics["consent_analytics_allowed"] ?? 0) + ($analytics ? 1 : 0);
            $metrics["consent_marketing_allowed"] = (int) ($metrics["consent_marketing_allowed"] ?? 0) + ($marketing ? 1 : 0);
            $metrics["daily_consent_events"] = $this->incrementMap((array) ($metrics["daily_consent_events"] ?? []), $today);
            $metrics["last_consent_event"] = [
                "state" => $state,
                "analytics" => $analytics,
                "marketing" => $marketing,
                "source" => $this->safeMetricLabel((string) ($preferences["source"] ?? "cookie-banner"), 80),
                "recorded_at_utc" => $recordedAt,
            ];

            $metrics = $this->trimTimeBuckets($metrics);

            $this->write($metrics);
        });
    }

    public function recordBehaviorEvent(array $payload): void
    {
        if (!\Fnlla\Php\Support\DeveloperModules::enabled("analytics")
            || !\Fnlla\Php\Support\DeveloperModules::enabled("heatmap")
            || !$this->enabled() || !(bool) config("observability.analytics.enabled", true) || !(bool) config("observability.heatmap.enabled", true)) {
            return;
        }

        if (!$this->withinHeatmapSampleRate()) {
            return;
        }

        $type = strtolower(trim((string) ($payload["type"] ?? "")));

        if (!in_array($type, ["view", "click", "scroll"], true)) {
            return;
        }

        $path = $this->safePath((string) ($payload["path"] ?? "/"));
        if (!$this->isPublicBehaviorPath($path)) {
            return;
        }

        $device = $this->safeBucket((string) ($payload["device"] ?? "unknown"), ["desktop", "tablet", "mobile", "unknown"], "unknown");
        $viewportWidth = $this->intRange($payload["viewport_width"] ?? 0, 0, 10000);
        $viewportHeight = $this->intRange($payload["viewport_height"] ?? 0, 0, 10000);

        $this->locked(function () use ($payload, $type, $path, $device, $viewportWidth, $viewportHeight): void {
            $metrics = $this->read();
            $recordedAt = gmdate(DATE_ATOM);
            $today = gmdate("Y-m-d");

            $metrics["schema"] = "fnlla.metrics.v1";
            $metrics["updated_at_utc"] = $recordedAt;
            $metrics["behavior_events_total"] = (int) ($metrics["behavior_events_total"] ?? 0) + 1;
            $metrics["behavior_event_counts"] = $this->incrementMap((array) ($metrics["behavior_event_counts"] ?? []), $type);
            $metrics["heatmap_page_counts"] = $this->incrementMap((array) ($metrics["heatmap_page_counts"] ?? []), $path);
            $metrics["heatmap_device_counts"] = $this->incrementMap((array) ($metrics["heatmap_device_counts"] ?? []), $device);
            $metrics["daily_behavior_events"] = $this->incrementMap((array) ($metrics["daily_behavior_events"] ?? []), $today);

            if ($type === "click") {
                $position = is_array($payload["position"] ?? null) ? (array) $payload["position"] : [];
                $xPercent = $this->floatRange($payload["x_percent"] ?? ($position["x_percent"] ?? 0), 0.0, 100.0);
                $yPercent = $this->floatRange($payload["y_percent"] ?? ($position["y_percent"] ?? 0), 0.0, 100.0);
                $zone = $this->heatmapZone($xPercent, $yPercent);
                $tag = $this->safeMetricLabel(strtolower((string) ($payload["element"] ?? "unknown")), 40);
                $tag = preg_match('/^[a-z0-9_-]+$/', $tag) === 1 ? $tag : "unknown";
                $target = $this->clickTargetLabel($tag, (string) ($payload["element_label"] ?? ""), (string) ($payload["element_context"] ?? ""));

                $metrics["heatmap_click_zones"] = (array) ($metrics["heatmap_click_zones"] ?? []);
                $metrics["heatmap_click_zones"][$path] = $this->incrementMap((array) ($metrics["heatmap_click_zones"][$path] ?? []), $zone);
                $metrics["heatmap_click_elements"] = $this->incrementMap((array) ($metrics["heatmap_click_elements"] ?? []), $tag);
                $metrics["heatmap_click_targets"] = (array) ($metrics["heatmap_click_targets"] ?? []);
                $metrics["heatmap_click_targets"][$path] = (array) ($metrics["heatmap_click_targets"][$path] ?? []);
                $metrics["heatmap_click_targets"][$path][$zone] = $this->incrementMap((array) ($metrics["heatmap_click_targets"][$path][$zone] ?? []), $target);
            }

            if ($type === "scroll") {
                $depth = $this->floatRange($payload["depth_percent"] ?? ($payload["depth"] ?? 0), 0.0, 100.0);
                $bucket = $depth >= 100 ? "100%" : ($depth >= 75 ? "75%" : ($depth >= 50 ? "50%" : ($depth >= 25 ? "25%" : "<25%")));

                $metrics["heatmap_scroll_depth"] = (array) ($metrics["heatmap_scroll_depth"] ?? []);
                $metrics["heatmap_scroll_depth"][$path] = $this->incrementMap((array) ($metrics["heatmap_scroll_depth"][$path] ?? []), $bucket);
            }

            $metrics["last_behavior_event"] = [
                "type" => $type,
                "path" => $path,
                "device" => $device,
                "viewport" => $viewportWidth . "x" . $viewportHeight,
                "recorded_at_utc" => $recordedAt,
            ];

            $metrics = $this->trimTimeBuckets($metrics);
            $metrics["heatmap_click_zones"] = $this->trimNestedMaps((array) ($metrics["heatmap_click_zones"] ?? []), 50, 144);
            $metrics["heatmap_scroll_depth"] = $this->trimNestedMaps((array) ($metrics["heatmap_scroll_depth"] ?? []), 50, 5);
            $metrics["heatmap_click_targets"] = $this->trimNestedMaps((array) ($metrics["heatmap_click_targets"] ?? []), 50, 144, 8);
            $metrics["heatmap_page_counts"] = $this->trimMap((array) ($metrics["heatmap_page_counts"] ?? []), 80);
            $metrics["heatmap_click_elements"] = $this->trimMap((array) ($metrics["heatmap_click_elements"] ?? []), 80);

            $this->write($metrics);
        });
    }

    public function clear(): void
    {
        $path = $this->path();

        if (is_file($path)) {
            unlink($path);
        }

        if (is_file($path . ".lock")) {
            unlink($path . ".lock");
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

    private function addToMap(array $map, string $key, float $value): array
    {
        $map[$key] = round((float) ($map[$key] ?? 0.0) + $value, 3);
        ksort($map);

        return $map;
    }

    private function routeName(): string
    {
        return trim((string) ($_SERVER["FNLLA_ROUTE_NAME"] ?? ""));
    }

    private function isPageView(Request $request, Response $response): bool
    {
        if (!in_array($request->method(), ["GET", "HEAD"], true) || $response->status() >= 400) {
            return false;
        }

        $accept = strtolower((string) $request->header("Accept", ""));

        return $accept === "" || str_contains($accept, "text/html") || str_contains($accept, "*/*");
    }

    private function isFormSubmission(Request $request, Response $response): bool
    {
        return in_array($request->method(), ["POST", "PUT", "PATCH"], true) && $response->status() < 400;
    }

    private function withinSampleRate(): bool
    {
        $rate = max(1, min(100, (int) config("observability.analytics.sample_rate", 100)));

        return $rate >= 100 || random_int(1, 100) <= $rate;
    }

    private function withinHeatmapSampleRate(): bool
    {
        $rate = max(1, min(100, (int) config("observability.heatmap.sample_rate", 100)));

        return $rate >= 100 || random_int(1, 100) <= $rate;
    }

    private function consentState(bool $analytics, bool $marketing): string
    {
        if ($analytics && $marketing) {
            return "accepted_all";
        }

        if ($analytics) {
            return "analytics_only";
        }

        if ($marketing) {
            return "marketing_only";
        }

        return "rejected_optional";
    }

    private function routeKey(Request $request, string $routeName): string
    {
        if ($routeName !== "") {
            return $routeName;
        }

        $path = $request->path();

        return $path !== "" ? $path : "/";
    }

    private function isLikelyBot(string $userAgent): bool
    {
        if ($userAgent === "") {
            return false;
        }

        return preg_match('/bot|crawl|spider|slurp|preview|scanner|monitor|pingdom|uptime/i', $userAgent) === 1;
    }

    private function deviceType(string $userAgent): string
    {
        $userAgent = strtolower($userAgent);

        if ($userAgent === "") {
            return "unknown";
        }

        if (str_contains($userAgent, "tablet") || str_contains($userAgent, "ipad")) {
            return "tablet";
        }

        if (str_contains($userAgent, "mobile") || str_contains($userAgent, "iphone") || str_contains($userAgent, "android")) {
            return "mobile";
        }

        return "desktop";
    }

    private function sourceType(string $referrerHost): string
    {
        if ($referrerHost === "") {
            return "direct";
        }

        if (preg_match('/(^|\.)google\.|(^|\.)bing\.|(^|\.)duckduckgo\.|(^|\.)yahoo\./i', $referrerHost) === 1) {
            return "search";
        }

        if (preg_match('/(^|\.)facebook\.|(^|\.)instagram\.|(^|\.)linkedin\.|(^|\.)x\.com$|(^|\.)twitter\./i', $referrerHost) === 1) {
            return "social";
        }

        return "referral";
    }

    private function trimTimeBuckets(array $metrics): array
    {
        $retentionDays = max(1, (int) config("observability.analytics.retention_days", 90));
        $minimumDate = gmdate("Y-m-d", strtotime("-" . ($retentionDays - 1) . " days"));

        foreach (["daily_requests", "daily_page_views", "daily_consent_events", "daily_behavior_events"] as $key) {
            $bucket = (array) ($metrics[$key] ?? []);
            $metrics[$key] = array_filter(
                $bucket,
                static fn (mixed $count, mixed $date): bool => is_string($date) && $date >= $minimumDate,
                ARRAY_FILTER_USE_BOTH
            );
        }

        if (isset($metrics["hourly_page_views"]) && is_array($metrics["hourly_page_views"])) {
            $metrics["hourly_page_views"] = array_slice($metrics["hourly_page_views"], -24, null, true);
        }

        return $metrics;
    }

    private function heatmapZone(float $xPercent, float $yPercent): string
    {
        $columns = max(1, min(12, (int) config("observability.heatmap.click_grid_columns", 5)));
        $rows = max(1, min(12, (int) config("observability.heatmap.click_grid_rows", 5)));
        $column = min($columns, max(1, (int) floor(($xPercent / 100) * $columns) + 1));
        $row = min($rows, max(1, (int) floor(($yPercent / 100) * $rows) + 1));

        return "r{$row}c{$column}";
    }

    private function safePath(string $value): string
    {
        $path = parse_url($value, PHP_URL_PATH);
        $path = is_string($path) && $path !== "" ? $path : "/";
        $path = "/" . ltrim($path, "/");
        $path = preg_replace('/[^A-Za-z0-9_\\-\\/\\.]/', "", $path) ?? "/";

        return substr($path !== "" ? $path : "/", 0, 180);
    }

    private function isPublicBehaviorPath(string $path): bool
    {
        $reserved = [
            "/developer",
            "/maintenance",
            "/fnlla",
            (string) config("developer_access.path", ""),
            (string) config("customer_access.path", ""),
        ];

        foreach ($reserved as $reservedPath) {
            $reservedPath = "/" . trim($reservedPath, "/");

            if ($reservedPath === "/") {
                continue;
            }

            if ($path === $reservedPath || str_starts_with($path, $reservedPath . "/")) {
                return false;
            }
        }

        return true;
    }

    private function clickTargetLabel(string $element, string $label, string $context): string
    {
        $element = $this->safeMetricLabel(strtolower($element), 40);
        $element = preg_match('/^[a-z0-9_-]+$/', $element) === 1 ? $element : "element";
        $label = $this->safeMetricLabel($label, 80);
        $context = $this->safeMetricLabel($context, 60);

        $descriptor = $element;

        if ($label !== "") {
            $descriptor .= ": " . $label;
        }

        if ($context !== "" && $context !== "page") {
            $descriptor .= " in " . $context;
        }

        return substr($descriptor, 0, 140);
    }

    private function safeBucket(string $value, array $allowed, string $fallback): string
    {
        $value = strtolower(trim($value));

        return in_array($value, $allowed, true) ? $value : $fallback;
    }

    private function intRange(mixed $value, int $min, int $max): int
    {
        if (!is_numeric($value)) {
            return $min;
        }

        return min($max, max($min, (int) $value));
    }

    private function floatRange(mixed $value, float $min, float $max): float
    {
        if (!is_numeric($value)) {
            return $min;
        }

        return round(min($max, max($min, (float) $value)), 3);
    }

    private function trimMap(array $map, int $limit): array
    {
        arsort($map);

        return array_slice($map, 0, max(1, $limit), true);
    }

    private function trimNestedMaps(array $maps, int $outerLimit, int $innerLimit, ?int $leafLimit = null): array
    {
        $trimmed = [];

        foreach (array_slice($maps, -max(1, $outerLimit), null, true) as $key => $map) {
            if (is_array($map)) {
                if ($leafLimit !== null) {
                    $inner = [];

                    foreach (array_slice($map, -max(1, $innerLimit), null, true) as $innerKey => $innerMap) {
                        if (is_array($innerMap)) {
                            $inner[$innerKey] = $this->trimMap($innerMap, $leafLimit);
                        }
                    }

                    $trimmed[$key] = $inner;
                    continue;
                }

                $trimmed[$key] = $this->trimMap($map, $innerLimit);
            }
        }

        return $trimmed;
    }

    private function referrerHost(Request $request): string
    {
        $referrer = trim((string) $request->header("Referer", ""));

        if ($referrer === "") {
            return "";
        }

        $host = parse_url($referrer, PHP_URL_HOST);

        if (!is_string($host) || $host === "") {
            return "";
        }

        return strtolower(substr(preg_replace('/[^a-z0-9.-]+/i', "", $host) ?? "", 0, 160));
    }

    private function safeMetricLabel(string $value, int $maxLength): string
    {
        $value = trim((string) preg_replace('/\s+/', ' ', strip_tags($value)));

        return strlen($value) <= $maxLength ? $value : substr($value, 0, $maxLength);
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
