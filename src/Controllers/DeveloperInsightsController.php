<?php

declare(strict_types=1);

namespace Fnlla\Php\Controllers;

use Fnlla\Php\Http\Request;
use Fnlla\Php\Http\Response;
use Fnlla\Php\Http\UploadedFile;
use Fnlla\Php\Mail\Mailer;
use Fnlla\Php\Maintenance\CustomerAccessManager;
use Fnlla\Php\Maintenance\DeveloperActivityLog;
use Fnlla\Php\Maintenance\DeveloperAccessManager;
use Fnlla\Php\Maintenance\DeveloperControlManager;
use Fnlla\Php\Maintenance\MaintenanceAccessManager;
use Fnlla\Php\Support\DeveloperAnalyticsReport;
use Fnlla\Php\Support\DeveloperHeatmapReport;
use Fnlla\Php\Support\DeveloperPanelPolicy;
use Fnlla\Php\Support\DeveloperNotificationCenter;
use Fnlla\Php\Support\DeveloperOperationsReport;
use Fnlla\Php\Support\DeveloperWorkspaceBoard;
use Fnlla\Php\Support\EnvironmentFileManager;
use Fnlla\Php\Support\FrameworkIdentity;
use Fnlla\Php\Support\Logger;
use Fnlla\Php\Support\ProjectLeadership;
use Fnlla\Php\Validation\ValidationException;

final class DeveloperInsightsController extends DeveloperPanelController
{
    public function analytics(Request $request, DeveloperAccessManager $developerAccess, MaintenanceAccessManager $maintenanceAccess, DeveloperAnalyticsReport $report): Response
    {
        if (!$this->ensureDeveloperCapability($developerAccess, "operations.view")) {
            return $this->redirect(route("developer.panel"));
        }

        return $this->renderDeveloperPanel(
            $developerAccess,
            $maintenanceAccess,
            "developer/analytics",
            "Analytics",
            "analytics",
            [
                "analyticsReport" => $report->build(),
            ]
        );
    }

    public function heatmap(Request $request, DeveloperAccessManager $developerAccess, MaintenanceAccessManager $maintenanceAccess, DeveloperHeatmapReport $report): Response
    {
        if (!$this->ensureDeveloperCapability($developerAccess, "operations.view")) {
            return $this->redirect(route("developer.panel"));
        }

        return $this->renderDeveloperPanel(
            $developerAccess,
            $maintenanceAccess,
            "developer/heatmap",
            "Heatmap",
            "heatmap",
            [
                "heatmapReport" => $report->build((string) $request->query("page", "")),
            ]
        );
    }

    public function updateHeatmapSettings(
        Request $request,
        DeveloperAccessManager $developerAccess,
        EnvironmentFileManager $environmentFileManager
    ): Response {
        if (!$this->ensureDeveloperCapability($developerAccess, "panel.settings.write")) {
            return $this->redirect(route("developer.panel.heatmap"));
        }

        $payload = [
            "enabled" => (string) $request->input("observability_heatmap_enabled", "0") === "1",
            "sample_rate" => (int) $request->input("observability_heatmap_sample_rate", (int) config("observability.heatmap.sample_rate", 100)),
            "grid_columns" => (int) $request->input("observability_heatmap_grid_columns", (int) config("observability.heatmap.click_grid_columns", 5)),
            "grid_rows" => (int) $request->input("observability_heatmap_grid_rows", (int) config("observability.heatmap.click_grid_rows", 5)),
        ];

        try {
            $this->validate($payload, [
                "sample_rate" => ["required", "integer", "min:1", "max:100"],
                "grid_columns" => ["required", "integer", "min:1", "max:12"],
                "grid_rows" => ["required", "integer", "min:1", "max:12"],
            ]);
        } catch (ValidationException $exception) {
            flash_set("errors", $exception->errors());
            flash_set("status", [
                "variant" => "warning",
                "title" => "Heatmap settings still need attention",
                "text" => "Use 1-100 sample rate and 1-12 rows or columns.",
                "toast" => false,
            ]);
            regenerate_csrf_token();

            return $this->redirect(route("developer.panel.heatmap"));
        }

        $telemetryPolicy = DeveloperPanelPolicy::telemetryPolicy();
        if ($payload["enabled"] && ($telemetryPolicy["regulated"] ?? false) === true && ($telemetryPolicy["heatmap_allowed"] ?? false) !== true) {
            flash_set("status", [
                "variant" => "warning",
                "title" => "Heatmap requires regulated opt-in",
                "text" => "Regulated telemetry policy keeps behavior heatmap disabled until OBSERVABILITY_REGULATED_HEATMAP_ENABLED is explicitly enabled.",
                "toast" => false,
            ]);
            regenerate_csrf_token();

            return $this->redirect(route("developer.panel.heatmap"));
        }

        $environmentValues = [
            "OBSERVABILITY_HEATMAP_ENABLED" => $payload["enabled"],
            "OBSERVABILITY_HEATMAP_SAMPLE_RATE" => (string) $payload["sample_rate"],
            "OBSERVABILITY_HEATMAP_GRID_COLUMNS" => (string) $payload["grid_columns"],
            "OBSERVABILITY_HEATMAP_GRID_ROWS" => (string) $payload["grid_rows"],
        ];

        try {
            $environmentFileManager->write($environmentValues);
            $environmentFileManager->apply($environmentValues);
        } catch (\RuntimeException $exception) {
            flash_set("status", [
                "variant" => "danger",
                "title" => "Heatmap settings could not be saved",
                "text" => $exception->getMessage(),
                "toast" => false,
            ]);
            regenerate_csrf_token();

            return $this->redirect(route("developer.panel.heatmap"));
        }

        config_set("observability.heatmap.enabled", $payload["enabled"]);
        config_set("observability.heatmap.sample_rate", $payload["sample_rate"]);
        config_set("observability.heatmap.click_grid_columns", $payload["grid_columns"]);
        config_set("observability.heatmap.click_grid_rows", $payload["grid_rows"]);

        developer_activity()->record(
            "heatmap_settings",
            "Developer heatmap settings updated",
            "Local first-party behavior heatmap configuration was changed from the Developer Panel.",
            $developerAccess->currentDeveloper()
        );

        flash_set("status", [
            "variant" => "success",
            "title" => "Heatmap settings saved",
            "text" => "The local first-party heatmap recorder configuration was updated for this project.",
            "toast" => true,
        ]);
        regenerate_csrf_token();

        return $this->redirect(route("developer.panel.heatmap"));
    }

