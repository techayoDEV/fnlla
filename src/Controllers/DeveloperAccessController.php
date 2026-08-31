<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA CONTROLLER SOURCE
File: src\Controllers\DeveloperAccessController.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Provides the hidden developer panel used to keep operator tools available
  after the public operations navigation is no longer shown to the client.
*/

namespace Fnlla\Php\Controllers;

use Fnlla\Php\Http\Request;
use Fnlla\Php\Http\Response;
use Fnlla\Php\Http\UploadedFile;
use Fnlla\Php\Maintenance\DeveloperActivityLog;
use Fnlla\Php\Maintenance\DeveloperAccessManager;
use Fnlla\Php\Maintenance\DeveloperControlManager;
use Fnlla\Php\Maintenance\MaintenanceAccessManager;
use Fnlla\Php\Support\DeveloperAnalyticsReport;
use Fnlla\Php\Support\DeveloperHeatmapReport;
use Fnlla\Php\Support\DeveloperNotificationCenter;
use Fnlla\Php\Support\DeveloperOperationsReport;
use Fnlla\Php\Support\DeveloperWorkspaceBoard;
use Fnlla\Php\Support\EnvironmentFileManager;
use Fnlla\Php\Support\Logger;
use Fnlla\Php\Support\ProjectLeadership;
use Fnlla\Php\Validation\ValidationException;

final class DeveloperAccessController extends Controller
{
    public function entry(Request $request, DeveloperAccessManager $developerAccess, MaintenanceAccessManager $maintenanceAccess): Response
    {
        if (!$developerAccess->enabled() || !$developerAccess->configured()) {
            return $this->view("pages/not-found", [
                "pageTitle" => "Not Found",
            ], 404);
        }

        if ($developerAccess->isUnlocked()) {
            return $this->redirect(route("developer.panel"));
        }

        return $this->view("developer/entry", [
            "pageTitle" => "Developer Sign In",
            "pageTitleSection" => "Operations",
            "developerAccess" => $developerAccess->viewState(),
            "maintenanceAccess" => $maintenanceAccess->viewState(),
            "developerLinks" => [
                "home" => route("home"),
            ],
            "projectSettings" => $this->projectSettings(),
            "developerNotice" => flash("developer_access_notice"),
        ]);
    }

    public function show(Request $request, DeveloperAccessManager $developerAccess, MaintenanceAccessManager $maintenanceAccess): Response
    {
        return $this->renderDeveloperPanel(
            $developerAccess,
            $maintenanceAccess,
            "developer/panel",
            "Dashboard",
            "overview"
        );
    }

    public function projectIdentity(Request $request, DeveloperAccessManager $developerAccess, MaintenanceAccessManager $maintenanceAccess): Response
    {
        return $this->renderDeveloperPanel(
            $developerAccess,
            $maintenanceAccess,
            "developer/project-identity",
            "Project Identity",
            "identity"
        );
    }

    public function projectSettingsPage(Request $request, DeveloperAccessManager $developerAccess, MaintenanceAccessManager $maintenanceAccess): Response
    {
        return $this->renderDeveloperPanel(
            $developerAccess,
            $maintenanceAccess,
            "developer/project-settings",
            "Project Settings",
            "project-settings"
        );
    }

    public function accessSettings(Request $request, DeveloperAccessManager $developerAccess, MaintenanceAccessManager $maintenanceAccess): Response
    {
        return $this->renderDeveloperPanel(
            $developerAccess,
            $maintenanceAccess,
            "developer/access-settings",
            "Access & Security",
            "access"
        );
    }

    public function profile(Request $request, DeveloperAccessManager $developerAccess, MaintenanceAccessManager $maintenanceAccess): Response
    {
        return $this->renderDeveloperPanel(
            $developerAccess,
            $maintenanceAccess,
            "developer/profile",
            "Developer Profile",
            "profile"
        );
    }

    public function security(Request $request, DeveloperAccessManager $developerAccess, MaintenanceAccessManager $maintenanceAccess): Response
    {
        if (!$this->ensureDeveloperCapability($developerAccess, "developer.security.manage")) {
            return $this->redirect(route("developer.panel.profile"));
        }

        return $this->renderDeveloperPanel(
            $developerAccess,
            $maintenanceAccess,
            "developer/access-settings",
            "Access & Security",
            "access"
        );
    }

    public function panelSettings(Request $request, DeveloperAccessManager $developerAccess, MaintenanceAccessManager $maintenanceAccess): Response
    {
        return $this->renderDeveloperPanel(
            $developerAccess,
            $maintenanceAccess,
            "developer/panel-settings",
            "Panel Settings",
            "settings"
        );
    }

    public function health(Request $request, DeveloperAccessManager $developerAccess, MaintenanceAccessManager $maintenanceAccess): Response
    {
        return $this->redirect(route("developer.panel.release_readiness"));
    }

    public function frameworkUpdates(Request $request, DeveloperAccessManager $developerAccess, MaintenanceAccessManager $maintenanceAccess): Response
    {
        return $this->renderDeveloperPanel(
            $developerAccess,
            $maintenanceAccess,
            "developer/framework-updates",
            "Framework Updates",
            "framework-updates",
            app(FrameworkUpdateController::class)->viewData($request, [
                "pageTitle" => "Framework Updates",
                "pageTitleSection" => "Developer Panel",
                "layoutChromeMode" => "developer-panel",
                "frameworkUpdateRunRoute" => route("developer.panel.framework_updates.run"),
                "frameworkUpdateRefreshRoute" => route("developer.panel.framework_updates"),
            ])
        );
    }

    public function runFrameworkUpdate(Request $request, DeveloperAccessManager $developerAccess): Response
    {
        if (!$this->ensureDeveloperCapability($developerAccess, "framework.update")) {
            return $this->redirect(route("developer.panel.framework_updates"));
        }

        return app(FrameworkUpdateController::class)->run($request);
    }

    public function operations(Request $request, DeveloperAccessManager $developerAccess, MaintenanceAccessManager $maintenanceAccess, DeveloperOperationsReport $report): Response
    {
        if (!$this->ensureDeveloperCapability($developerAccess, "operations.view")) {
            return $this->redirect(route("developer.panel"));
        }

        return $this->renderDeveloperPanel(
            $developerAccess,
            $maintenanceAccess,
            "developer/operations",
            "Operations",
            "operations",
            [
                "operationsReport" => $report->build(),
                "health" => app(HomeController::class)->healthPayload($request),
            ]
        );
    }

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
                "heatmapReport" => $report->build(),
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

    public function notifications(
        Request $request,
        DeveloperAccessManager $developerAccess,
        MaintenanceAccessManager $maintenanceAccess,
        DeveloperOperationsReport $operationsReport,
        DeveloperNotificationCenter $notifications
    ): Response {
        if (!$this->ensureDeveloperCapability($developerAccess, "operations.view")) {
            return $this->redirect(route("developer.panel"));
        }

        $developerAccessState = $developerAccess->viewState();
        $maintenanceAccessState = $maintenanceAccess->viewState();
        $dashboard = $this->developerDashboard($developerAccessState, $maintenanceAccessState);
        $operations = $operationsReport->build();

        return $this->renderDeveloperPanel(
            $developerAccess,
            $maintenanceAccess,
            "developer/notifications",
            "Notifications",
            "notifications",
            [
                "notificationsReport" => $notifications->build($developerAccessState, $dashboard, $operations, developer_control()->state()),
            ]
        );
    }

    public function releaseReadiness(Request $request, DeveloperAccessManager $developerAccess, MaintenanceAccessManager $maintenanceAccess, DeveloperOperationsReport $report): Response
    {
        if (!$this->ensureDeveloperCapability($developerAccess, "operations.view")) {
            return $this->redirect(route("developer.panel"));
        }

        return $this->renderDeveloperPanel(
            $developerAccess,
            $maintenanceAccess,
            "developer/release-readiness",
            "Release Readiness",
            "release-readiness",
            [
                "operationsReport" => $report->build(),
                "health" => app(HomeController::class)->healthPayload($request),
            ]
        );
    }

    public function integrations(Request $request, DeveloperAccessManager $developerAccess, MaintenanceAccessManager $maintenanceAccess, DeveloperOperationsReport $report): Response
    {
        if (!$this->ensureDeveloperCapability($developerAccess, "operations.view")) {
            return $this->redirect(route("developer.panel"));
        }

        return $this->renderDeveloperPanel(
            $developerAccess,
            $maintenanceAccess,
            "developer/integrations",
            "Integrations",
            "integrations",
            [
                "operationsReport" => $report->build(),
            ]
        );
    }

    public function documentation(Request $request, DeveloperAccessManager $developerAccess, MaintenanceAccessManager $maintenanceAccess): Response
    {
        if (!$this->ensureDeveloperCapability($developerAccess, "policy.view")) {
            return $this->redirect(route("developer.panel"));
        }

        return $this->renderDeveloperPanel(
            $developerAccess,
            $maintenanceAccess,
            "developer/documentation",
            "Documentation",
            "documentation"
        );
    }

    public function about(Request $request, DeveloperAccessManager $developerAccess, MaintenanceAccessManager $maintenanceAccess): Response
    {
        if (!$this->ensureDeveloperCapability($developerAccess, "policy.view")) {
            return $this->redirect(route("developer.panel"));
        }

        return $this->renderDeveloperPanel(
            $developerAccess,
            $maintenanceAccess,
            "developer/about",
            "About FNLLA",
            "about",
            [
                "aboutFnlla" => [
                    "framework_version" => (string) config("app.framework_version", "unknown"),
                    "runtime_version" => (string) config("fnlla_runtime.version", "unknown"),
                    "app_name" => (string) config("app.name", "FNLLA"),
                    "environment" => app_environment(),
                    "maintainer" => "TechAyo Limited",
                    "license" => "MIT",
                ],
            ]
        );
    }

