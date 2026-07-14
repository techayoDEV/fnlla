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
use Fnlla\Php\Maintenance\DeveloperAccessManager;
use Fnlla\Php\Maintenance\MaintenanceAccessManager;
use Fnlla\Php\Support\EnvironmentFileManager;
use Fnlla\Php\Validation\ValidationException;

final class DeveloperAccessController extends Controller
{
    public function entry(Request $request, DeveloperAccessManager $developerAccess, MaintenanceAccessManager $maintenanceAccess): Response
    {
        if (!$developerAccess->configured()) {
            return $this->view("pages/not-found", [
                "pageTitle" => "Not Found",
            ], 404);
        }

        if ($developerAccess->isUnlocked()) {
            return $this->redirect(route("developer.panel"));
        }

        return $this->view("developer/entry", [
            "pageTitle" => "Developer Login",
            "pageTitleSection" => "Operations",
            "developerAccess" => $developerAccess->viewState(),
            "maintenanceAccess" => $maintenanceAccess->viewState(),
            "developerLinks" => [
                "home" => route("home"),
            ],
            "developerNotice" => flash("developer_access_notice"),
        ]);
    }

    public function show(Request $request, DeveloperAccessManager $developerAccess, MaintenanceAccessManager $maintenanceAccess): Response
    {
        if (!$developerAccess->configured()) {
            return $this->view("pages/not-found", [
                "pageTitle" => "Not Found",
            ], 404);
        }

        if (!$developerAccess->isUnlocked()) {
            return $this->redirect(route("developer.entry"));
        }

        $maintenanceAccess->lock();

        return $this->view("developer/panel", [
            "pageTitle" => "Developer Panel",
            "pageTitleSection" => "Operations",
            "developerAccess" => $developerAccess->viewState(),
            "maintenanceAccess" => $maintenanceAccess->viewState(),
            "developerLinks" => [
                "home" => route("home"),
            ],
            "developerNotice" => flash("developer_access_notice"),
        ]);
    }

    public function unlock(Request $request, DeveloperAccessManager $developerAccess, MaintenanceAccessManager $maintenanceAccess): Response
    {
        if (!$developerAccess->configured()) {
            return $this->view("pages/not-found", [
                "pageTitle" => "Not Found",
            ], 404);
        }

        $password = trim((string) $request->input("developer_access_password", ""));

        try {
            $this->validate([
                "developer_access_password" => $password,
            ], [
                "developer_access_password" => ["required", "string", "min:8", "max:255"],
            ]);
        } catch (ValidationException $exception) {
            flash_set("errors", $exception->errors());
            flash_set("status", [
                "variant" => "warning",
                "title" => "Developer access still needs attention",
                "text" => "Enter the developer password to unlock this hidden panel.",
                "toast" => false,
            ]);
            regenerate_csrf_token();

            return $this->redirect(route("developer.entry"));
        }

        $result = $developerAccess->unlock($request, $password);

        if (!$result["success"]) {
            flash_set("status", [
                "variant" => "warning",
                "title" => "Developer access denied",
                "text" => (string) $result["error"],
                "toast" => false,
            ]);
            regenerate_csrf_token();

            return $this->redirect(route("developer.entry"));
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

    public function lock(Request $request, DeveloperAccessManager $developerAccess): Response
    {
        $developerAccess->lock();
        flash_set("status", [
            "variant" => "info",
            "title" => "Developer session closed",
            "text" => "The hidden developer panel now requires its password again.",
            "toast" => false,
        ]);
        regenerate_csrf_token();

        return $this->redirect(route("developer.entry"));
    }

    public function updateMaintenanceCredentials(
        Request $request,
        DeveloperAccessManager $developerAccess,
        MaintenanceAccessManager $maintenanceAccess,
        EnvironmentFileManager $environmentFileManager
    ): Response {
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

            return $this->redirect(route("developer.panel") . "#developer-maintenance-settings");
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

            return $this->redirect(route("developer.panel") . "#developer-maintenance-settings");
        }

        config_set("maintenance", array_merge((array) config("maintenance", []), [
            "enabled" => $maintenanceEnabled,
            "username" => $environmentValues["MAINTENANCE_ACCESS_USERNAME"],
            "password" => $environmentValues["MAINTENANCE_ACCESS_PASSWORD"],
        ]));
        $maintenanceAccess->lock();
        $developerAccess->grantAccess();

        flash_set("status", [
            "variant" => "success",
            "title" => $maintenanceEnabled ? "Maintenance enabled" : "Maintenance credentials saved",
            "text" => $maintenanceEnabled
                ? "The maintenance password was saved and public routes are now protected by maintenance mode. The developer session stays active."
                : "The maintenance password was saved, but maintenance mode stays off until you decide to enable it.",
            "toast" => true,
        ]);
        regenerate_csrf_token();

        return $this->redirect(route("developer.panel") . "#developer-maintenance-settings");
    }

    public function updateDeveloperPassword(
        Request $request,
        DeveloperAccessManager $developerAccess,
        EnvironmentFileManager $environmentFileManager
    ): Response {
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

            return $this->redirect(route("developer.panel") . "#developer-access-settings");
        }

        try {
            $environmentFileManager->write([
                "DEVELOPER_ACCESS_PASSWORD" => $payload["developer_access_password"],
            ]);
            $environmentFileManager->apply([
                "DEVELOPER_ACCESS_PASSWORD" => $payload["developer_access_password"],
            ]);
        } catch (\RuntimeException $exception) {
            flash_set("status", [
                "variant" => "danger",
                "title" => "Developer password could not be saved",
                "text" => $exception->getMessage(),
                "toast" => false,
            ]);
            regenerate_csrf_token();

            return $this->redirect(route("developer.panel") . "#developer-access-settings");
        }

        config_set("developer_access", array_merge((array) config("developer_access", []), [
            "password" => $payload["developer_access_password"],
        ]));
        $developerAccess->grantAccess();

        flash_set("status", [
            "variant" => "success",
            "title" => "Developer password updated",
            "text" => "The hidden panel password was saved and this browser session stayed unlocked.",
            "toast" => true,
        ]);
        regenerate_csrf_token();

        return $this->redirect(route("developer.panel") . "#developer-access-settings");
    }

