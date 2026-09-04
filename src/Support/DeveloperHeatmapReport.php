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

    public function build(): array
    {
        $metrics = $this->readMetrics();
        $clickZones = (array) ($metrics["heatmap_click_zones"] ?? []);
        $clickTargets = (array) ($metrics["heatmap_click_targets"] ?? []);
        $scrollDepth = (array) ($metrics["heatmap_scroll_depth"] ?? []);
        $topPage = $this->topPage((array) ($metrics["heatmap_page_counts"] ?? []));

        return [
            "schema" => "fnlla.developer_heatmap.v1",
            "generated_at_utc" => gmdate(DATE_ATOM),
            "enabled" => (bool) config("observability.heatmap.enabled", true),
            "privacy" => [
                "mode" => "first-party aggregate heatmap",
                "raw_session_recording" => false,
                "raw_cursor_trails" => false,
                "keystrokes" => false,
                "raw_ip_addresses" => false,
                "raw_user_agents" => false,
                "visitor_fingerprinting" => false,
                "requires_analytics_consent" => true,
            ],
            "summary" => [
                "behavior_events" => (int) ($metrics["behavior_events_total"] ?? 0),
                "click_events" => (int) (((array) ($metrics["behavior_event_counts"] ?? []))["click"] ?? 0),
                "scroll_events" => (int) (((array) ($metrics["behavior_event_counts"] ?? []))["scroll"] ?? 0),
                "view_events" => (int) (((array) ($metrics["behavior_event_counts"] ?? []))["view"] ?? 0),
                "pages_seen" => count((array) ($metrics["heatmap_page_counts"] ?? [])),
                "top_page" => $topPage,
            ],
            "charts" => [
                "pages" => $this->topMap((array) ($metrics["heatmap_page_counts"] ?? []), 10),
                "devices" => $this->topMap((array) ($metrics["heatmap_device_counts"] ?? []), 6),
                "events" => $this->topMap((array) ($metrics["behavior_event_counts"] ?? []), 6),
                "click_elements" => $this->topMap((array) ($metrics["heatmap_click_elements"] ?? []), 8),
                "daily_behavior_events" => $this->series((array) ($metrics["daily_behavior_events"] ?? []), 14),
                "top_page_click_grid" => $this->clickGrid($topPage, (array) ($clickZones[$topPage] ?? []), (array) ($clickTargets[$topPage] ?? [])),
                "top_page_scroll_depth" => $this->scrollDepth((array) ($scrollDepth[$topPage] ?? [])),
            ],
            "last_behavior_event" => (array) ($metrics["last_behavior_event"] ?? []),
            "settings" => [
                "sample_rate" => max(1, min(100, (int) config("observability.heatmap.sample_rate", 100))),
                "grid_columns" => max(1, min(12, (int) config("observability.heatmap.click_grid_columns", 5))),
                "grid_rows" => max(1, min(12, (int) config("observability.heatmap.click_grid_rows", 5))),
                "storage_path" => "storage/" . ltrim((string) config("observability.metrics.path", "framework/metrics.json"), "\\/"),
            ],
            "insights" => $this->insights($metrics, $topPage),
        ];
    }

    private function topPage(array $pages): string
    {
        arsort($pages);

        $page = (string) array_key_first($pages);

        return $page !== "" ? $page : "/";
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