    public function updateNotification(Request $request, DeveloperAccessManager $developerAccess, DeveloperNotificationCenter $notifications): Response
    {
        if (!$this->ensureDeveloperCapability($developerAccess, "operations.view")) {
            return $this->redirect(route("developer.panel"));
        }

        $key = strtolower(trim((string) $request->input("developer_notification_key", "")));
        $action = strtolower(trim((string) $request->input("developer_notification_action", "")));

        if ($key === "" || !in_array($action, ["acknowledge", "archive", "restore"], true)) {
            flash_set("status", [
                "variant" => "warning",
                "title" => "Notification action was not applied",
                "text" => "Choose a valid notification action before updating the inbox.",
                "toast" => true,
            ]);
            regenerate_csrf_token();

            return $this->redirect(route("developer.panel.notifications"));
        }

        $developer = $developerAccess->currentDeveloper();

        if ($action === "acknowledge") {
            $notifications->acknowledge($key, $developer);
            $message = "Notification marked as read";
        } elseif ($action === "archive") {
            $notifications->archive($key, $developer);
            $message = "Notification archived";
        } else {
            $notifications->restore($key, $developer);
            $message = "Notification restored";
        }

        developer_activity()->record(
            "developer_notification",
            $message,
            "A developer changed notification state for {$key}.",
            $developer
        );

        flash_set("status", [
            "variant" => "success",
            "title" => $message,
            "text" => "The notification center state was saved for every developer session.",
            "toast" => true,
        ]);
        regenerate_csrf_token();

        return $this->redirect(route("developer.panel.notifications"));
    }

    public function updateIntegrationSettings(
        Request $request,
        DeveloperAccessManager $developerAccess,
        EnvironmentFileManager $environmentFileManager
    ): Response {
        if (!$this->ensureDeveloperCapability($developerAccess, "panel.settings.write")) {
            return $this->redirect(route("developer.panel.integrations"));
        }

        $requestPayload = $request->all();
        $fionnToken = trim((string) $request->input("ai_fionn_api_token", ""));
        $values = [
            "FNLLA_INTEGRATION_GA4_ENABLED" => (string) $request->input("fnlla_integration_ga4_enabled", "0") === "1",
            "FNLLA_INTEGRATION_GA4_MEASUREMENT_ID" => trim((string) $request->input("fnlla_integration_ga4_measurement_id", "")),
            "FNLLA_INTEGRATION_CLARITY_ENABLED" => (string) $request->input("fnlla_integration_clarity_enabled", "0") === "1",
            "FNLLA_INTEGRATION_CLARITY_PROJECT_ID" => trim((string) $request->input("fnlla_integration_clarity_project_id", "")),
            "FNLLA_INTEGRATION_SENTRY_ENABLED" => (string) $request->input("fnlla_integration_sentry_enabled", "0") === "1",
            "FNLLA_INTEGRATION_SENTRY_DSN" => trim((string) $request->input("fnlla_integration_sentry_dsn", "")),
            "FNLLA_INTEGRATION_SENTRY_ENVIRONMENT" => trim((string) $request->input("fnlla_integration_sentry_environment", app_environment())),
            "FNLLA_INTEGRATION_API_HOOKS_ENABLED" => (string) $request->input("fnlla_integration_api_hooks_enabled", "0") === "1",
            "FNLLA_INTEGRATION_API_HOOKS_ENDPOINT" => trim((string) $request->input("fnlla_integration_api_hooks_endpoint", "")),
            "FNLLA_INTEGRATION_HEATMAPS_ENABLED" => (string) $request->input("fnlla_integration_heatmaps_enabled", "0") === "1",
            "FNLLA_INTEGRATION_HEATMAPS_PROVIDER" => trim((string) $request->input("fnlla_integration_heatmaps_provider", "")),
            "DEVELOPER_CONTROL_REMOTE_ENABLED" => (string) $request->input("developer_control_remote_enabled", "0") === "1",
            "DEVELOPER_CONTROL_REMOTE_ENDPOINT" => trim((string) $request->input("developer_control_remote_endpoint", "")),
            "DEVELOPER_CONTROL_REMOTE_PROJECT_ID" => trim((string) $request->input("developer_control_remote_project_id", "")),
            "AI_FIONN_BRIDGE_ENABLED" => array_key_exists("ai_fionn_enabled", $requestPayload)
                ? (string) $request->input("ai_fionn_enabled", "0") === "1"
                : (bool) config("ai.runtime.fionn.enabled", false),
            "AI_FIONN_ENDPOINT" => array_key_exists("ai_fionn_endpoint", $requestPayload)
                ? trim((string) $request->input("ai_fionn_endpoint", ""))
                : trim((string) config("ai.runtime.fionn.endpoint", "")),
            "AI_FIONN_CHAT_PATH" => array_key_exists("ai_fionn_chat_path", $requestPayload)
                ? trim((string) $request->input("ai_fionn_chat_path", "/api/chat"))
                : trim((string) config("ai.runtime.fionn.chat_path", "/api/chat")),
            "AI_FIONN_API_TOKEN" => $fionnToken !== "" ? $fionnToken : trim((string) config("ai.runtime.fionn.api_token", "")),
            "AI_FIONN_ALLOWED_HOSTS" => array_key_exists("ai_fionn_allowed_hosts", $requestPayload)
                ? trim((string) $request->input("ai_fionn_allowed_hosts", ""))
                : implode(",", (array) config("ai.runtime.fionn.allowed_hosts", ["127.0.0.1", "localhost"])),
            "AI_FIONN_ALLOW_INSECURE_LOCALHOST" => array_key_exists("ai_fionn_allow_insecure_localhost", $requestPayload)
                ? (string) $request->input("ai_fionn_allow_insecure_localhost", "0") === "1"
                : (bool) config("ai.runtime.fionn.allow_insecure_localhost", true),
        ];

        foreach ([
            "FNLLA_INTEGRATION_GA4_MEASUREMENT_ID",
            "FNLLA_INTEGRATION_CLARITY_PROJECT_ID",
            "FNLLA_INTEGRATION_SENTRY_DSN",
            "FNLLA_INTEGRATION_SENTRY_ENVIRONMENT",
            "FNLLA_INTEGRATION_API_HOOKS_ENDPOINT",
            "FNLLA_INTEGRATION_HEATMAPS_PROVIDER",
            "DEVELOPER_CONTROL_REMOTE_ENDPOINT",
            "DEVELOPER_CONTROL_REMOTE_PROJECT_ID",
            "AI_FIONN_ENDPOINT",
            "AI_FIONN_CHAT_PATH",
            "AI_FIONN_API_TOKEN",
            "AI_FIONN_ALLOWED_HOSTS",
        ] as $key) {
            if (strlen((string) $values[$key]) > 240) {
                flash_set("status", [
                    "variant" => "warning",
                    "title" => "Integration settings still need attention",
                    "text" => "Adapter values must stay below 240 characters.",
                    "toast" => false,
                ]);
                regenerate_csrf_token();

                return $this->redirect(route("developer.panel.integrations"));
            }
        }

        try {
            $environmentFileManager->write($values);
            $environmentFileManager->apply($values);
        } catch (\RuntimeException $exception) {
            flash_set("status", [
                "variant" => "danger",
                "title" => "Integration settings could not be saved",
                "text" => $exception->getMessage(),
                "toast" => false,
            ]);
            regenerate_csrf_token();

            return $this->redirect(route("developer.panel.integrations"));
        }

        config_set("integrations.ga4.enabled", $values["FNLLA_INTEGRATION_GA4_ENABLED"]);
        config_set("integrations.ga4.measurement_id", $values["FNLLA_INTEGRATION_GA4_MEASUREMENT_ID"]);
        config_set("integrations.clarity.enabled", $values["FNLLA_INTEGRATION_CLARITY_ENABLED"]);
        config_set("integrations.clarity.project_id", $values["FNLLA_INTEGRATION_CLARITY_PROJECT_ID"]);
        config_set("integrations.sentry.enabled", $values["FNLLA_INTEGRATION_SENTRY_ENABLED"]);
        config_set("integrations.sentry.dsn", $values["FNLLA_INTEGRATION_SENTRY_DSN"]);
        config_set("integrations.sentry.environment", $values["FNLLA_INTEGRATION_SENTRY_ENVIRONMENT"]);
        config_set("integrations.api_hooks.enabled", $values["FNLLA_INTEGRATION_API_HOOKS_ENABLED"]);
        config_set("integrations.api_hooks.endpoint", $values["FNLLA_INTEGRATION_API_HOOKS_ENDPOINT"]);
        config_set("integrations.heatmaps.enabled", $values["FNLLA_INTEGRATION_HEATMAPS_ENABLED"]);
        config_set("integrations.heatmaps.provider", $values["FNLLA_INTEGRATION_HEATMAPS_PROVIDER"]);
        config_set("developer_control.remote.enabled", $values["DEVELOPER_CONTROL_REMOTE_ENABLED"]);
        config_set("developer_control.remote.endpoint", $values["DEVELOPER_CONTROL_REMOTE_ENDPOINT"]);
        config_set("developer_control.remote.project_id", $values["DEVELOPER_CONTROL_REMOTE_PROJECT_ID"]);
        config_set("ai.runtime.fionn.enabled", $values["AI_FIONN_BRIDGE_ENABLED"]);
        config_set("ai.runtime.fionn.endpoint", $values["AI_FIONN_ENDPOINT"]);
        config_set("ai.runtime.fionn.chat_path", $values["AI_FIONN_CHAT_PATH"] !== "" ? $values["AI_FIONN_CHAT_PATH"] : "/api/chat");
        config_set("ai.runtime.fionn.api_token", $values["AI_FIONN_API_TOKEN"]);
        config_set("ai.runtime.fionn.allowed_hosts", array_values(array_filter(array_map("trim", explode(",", $values["AI_FIONN_ALLOWED_HOSTS"])))));
        config_set("ai.runtime.fionn.allow_insecure_localhost", $values["AI_FIONN_ALLOW_INSECURE_LOCALHOST"]);

        developer_activity()->record(
            "developer_integrations",
            "Integration adapter settings updated",
            "A developer changed FNLLA-side integration adapter configuration.",
            $developerAccess->currentDeveloper()
        );

        flash_set("status", [
            "variant" => "success",
            "title" => "Integration settings saved",
            "text" => "Adapter configuration was saved without enabling private product logic in FNLLA.",
            "toast" => true,
        ]);
        regenerate_csrf_token();

        return $this->redirect(route("developer.panel.integrations"));
    }

    public function policy(Request $request, DeveloperAccessManager $developerAccess, MaintenanceAccessManager $maintenanceAccess): Response
    {
        if (!$this->ensureDeveloperCapability($developerAccess, "policy.view")) {
            return $this->redirect(route("developer.panel"));
        }

        return $this->renderDeveloperPanel(
            $developerAccess,
            $maintenanceAccess,
            "developer/policy",
            "Policy Boundary",
            "policy",
            [
                "developerPolicy" => $this->developerPolicy($developerAccess),
            ]
        );
    }