    public function updateNavigationMode(
        Request $request,
        DeveloperAccessManager $developerAccess,
        EnvironmentFileManager $environmentFileManager
    ): Response {
        try {
            $environmentFileManager->write([
                "DEVELOPER_OPERATIONS_NAV_MODE" => "hidden",
            ]);
            $environmentFileManager->apply([
                "DEVELOPER_OPERATIONS_NAV_MODE" => "hidden",
            ]);
        } catch (\RuntimeException $exception) {
            flash_set("status", [
                "variant" => "danger",
                "title" => "Developer navigation preference could not be saved",
                "text" => $exception->getMessage(),
                "toast" => false,
            ]);
            regenerate_csrf_token();

            return $this->redirect(route("developer.panel"));
        }

        config_set("developer_access", array_merge((array) config("developer_access", []), [
            "operations_nav_mode" => "hidden",
        ]));
        $developerAccess->grantAccess();

        flash_set("status", [
            "variant" => "success",
            "title" => "Developer navigation kept private",
            "text" => "The public header stays plain. DEV OPERATIONS appears only for an unlocked developer session and links straight back to the developer panel.",
            "toast" => true,
        ]);
        regenerate_csrf_token();

        return $this->redirect(route("developer.panel"));
    }

    public function rotatePath(
        Request $request,
        DeveloperAccessManager $developerAccess,
        EnvironmentFileManager $environmentFileManager
    ): Response {
        $nextPath = $developerAccess->generatePanelPath();

        try {
            $environmentFileManager->write([
                "DEVELOPER_ACCESS_PATH" => $nextPath,
            ]);
            $environmentFileManager->apply([
                "DEVELOPER_ACCESS_PATH" => $nextPath,
            ]);
        } catch (\RuntimeException $exception) {
            flash_set("status", [
                "variant" => "danger",
                "title" => "Developer path could not be rotated",
                "text" => $exception->getMessage(),
                "toast" => false,
            ]);
            regenerate_csrf_token();

            return $this->redirect(route("developer.panel") . "#developer-access-settings");
        }

        config_set("developer_access", array_merge((array) config("developer_access", []), [
            "path" => $nextPath,
        ]));
        $developerAccess->grantAccess();
        flash_set("status", [
            "variant" => "success",
            "title" => "Developer path rotated",
            "text" => "Use the new private address from now on. The previous hidden path is no longer valid.",
            "toast" => true,
        ]);
        flash_set("developer_access_notice", [
            "path" => $nextPath,
            "title" => "New hidden developer path",
            "text" => "Save this private address. It will continue to open the developer panel for this project.",
        ]);
        regenerate_csrf_token();

        return $this->redirect($nextPath . "/panel#developer-access-settings");
    }
}