    public function updateAnalyticsSettings(
        Request $request,
        DeveloperAccessManager $developerAccess,
        EnvironmentFileManager $environmentFileManager
    ): Response {
        if (!$this->ensureDeveloperCapability($developerAccess, "panel.settings.write")) {
            return $this->redirect(route("developer.panel.analytics"));
        }

        $payload = [
            "metrics_enabled" => (string) $request->input("observability_metrics_enabled", "0") === "1",
            "analytics_enabled" => (string) $request->input("observability_analytics_enabled", "0") === "1",
            "bot_filtering" => (string) $request->input("observability_analytics_bot_filtering", "0") === "1",
            "device_detection" => (string) $request->input("observability_analytics_device_detection", "0") === "1",
            "track_query_strings" => (string) $request->input("observability_analytics_track_query_strings", "0") === "1",
            "retention_days" => (int) $request->input("observability_analytics_retention_days", (int) config("observability.analytics.retention_days", 90)),
            "sample_rate" => (int) $request->input("observability_analytics_sample_rate", (int) config("observability.analytics.sample_rate", 100)),
            "slow_route_threshold_ms" => (int) $request->input("observability_slow_route_threshold_ms", (int) config("observability.slow_route_threshold_ms", 750)),
        ];

        try {
            $this->validate($payload, [
                "retention_days" => ["required", "integer", "min:1", "max:730"],
                "sample_rate" => ["required", "integer", "min:1", "max:100"],
                "slow_route_threshold_ms" => ["required", "integer", "min:50", "max:30000"],
            ]);
        } catch (ValidationException $exception) {
            flash_set("errors", $exception->errors());
            flash_set("status", [
                "variant" => "warning",
                "title" => "Analytics settings still need attention",
                "text" => "Use 1-730 retention days, 1-100 sample rate and 50-30000ms slow-route threshold.",
                "toast" => false,
            ]);
            regenerate_csrf_token();

            return $this->redirect(route("developer.panel.analytics"));
        }

        $telemetryPolicy = DeveloperPanelPolicy::telemetryPolicy();
        if (($telemetryPolicy["regulated"] ?? false) === true) {
            $payload["track_query_strings"] = false;
            $payload["retention_days"] = min($payload["retention_days"], (int) ($telemetryPolicy["retention_days"] ?? $payload["retention_days"]));
        }

        $environmentValues = [
            "OBSERVABILITY_METRICS_ENABLED" => $payload["metrics_enabled"],
            "OBSERVABILITY_ANALYTICS_ENABLED" => $payload["analytics_enabled"],
            "OBSERVABILITY_ANALYTICS_RETENTION_DAYS" => (string) $payload["retention_days"],
            "OBSERVABILITY_ANALYTICS_SAMPLE_RATE" => (string) $payload["sample_rate"],
            "OBSERVABILITY_ANALYTICS_BOT_FILTERING" => $payload["bot_filtering"],
            "OBSERVABILITY_ANALYTICS_DEVICE_DETECTION" => $payload["device_detection"],
            "OBSERVABILITY_ANALYTICS_TRACK_QUERY_STRINGS" => $payload["track_query_strings"],
            "OBSERVABILITY_SLOW_ROUTE_THRESHOLD_MS" => (string) $payload["slow_route_threshold_ms"],
        ];

        try {
            $environmentFileManager->write($environmentValues);
            $environmentFileManager->apply($environmentValues);
        } catch (\RuntimeException $exception) {
            flash_set("status", [
                "variant" => "danger",
                "title" => "Analytics settings could not be saved",
                "text" => $exception->getMessage(),
                "toast" => false,
            ]);
            regenerate_csrf_token();

            return $this->redirect(route("developer.panel.analytics"));
        }

        config_set("observability.metrics.enabled", $payload["metrics_enabled"]);
        config_set("observability.analytics.enabled", $payload["analytics_enabled"]);
        config_set("observability.analytics.retention_days", $payload["retention_days"]);
        config_set("observability.analytics.sample_rate", $payload["sample_rate"]);
        config_set("observability.analytics.bot_filtering", $payload["bot_filtering"]);
        config_set("observability.analytics.device_detection", $payload["device_detection"]);
        config_set("observability.analytics.track_query_strings", $payload["track_query_strings"]);
        config_set("observability.slow_route_threshold_ms", $payload["slow_route_threshold_ms"]);

        developer_activity()->record(
            "analytics_settings",
            "Developer analytics settings updated",
            "Local first-party analytics configuration was changed from the Developer Panel.",
            $developerAccess->currentDeveloper()
        );

        flash_set("status", [
            "variant" => "success",
            "title" => "Analytics settings saved",
            "text" => "The local analytics recorder configuration was updated for this project.",
            "toast" => true,
        ]);
        regenerate_csrf_token();

        return $this->redirect(route("developer.panel.analytics"));
    }
}