    public function exportAuditLog(Request $request, DeveloperAccessManager $developerAccess, DeveloperActivityLog $activityLog): Response
    {
        if (!$this->ensureDeveloperCapability($developerAccess, "audit.export")) {
            return $this->redirect(route("developer.panel.operations"));
        }

        return Response::json($activityLog->exportPayload(500), 200, [
            "Content-Disposition" => "attachment; filename=\"fnlla-developer-audit-log.json\"",
            "Cache-Control" => "no-store",
        ]);
    }

    public function exportAuditLogCsv(Request $request, DeveloperAccessManager $developerAccess, DeveloperActivityLog $activityLog): Response
    {
        if (!$this->ensureDeveloperCapability($developerAccess, "audit.export")) {
            return $this->redirect(route("developer.panel.operations"));
        }

        return Response::text($activityLog->exportCsv(500), 200, [
            "Content-Disposition" => "attachment; filename=\"fnlla-developer-audit-log.csv\"",
            "Content-Type" => "text/csv; charset=UTF-8",
            "Cache-Control" => "no-store",
        ]);
    }

    public function workspace(Request $request, DeveloperAccessManager $developerAccess, MaintenanceAccessManager $maintenanceAccess, DeveloperWorkspaceBoard $workspace): Response
    {
        return $this->renderDeveloperPanel(
            $developerAccess,
            $maintenanceAccess,
            "developer/workspace",
            "Project Workspace",
            "workspace",
            [
                "workspaceBoard" => $workspace->state($developerAccess->currentDeveloper()),
            ]
        );
    }

    public function unlock(Request $request, DeveloperAccessManager $developerAccess, MaintenanceAccessManager $maintenanceAccess): Response
    {
        if (!$developerAccess->enabled() || !$developerAccess->configured()) {
            return $this->view("pages/not-found", [
                "pageTitle" => "Not Found",
            ], 404);
        }

        $email = trim((string) $request->input("developer_access_email", ""));
        $password = trim((string) $request->input("developer_access_password", ""));
        $totpCode = trim((string) $request->input("developer_access_totp", ""));
        $emailRequired = (bool) ($developerAccess->viewState()["email_required"] ?? false);

        try {
            if ($emailRequired) {
                $this->validate([
                    "developer_access_email" => $email,
                ], [
                    "developer_access_email" => ["required", "email", "max:160"],
                ]);
            }

            $this->validate([
                "developer_access_password" => $password,
            ], [
                "developer_access_password" => ["required", "string", "min:8", "max:255"],
            ]);
        } catch (ValidationException $exception) {
            flash_set("errors", $exception->errors());
            flash_set("old", ["developer_access_email" => $email]);
            flash_set("status", [
                "variant" => "warning",
                "title" => "Developer access still needs attention",
                "text" => $emailRequired
                    ? "Enter your developer email and password to unlock this hidden panel."
                    : "Enter the developer password to unlock this hidden panel.",
                "toast" => false,
            ]);
            regenerate_csrf_token();

            return $this->redirect(route("developer.login"));
        }

        $result = $developerAccess->unlock($request, $password, $email, $totpCode);

        if (!$result["success"]) {
            flash_set("old", ["developer_access_email" => $email]);
            flash_set("status", [
                "variant" => "warning",
                "title" => "Developer access denied",
                "text" => (string) $result["error"],
                "toast" => false,
            ]);
            regenerate_csrf_token();

            return $this->redirect(route("developer.login"));
        }

        flash_set("status", [
            "variant" => "success",
            "title" => "Developer session unlocked",
            "text" => "The hidden developer panel is available in this browser session for the configured access window.",
            "toast" => true,
        ]);
        $maintenanceAccess->lock();
        regenerate_csrf_token();

        return $this->redirect(route("developer.panel"));
    }

    public function updateServiceControl(
        Request $request,
        DeveloperAccessManager $developerAccess,
        DeveloperControlManager $developerControl,
        DeveloperActivityLog $activityLog
    ): Response {
        if (!$this->ensureDeveloperCapability($developerAccess, "service_control.write")) {
            return $this->redirect(route("developer.panel.project_settings"));
        }

        $payload = [
            "developer_control_disabled" => (string) $request->input("developer_control_disabled", "0"),
            "developer_control_message" => trim((string) $request->input("developer_control_message", "")),
            "developer_control_contact" => trim((string) $request->input("developer_control_contact", "")),
        ];

        try {
            $this->validate($payload, [
                "developer_control_disabled" => ["required", "string"],
                "developer_control_message" => ["nullable", "string", "max:240"],
                "developer_control_contact" => ["nullable", "string", "max:160"],
            ]);
        } catch (ValidationException $exception) {
            flash_set("errors", $exception->errors());
            flash_set("status", [
                "variant" => "warning",
                "title" => "Service control still needs attention",
                "text" => "Use a short public message and a valid developer contact reference.",
                "toast" => false,
            ]);
            regenerate_csrf_token();

            return $this->redirect(route("developer.panel.project_settings"));
        }

        $developer = $developerAccess->currentDeveloper();
        $disabled = $payload["developer_control_disabled"] === "1";

        if ($disabled) {
            $developerControl->disable($payload["developer_control_message"], $payload["developer_control_contact"], $developer);
        } else {
            $developerControl->enable($developer);
        }

        $activityLog->record(
            "service_control",
            $disabled ? "Public service disabled" : "Public service re-enabled",
            $disabled ? "Public routes now show the developer-disabled service notice." : "Public routes were reopened by the developer team.",
            $developer
        );

        flash_set("status", [
            "variant" => "success",
            "title" => $disabled ? "Service disabled" : "Service enabled",
            "text" => $disabled
                ? "Public routes now show the developer service-disabled message while developer access remains available."
                : "The local developer service lock was cleared.",
            "toast" => true,
        ]);
        regenerate_csrf_token();

        return $this->redirect(route("developer.panel.project_settings"));
    }

    public function lock(Request $request, DeveloperAccessManager $developerAccess): Response
    {
        $developerAccess->lock();
        Logger::write("notice", "Developer session locked", [
            "event" => "developer_session_locked",
            "ip" => $request->ip(),
        ]);
        flash_set("status", [
            "variant" => "info",
            "title" => "Developer session locked",
            "text" => "The private developer panel now requires its password again.",
            "toast" => false,
        ]);
        regenerate_csrf_token();

        return $this->redirect(route("developer.login"));
    }

    public function extend(Request $request, DeveloperAccessManager $developerAccess): Response
    {
        if (!$developerAccess->extendAccess()) {
            return $this->redirect(route("developer.login"));
        }

        Logger::write("notice", "Developer session extended", [
            "event" => "developer_session_extended",
            "ip" => $request->ip(),
        ]);
        flash_set("status", [
            "variant" => "success",
            "title" => "Developer session extended",
            "text" => "The current developer session window was refreshed without changing the absolute session limit.",
            "toast" => true,
        ]);
        regenerate_csrf_token();

        return $this->redirect(route("developer.panel"));
    }

    public function updateProjectSettings(
        Request $request,
        DeveloperAccessManager $developerAccess,
        EnvironmentFileManager $environmentFileManager
    ): Response {
        if (!$this->ensureDeveloperCapability($developerAccess, "project.identity.write")) {
            return $this->redirect(route("developer.panel.project_identity"));
        }

        $payload = [
            "project_name" => $this->normalizeProjectName((string) $request->input("project_name", "")),
            "project_tagline" => $this->normalizeProjectName((string) $request->input("project_tagline", "")),
            "project_url" => trim((string) $request->input("project_url", "")),
        ];

        try {
            $this->validate($payload, [
                "project_name" => ["required", "string", "min:2", "max:80"],
                "project_tagline" => ["nullable", "string", "max:120"],
                "project_url" => ["nullable", "string", "url", "max:2048"],
            ]);
        } catch (ValidationException $exception) {
            flash_set("errors", $exception->errors());
            flash_set("status", [
                "variant" => "warning",
                "title" => "Project settings still need attention",
                "text" => "Review the project name and public URL before saving again.",
                "toast" => false,
            ]);
            regenerate_csrf_token();

            return $this->redirect(route("developer.panel.project_identity"));
        }

        $environmentValues = [
            "APP_NAME" => $payload["project_name"],
            "APP_TAGLINE" => $payload["project_tagline"],
            "APP_URL" => $payload["project_url"],
        ];

        try {
            $environmentFileManager->write($environmentValues);
            $environmentFileManager->apply($environmentValues);
        } catch (\RuntimeException $exception) {
            flash_set("status", [
                "variant" => "danger",
                "title" => "Project settings could not be saved",
                "text" => $exception->getMessage(),
                "toast" => false,
            ]);
            regenerate_csrf_token();

            return $this->redirect(route("developer.panel.project_identity"));
        }

        config_set("app", array_merge((array) config("app", []), [
            "name" => $payload["project_name"],
            "tagline" => $payload["project_tagline"],
            "base_url" => rtrim($payload["project_url"], "/"),
        ]));
        $developerAccess->grantAccess();
        developer_activity()->record(
            "project_identity",
            "Project identity updated",
            "Project name, slogan or public URL was changed from the developer panel.",
            $developerAccess->currentDeveloper()
        );

        flash_set("status", [
            "variant" => "success",
            "title" => "Project settings saved",
            "text" => "The project identity, slogan and public URL were saved to the environment file.",
            "toast" => true,
        ]);
        regenerate_csrf_token();

        return $this->redirect(route("developer.panel.project_identity"));
    }

