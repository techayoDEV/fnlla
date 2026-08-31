<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA SUPPORT SOURCE
File: src\Support\DeveloperAnalyticsReport.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

Purpose:
- Builds the privacy-light analytics cockpit used by the Developer Panel.
*/

namespace Fnlla\Php\Support;

final class DeveloperAnalyticsReport
{
    use DeveloperMetricsReportHelpers;

    public function build(): array
    {
        $metrics = $this->readMetrics();
        $totalRequests = (int) ($metrics["total_requests"] ?? 0);
        $pageViews = (int) ($metrics["page_views"] ?? 0);
        $errors = (int) ($this->mapTotal($this->filterStatuses((array) ($metrics["status_counts"] ?? []), 400)));
        $conversions = (int) ($metrics["form_submissions"] ?? 0);
        $averageResponseMs = $this->averageDuration($metrics);
        $errorRate = $totalRequests > 0 ? round(($errors / $totalRequests) * 100, 2) : 0.0;
        $conversionRate = $pageViews > 0 ? round(($conversions / $pageViews) * 100, 2) : 0.0;
        $consentEvents = (int) ($metrics["consent_events_total"] ?? 0);
        $analyticsConsentEvents = (int) ($metrics["consent_analytics_allowed"] ?? 0);
        $analyticsConsentRate = $consentEvents > 0 ? round(($analyticsConsentEvents / $consentEvents) * 100, 2) : 0.0;

        return [
            "schema" => "fnlla.developer_analytics.v1",
            "generated_at_utc" => gmdate(DATE_ATOM),
            "enabled" => (bool) config("observability.metrics.enabled", true),
            "privacy" => [
                "mode" => "privacy-light",
                "raw_ip_addresses" => false,
                "raw_user_agents" => false,
                "referrers" => "host-only",
                "cookie_consent_key" => "fnlla_cookie_consent_v1",
                "analytics_consent_event" => "fnlla:analytics-consent-granted",
                "visitor_fingerprinting" => false,
                "external_analytics" => false,
            ],
            "summary" => [
                "page_views" => $pageViews,
                "total_requests" => $totalRequests,
                "average_response_ms" => $averageResponseMs,
                "max_response_ms" => (float) ($metrics["max_duration_ms"] ?? 0.0),
                "error_requests" => $errors,
                "error_rate" => $errorRate,
                "conversion_events" => $conversions,
                "conversion_rate" => $conversionRate,
                "slow_requests" => $this->mapTotal((array) ($metrics["slow_route_counts"] ?? [])),
                "bot_page_view_requests" => (int) ($metrics["bot_page_view_requests"] ?? 0),
                "consent_events" => $consentEvents,
                "analytics_consent_rate" => $analyticsConsentRate,
            ],
            "charts" => [
                "top_routes" => $this->topMap((array) ($metrics["page_route_counts"] ?? $metrics["route_counts"] ?? []), 8),
                "referrers" => $this->topMap((array) ($metrics["referrer_counts"] ?? []), 8),
                "status_counts" => $this->topMap((array) ($metrics["status_counts"] ?? []), 8),
                "method_counts" => $this->topMap((array) ($metrics["method_counts"] ?? []), 8),
                "source_counts" => $this->topMap((array) ($metrics["source_counts"] ?? []), 8),
                "device_counts" => $this->topMap((array) ($metrics["device_counts"] ?? []), 8),
                "form_routes" => $this->topMap((array) ($metrics["form_route_counts"] ?? []), 8),
                "slow_routes" => $this->topMap((array) ($metrics["slow_route_counts"] ?? []), 8),
                "consent_counts" => $this->topMap((array) ($metrics["consent_counts"] ?? []), 8),
                "daily_consent_events" => $this->series((array) ($metrics["daily_consent_events"] ?? []), 14, "day"),
                "daily_page_views" => $this->series((array) ($metrics["daily_page_views"] ?? []), 14, "day"),
                "hourly_page_views" => $this->series((array) ($metrics["hourly_page_views"] ?? []), 24, "hour"),
                "route_response_times" => $this->routeAverages($metrics, 8),
            ],
            "last_request" => (array) ($metrics["last_request"] ?? []),
            "last_consent_event" => (array) ($metrics["last_consent_event"] ?? []),
            "goals" => $this->goals($metrics),
            "insights" => $this->insights($pageViews, $totalRequests, $errors, $conversions, $averageResponseMs),
            "settings" => $this->settings(),
            "data_quality" => [
                "storage_path" => "storage/" . ltrim((string) config("observability.metrics.path", "framework/metrics.json"), "\\/"),
                "updated_at_utc" => (string) ($metrics["updated_at_utc"] ?? ""),
                "events_are_aggregate_only" => true,
                "query_strings_tracked" => (bool) config("observability.analytics.track_query_strings", false),
                "consent_rate_source" => $consentEvents > 0 ? "backend aggregate consent events" : "waiting for consent events",
            ],
            "integrations" => [
                "fnlla_internal" => [
                    "name" => "FNLLA Internal Analytics",
                    "status" => "enabled",
                    "external_calls" => false,
                    "data_model" => "aggregate counters",
                ],
            ],
        ];
    }

