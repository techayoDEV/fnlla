<?php

declare(strict_types=1);

namespace Fnlla\Php\Controllers;

use DateTimeImmutable;
use DateTimeZone;
use Fnlla\Php\Http\Request;
use Fnlla\Php\Http\Response;
use Fnlla\Php\Maintenance\DeveloperAccessManager;
use Fnlla\Php\Maintenance\MaintenanceAccessManager;
use Fnlla\Php\Support\EnvironmentFileManager;
use Fnlla\Php\Support\FrameworkReleaseChannel;
use Fnlla\Php\Support\FrameworkUpdater;
use Fnlla\Php\Support\Logger;
use Fnlla\Php\Support\ProjectLeadership;
use Fnlla\Php\Support\VersionManifest;
use Fnlla\Php\Validation\ValidationException;

final class MaintenanceController extends ProjectAccessController
{
    public function maintenanceHome(
        Request $request,
        MaintenanceAccessManager $maintenanceAccess,
        DeveloperAccessManager $developerAccess
    ): Response
    {
        $environmentFileManager = app(EnvironmentFileManager::class);
        $accessState = $maintenanceAccess->viewState();
        $setupState = $this->maintenanceSetupState($request, $environmentFileManager, $maintenanceAccess, $developerAccess);
        $developerSetupState = $this->developerAccessSetupState($request, $environmentFileManager, $developerAccess);
        $developerAccessState = $developerAccess->viewState();
        $clientPreviewState = $this->clientPreviewState();

        if ($request->path() === "/maintenance" && $this->freshDeveloperOnboardingAvailable($request, $maintenanceAccess, $developerAccess)) {
            return $this->redirect(route("home"));
        }

        if ($accessState["enabled"] && !$accessState["unlocked"]) {
            $useClientPreview = $clientPreviewState["active"] && $accessState["configured"];

            return $this->view($useClientPreview ? "maintenance/client-preview" : "maintenance/index", [
                "pageTitle" => $useClientPreview ? "Private Client Preview" : "Maintenance Access",
                "pageTitleSection" => $useClientPreview ? "" : "Operations",
                "maintenanceAccess" => $accessState,
                "developerAccess" => $developerAccessState,
                "maintenanceSetup" => $setupState,
                "developerSetup" => $developerSetupState,
                "maintenanceLocked" => true,
                "maintenanceRedirectTarget" => $this->sanitizeMaintenanceRedirectTarget((string) $request->query("redirect", "")),
                "maintenanceHighlights" => [],
                "clientPreview" => $clientPreviewState,
                "layoutChromeMode" => $useClientPreview ? "client-preview" : "default",
            ]);
        }

        if (!$developerAccess->canAccessOperations()) {
            return $this->notFoundResponse();
        }

        return $this->view("maintenance/index", [
            "pageTitle" => "Maintenance Center",
            "pageTitleSection" => "Operations",
            "maintenanceAccess" => $accessState,
            "developerAccess" => $developerAccessState,
            "maintenanceSetup" => $setupState,
            "developerSetup" => $developerSetupState,
            "maintenanceLocked" => false,
        ]);
    }