    public function updateProjectLeadership(
        Request $request,
        DeveloperAccessManager $developerAccess,
        EnvironmentFileManager $environmentFileManager
    ): Response {
        if (!$this->ensureDeveloperCapability($developerAccess, "project.identity.write")) {
            return $this->redirect(route("developer.panel.project_identity") . "#project-leadership");
        }

        $leadership = new ProjectLeadership();
        $payload = [
            "organization" => $this->normalizeProjectName((string) $request->input("project_leadership_organization", "")),
            "person_name" => $this->normalizeProjectName((string) $request->input("project_leadership_person_name", "")),
            "person_email" => strtolower(trim((string) $request->input("project_leadership_person_email", ""))),
            "person_role" => $this->normalizeProjectName((string) $request->input("project_leadership_person_role", "")),
            "responsibility" => $this->normalizeProjectName((string) $request->input("project_leadership_responsibility", "")),
            "profile_url" => trim((string) $request->input("project_leadership_profile_url", "")),
            "visibility" => $leadership->visibility((string) $request->input("project_leadership_visibility", ProjectLeadership::VISIBILITY_DISABLED)),
        ];
        $enabled = $payload["visibility"] !== ProjectLeadership::VISIBILITY_DISABLED;

        try {
            $rules = [
                "organization" => [$enabled ? "required" : "nullable", "string", "max:120"],
                "person_name" => [$enabled ? "required" : "nullable", "string", "max:120"],
                "person_email" => [$enabled ? "required" : "nullable", "email", "max:160"],
                "person_role" => [$enabled ? "required" : "nullable", "string", "max:120"],
                "responsibility" => [$enabled ? "required" : "nullable", "string", "max:240"],
                "profile_url" => ["nullable", "string", "url", "max:2048"],
                "visibility" => ["required", "string"],
            ];
            $this->validate($payload, $rules);
        } catch (ValidationException $exception) {
            flash_set("errors", $exception->errors());
            flash_set("old", [
                "project_leadership_organization" => $payload["organization"],
                "project_leadership_person_name" => $payload["person_name"],
                "project_leadership_person_email" => $payload["person_email"],
                "project_leadership_person_role" => $payload["person_role"],
                "project_leadership_responsibility" => $payload["responsibility"],
                "project_leadership_profile_url" => $payload["profile_url"],
                "project_leadership_visibility" => $payload["visibility"],
            ]);
            flash_set("status", [
                "variant" => "warning",
                "title" => "Leadership information still needs attention",
                "text" => "Use a real person, their confirmation email, role, responsibility scope and a supported visibility option.",
                "toast" => false,
            ]);
            regenerate_csrf_token();

            return $this->redirect(route("developer.panel.project_identity") . "#project-leadership");
        }

        $current = $leadership->state("admin");
        $status = $enabled && $leadership->sameIdentity($current, $payload)
            ? (string) ($current["status"] ?? ProjectLeadership::STATUS_PENDING)
            : ProjectLeadership::STATUS_PENDING;
        $confirmedBy = $status === ProjectLeadership::STATUS_CONFIRMED ? (string) ($current["confirmed_by"] ?? "") : "";
        $confirmedAt = $status === ProjectLeadership::STATUS_CONFIRMED ? (string) ($current["confirmed_at"] ?? "") : "";

        if (!$enabled) {
            $status = ProjectLeadership::STATUS_PENDING;
            $confirmedBy = "";
            $confirmedAt = "";
        }

        $environmentValues = [
            "PROJECT_LEADERSHIP_ORGANIZATION" => $payload["organization"],
            "PROJECT_LEADERSHIP_PERSON_NAME" => $payload["person_name"],
            "PROJECT_LEADERSHIP_PERSON_EMAIL" => $payload["person_email"],
            "PROJECT_LEADERSHIP_PERSON_ROLE" => $payload["person_role"],
            "PROJECT_LEADERSHIP_RESPONSIBILITY" => $payload["responsibility"],
            "PROJECT_LEADERSHIP_PROFILE_URL" => $payload["profile_url"],
            "PROJECT_LEADERSHIP_VISIBILITY" => $payload["visibility"],
            "PROJECT_LEADERSHIP_STATUS" => $status,
            "PROJECT_LEADERSHIP_CONFIRMED_BY" => $confirmedBy,
            "PROJECT_LEADERSHIP_CONFIRMED_AT" => $confirmedAt,
        ];

        try {
            $environmentFileManager->write($environmentValues);
            $environmentFileManager->apply($environmentValues);
        } catch (\RuntimeException $exception) {
            flash_set("status", [
                "variant" => "danger",
                "title" => "Leadership information could not be saved",
                "text" => $exception->getMessage(),
                "toast" => false,
            ]);
            regenerate_csrf_token();

            return $this->redirect(route("developer.panel.project_identity") . "#project-leadership");
        }

        $this->applyProjectLeadershipConfig($environmentValues);
        developer_activity()->record(
            "project_leadership",
            "Project leadership updated",
            $enabled
                ? "A developer named the person responsible for product direction or technical delivery. Confirmation is required before public display."
                : "Project leadership display was disabled.",
            $developerAccess->currentDeveloper()
        );

        flash_set("status", [
            "variant" => "success",
            "title" => $enabled ? "Leadership information saved" : "Leadership display disabled",
            "text" => $enabled
                ? "The named person must confirm this responsibility before it can appear publicly."
                : "The reusable leadership block is now off for this project.",
            "toast" => true,
        ]);
        regenerate_csrf_token();

        return $this->redirect(route("developer.panel.project_identity") . "#project-leadership");
    }

    public function confirmProjectLeadership(
        Request $request,
        DeveloperAccessManager $developerAccess,
        EnvironmentFileManager $environmentFileManager
    ): Response {
        $leadership = new ProjectLeadership();
        $state = $leadership->state("admin");
        $developer = $developerAccess->currentDeveloper();
        $action = strtolower(trim((string) $request->input("project_leadership_action", "")));

        if (!$leadership->canConfirm($state, $developer) || !in_array($action, ["confirm", "reject"], true)) {
            flash_set("status", [
                "variant" => "warning",
                "title" => "Leadership confirmation was not applied",
                "text" => "Only the named person, signed in with the matching developer email, can confirm or reject this responsibility.",
                "toast" => false,
            ]);
            regenerate_csrf_token();

            return $this->redirect(route("developer.panel.project_identity") . "#project-leadership");
        }

        $status = $action === "confirm" ? ProjectLeadership::STATUS_CONFIRMED : ProjectLeadership::STATUS_REJECTED;
        $environmentValues = [
            "PROJECT_LEADERSHIP_STATUS" => $status,
            "PROJECT_LEADERSHIP_CONFIRMED_BY" => (string) ($developer["email"] ?? ""),
            "PROJECT_LEADERSHIP_CONFIRMED_AT" => gmdate(DATE_ATOM),
        ];

        try {
            $environmentFileManager->write($environmentValues);
            $environmentFileManager->apply($environmentValues);
        } catch (\RuntimeException $exception) {
            flash_set("status", [
                "variant" => "danger",
                "title" => "Leadership confirmation could not be saved",
                "text" => $exception->getMessage(),
                "toast" => false,
            ]);
            regenerate_csrf_token();

            return $this->redirect(route("developer.panel.project_identity") . "#project-leadership");
        }

        $this->applyProjectLeadershipConfig($environmentValues);
        developer_activity()->record(
            "project_leadership",
            $action === "confirm" ? "Project leadership confirmed" : "Project leadership rejected",
            $action === "confirm"
                ? "The named person confirmed the displayed responsibility scope."
                : "The named person rejected the displayed responsibility scope.",
            $developer
        );

        flash_set("status", [
            "variant" => $action === "confirm" ? "success" : "warning",
            "title" => $action === "confirm" ? "Leadership confirmed" : "Leadership rejected",
            "text" => $action === "confirm"
                ? "The responsibility block can now be shown wherever its visibility allows it."
                : "The responsibility block will stay private until the details are corrected and confirmed.",
            "toast" => true,
        ]);
        regenerate_csrf_token();

        return $this->redirect(route("developer.panel.project_identity") . "#project-leadership");
    }

    public function updateMaintenanceCredentials(
        Request $request,
        DeveloperAccessManager $developerAccess,
        MaintenanceAccessManager $maintenanceAccess,
        EnvironmentFileManager $environmentFileManager
    ): Response {
        if (!$this->ensureDeveloperCapability($developerAccess, "preview.manage")) {
            return $this->redirect(route("developer.panel.project_settings"));
        }

        $payload = [
            "maintenance_access_password" => trim((string) $request->input("maintenance_access_password", "")),
            "maintenance_access_password_confirmation" => trim((string) $request->input("maintenance_access_password_confirmation", "")),
        ];
        $maintenanceEnabled = (string) $request->input("maintenance_access_enabled", "0") === "1";

        try {
            $this->validate($payload, [
                "maintenance_access_password" => ["required", "string", "min:8", "max:255", "confirmed"],
            ]);
        } catch (ValidationException $exception) {
            flash_set("errors", $exception->errors());
            flash_set("status", [
                "variant" => "warning",
                "title" => "Maintenance credentials still need attention",
                "text" => "Review the maintenance fields and save them again.",
                "toast" => false,
            ]);
            regenerate_csrf_token();

            return $this->redirect(route("developer.panel.project_settings"));
        }

        $environmentValues = [
            "MAINTENANCE_MODE_ENABLED" => $maintenanceEnabled ? "true" : "false",
            "MAINTENANCE_ACCESS_USERNAME" => "",
            "MAINTENANCE_ACCESS_PASSWORD" => $payload["maintenance_access_password"],
        ];

        try {
            $environmentFileManager->write($environmentValues);
            $environmentFileManager->apply($environmentValues);
        } catch (\RuntimeException $exception) {
            flash_set("status", [
                "variant" => "danger",
                "title" => "Maintenance credentials could not be saved",
                "text" => $exception->getMessage(),
                "toast" => false,
            ]);
            regenerate_csrf_token();

            return $this->redirect(route("developer.panel.project_settings"));
        }

        config_set("maintenance", array_merge((array) config("maintenance", []), [
            "enabled" => $maintenanceEnabled,
            "username" => $environmentValues["MAINTENANCE_ACCESS_USERNAME"],
            "password" => $environmentValues["MAINTENANCE_ACCESS_PASSWORD"],
        ]));
        $maintenanceAccess->lock();
        $developerAccess->grantAccess();
        developer_activity()->record(
            "client_preview",
            $maintenanceEnabled ? "Client preview lock enabled" : "Client preview credentials saved",
            $maintenanceEnabled ? "Public routes were protected by maintenance preview access." : "Preview credentials were rotated without locking public routes.",
            $developerAccess->currentDeveloper()
        );

        flash_set("status", [
            "variant" => "success",
            "title" => $maintenanceEnabled ? "Maintenance enabled" : "Maintenance credentials saved",
            "text" => $maintenanceEnabled
                ? "The maintenance password was saved and public routes are now protected by maintenance mode. The developer session stays active."
                : "The maintenance password was saved, but maintenance mode stays off until you decide to enable it.",
            "toast" => true,
        ]);
        regenerate_csrf_token();

        return $this->redirect(route("developer.panel.project_settings"));
    }

