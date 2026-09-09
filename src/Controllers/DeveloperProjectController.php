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
use Fnlla\Php\Support\FrameworkIdentity;
use Fnlla\Php\Support\Logger;
use Fnlla\Php\Support\ProjectLeadership;
use Fnlla\Php\Validation\ValidationException;

final class DeveloperProjectController extends DeveloperPanelController
{
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
        return $this->redirect(route("developer.panel.project_identity") . "#developer-access-preview");
    }

    public function updateServiceControl(
        Request $request,
        DeveloperAccessManager $developerAccess,
        DeveloperControlManager $developerControl,
        DeveloperActivityLog $activityLog
    ): Response {
        if (!$this->ensureDeveloperCapability($developerAccess, "service_control.write")) {
            return $this->redirect(route("developer.panel.project_identity") . "#developer-access-preview");
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

            return $this->redirect(route("developer.panel.project_identity") . "#developer-access-preview");
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

        return $this->redirect(route("developer.panel.project_identity") . "#developer-access-preview");
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

    public function updateRuntimeEnvironment(
        Request $request,
        DeveloperAccessManager $developerAccess,
        EnvironmentFileManager $environmentFileManager
    ): Response {
        if (!$this->ensureDeveloperCapability($developerAccess, "project.identity.write")) {
            return $this->redirect(route("developer.panel.project_identity") . "#runtime-environment");
        }

        $environment = strtolower(trim((string) $request->input("runtime_environment", "development")));
        $trustedHostsInput = trim((string) $request->input("runtime_trusted_hosts", ""));
        $trustedHosts = $this->normalizeRuntimeTrustedHosts($trustedHostsInput);

        if (!in_array($environment, ["development", "production"], true) || strlen($trustedHostsInput) > 512 || ($trustedHostsInput !== "" && $trustedHosts === [])) {
            flash_set("old", [
                "runtime_environment" => $environment,
                "runtime_trusted_hosts" => $trustedHostsInput,
                "runtime_debug_enabled" => (string) $request->input("runtime_debug_enabled", "0"),
                "runtime_debug_toolbar_enabled" => (string) $request->input("runtime_debug_toolbar_enabled", "0"),
                "runtime_request_history_enabled" => (string) $request->input("runtime_request_history_enabled", "0"),
            ]);
            flash_set("status", [
                "variant" => "warning",
                "title" => "Runtime environment still needs attention",
                "text" => "Choose development or production and use comma-separated trusted host names.",
                "toast" => false,
            ]);
            regenerate_csrf_token();

            return $this->redirect(route("developer.panel.project_identity") . "#runtime-environment");
        }

        $production = $environment === "production";
        $debugEnabled = !$production && (string) $request->input("runtime_debug_enabled", "0") === "1";
        $debugToolbarEnabled = !$production && (string) $request->input("runtime_debug_toolbar_enabled", "0") === "1";
        $requestHistoryEnabled = !$production && (string) $request->input("runtime_request_history_enabled", "0") === "1";
        $environmentValues = [
            "APP_ENV" => $environment,
            "APP_DEBUG" => $debugEnabled,
            "DEBUG_TOOLBAR" => $debugToolbarEnabled,
            "DEBUG_REQUEST_HISTORY" => $requestHistoryEnabled,
            "TRUSTED_HOSTS" => implode(",", $trustedHosts),
        ];

        try {
            $environmentFileManager->write($environmentValues);
            $environmentFileManager->apply($environmentValues);
        } catch (\RuntimeException $exception) {
            flash_set("status", [
                "variant" => "danger",
                "title" => "Runtime environment could not be saved",
                "text" => $exception->getMessage(),
                "toast" => false,
            ]);
            regenerate_csrf_token();

            return $this->redirect(route("developer.panel.project_identity") . "#runtime-environment");
        }

        config_set("app.environment", $environment);
        config_set("app.debug", $debugEnabled);
        config_set("debug.toolbar", $debugToolbarEnabled);
        config_set("debug.history.enabled", $requestHistoryEnabled);
        config_set("security.trusted_hosts", $trustedHosts);
        $developerAccess->grantAccess();
        developer_activity()->record(
            "runtime_environment",
            $production ? "Runtime switched to production" : "Runtime switched to development",
            $production
                ? "APP_ENV is production, diagnostic switches are off and trusted hosts were saved."
                : "APP_ENV is development and diagnostic switches were saved from the Developer Panel.",
            $developerAccess->currentDeveloper()
        );

        flash_set("status", [
            "variant" => "success",
            "title" => $production ? "Production mode saved" : "Development mode saved",
            "text" => $production
                ? "The environment file now uses production with APP_DEBUG, debug toolbar and request history off."
                : "The environment file now uses development with the selected diagnostic switches.",
            "toast" => true,
        ]);
        regenerate_csrf_token();

        return $this->redirect(route("developer.panel.project_identity") . "#runtime-environment");
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

        if (!$leadership->canConfirm($state, $developer, $developerAccess->capabilitiesFor($developer)) || !in_array($action, ["confirm", "reject"], true)) {
            flash_set("status", [
                "variant" => "warning",
                "title" => "Leadership confirmation was not applied",
                "text" => "Only the named person or a lead developer can confirm or reject this responsibility.",
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
            return $this->redirect(route("developer.panel.project_identity") . "#developer-access-preview");
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

            return $this->redirect(route("developer.panel.project_identity") . "#developer-access-preview");
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

            return $this->redirect(route("developer.panel.project_identity") . "#developer-access-preview");
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

        return $this->redirect(route("developer.panel.project_identity") . "#developer-access-preview");
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
}
