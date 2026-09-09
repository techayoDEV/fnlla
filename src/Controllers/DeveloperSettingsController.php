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
use Fnlla\Php\Support\DeveloperNotificationCenter;
use Fnlla\Php\Support\DeveloperOperationsReport;
use Fnlla\Php\Support\DeveloperWorkspaceBoard;
use Fnlla\Php\Support\EnvironmentFileManager;
use Fnlla\Php\Support\DeveloperModules;
use Fnlla\Php\Support\FrameworkIdentity;
use Fnlla\Php\Support\Logger;
use Fnlla\Php\Support\ProjectLeadership;
use Fnlla\Php\Validation\ValidationException;

final class DeveloperSettingsController extends DeveloperPanelController
{
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
            "FNLLA_INTEGRATION_API_HOOKS_ENABLED" => (string) $request->input("fnlla_integration_api_hooks_enabled", "0") === "1",
            "FNLLA_INTEGRATION_API_HOOKS_ENDPOINT" => trim((string) $request->input("fnlla_integration_api_hooks_endpoint", "")),
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
            "FNLLA_INTEGRATION_API_HOOKS_ENDPOINT",
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
            $environmentFileManager->remove([
                "FNLLA_INTEGRATION_GA4_ENABLED",
                "FNLLA_INTEGRATION_GA4_MEASUREMENT_ID",
                "FNLLA_INTEGRATION_CLARITY_ENABLED",
                "FNLLA_INTEGRATION_CLARITY_PROJECT_ID",
                "FNLLA_INTEGRATION_SENTRY_ENABLED",
                "FNLLA_INTEGRATION_SENTRY_DSN",
                "FNLLA_INTEGRATION_SENTRY_ENVIRONMENT",
                "FNLLA_INTEGRATION_HEATMAPS_ENABLED",
                "FNLLA_INTEGRATION_HEATMAPS_PROVIDER",
            ]);
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

        config_set("integrations.api_hooks.enabled", $values["FNLLA_INTEGRATION_API_HOOKS_ENABLED"]);
        config_set("integrations.api_hooks.endpoint", $values["FNLLA_INTEGRATION_API_HOOKS_ENDPOINT"]);
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

    public function updateNavigationMode(
        Request $request,
        DeveloperAccessManager $developerAccess,
        EnvironmentFileManager $environmentFileManager
    ): Response {
        if (!$this->ensureDeveloperCapability($developerAccess, "panel.settings.write")) {
            return $this->redirect(route("developer.panel.settings"));
        }

        $payload = [
            "developer_access_path" => $developerAccess->normalisePath((string) $request->input("developer_access_path", $developerAccess->path())),
            "developer_operations_nav_mode" => trim((string) $request->input("developer_operations_nav_mode", "hidden")),
            "developer_access_ttl_minutes" => (int) $request->input("developer_access_ttl_minutes", (int) config("developer_access.unlock_ttl_minutes", 120)),
            "developer_access_absolute_ttl_minutes" => (int) $request->input("developer_access_absolute_ttl_minutes", (int) config("developer_access.absolute_ttl_minutes", 480)),
        ];

        if (!in_array($payload["developer_operations_nav_mode"], ["hidden", "developer_session_only"], true)) {
            $payload["developer_operations_nav_mode"] = "hidden";
        }

        if (!$developerAccess->pathAllowed($payload["developer_access_path"])) {
            flash_set("status", [
                "variant" => "warning",
                "title" => "Developer entry URL still needs attention",
                "text" => "Use one or two lowercase URL segments with letters, numbers and hyphens, and avoid public route names such as maintenance, api or docs.",
                "toast" => false,
            ]);
            regenerate_csrf_token();

            return $this->redirect(route("developer.panel.settings"));
        }

        try {
            $this->validate($payload, [
                "developer_access_path" => ["required", "string", "min:4", "max:100"],
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

        $values = [
                "DEVELOPER_ACCESS_PATH" => $payload["developer_access_path"],
                "DEVELOPER_OPERATIONS_NAV_MODE" => $payload["developer_operations_nav_mode"],
                "DEVELOPER_ACCESS_TTL_MINUTES" => (string) $payload["developer_access_ttl_minutes"],
                "DEVELOPER_ACCESS_ABSOLUTE_TTL_MINUTES" => (string) $payload["developer_access_absolute_ttl_minutes"],
        ];
        if ($request->input("fnlla_modules_present") === "1") {
            $values += DeveloperModules::environmentValues($request->all());
        }
        try {
            $environmentFileManager->write($values);
            $environmentFileManager->apply($values);
            DeveloperModules::applyEnvironmentValues($values);
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
            "path" => $payload["developer_access_path"],
            "operations_nav_mode" => $payload["developer_operations_nav_mode"],
            "unlock_ttl_minutes" => $payload["developer_access_ttl_minutes"],
            "absolute_ttl_minutes" => $payload["developer_access_absolute_ttl_minutes"],
        ]));
        $developerAccess->grantAccess();
        developer_activity()->record(
            "panel_settings",
            "Developer panel settings updated",
            "Developer entry path, session windows or navigation visibility were changed.",
            $developerAccess->currentDeveloper()
        );

        flash_set("status", [
            "variant" => "success",
            "title" => "Developer panel settings saved",
            "text" => "The developer entry URL, session window and private navigation preference were saved for this project.",
            "toast" => true,
        ]);
        regenerate_csrf_token();

        return $this->redirect($payload["developer_access_path"] . "/panel/settings");
    }
}