    public function updateDeveloperPassword(
        Request $request,
        DeveloperAccessManager $developerAccess,
        EnvironmentFileManager $environmentFileManager
    ): Response {
        if (!$this->ensureDeveloperCapability($developerAccess, "developer.profile.write")) {
            return $this->redirect($this->developerPasswordRedirect($request));
        }

        $payload = [
            "developer_access_password" => trim((string) $request->input("developer_access_password", "")),
            "developer_access_password_confirmation" => trim((string) $request->input("developer_access_password_confirmation", "")),
        ];

        try {
            $this->validate($payload, [
                "developer_access_password" => ["required", "string", "min:8", "max:255", "confirmed"],
            ]);
        } catch (ValidationException $exception) {
            flash_set("errors", $exception->errors());
            flash_set("status", [
                "variant" => "warning",
                "title" => "Developer password still needs attention",
                "text" => "Review the developer password fields and save them again.",
                "toast" => false,
            ]);
            regenerate_csrf_token();

            return $this->redirect($this->developerPasswordRedirect($request));
        }

        $passwordHash = password_hash($payload["developer_access_password"], PASSWORD_DEFAULT);
        $currentDeveloper = $developerAccess->currentDeveloper();
        $currentEmail = trim((string) ($currentDeveloper["email"] ?? ""));
        $usesNamedAccounts = $currentEmail !== "";

        try {
            if ($usesNamedAccounts) {
                $accounts = $developerAccess->upsertAccount([
                    "email" => $currentEmail,
                    "name" => (string) ($currentDeveloper["name"] ?? "Developer"),
                    "role" => (string) ($currentDeveloper["role"] ?? "admin"),
                    "password_hash" => $passwordHash,
                ]);
                $serializedAccounts = $developerAccess->serializeAccounts($accounts);
                $environmentValues = [
                    "DEVELOPER_ACCESS_EMAIL" => $currentEmail,
                    "DEVELOPER_ACCESS_PASSWORD" => "",
                    "DEVELOPER_ACCESS_PASSWORD_HASH" => "",
                    "DEVELOPER_ACCESS_USERS" => $serializedAccounts,
                ];
            } else {
                $environmentValues = [
                    "DEVELOPER_ACCESS_PASSWORD" => "",
                    "DEVELOPER_ACCESS_PASSWORD_HASH" => $passwordHash,
                ];
            }

            $environmentFileManager->write($environmentValues);
            $environmentFileManager->apply($environmentValues);
        } catch (\RuntimeException $exception) {
            flash_set("status", [
                "variant" => "danger",
                "title" => "Developer password could not be saved",
                "text" => $exception->getMessage(),
                "toast" => false,
            ]);
            regenerate_csrf_token();

            return $this->redirect($this->developerPasswordRedirect($request));
        }

        config_set("developer_access", array_merge((array) config("developer_access", []), $usesNamedAccounts
            ? [
                "email" => $currentEmail,
                "password" => "",
                "password_hash" => "",
                "users" => (string) ($environmentValues["DEVELOPER_ACCESS_USERS"] ?? ""),
            ]
            : [
                "password" => "",
                "password_hash" => $passwordHash,
            ]));
        $developerAccess->grantAccess($usesNamedAccounts ? $this->developerAccountByEmail($developerAccess->accounts(), $currentEmail) : null);
        Logger::write("notice", "Developer password updated", [
            "event" => "developer_password_updated",
            "email" => $currentEmail,
        ]);
        developer_activity()->record(
            "developer_access",
            "Developer password rotated",
            $usesNamedAccounts ? "A named developer account password was rotated." : "The legacy developer panel password was rotated.",
            $developerAccess->currentDeveloper()
        );

        flash_set("status", [
            "variant" => "success",
            "title" => "Developer password updated",
            "text" => "The hidden panel password was saved and this browser session stayed unlocked.",
            "toast" => true,
        ]);
        regenerate_csrf_token();

        return $this->redirect($this->developerPasswordRedirect($request));
    }

    public function saveDeveloperProfile(
        Request $request,
        DeveloperAccessManager $developerAccess,
        EnvironmentFileManager $environmentFileManager
    ): Response {
        if (!$this->ensureDeveloperCapability($developerAccess, "developer.profile.write")) {
            return $this->redirect(route("developer.panel.profile"));
        }

        $currentDeveloper = $developerAccess->currentDeveloper();
        $currentEmail = strtolower(trim((string) ($currentDeveloper["email"] ?? "")));
        $roleOptions = $developerAccess->roleOptions();
        $payload = [
            "developer_profile_name" => trim((string) $request->input("developer_profile_name", "")),
            "developer_profile_role" => strtolower(str_replace([" ", "-"], "_", trim((string) $request->input("developer_profile_role", "")))),
            "developer_profile_avatar" => trim((string) $request->input("developer_profile_avatar", "")),
            "developer_profile_generate_avatar" => (string) $request->input("developer_profile_generate_avatar", "") === "1",
        ];

        if (!array_key_exists($payload["developer_profile_role"], $roleOptions)) {
            $payload["developer_profile_role"] = "application_developer";
        }

        if ($currentEmail === "") {
            flash_set("status", [
                "variant" => "warning",
                "title" => "Developer profile needs a named account",
                "text" => "Create a named developer account before editing the profile details.",
                "toast" => false,
            ]);
            regenerate_csrf_token();

            return $this->redirect(route("developer.panel.access"));
        }

        try {
            $this->validate([
                "developer_profile_name" => $payload["developer_profile_name"],
                "developer_profile_role" => $payload["developer_profile_role"],
                "developer_profile_avatar" => $payload["developer_profile_avatar"],
            ], [
                "developer_profile_name" => ["required", "string", "min:2", "max:100"],
                "developer_profile_role" => ["required", "string"],
                "developer_profile_avatar" => ["nullable", "string", "max:2048"],
            ]);
        } catch (ValidationException $exception) {
            flash_set("errors", $exception->errors());
            flash_set("old", [
                "developer_profile_name" => $payload["developer_profile_name"],
                "developer_profile_role" => $payload["developer_profile_role"],
                "developer_profile_avatar" => $payload["developer_profile_avatar"],
            ]);
            flash_set("status", [
                "variant" => "warning",
                "title" => "Developer profile still needs attention",
                "text" => "Use a display name, a supported developer role and an optional short avatar mark or HTTP(S) image URL.",
                "toast" => false,
            ]);
            regenerate_csrf_token();

            return $this->redirect(route("developer.panel.profile"));
        }

        try {
            $payload["developer_profile_avatar"] = $this->resolveDeveloperAvatar($request, $payload);
        } catch (\RuntimeException $exception) {
            flash_set("old", [
                "developer_profile_name" => $payload["developer_profile_name"],
                "developer_profile_role" => $payload["developer_profile_role"],
                "developer_profile_avatar" => $payload["developer_profile_avatar"],
            ]);
            flash_set("status", [
                "variant" => "warning",
                "title" => "Developer avatar could not be saved",
                "text" => $exception->getMessage(),
                "toast" => false,
            ]);
            regenerate_csrf_token();

            return $this->redirect(route("developer.panel.profile"));
        }

        $accounts = $developerAccess->updateAccountProfile($currentEmail, [
            "name" => $payload["developer_profile_name"],
            "role" => $payload["developer_profile_role"],
            "avatar" => $payload["developer_profile_avatar"],
        ]);
        $serializedAccounts = $developerAccess->serializeAccounts($accounts);

        try {
            $environmentFileManager->write([
                "DEVELOPER_ACCESS_EMAIL" => $accounts[0]["email"] ?? $currentEmail,
                "DEVELOPER_ACCESS_PASSWORD" => "",
                "DEVELOPER_ACCESS_PASSWORD_HASH" => "",
                "DEVELOPER_ACCESS_USERS" => $serializedAccounts,
            ]);
            $environmentFileManager->apply([
                "DEVELOPER_ACCESS_EMAIL" => $accounts[0]["email"] ?? $currentEmail,
                "DEVELOPER_ACCESS_PASSWORD" => "",
                "DEVELOPER_ACCESS_PASSWORD_HASH" => "",
                "DEVELOPER_ACCESS_USERS" => $serializedAccounts,
            ]);
        } catch (\RuntimeException $exception) {
            flash_set("status", [
                "variant" => "danger",
                "title" => "Developer profile could not be saved",
                "text" => $exception->getMessage(),
                "toast" => false,
            ]);
            regenerate_csrf_token();

            return $this->redirect(route("developer.panel.profile"));
        }

        config_set("developer_access", array_merge((array) config("developer_access", []), [
            "email" => (string) ($accounts[0]["email"] ?? $currentEmail),
            "password" => "",
            "password_hash" => "",
            "users" => $serializedAccounts,
        ]));
        $developerAccess->grantAccess($this->developerAccountByEmail($developerAccess->accounts(), $currentEmail));
        developer_activity()->record(
            "developer_profile",
            "Developer profile updated",
            "A developer changed their display name, role or avatar metadata.",
            $developerAccess->currentDeveloper()
        );

        flash_set("status", [
            "variant" => "success",
            "title" => "Developer profile saved",
            "text" => "Your developer profile was saved globally for this project.",
            "toast" => true,
        ]);
        regenerate_csrf_token();

        return $this->redirect(route("developer.panel.profile"));
    }

