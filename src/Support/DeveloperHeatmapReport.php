<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA SUPPORT SOURCE
File: src\Support\DeveloperHeatmapReport.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

Purpose:
- Builds the first-party heatmap cockpit used by the Developer Panel.
*/

namespace Fnlla\Php\Support;

final class DeveloperHeatmapReport
{
    use DeveloperMetricsReportHelpers;

    public function build(string $selectedPage = ""): array
    {
        $metrics = $this->readMetrics();
        $telemetryPolicy = DeveloperPanelPolicy::telemetryPolicy();
        $enabled = (bool) config("observability.heatmap.enabled", true) && (bool) $telemetryPolicy["heatmap_allowed"];
        $pageCounts = $this->publicMetricMap((array) ($metrics["heatmap_page_counts"] ?? []));
        $clickZones = $this->publicNestedMetricMap((array) ($metrics["heatmap_click_zones"] ?? []));
        $clickTargets = $this->publicNestedMetricMap((array) ($metrics["heatmap_click_targets"] ?? []));
        $scrollDepth = $this->publicNestedMetricMap((array) ($metrics["heatmap_scroll_depth"] ?? []));
        $publicPages = $this->publicPageOptions($pageCounts);
        $selectedPage = $this->selectPage($selectedPage, $publicPages);
        $topPage = $selectedPage !== "" ? $selectedPage : $this->topPage($pageCounts, $publicPages);
        $clickEvents = $this->nestedMapTotal($clickZones);
        $scrollEvents = $this->nestedMapTotal($scrollDepth);
        $behaviorEvents = array_sum(array_map(static fn (mixed $value): int => max(0, (int) $value), $pageCounts));
        $eventCounts = [
            "click" => $clickEvents,
            "scroll" => $scrollEvents,
            "view" => max(0, $behaviorEvents - $clickEvents - $scrollEvents),
        ];
        $lastBehaviorEvent = (array) ($metrics["last_behavior_event"] ?? []);
        if ($this->isPrivateMetricKey((string) ($lastBehaviorEvent["path"] ?? ""))) {
            $lastBehaviorEvent = [];
        }

        return [
            "schema" => "fnlla.developer_heatmap.v1",
            "generated_at_utc" => gmdate(DATE_ATOM),
            "enabled" => $enabled,
            "privacy" => [
                "mode" => $enabled ? "first-party aggregate heatmap" : ((bool) $telemetryPolicy["regulated"] ? "regulated disabled" : "first-party aggregate heatmap"),
                "profile" => (string) $telemetryPolicy["profile"],
                "regulated" => (bool) $telemetryPolicy["regulated"],
                "raw_session_recording" => false,
                "raw_cursor_trails" => false,
                "keystrokes" => false,
                "form_fields_recorded" => false,
                "query_strings_tracked" => false,
                "raw_ip_addresses" => false,
                "raw_user_agents" => false,
                "visitor_fingerprinting" => false,
                "requires_analytics_consent" => true,
                "excluded_paths" => (array) $telemetryPolicy["excluded_paths"],
            ],
            "summary" => [
                "behavior_events" => $behaviorEvents,
                "click_events" => $clickEvents,
                "scroll_events" => $scrollEvents,
                "view_events" => $eventCounts["view"],
                "pages_seen" => count($pageCounts),
                "public_pages_available" => count($publicPages),
                "top_page" => $topPage,
            ],
            "charts" => [
                "pages" => $this->topMap($pageCounts, 10),
                "devices" => $this->topMap((array) ($metrics["heatmap_device_counts"] ?? []), 6),
                "events" => $this->topMap($eventCounts, 6),
                "click_elements" => $this->topMap((array) ($metrics["heatmap_click_elements"] ?? []), 8),
                "daily_behavior_events" => $this->series((array) ($metrics["daily_behavior_events"] ?? []), 14),
                "top_page_click_grid" => $this->clickGrid($topPage, (array) ($clickZones[$topPage] ?? []), (array) ($clickTargets[$topPage] ?? [])),
                "top_page_scroll_depth" => $this->scrollDepth((array) ($scrollDepth[$topPage] ?? [])),
            ],
            "last_behavior_event" => $lastBehaviorEvent,
            "selected_page" => $topPage,
            "public_pages" => $publicPages,
            "settings" => [
                "sample_rate" => max(1, min(100, (int) config("observability.heatmap.sample_rate", 100))),
                "grid_columns" => max(1, min(12, (int) config("observability.heatmap.click_grid_columns", 5))),
                "grid_rows" => max(1, min(12, (int) config("observability.heatmap.click_grid_rows", 5))),
                "storage_path" => "storage/" . ltrim((string) config("observability.metrics.path", "framework/metrics.json"), "\\/"),
                "regulated_policy_allows_heatmap" => (bool) $telemetryPolicy["heatmap_allowed"],
            ],
            "insights" => array_values(array_filter(array_merge(
                !$enabled && (bool) $telemetryPolicy["regulated"] ? ["Regulated telemetry policy keeps heatmap disabled until explicit project opt-in."] : [],
                $this->insights($metrics, $topPage)
            ))),
        ];
    }

    private function topPage(array $pages, array $publicPages): string
    {
        arsort($pages);

        $page = (string) array_key_first($pages);

        if ($page !== "") {
            return $page;
        }

        return (string) ($publicPages[0]["path"] ?? "/");
    }