    public function setupMaintenanceAccess(
        Request $request,
        MaintenanceAccessManager $maintenanceAccess,
        DeveloperAccessManager $developerAccess,
        EnvironmentFileManager $environmentFileManager
    ): Response
    {
        $redirectTarget = $this->sanitizeMaintenanceRedirectTarget((string) $request->input("maintenance_redirect", $request->query("redirect", "")));
        $setupState = $this->maintenanceSetupState($request, $environmentFileManager, $maintenanceAccess, $developerAccess);

        if ($setupState["can_setup"] !== true) {
            flash_set("status", [
                "variant" => "warning",
                "title" => "Maintenance setup is unavailable here",
                "text" => (string) $setupState["message"],
                "toast" => false,
            ]);
            regenerate_csrf_token();

            return $this->redirect($this->maintenanceRedirectUrl($redirectTarget, "#maintenance-setup"));
        }

        $payload = [
            "maintenance_setup_password" => trim((string) $request->input("maintenance_setup_password", "")),
            "maintenance_setup_password_confirmation" => trim((string) $request->input("maintenance_setup_password_confirmation", "")),
            "developer_setup_email" => strtolower(trim((string) $request->input("developer_setup_email", ""))),
            "developer_setup_password" => trim((string) $request->input("developer_setup_password", "")),
            "developer_setup_password_confirmation" => trim((string) $request->input("developer_setup_password_confirmation", "")),
        ];

        try {
            $rules = [
                "maintenance_setup_password" => ["required", "string", "min:8", "max:255", "confirmed"],
                "developer_setup_password" => ["nullable", "string", "min:8", "max:255", "confirmed"],
            ];

            if (!$developerAccess->configured()) {
                $rules["developer_setup_email"] = ["required", "email", "max:160"];
            }

            $this->validate($payload, $rules);
        } catch (ValidationException $exception) {
            flash_set("errors", $exception->errors());
            flash_set("status", [
                "variant" => "warning",
                "title" => "Maintenance setup still needs attention",
                "text" => "Review the highlighted fields and save the maintenance credentials again.",
                "toast" => false,
            ]);
            regenerate_csrf_token();

            return $this->redirect($this->maintenanceRedirectUrl($redirectTarget, "#maintenance-setup"));
        }

        $environmentValues = [
            "MAINTENANCE_MODE_ENABLED" => "true",
            "MAINTENANCE_ACCESS_USERNAME" => "",
            "MAINTENANCE_ACCESS_PASSWORD" => $payload["maintenance_setup_password"],
        ];
        $developerAccessCreated = false;

        if (!$developerAccess->configured()) {
            $developerPassword = $payload["developer_setup_password"] !== ""
                ? $payload["developer_setup_password"]
                : $payload["maintenance_setup_password"];
            $developerPasswordHash = password_hash($developerPassword, PASSWORD_DEFAULT);
            $developerAccount = [
                "email" => $payload["developer_setup_email"],
                "name" => "Developer",
                "role" => "lead_developer",
                "password_hash" => $developerPasswordHash,
            ];
            $environmentValues["DEVELOPER_ACCESS_ENABLED"] = "true";
            $environmentValues["DEVELOPER_ACCESS_PATH"] = $developerAccess->path();
            $environmentValues["DEVELOPER_ACCESS_EMAIL"] = $payload["developer_setup_email"];
            $environmentValues["DEVELOPER_ACCESS_USERS"] = $developerAccess->serializeAccounts([$developerAccount]);
            $environmentValues["DEVELOPER_OPERATIONS_NAV_MODE"] = "hidden";
            $developerAccessCreated = true;
        }

        try {
            $environmentFileManager->write($environmentValues);
            $environmentFileManager->apply($environmentValues);
            $environmentFileManager->remove(["DEVELOPER_ACCESS_PASSWORD", "DEVELOPER_ACCESS_PASSWORD_HASH"]);
        } catch (\RuntimeException $exception) {
            flash_set("status", [
                "variant" => "danger",
                "title" => "Maintenance credentials could not be saved",
                "text" => $exception->getMessage(),
                "toast" => false,
            ]);
            regenerate_csrf_token();

            return $this->redirect($this->maintenanceRedirectUrl($redirectTarget, "#maintenance-setup"));
        }

        $currentConfig = (array) config("maintenance", []);
        config_set("maintenance", array_merge($currentConfig, [
            "enabled" => true,
            "username" => $environmentValues["MAINTENANCE_ACCESS_USERNAME"],
            "password" => $environmentValues["MAINTENANCE_ACCESS_PASSWORD"],
        ]));

        if ($developerAccessCreated) {
            config_set("developer_access", array_merge((array) config("developer_access", []), [
                "enabled" => true,
                "email" => (string) $environmentValues["DEVELOPER_ACCESS_EMAIL"],
                "password" => "",
                "password_hash" => "",
                "path" => (string) $environmentValues["DEVELOPER_ACCESS_PATH"],
                "users" => (string) $environmentValues["DEVELOPER_ACCESS_USERS"],
                "operations_nav_mode" => "hidden",
            ]));
            Logger::write("notice", "Developer access created during maintenance setup", [
                "event" => "developer_access_created",
            ]);
        }

        $maintenanceAccess->unlock(
            $request,
            $payload["maintenance_setup_password"]
        );

        if ($developerAccessCreated) {
            $developerAccess->grantAccess($developerAccount);
            $maintenanceAccess->lock();
            flash_set("developer_access_notice", [
                "title" => "Named developer account created",
                "text" => "The developer session is ready at " . $developerAccess->path() . " and uses email plus password sign-in.",
            ]);
        }

        flash_set("status", [
            "variant" => "success",
            "title" => "Maintenance access configured",
            "text" => $developerAccessCreated
                ? "The project setup flow saved the maintenance credentials, enabled the named developer session and kept this browser session unlocked for follow-up work."
                : "The project setup flow saved the maintenance credentials to .env, enabled preview protection and kept this browser session unlocked for setup work.",
            "toast" => true,
        ]);
        regenerate_csrf_token();

        if ($developerAccessCreated) {
            return $this->redirect(route("developer.panel"));
        }

        return $this->redirect($redirectTarget !== "" ? $redirectTarget : route("maintenance.home"));
    }


    public function unlockMaintenance(Request $request, MaintenanceAccessManager $maintenanceAccess): Response
    {
        $redirectTarget = $this->sanitizeMaintenanceRedirectTarget((string) $request->input("maintenance_redirect", $request->query("redirect", "")));
        $username = trim((string) $request->input("maintenance_username", ""));
        $password = (string) $request->input("maintenance_password", "");
        $result = $maintenanceAccess->unlock($request, $password, $username);

        if (!$result["success"]) {
            flash_set("old", [
                "maintenance_username" => $username,
            ]);
            flash_set("status", [
                "variant" => "warning",
                "title" => "Maintenance access denied",
                "text" => (string) $result["error"],
                "toast" => false,
            ]);
            regenerate_csrf_token();

            return $this->redirect($this->maintenanceRedirectUrl($redirectTarget, $this->maintenanceLockedAccessFragment($maintenanceAccess)));
        }

        flash_set("status", [
            "variant" => "success",
            "title" => "Maintenance unlocked",
            "text" => "The application is unlocked for this session for the next " . (string) max(1, (int) ($maintenanceAccess->viewState()["unlock_ttl_minutes"] ?? 10)) . " minutes.",
            "toast" => true,
        ]);
        regenerate_csrf_token();

        return $this->redirect($redirectTarget !== "" ? $redirectTarget : route("maintenance.home"));
    }


    public function lockMaintenance(Request $request, MaintenanceAccessManager $maintenanceAccess): Response
    {
        $maintenanceAccess->lock();
        flash_set("status", [
            "variant" => "info",
            "title" => "Maintenance lock restored",
            "text" => "Public routes are protected again until the maintenance password is entered.",
            "toast" => false,
        ]);
        regenerate_csrf_token();

        return $this->redirect(route("maintenance.home") . $this->maintenanceLockedAccessFragment($maintenanceAccess));
    }




}