    public function updateDeveloperSecurity(
        Request $request,
        DeveloperAccessManager $developerAccess,
        EnvironmentFileManager $environmentFileManager
    ): Response {
        if (!$this->ensureDeveloperCapability($developerAccess, "developer.security.manage")) {
            return $this->redirect(route("developer.panel.security"));
        }

        $currentDeveloper = $developerAccess->currentDeveloper();
        $currentEmail = strtolower(trim((string) ($currentDeveloper["email"] ?? "")));

        if ($currentEmail === "") {
            flash_set("status", [
                "variant" => "warning",
                "title" => "Named developer required",
                "text" => "Create a named developer account before enabling two-factor security.",
                "toast" => false,
            ]);
            regenerate_csrf_token();

            return $this->redirect(route("developer.panel.access"));
        }

        $action = trim((string) $request->input("developer_security_action", ""));
        $state = $developerAccess->securityState($currentDeveloper);

        if ($action === "generate_totp") {
            $accounts = $developerAccess->updateAccountSecurity($currentEmail, [
                "totp_secret" => $developerAccess->newTotpSecret(),
                "totp_enabled" => false,
            ]);
            $message = "Authenticator setup key generated. Confirm it with a six-digit code before it is enforced.";
        } elseif ($action === "enable_totp") {
            $code = trim((string) $request->input("developer_totp_code", ""));

            if (!($state["totp_configured"] ?? false) || !$developerAccess->verifyTotpForEmail($currentEmail, $code)) {
                flash_set("status", [
                    "variant" => "warning",
                    "title" => "Authenticator code rejected",
                    "text" => "Enter the current six-digit code from your authenticator app before enabling TOTP.",
                    "toast" => false,
                ]);
                regenerate_csrf_token();

                return $this->redirect(route("developer.panel.security"));
            }

            $accounts = $developerAccess->updateAccountSecurity($currentEmail, [
                "totp_secret" => (string) ($state["totp_secret"] ?? ""),
                "totp_enabled" => true,
            ]);
            $message = "Two-factor authentication is now required for this developer account.";
        } elseif ($action === "disable_totp") {
            $accounts = $developerAccess->updateAccountSecurity($currentEmail, [
                "totp_secret" => "",
                "totp_enabled" => false,
            ]);
            $message = "Two-factor authentication was disabled for this developer account.";
        } elseif ($action === "passkey_contract") {
            $label = trim((string) $request->input("developer_passkey_label", ""));
            $adapter = trim((string) $request->input("developer_passkey_adapter", "disabled"));

            try {
                $this->validate([
                    "developer_passkey_label" => $label,
                    "developer_passkey_adapter" => $adapter,
                ], [
                    "developer_passkey_label" => ["nullable", "string", "max:100"],
                    "developer_passkey_adapter" => ["required", "string"],
                ]);
            } catch (ValidationException $exception) {
                flash_set("errors", $exception->errors());
                regenerate_csrf_token();

                return $this->redirect(route("developer.panel.security"));
            }

            $accounts = $developerAccess->updateAccountSecurity($currentEmail, [
                "passkey_label" => $label,
                "passkey_adapter" => $adapter === "external" ? "external" : "disabled",
            ]);
            $message = $adapter === "external"
                ? "Passkey adapter metadata was saved. The actual WebAuthn provider remains project-owned."
                : "Passkey adapter metadata was cleared.";
        } else {
            return $this->redirect(route("developer.panel.security"));
        }

        $serializedAccounts = $developerAccess->serializeAccounts($accounts);

        try {
            $environmentFileManager->write([
                "DEVELOPER_ACCESS_EMAIL" => $accounts[0]["email"] ?? $currentEmail,
                "DEVELOPER_ACCESS_PASSWORD" => "",
                "DEVELOPER_ACCESS_PASSWORD_HASH" => "",
                "DEVELOPER_ACCESS_USERS" => $serializedAccounts,
            ]);
            $environmentFileManager->apply([
                "DEVELOPER_ACCESS_EMAIL" => $accounts[0]["email"] ?? $currentEmail,
                "DEVELOPER_ACCESS_PASSWORD" => "",
                "DEVELOPER_ACCESS_PASSWORD_HASH" => "",
                "DEVELOPER_ACCESS_USERS" => $serializedAccounts,
            ]);
        } catch (\RuntimeException $exception) {
            flash_set("status", [
                "variant" => "danger",
                "title" => "Developer security could not be saved",
                "text" => $exception->getMessage(),
                "toast" => false,
            ]);
            regenerate_csrf_token();

            return $this->redirect(route("developer.panel.security"));
        }

        config_set("developer_access", array_merge((array) config("developer_access", []), [
            "email" => (string) ($accounts[0]["email"] ?? $currentEmail),
            "password" => "",
            "password_hash" => "",
            "users" => $serializedAccounts,
        ]));
        $developerAccess->grantAccess($this->developerAccountByEmail($developerAccess->accounts(), $currentEmail));
        developer_activity()->record(
            "developer_security",
            "Developer security updated",
            $message,
            $developerAccess->currentDeveloper()
        );

        flash_set("status", [
            "variant" => "success",
            "title" => "Developer security saved",
            "text" => $message,
            "toast" => true,
        ]);
        regenerate_csrf_token();

        return $this->redirect(route("developer.panel.security"));
    }

    public function createWorkspaceTask(Request $request, DeveloperAccessManager $developerAccess, DeveloperWorkspaceBoard $workspace): Response
    {
        if (!$this->ensureDeveloperCapability($developerAccess, "workspace.write")) {
            return $this->redirect(route("developer.panel.workspace"));
        }

        $payload = $this->workspaceTaskPayload($request, $developerAccess->currentDeveloper());

        if ($payload["title"] === "") {
            flash_set("status", [
                "variant" => "warning",
                "title" => "Task needs a title",
                "text" => "Add a short task title before saving it to the developer workspace.",
                "toast" => true,
            ]);
            regenerate_csrf_token();

            return $this->redirect(route("developer.panel.workspace"));
        }

        $workspace->create($payload, $developerAccess->currentDeveloper());
        developer_activity()->record(
            "developer_workspace_task",
            "Workspace task created",
            "A developer added a project task to the Kanban workspace.",
            $developerAccess->currentDeveloper()
        );

        flash_set("status", [
            "variant" => "success",
            "title" => "Workspace task saved",
            "text" => "The project workspace was updated for every developer session.",
            "toast" => true,
        ]);
        regenerate_csrf_token();

        return $this->redirect(route("developer.panel.workspace"));
    }

    public function updateWorkspaceTask(Request $request, DeveloperAccessManager $developerAccess, DeveloperWorkspaceBoard $workspace): Response
    {
        if (!$this->ensureDeveloperCapability($developerAccess, "workspace.write")) {
            return $this->redirect(route("developer.panel.workspace"));
        }

        $taskId = trim((string) $request->input("developer_workspace_task_id", ""));

        if ($taskId === "") {
            return $this->redirect(route("developer.panel.workspace"));
        }

        $workspace->update($taskId, $this->workspaceTaskPayload($request, $developerAccess->currentDeveloper()), $developerAccess->currentDeveloper());
        developer_activity()->record(
            "developer_workspace_task",
            "Workspace task updated",
            "A developer changed a Kanban task status, owner or priority.",
            $developerAccess->currentDeveloper()
        );

        flash_set("status", [
            "variant" => "success",
            "title" => "Workspace task updated",
            "text" => "The shared developer workspace now shows the latest state.",
            "toast" => true,
        ]);
        regenerate_csrf_token();

        return $this->redirect(route("developer.panel.workspace"));
    }

    public function deleteWorkspaceTask(Request $request, DeveloperAccessManager $developerAccess, DeveloperWorkspaceBoard $workspace): Response
    {
        if (!$this->ensureDeveloperCapability($developerAccess, "workspace.write")) {
            return $this->redirect(route("developer.panel.workspace"));
        }

        $taskId = trim((string) $request->input("developer_workspace_task_id", ""));

        if ($taskId !== "") {
            $workspace->delete($taskId);
            developer_activity()->record(
                "developer_workspace_task",
                "Workspace task removed",
                "A developer removed a project task from the Kanban workspace.",
                $developerAccess->currentDeveloper()
            );
        }

        flash_set("status", [
            "variant" => "success",
            "title" => "Workspace task removed",
            "text" => "The shared developer workspace was updated.",
            "toast" => true,
        ]);
        regenerate_csrf_token();

        return $this->redirect(route("developer.panel.workspace"));
    }

    public function saveDeveloperAccount(
        Request $request,
        DeveloperAccessManager $developerAccess,
        EnvironmentFileManager $environmentFileManager
    ): Response {
        if (!$this->ensureDeveloperCapability($developerAccess, "developer.accounts.write")) {
            return $this->redirect(route("developer.panel.access"));
        }

        $payload = [
            "developer_account_email" => strtolower(trim((string) $request->input("developer_account_email", ""))),
            "developer_account_name" => trim((string) $request->input("developer_account_name", "Developer")),
            "developer_account_role" => strtolower(str_replace([" ", "-"], "_", trim((string) $request->input("developer_account_role", "application_developer")))),
            "developer_account_password" => trim((string) $request->input("developer_account_password", "")),
            "developer_account_password_confirmation" => trim((string) $request->input("developer_account_password_confirmation", "")),
        ];

        if (!array_key_exists($payload["developer_account_role"], $developerAccess->roleOptions())) {
            $payload["developer_account_role"] = "application_developer";
        }

        try {
            $this->validate($payload, [
                "developer_account_email" => ["required", "email", "max:160"],
                "developer_account_name" => ["required", "string", "min:2", "max:100"],
                "developer_account_role" => ["required", "string"],
                "developer_account_password" => ["required", "string", "min:8", "max:255", "confirmed"],
            ]);
        } catch (ValidationException $exception) {
            flash_set("errors", $exception->errors());
            flash_set("old", [
                "developer_account_email" => $payload["developer_account_email"],
                "developer_account_name" => $payload["developer_account_name"],
                "developer_account_role" => $payload["developer_account_role"],
            ]);
            flash_set("status", [
                "variant" => "warning",
                "title" => "Developer account still needs attention",
                "text" => "Use a valid email, name, role and confirmed password for the developer account.",
                "toast" => false,
            ]);
            regenerate_csrf_token();

            return $this->redirect(route("developer.panel.access"));
        }

        $currentDeveloper = $developerAccess->currentDeveloper();
        $accounts = $developerAccess->upsertAccount([
            "email" => $payload["developer_account_email"],
            "name" => $payload["developer_account_name"],
            "role" => $payload["developer_account_role"],
            "password_hash" => password_hash($payload["developer_account_password"], PASSWORD_DEFAULT),
        ]);
        $serializedAccounts = $developerAccess->serializeAccounts($accounts);
        $environmentValues = [
            "DEVELOPER_ACCESS_EMAIL" => $accounts[0]["email"] ?? $payload["developer_account_email"],
            "DEVELOPER_ACCESS_PASSWORD" => "",
            "DEVELOPER_ACCESS_PASSWORD_HASH" => "",
            "DEVELOPER_ACCESS_USERS" => $serializedAccounts,
        ];

        try {
            $environmentFileManager->write($environmentValues);
            $environmentFileManager->apply($environmentValues);
        } catch (\RuntimeException $exception) {
            flash_set("status", [
                "variant" => "danger",
                "title" => "Developer account could not be saved",
                "text" => $exception->getMessage(),
                "toast" => false,
            ]);
            regenerate_csrf_token();

            return $this->redirect(route("developer.panel.access"));
        }

        config_set("developer_access", array_merge((array) config("developer_access", []), [
            "email" => (string) $environmentValues["DEVELOPER_ACCESS_EMAIL"],
            "password" => "",
            "password_hash" => "",
            "users" => $serializedAccounts,
        ]));
        $developerAccess->grantAccess($this->developerAccountByEmail($developerAccess->accounts(), (string) ($currentDeveloper["email"] ?? "")) ?? $developerAccess->accounts()[0] ?? null);
        developer_activity()->record(
            "developer_access",
            "Developer account saved",
            "A named developer account was added or rotated in the project access list.",
            $developerAccess->currentDeveloper()
        );

        flash_set("status", [
            "variant" => "success",
            "title" => "Developer account saved",
            "text" => "The named developer account was saved globally. Other developer sessions will see this change in the activity feed.",
            "toast" => true,
        ]);
        regenerate_csrf_token();

        return $this->redirect(route("developer.panel.access"));
    }