    private function selectPage(string $selectedPage, array $publicPages): string
    {
        $selectedPage = $this->safePath($selectedPage);

        foreach ($publicPages as $page) {
            if ($selectedPage !== "" && $selectedPage === (string) ($page["path"] ?? "")) {
                return $selectedPage;
            }
        }

        return "";
    }

    private function nestedMapTotal(array $map): int
    {
        $total = 0;

        foreach ($map as $items) {
            $total += array_sum(array_map(static fn (mixed $value): int => max(0, (int) $value), (array) $items));
        }

        return $total;
    }

    private function safePath(string $value): string
    {
        $path = parse_url($value, PHP_URL_PATH);
        $path = is_string($path) && $path !== "" ? $path : "/";
        $path = "/" . ltrim($path, "/");
        $path = preg_replace('/[^A-Za-z0-9_\\-\\/\\.]/', "", $path) ?? "/";

        return substr($path !== "" ? $path : "/", 0, 180);
    }

    private function clickGrid(string $page, array $zones, array $targets): array
    {
        $columns = max(1, min(12, (int) config("observability.heatmap.click_grid_columns", 5)));
        $rows = max(1, min(12, (int) config("observability.heatmap.click_grid_rows", 5)));
        $max = max(1, max(array_map(static fn (mixed $value): int => max(0, (int) $value), $zones ?: [1])));
        $gridRows = [];

        for ($row = 1; $row <= $rows; $row++) {
            $cells = [];

            for ($column = 1; $column <= $columns; $column++) {
                $key = "r{$row}c{$column}";
                $count = max(0, (int) ($zones[$key] ?? 0));
                $topTargets = $this->topMap((array) ($targets[$key] ?? []), 3, false);
                $zoneLabel = $this->zoneLabel($row, $column, $rows, $columns);
                $cells[] = [
                    "key" => $key,
                    "zone" => $zoneLabel,
                    "zone_key" => $key,
                    "row" => $row,
                    "column" => $column,
                    "count" => $count,
                    "targets" => $topTargets,
                    "tooltip" => $this->zoneTooltip($page, $zoneLabel, $count, $topTargets),
                    "intensity" => (int) round(($count / $max) * 100),
                ];
            }

            $gridRows[] = $cells;
        }

        return [
            "page" => $page,
            "columns" => $columns,
            "rows_count" => $rows,
            "max" => $max,
            "rows" => $gridRows,
        ];
    }

    private function zoneLabel(int $row, int $column, int $rows, int $columns): string
    {
        $vertical = $this->axisLabel($row, $rows, ["top", "upper-middle", "middle", "lower-middle", "bottom"]);
        $horizontal = $this->axisLabel($column, $columns, ["left", "center-left", "center", "center-right", "right"]);

        if ($vertical === "middle" && $horizontal === "center") {
            return "Center of the public page";
        }

        if ($vertical === "middle") {
            return ucfirst($horizontal . " public page area");
        }

        if ($horizontal === "center") {
            return ucfirst($vertical . " public page area");
        }

        return ucfirst($vertical . "-" . $horizontal . " public page area");
    }

    /**
     * @param array<int, string> $labels
     */
    private function axisLabel(int $index, int $total, array $labels): string
    {
        if ($total <= 1) {
            return "middle";
        }

        $position = (int) round((($index - 1) / max(1, $total - 1)) * (count($labels) - 1));

        return $labels[max(0, min(count($labels) - 1, $position))];
    }

    private function zoneTooltip(string $page, string $zoneLabel, int $count, array $targets): string
    {
        $clickLabel = $count === 1 ? "1 click" : "{$count} clicks";

        if ($count <= 0) {
            return "Public page {$page}. {$zoneLabel}: no clicks recorded yet.";
        }

        $targetLabels = array_map(
            static fn (array $target): string => (string) ($target["label"] ?? "") . " (" . (string) ($target["count"] ?? 0) . ")",
            array_filter($targets, static fn (mixed $target): bool => is_array($target))
        );
        $targetSummary = $targetLabels !== [] ? " Most clicked: " . implode(", ", $targetLabels) . "." : "";

        return "Public page {$page}. {$zoneLabel}: {$clickLabel}.{$targetSummary}";
    }

    private function scrollDepth(array $depth): array
    {
        $ordered = [];
        $max = max(1, max(array_map(static fn (mixed $value): int => max(0, (int) $value), $depth ?: [1])));

        foreach (["<25%", "25%", "50%", "75%", "100%"] as $bucket) {
            $count = max(0, (int) ($depth[$bucket] ?? 0));
            $ordered[] = [
                "label" => $bucket,
                "count" => $count,
                "percent" => (int) round(($count / $max) * 100),
            ];
        }

        return $ordered;
    }

    private function insights(array $metrics, string $topPage): array
    {
        $clicks = (int) (((array) ($metrics["behavior_event_counts"] ?? []))["click"] ?? 0);
        $scrolls = (int) (((array) ($metrics["behavior_event_counts"] ?? []))["scroll"] ?? 0);

        return [
            $clicks > 0 ? "Click heatmap data is active for {$topPage}." : "No click heatmap events have been recorded yet.",
            $scrolls > 0 ? "Scroll-depth buckets are available for review." : "No scroll-depth events have been recorded yet.",
            "FNLLA stores aggregate zones and depth buckets only; raw session replay is intentionally outside core.",
        ];
    }

}