    private function routeAverages(array $metrics, int $limit): array
    {
        $totals = (array) ($metrics["route_duration_totals"] ?? []);
        $counts = (array) ($metrics["route_duration_counts"] ?? []);
        $averages = [];

        foreach ($totals as $route => $total) {
            $count = max(1, (int) ($counts[$route] ?? 0));
            $averages[(string) $route] = round((float) $total / $count, 2);
        }

        arsort($averages);
        $max = max(1, (int) ceil(max($averages ?: [1])));
        $items = [];

        foreach (array_slice($averages, 0, max(1, $limit), true) as $label => $average) {
            $items[] = [
                "label" => $this->safeLabel((string) $label),
                "count" => $average,
                "percent" => (int) round(($average / $max) * 100),
            ];
        }

        return $items;
    }

    private function goals(array $metrics): array
    {
        $configured = (array) config("observability.analytics.goals", []);
        $goals = [];

        foreach ($configured as $goal) {
            if (!is_array($goal)) {
                continue;
            }

            $metric = (string) ($goal["metric"] ?? "");
            $route = (string) ($goal["route"] ?? "");
            $value = 0;

            if ($metric !== "") {
                $value = (int) ($metrics[$metric] ?? 0);
            } elseif ($route !== "") {
                $value = (int) (((array) ($metrics["route_counts"] ?? []))[$route] ?? 0);
            }

            $goals[] = [
                "key" => $this->safeLabel((string) ($goal["key"] ?? "goal")),
                "label" => $this->safeLabel((string) ($goal["label"] ?? "Goal")),
                "value" => max(0, $value),
            ];
        }

        return $goals;
    }

    private function insights(int $pageViews, int $totalRequests, int $errors, int $conversions, float $averageResponseMs): array
    {
        $insights = [];

        $insights[] = $pageViews > 0
            ? "Traffic collection is active and recording aggregate page-view trends."
            : "No page views have been recorded yet; open the public site to seed the local dashboard.";

        $insights[] = $conversions > 0
            ? "Form submissions are being counted as first-party conversion events."
            : "No successful form submissions have been recorded yet.";

        $insights[] = $errors > 0
            ? "HTTP 4xx/5xx responses exist in the local aggregate data and should be reviewed."
            : "No HTTP error responses are present in the current aggregate data.";

        $insights[] = $totalRequests > 0 && $averageResponseMs > (float) config("observability.slow_route_threshold_ms", 750)
            ? "Average response time is above the configured slow-route threshold."
            : "Average response time is inside the configured slow-route threshold.";

        return $insights;
    }

    private function settings(): array
    {
        return [
            "metrics_enabled" => (bool) config("observability.metrics.enabled", true),
            "analytics_enabled" => (bool) config("observability.analytics.enabled", true),
            "retention_days" => max(1, (int) config("observability.analytics.retention_days", 90)),
            "sample_rate" => max(1, min(100, (int) config("observability.analytics.sample_rate", 100))),
            "bot_filtering" => (bool) config("observability.analytics.bot_filtering", true),
            "device_detection" => (bool) config("observability.analytics.device_detection", true),
            "track_query_strings" => (bool) config("observability.analytics.track_query_strings", false),
            "slow_route_threshold_ms" => max(1, (int) config("observability.slow_route_threshold_ms", 750)),
            "editable_from_panel" => true,
        ];
    }

    private function filterStatuses(array $statuses, int $minimum): array
    {
        return array_filter(
            $statuses,
            static fn (mixed $count, mixed $status): bool => is_numeric($status) && (int) $status >= $minimum,
            ARRAY_FILTER_USE_BOTH
        );
    }

    private function mapTotal(array $map): int
    {
        return array_sum(array_map(static fn (mixed $value): int => max(0, (int) $value), $map));
    }

}