    public function deleteDeveloperAccount(
        Request $request,
        DeveloperAccessManager $developerAccess,
        EnvironmentFileManager $environmentFileManager
    ): Response {
        if (!$this->ensureDeveloperCapability($developerAccess, "developer.accounts.write")) {
            return $this->redirect(route("developer.panel.access"));
        }

        $email = strtolower(trim((string) $request->input("developer_account_email", "")));
        $currentEmail = strtolower(trim((string) ($developerAccess->currentDeveloper()["email"] ?? "")));

        if ($email === "" || $email === $currentEmail || count($developerAccess->accounts()) <= 1) {
            flash_set("status", [
                "variant" => "warning",
                "title" => "Developer account was not deactivated",
                "text" => "The current account and the final remaining developer account cannot be removed from the panel.",
                "toast" => false,
            ]);
            regenerate_csrf_token();

            return $this->redirect(route("developer.panel.access"));
        }

        $accounts = $developerAccess->removeAccount($email);
        $serializedAccounts = $developerAccess->serializeAccounts($accounts);

        try {
            $environmentFileManager->write([
                "DEVELOPER_ACCESS_EMAIL" => $accounts[0]["email"] ?? "",
                "DEVELOPER_ACCESS_PASSWORD" => "",
                "DEVELOPER_ACCESS_PASSWORD_HASH" => "",
                "DEVELOPER_ACCESS_USERS" => $serializedAccounts,
            ]);
            $environmentFileManager->apply([
                "DEVELOPER_ACCESS_EMAIL" => $accounts[0]["email"] ?? "",
                "DEVELOPER_ACCESS_PASSWORD" => "",
                "DEVELOPER_ACCESS_PASSWORD_HASH" => "",
                "DEVELOPER_ACCESS_USERS" => $serializedAccounts,
            ]);
        } catch (\RuntimeException $exception) {
            flash_set("status", [
                "variant" => "danger",
                "title" => "Developer account could not be deactivated",
                "text" => $exception->getMessage(),
                "toast" => false,
            ]);
            regenerate_csrf_token();

            return $this->redirect(route("developer.panel.access"));
        }

        config_set("developer_access", array_merge((array) config("developer_access", []), [
            "email" => (string) ($accounts[0]["email"] ?? ""),
            "password" => "",
            "password_hash" => "",
            "users" => $serializedAccounts,
        ]));
        $developerAccess->grantAccess($this->developerAccountByEmail($developerAccess->accounts(), $currentEmail));
        developer_activity()->record(
            "developer_access",
            "Developer account deactivated",
            "A named developer account was removed from the project access list.",
            $developerAccess->currentDeveloper()
        );

        flash_set("status", [
            "variant" => "success",
            "title" => "Developer account deactivated",
            "text" => "The account was removed and existing sessions will be invalidated by the updated credential fingerprint.",
            "toast" => true,
        ]);
        regenerate_csrf_token();

        return $this->redirect(route("developer.panel.access"));
    }

    public function updateNavigationMode(
        Request $request,
        DeveloperAccessManager $developerAccess,
        EnvironmentFileManager $environmentFileManager
    ): Response {
        if (!$this->ensureDeveloperCapability($developerAccess, "panel.settings.write")) {
            return $this->redirect(route("developer.panel.settings"));
        }

        $payload = [
            "developer_operations_nav_mode" => trim((string) $request->input("developer_operations_nav_mode", "hidden")),
            "developer_access_ttl_minutes" => (int) $request->input("developer_access_ttl_minutes", (int) config("developer_access.unlock_ttl_minutes", 120)),
            "developer_access_absolute_ttl_minutes" => (int) $request->input("developer_access_absolute_ttl_minutes", (int) config("developer_access.absolute_ttl_minutes", 480)),
        ];

        if (!in_array($payload["developer_operations_nav_mode"], ["hidden", "developer_session_only"], true)) {
            $payload["developer_operations_nav_mode"] = "hidden";
        }

        try {
            $this->validate($payload, [
                "developer_operations_nav_mode" => ["required", "string"],
                "developer_access_ttl_minutes" => ["required", "integer", "min:5", "max:240"],
                "developer_access_absolute_ttl_minutes" => ["required", "integer", "min:5", "max:720"],
            ]);
        } catch (ValidationException $exception) {
            flash_set("errors", $exception->errors());
            flash_set("status", [
                "variant" => "warning",
                "title" => "Developer panel settings still need attention",
                "text" => "Use a session window between 5 and 240 minutes and an absolute window between 5 and 720 minutes.",
                "toast" => false,
            ]);
            regenerate_csrf_token();

            return $this->redirect(route("developer.panel.settings"));
        }

        if ($payload["developer_access_ttl_minutes"] > $payload["developer_access_absolute_ttl_minutes"]) {
            flash_set("status", [
                "variant" => "warning",
                "title" => "Developer panel settings still need attention",
                "text" => "The normal session window cannot be longer than the absolute developer access window.",
                "toast" => false,
            ]);
            regenerate_csrf_token();

            return $this->redirect(route("developer.panel.settings"));
        }

        try {
            $environmentFileManager->write([
                "DEVELOPER_OPERATIONS_NAV_MODE" => $payload["developer_operations_nav_mode"],
                "DEVELOPER_ACCESS_TTL_MINUTES" => (string) $payload["developer_access_ttl_minutes"],
                "DEVELOPER_ACCESS_ABSOLUTE_TTL_MINUTES" => (string) $payload["developer_access_absolute_ttl_minutes"],
            ]);
            $environmentFileManager->apply([
                "DEVELOPER_OPERATIONS_NAV_MODE" => $payload["developer_operations_nav_mode"],
                "DEVELOPER_ACCESS_TTL_MINUTES" => (string) $payload["developer_access_ttl_minutes"],
                "DEVELOPER_ACCESS_ABSOLUTE_TTL_MINUTES" => (string) $payload["developer_access_absolute_ttl_minutes"],
            ]);
        } catch (\RuntimeException $exception) {
            flash_set("status", [
                "variant" => "danger",
                "title" => "Developer navigation preference could not be saved",
                "text" => $exception->getMessage(),
                "toast" => false,
            ]);
            regenerate_csrf_token();

            return $this->redirect(route("developer.panel.settings"));
        }

        config_set("developer_access", array_merge((array) config("developer_access", []), [
            "operations_nav_mode" => $payload["developer_operations_nav_mode"],
            "unlock_ttl_minutes" => $payload["developer_access_ttl_minutes"],
            "absolute_ttl_minutes" => $payload["developer_access_absolute_ttl_minutes"],
        ]));
        $developerAccess->grantAccess();
        developer_activity()->record(
            "panel_settings",
            "Developer panel settings updated",
            "Developer session windows or navigation visibility were changed.",
            $developerAccess->currentDeveloper()
        );

        flash_set("status", [
            "variant" => "success",
            "title" => "Developer panel settings saved",
            "text" => "The developer entry, session window and private navigation preference were saved for this project.",
            "toast" => true,
        ]);
        regenerate_csrf_token();

        return $this->redirect(route("developer.panel.settings"));
    }

    private function projectSettings(?array $leadership = null): array
    {
        return [
            "name" => (string) config("app.name", "FNLLA Project"),
            "tagline" => (string) config("app.tagline", ""),
            "url" => (string) config("app.base_url", ""),
            "leadership" => $leadership ?? project_leadership("admin"),
        ];
    }

    private function developerDashboard(array $developerAccess, array $maintenanceAccess, ?array $leadership = null): array
    {
        return [
            "environment" => app_environment(),
            "project_name" => (string) config("app.name", "FNLLA Project"),
            "project_tagline" => (string) config("app.tagline", ""),
            "project_url" => (string) config("app.base_url", ""),
            "project_leadership" => $leadership ?? project_leadership("admin"),
            "maintenance_enabled" => (bool) ($maintenanceAccess["enabled"] ?? false),
            "maintenance_configured" => (bool) ($maintenanceAccess["configured"] ?? false),
            "developer_session_minutes" => (int) ($developerAccess["unlock_ttl_minutes"] ?? 120),
            "developer_absolute_minutes" => (int) ($developerAccess["absolute_ttl_minutes"] ?? 480),
            "developer_nav_mode" => (string) ($developerAccess["operations_nav_mode"] ?? "hidden"),
            "framework_version" => $this->readVersionFile(base_path("VERSION")) ?? "unknown",
            "runtime_version" => $this->readVersionFile(public_path("vendor/fnlla-runtime/VERSION")) ?? "unknown",
            "framework_lock" => is_file(base_path(".fnlla/framework-lock.json")),
            "storage_writable" => is_dir(storage_path()) && is_writable(storage_path()),
            "session_storage_writable" => is_dir(storage_path("framework/sessions")) && is_writable(storage_path("framework/sessions")),
            "queue_storage_writable" => is_dir(storage_path("framework/queue")) && is_writable(storage_path("framework/queue")),
            "observability_enabled" => (bool) config("observability.metrics.enabled", false),
        ];
    }

    private function normalizeProjectName(string $value): string
    {
        return trim((string) preg_replace('/\s+/', ' ', $value));
    }

    private function applyProjectLeadershipConfig(array $values): void
    {
        $current = (array) config("app.project_leadership", []);
        $map = [
            "PROJECT_LEADERSHIP_ORGANIZATION" => "organization",
            "PROJECT_LEADERSHIP_PERSON_NAME" => "person_name",
            "PROJECT_LEADERSHIP_PERSON_EMAIL" => "person_email",
            "PROJECT_LEADERSHIP_PERSON_ROLE" => "person_role",
            "PROJECT_LEADERSHIP_RESPONSIBILITY" => "responsibility",
            "PROJECT_LEADERSHIP_PROFILE_URL" => "profile_url",
            "PROJECT_LEADERSHIP_VISIBILITY" => "visibility",
            "PROJECT_LEADERSHIP_STATUS" => "status",
            "PROJECT_LEADERSHIP_CONFIRMED_BY" => "confirmed_by",
            "PROJECT_LEADERSHIP_CONFIRMED_AT" => "confirmed_at",
        ];

        foreach ($map as $envKey => $configKey) {
            if (array_key_exists($envKey, $values)) {
                $current[$configKey] = is_bool($values[$envKey])
                    ? ($values[$envKey] ? "true" : "false")
                    : (string) $values[$envKey];
            }
        }

        config_set("app.project_leadership", $current);
    }

    private function renderDeveloperPanel(
        DeveloperAccessManager $developerAccess,
        MaintenanceAccessManager $maintenanceAccess,
        string $view,
        string $pageTitle,
        string $activeSection,
        array $extraData = []
    ): Response {
        if (!$developerAccess->enabled() || !$developerAccess->configured()) {
            return $this->view("pages/not-found", [
                "pageTitle" => "Not Found",
            ], 404);
        }

        if (!$developerAccess->isUnlocked()) {
            return $this->redirect(route("developer.login"));
        }

        $maintenanceAccess->lock();
        $developerAccessState = $developerAccess->viewState();
        $maintenanceAccessState = $maintenanceAccess->viewState();
        $projectLeadership = project_leadership("admin");
        $developerControl = developer_control()->state();
        $developerDashboard = $this->developerDashboard($developerAccessState, $maintenanceAccessState, $projectLeadership);
        $developerHeaderNotifications = (new DeveloperNotificationCenter())->build($developerAccessState, $developerDashboard, [], $developerControl);
        return $this->view($view, array_merge([
            "pageTitle" => $pageTitle,
            "pageTitleSection" => $pageTitle === "Dashboard" ? "Developer Panel" : "Developer Panel",
            "layoutChromeMode" => "developer-panel",
            "developerPanelActive" => $activeSection,
            "developerAccess" => $developerAccessState,
            "maintenanceAccess" => $maintenanceAccessState,
            "developerActivity" => developer_activity()->recent(),
            "developerControl" => $developerControl,
            "developerHeaderNotifications" => $developerHeaderNotifications,
            "developerLinks" => [
                "home" => route("home"),
                "overview" => route("developer.panel"),
                "identity" => route("developer.panel.project_identity"),
                "project_settings" => route("developer.panel.project_settings"),
                "access" => route("developer.panel.access"),
                "profile" => route("developer.panel.profile"),
                "security" => route("developer.panel.access"),
                "settings" => route("developer.panel.settings"),
                "health" => route("developer.panel.release_readiness"),
                "framework_updates" => route("developer.panel.framework_updates"),
                "framework_updates_run" => route("developer.panel.framework_updates.run"),
                "operations" => route("developer.panel.operations"),
                "analytics" => route("developer.panel.analytics"),
                "heatmap" => route("developer.panel.heatmap"),
                "notifications" => route("developer.panel.notifications"),
                "notifications_action" => route("developer.panel.notifications.action"),
                "release_readiness" => route("developer.panel.release_readiness"),
                "integrations" => route("developer.panel.integrations"),
                "workspace" => route("developer.panel.workspace"),
                "policy" => route("developer.panel.policy"),
                "documentation" => route("developer.panel.documentation"),
                "about" => route("developer.panel.about"),
                "audit_export" => route("developer.panel.audit_export"),
                "audit_export_csv" => route("developer.panel.audit_export_csv"),
                "project_leadership" => route("developer.settings.project_leadership"),
                "project_leadership_confirmation" => route("developer.settings.project_leadership.confirmation"),
            ],
            "projectSettings" => $this->projectSettings($projectLeadership),
            "developerDashboard" => $developerDashboard,
            "developerNotice" => flash("developer_access_notice"),
        ], $extraData));
    }

    private function readVersionFile(string $path): ?string
    {
        if (!is_file($path)) {
            return null;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES);
        $version = is_array($lines) ? trim((string) ($lines[0] ?? "")) : "";

        return $version !== "" ? $version : null;
    }

    private function developerAccountByEmail(array $accounts, string $email): ?array
    {
        $email = strtolower(trim($email));

        if ($email === "") {
            return null;
        }

        foreach ($accounts as $account) {
            if (($account["email"] ?? "") === $email) {
                return $account;
            }
        }

        return null;
    }

    private function developerPasswordRedirect(Request $request): string
    {
        return (string) $request->input("developer_password_redirect", "") === "profile"
            ? route("developer.panel.profile")
            : route("developer.panel.access");
    }

    private function workspaceTaskPayload(Request $request, array $developer = []): array
    {
        $developerEmail = strtolower(trim((string) ($developer["email"] ?? "")));

        return [
            "title" => trim((string) $request->input("developer_workspace_title", "")),
            "notes" => trim((string) $request->input("developer_workspace_notes", "")),
            "status" => trim((string) $request->input("developer_workspace_status", "todo")),
            "priority" => trim((string) $request->input("developer_workspace_priority", "normal")),
            "type" => trim((string) $request->input("developer_workspace_type", "task")),
            "color" => trim((string) $request->input("developer_workspace_color", "blue")),
            "assignee" => trim((string) $request->input("developer_workspace_assignee", "")),
            "due_date" => trim((string) $request->input("developer_workspace_due_date", "")),
            "estimate" => trim((string) $request->input("developer_workspace_estimate", "")),
            "position" => $request->input("developer_workspace_position", null),
            "blocked" => (string) $request->input("developer_workspace_blocked", "0") === "1",
            "checklist" => trim((string) $request->input("developer_workspace_checklist", "")),
            "subtask" => trim((string) $request->input("developer_workspace_subtask", "")),
            "subtask_color" => trim((string) $request->input("developer_workspace_subtask_color", "blue")),
            "toggle_subtask_index" => $request->input("developer_workspace_toggle_subtask_index", null),
            "delete_subtask_index" => $request->input("developer_workspace_delete_subtask_index", null),
            "edit_subtask_index" => $request->input("developer_workspace_edit_subtask_index", null),
            "edit_subtask_text" => trim((string) $request->input("developer_workspace_edit_subtask_text", "")),
            "edit_subtask_color" => trim((string) $request->input("developer_workspace_edit_subtask_color", "blue")),
            "comment" => trim((string) $request->input("developer_workspace_comment", "")),
            "attachment_label" => trim((string) $request->input("developer_workspace_attachment_label", "")),
            "attachment_url" => trim((string) $request->input("developer_workspace_attachment_url", "")),
            "attachment_added_by" => $developerEmail,
        ];
    }

    private function ensureDeveloperCapability(DeveloperAccessManager $developerAccess, string $capability): bool
    {
        if ($developerAccess->can($capability)) {
            return true;
        }

        flash_set("status", [
            "variant" => "warning",
            "title" => "Developer permission required",
            "text" => "Your current developer role does not include the '{$capability}' capability.",
            "toast" => true,
        ]);
        regenerate_csrf_token();

        return false;
    }

    private function developerPolicy(DeveloperAccessManager $developerAccess): array
    {
        return [
            "schema" => "fnlla.developer_policy_boundary.v1",
            "boundary" => [
                "fnlla_managed" => [
                    "Developer access, sessions, capability checks and local developer profiles.",
                    "Client-preview, maintenance and developer service-control contracts.",
                    "Framework update checks, update audit, runtime validation and release readiness signals.",
                    "Privacy-light operational summaries, consent-aware integration hooks and reserved Fionn bridge policy.",
                    "The lightweight technical workspace used to coordinate framework/project setup tasks.",
                    "Optional project leadership records that name responsibility without storing customer data.",
                ],
                "project_owned" => [
                    "Business domain models, database schema, customer records and product workflows.",
                    "Application admin panels, CRM, billing, bookings, documents, orders, dashboards and reports.",
                    "Business user roles, customer permissions, product-specific auth journeys and account policies.",
                    "Brand copy, client content, uploaded business files, production secrets and private operating knowledge.",
                    "Whether a client system exposes TechAyo or named-lead information publicly, privately or not at all.",
                    "External SaaS integrations after the project explicitly connects and audits them.",
                ],
                "forbidden_in_fnlla_core" => [
                    "Customer data or private client workflows.",
                    "The private Fionn brain, learned memory, model weights, queues or eval data.",
                    "Hard-coded TechAyo central-control business logic beyond the public API contract.",
                    "Product-specific CRM, CMS, billing or industry workflows.",
                    "Tracking pixels, heatmap scripts or analytics tags enabled without explicit consent and project opt-in.",
                ],
            ],
            "capabilities" => $developerAccess->capabilityCatalog(),
            "roles" => array_map(
                fn (string $role, string $label): array => [
                    "role" => $role,
                    "label" => $label,
                    "capabilities" => $developerAccess->capabilitiesFor($role),
                ],
                array_keys($developerAccess->roleOptions()),
                array_values($developerAccess->roleOptions())
            ),
            "storage" => [
                "default" => "File-backed JSON in storage/framework/developer for starter portability.",
                "production_note" => "High-change teams should move long-lived workspace and audit data to project-owned database tables without changing the public panel contract.",
            ],
        ];
    }

    private function resolveDeveloperAvatar(Request $request, array $payload): string
    {
        $uploaded = $request->file("developer_profile_avatar_file");

        if ($uploaded instanceof UploadedFile && $uploaded->isValid()) {
            $uploaded->validate(1048576, ["image/jpeg", "image/png", "image/webp", "image/svg+xml"]);
            $storedPath = $uploaded->store("developer-avatars", "public");

            return "/uploads/" . trim($storedPath, "/");
        }

        if (($payload["developer_profile_generate_avatar"] ?? false) === true) {
            return $this->writeGeneratedDeveloperAvatar((string) ($payload["developer_profile_name"] ?? "Developer"));
        }

        return (string) ($payload["developer_profile_avatar"] ?? "");
    }

    private function writeGeneratedDeveloperAvatar(string $name): string
    {
        $mark = strtoupper(substr((string) preg_replace('/[^A-Za-z0-9]/', "", $name), 0, 2));
        $mark = $mark !== "" ? $mark : "DV";
        $filename = "avatar-" . sha1(strtolower($name) . "|" . microtime(true)) . ".svg";
        $relativePath = "uploads/developer-avatars/" . $filename;
        $absolutePath = public_path($relativePath);
        $directory = dirname($absolutePath);

        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $safeMark = htmlspecialchars($mark, ENT_QUOTES, "UTF-8");
        $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 128 128" role="img" aria-label="Developer avatar">
  <rect width="128" height="128" rx="26" fill="#eff6ff"/>
  <rect x="8" y="8" width="112" height="112" rx="22" fill="#ffffff" stroke="#2563eb" stroke-opacity=".28"/>
  <text x="64" y="74" fill="#2563eb" font-family="Arial, Helvetica, sans-serif" font-size="38" font-weight="800" text-anchor="middle">{$safeMark}</text>
</svg>
SVG;

        file_put_contents($absolutePath, $svg . PHP_EOL, LOCK_EX);

        return "/" . $relativePath;
    }

}
