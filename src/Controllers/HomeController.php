<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA CONTROLLER SOURCE
File: src\Controllers\HomeController.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Provides HTTP-facing controller behavior for maintained framework flows and demos.
*/

namespace Fnlla\Php\Controllers;

use Fnlla\Php\Http\Request;
use Fnlla\Php\Http\Response;
use Fnlla\Php\Maintenance\DeveloperAccessManager;
use Fnlla\Php\Maintenance\MaintenanceAccessManager;
use Fnlla\Php\Support\EnvironmentFileManager;
use Fnlla\Php\Support\FrameworkReleaseChannel;
use Fnlla\Php\Support\FrameworkUpdater;
use Fnlla\Php\Support\VersionManifest;
use Fnlla\Php\Validation\ValidationException;

final class HomeController extends Controller
{
    public function maintenanceHome(
        Request $request,
        MaintenanceAccessManager $maintenanceAccess,
        DeveloperAccessManager $developerAccess
    ): Response
    {
        $accessState = $maintenanceAccess->viewState();
        $setupState = $this->maintenanceSetupState($request, app(EnvironmentFileManager::class), $maintenanceAccess, $developerAccess);
        $developerSetupState = $this->developerAccessSetupState($request, app(EnvironmentFileManager::class), $developerAccess);
        $developerAccessState = $developerAccess->viewState();

        if ($accessState["enabled"] && !$accessState["unlocked"]) {
            return $this->view("maintenance/index", [
                "pageTitle" => "Maintenance Access",
                "pageTitleSection" => "Operations",
                "maintenanceAccess" => $accessState,
                "developerAccess" => $developerAccessState,
                "maintenanceSetup" => $setupState,
                "developerSetup" => $developerSetupState,
                "maintenanceLocked" => true,
                "maintenanceRedirectTarget" => $this->sanitizeMaintenanceRedirectTarget((string) $request->query("redirect", "")),
                "maintenanceHighlights" => [],
            ]);
        }

        if (!$developerAccess->canAccessOperations()) {
            return $this->notFoundResponse();
        }

        return $this->view("maintenance/index", [
            "pageTitle" => "Maintenance",
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
            "developer_setup_password" => trim((string) $request->input("developer_setup_password", "")),
            "developer_setup_password_confirmation" => trim((string) $request->input("developer_setup_password_confirmation", "")),
        ];

        try {
            $this->validate($payload, [
                "maintenance_setup_password" => ["required", "string", "min:8", "max:255", "confirmed"],
                "developer_setup_password" => ["nullable", "string", "min:8", "max:255", "confirmed"],
            ]);
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
        $generatedDeveloperPath = "";

        if (!$developerAccess->configured()) {
            $generatedDeveloperPath = $developerAccess->generatePanelPath();
            $developerPassword = $payload["developer_setup_password"] !== ""
                ? $payload["developer_setup_password"]
                : $payload["maintenance_setup_password"];
            $environmentValues["DEVELOPER_ACCESS_ENABLED"] = "true";
            $environmentValues["DEVELOPER_ACCESS_PATH"] = $generatedDeveloperPath;
            $environmentValues["DEVELOPER_ACCESS_PASSWORD"] = $developerPassword;
            $environmentValues["DEVELOPER_OPERATIONS_NAV_MODE"] = "hidden";
        }

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

            return $this->redirect($this->maintenanceRedirectUrl($redirectTarget, "#maintenance-setup"));
        }

        $currentConfig = (array) config("maintenance", []);
        config_set("maintenance", array_merge($currentConfig, [
            "enabled" => true,
            "username" => $environmentValues["MAINTENANCE_ACCESS_USERNAME"],
            "password" => $environmentValues["MAINTENANCE_ACCESS_PASSWORD"],
        ]));

        if ($generatedDeveloperPath !== "") {
            config_set("developer_access", array_merge((array) config("developer_access", []), [
                "enabled" => true,
                "path" => $generatedDeveloperPath,
                "password" => $environmentValues["DEVELOPER_ACCESS_PASSWORD"],
                "operations_nav_mode" => "hidden",
            ]));
        }

        $maintenanceAccess->unlock(
            $request,
            $payload["maintenance_setup_password"]
        );

        if ($generatedDeveloperPath !== "") {
            $developerAccess->grantAccess();
            $maintenanceAccess->lock();
            flash_set("developer_access_notice", [
                "path" => $generatedDeveloperPath,
                "title" => "Private developer panel created",
            "text" => "Save this hidden address. It is the private developer entry point that keeps the public starter header clean for the client.",
        ]);
        }

        flash_set("status", [
            "variant" => "success",
            "title" => "Maintenance access configured",
            "text" => $generatedDeveloperPath !== ""
                ? "The starter saved the maintenance credentials, generated a private developer panel path and kept this browser session unlocked for follow-up work."
                : "The starter saved the maintenance credentials to .env, enabled preview protection and kept this browser session unlocked for setup work.",
            "toast" => true,
        ]);
        regenerate_csrf_token();

        if ($generatedDeveloperPath !== "") {
            return $this->redirect($generatedDeveloperPath . "/panel");
        }

        return $this->redirect($redirectTarget !== "" ? $redirectTarget : route("maintenance.home"));
    }

    public function setupDeveloperAccess(
        Request $request,
        DeveloperAccessManager $developerAccess,
        MaintenanceAccessManager $maintenanceAccess,
        EnvironmentFileManager $environmentFileManager
    ): Response {
        $setupState = $this->developerAccessSetupState($request, $environmentFileManager, $developerAccess);

        if ($setupState["can_setup"] !== true || $developerAccess->configured()) {
            flash_set("status", [
                "variant" => "warning",
                "title" => "Developer panel setup is unavailable here",
                "text" => $developerAccess->configured()
                    ? "The hidden developer panel is already configured for this project."
                    : (string) $setupState["message"],
                "toast" => false,
            ]);
            regenerate_csrf_token();

            return $this->redirect(route("maintenance.home") . "#developer-panel-setup");
        }

        $payload = [
            "developer_setup_password" => trim((string) $request->input("developer_setup_password", "")),
            "developer_setup_password_confirmation" => trim((string) $request->input("developer_setup_password_confirmation", "")),
        ];

        try {
            $this->validate($payload, [
                "developer_setup_password" => ["required", "string", "min:8", "max:255", "confirmed"],
            ]);
        } catch (ValidationException $exception) {
            flash_set("errors", $exception->errors());
            flash_set("status", [
                "variant" => "warning",
                "title" => "Developer panel setup still needs attention",
                "text" => "Review the hidden panel password fields before activating the developer panel.",
                "toast" => false,
            ]);
            regenerate_csrf_token();

            return $this->redirect(route("maintenance.home") . "#developer-panel-setup");
        }

        $generatedDeveloperPath = $developerAccess->generatePanelPath();
        $environmentValues = [
            "DEVELOPER_ACCESS_ENABLED" => "true",
            "DEVELOPER_ACCESS_PATH" => $generatedDeveloperPath,
            "DEVELOPER_ACCESS_PASSWORD" => $payload["developer_setup_password"],
            "DEVELOPER_OPERATIONS_NAV_MODE" => "hidden",
        ];

        try {
            $environmentFileManager->write($environmentValues);
            $environmentFileManager->apply($environmentValues);
        } catch (\RuntimeException $exception) {
            flash_set("status", [
                "variant" => "danger",
                "title" => "Developer panel could not be activated",
                "text" => $exception->getMessage(),
                "toast" => false,
            ]);
            regenerate_csrf_token();

            return $this->redirect(route("maintenance.home") . "#developer-panel-setup");
        }

        config_set("developer_access", array_merge((array) config("developer_access", []), [
            "enabled" => true,
            "path" => $generatedDeveloperPath,
            "password" => $payload["developer_setup_password"],
            "operations_nav_mode" => "hidden",
        ]));
        $developerAccess->grantAccess();
        $maintenanceAccess->lock();
        flash_set("developer_access_notice", [
            "path" => $generatedDeveloperPath,
            "title" => "Private developer panel created",
            "text" => "Save this hidden address. It is now the private service entry point for this existing project.",
        ]);
        flash_set("status", [
            "variant" => "success",
            "title" => "Developer panel activated",
            "text" => "The hidden developer panel was added to this project and the current browser session can use it immediately.",
            "toast" => true,
        ]);
        regenerate_csrf_token();

        return $this->redirect($generatedDeveloperPath . "/panel");
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

            return $this->redirect($this->maintenanceRedirectUrl($redirectTarget, "#maintenance-access"));
        }

        flash_set("status", [
            "variant" => "success",
            "title" => "Maintenance unlocked",
            "text" => "The application is unlocked for this session for the next " . (string) max(1, (int) config("maintenance.unlock_ttl_minutes", 10)) . " minutes.",
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

        return $this->redirect(route("maintenance.home") . "#maintenance-access");
    }

    public function redirectHealthToMaintenance(Request $request, DeveloperAccessManager $developerAccess): Response
    {
        if (!$developerAccess->canAccessOperations()) {
            return $this->notFoundResponse();
        }

        return $this->redirect(route("health"));
    }

    public function healthPage(Request $request): Response
    {
        $health = $this->buildHealthPayload($request);

        return $this->view("pages/health", [
            "pageTitle" => "Health",
            "pageTitleSection" => "Operations",
            "health" => $health,
        ]);
    }

    public function healthApi(Request $request): Response
    {
        $health = $this->buildHealthPayload($request);

        if ($this->healthApiWantsJson($request)) {
            return Response::json($health);
        }

        return $this->view("pages/api-health", [
            "pageTitle" => "API Health",
            "pageTitleSection" => "Operations",
            "health" => $health,
        ]);
    }

    private function buildHealthPayload(Request $request): array
    {
        $sourceDetection = FrameworkUpdater::detectSourceRoot(base_path(), (string) config("framework_update.source_path", ""));
        $versionStatus = VersionManifest::status();
        $frameworkVersion = $this->readVersionLine(base_path("VERSION"));
        $uiVersion = $this->readVersionLine(public_path("vendor/fnlla-runtime/VERSION"));
        $frameworkLockPresent = is_file(base_path(".fnlla/framework-lock.json"));
        $cachedRelease = FrameworkReleaseChannel::readCachedReleaseSummary(base_path()) ?? [];
        $releaseChannelEnabled = (bool) config("framework_update.github_enabled", true);
        $frameworkStoragePath = storage_path("framework");
        $updatesStoragePath = storage_path("framework/updates");
        $storageReady = $this->isWritableDirectory($frameworkStoragePath) && $this->isWritableDirectory($updatesStoragePath);
        $releaseCacheReady = trim((string) ($cachedRelease["tag"] ?? "")) !== "";
        $vendoredRuntimeReady = $uiVersion !== null
            && is_file(public_path("vendor/fnlla-runtime/assets/css/fnlla-runtime.css"))
            && is_file(public_path("vendor/fnlla-runtime/assets/js/fnlla-runtime.js"));
        $versionContractReady = (bool) ($versionStatus["version_contract_ok"] ?? false);
        $sourceAvailable = is_string($sourceDetection["resolved_path"] ?? null) && $sourceDetection["resolved_path"] !== "";
        $releaseReadiness = !$releaseChannelEnabled
            ? "disabled"
            : ($releaseCacheReady ? "ready" : "standby");
        $operatorNotes = [
            "This browser view sits on top of the same /api/health payload. Automations should still use an Accept: application/json header or append ?format=json.",
            "The current PHP runtime reports a readiness snapshot for this request. It does not claim long-running process uptime.",
            $releaseChannelEnabled
                ? ($releaseCacheReady
                    ? "A published framework baseline is already cached locally and can be reviewed or applied from the maintenance surface."
                    : "Published release checks are enabled, but no cached baseline is stored yet for this project.")
                : "Published release checks are disabled in this environment, so operators should use the local maintainer source workflow instead.",
        ];

        return [
            "service" => [
                "name" => config("app.name"),
                "slug" => $this->slugifyServiceName((string) config("app.name")),
                "status" => "ok",
                "environment" => app_environment(),
                "timestamp" => gmdate(DATE_ATOM),
                "description" => "FNLLA starter application health status.",
            ],
            "versions" => [
                "fnlla" => $frameworkVersion,
                "fnlla_runtime" => $uiVersion,
            ],
            "runtime" => [
                "php_version" => PHP_VERSION,
                "sapi" => PHP_SAPI,
                "secure_request" => app_request_is_secure(),
                "timezone" => (string) date_default_timezone_get(),
            ],
            "request" => [
                "id" => request_id(),
                "method" => $request->method(),
                "path" => $request->path(),
                "secure" => app_request_is_secure(),
                "ip" => $request->ip(),
            ],
            "checks" => [
                "framework_lock" => $frameworkLockPresent ? "ok" : "missing",
                "vendored_fnlla_runtime" => $vendoredRuntimeReady ? "ok" : "missing",
                "framework_update_ui" => config("framework_update.ui_enabled", false) ? "enabled" : "disabled",
                "auto_detected_source" => $sourceAvailable ? "available" : "not_detected",
                "maintenance_mode" => maintenance_access()->enabled()
                    ? (maintenance_access()->isUnlocked() ? "unlocked" : "locked")
                    : "disabled",
            ],
            "readiness" => [
                "version_contract" => $versionContractReady ? "ready" : "attention",
                "vendored_runtime" => $vendoredRuntimeReady ? "ready" : "attention",
                "storage" => $storageReady ? "ready" : "attention",
                "release_channel" => $releaseReadiness,
                "maintenance_mode" => maintenance_access()->enabled() ? "restricted" : "open",
            ],
            "dependencies" => [
                [
                    "label" => "cURL client",
                    "status" => function_exists("curl_init") ? "available" : "unavailable",
                    "detail" => function_exists("curl_init")
                        ? "The runtime can request published release metadata through cURL."
                        : "The runtime will rely on stream access when release metadata must be resolved.",
                ],
                [
                    "label" => "allow_url_fopen",
                    "status" => filter_var(ini_get("allow_url_fopen"), FILTER_VALIDATE_BOOL) ? "enabled" : "disabled",
                    "detail" => "Used as the fallback HTTP transport when cURL is unavailable.",
                ],
                [
                    "label" => "PDO MySQL",
                    "status" => extension_loaded("pdo_mysql") ? "available" : "unavailable",
                    "detail" => "Confirms whether the expected MySQL PDO driver is loaded for downstream database work.",
                ],
                [
                    "label" => "Session support",
                    "status" => function_exists("session_status") ? "available" : "unavailable",
                    "detail" => "Required by the application shell for flash messages, CSRF handling and optional auth foundations.",
                ],
            ],
            "release_channel" => [
                "enabled" => $releaseChannelEnabled,
                "status" => $releaseReadiness,
                "latest_cached_tag" => (string) ($cachedRelease["tag"] ?? ""),
                "latest_cached_version" => (string) ($cachedRelease["version"] ?? ""),
                "comparison" => (string) ($cachedRelease["comparison"] ?? "unknown"),
                "checked_at_utc" => (string) ($cachedRelease["checked_at_utc"] ?? ""),
                "cache_path" => (string) ($cachedRelease["cache_path"] ?? storage_path("framework/updates/fnlla")),
                "notes_preview_available" => trim((string) ($cachedRelease["notes"] ?? "")) !== "",
                "notes_preview" => trim((string) ($cachedRelease["notes"] ?? "")),
                "published_at_utc" => (string) ($cachedRelease["published_at_utc"] ?? ""),
            ],
            "version_contract" => [
                "ok" => $versionContractReady,
                "errors" => (array) ($versionStatus["errors"] ?? []),
            ],
            "storage" => [
                "framework_path" => $frameworkStoragePath,
                "framework_writable" => $this->isWritableDirectory($frameworkStoragePath),
                "updates_path" => $updatesStoragePath,
                "updates_writable" => $this->isWritableDirectory($updatesStoragePath),
            ],
            "framework_update" => [
                "source_path" => $sourceDetection["resolved_path"] ?? null,
                "source_origin" => $sourceDetection["origin"] ?? "manual input required",
            ],
            "operator_notes" => $operatorNotes,
            "links" => [
                "home" => route("home"),
                "about" => route("about"),
                "services" => route("services"),
                "maintenance" => route("maintenance.home"),
                "health" => route("health"),
                "api_health" => route("api.health"),
                "api_health_json" => route("api.health") . "?format=json",
                "framework_updates" => route("maintenance.framework_update"),
            ],
        ];
    }

    private function healthApiWantsJson(Request $request): bool
    {
        $format = strtolower(trim((string) $request->query("format", "")));

        return $request->expectsJson() || $format === "json";
    }

    private function isWritableDirectory(string $path): bool
    {
        return is_dir($path) && is_writable($path);
    }

    private function readVersionLine(string $path): ?string
    {
        if (!is_file($path)) {
            return null;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES);

        if (!is_array($lines)) {
            return null;
        }

        $version = trim((string) ($lines[0] ?? ""));

        return $version !== "" ? $version : null;
    }

    private function slugifyServiceName(string $name): string
    {
        $slug = preg_replace('/[^a-z0-9]+/i', '-', strtolower(trim($name)));
        $slug = is_string($slug) ? trim($slug, '-') : '';

        return $slug !== '' ? $slug : 'application';
    }

    private function maintenanceSetupState(
        Request $request,
        EnvironmentFileManager $environmentFileManager,
        MaintenanceAccessManager $maintenanceAccess,
        DeveloperAccessManager $developerAccess
    ): array {
        $setupEnabled = (bool) config("maintenance.setup_ui_enabled", app_environment() !== "production");
        $localOnly = (bool) config("maintenance.setup_ui_local_only", true);
        $isLocalRequest = in_array($request->ip(), ["127.0.0.1", "::1"], true);
        $isLocalContext = !$localOnly || $isLocalRequest;
        $envWritable = $environmentFileManager->isWritable();
        $canSetup = $setupEnabled && $isLocalContext && $envWritable;
        $needsSetup = !$maintenanceAccess->configured() || !$maintenanceAccess->enabled();

        $message = match (true) {
            $setupEnabled !== true => "Browser-based maintenance setup is disabled in this environment.",
            $isLocalContext !== true => "Browser-based maintenance setup is local-only. Open this page from the same machine as the project runtime.",
            $envWritable !== true => "The project .env file is not writable. Make .env or the project directory writable before configuring maintenance here.",
            default => "This starter can save maintenance credentials directly into the project environment file.",
        };

        return [
            "enabled" => $setupEnabled,
            "local_only" => $localOnly,
            "is_local_request" => $isLocalRequest,
            "can_setup" => $canSetup,
            "needs_setup" => $needsSetup,
            "show_setup" => $needsSetup && $canSetup,
            "env_exists" => $environmentFileManager->envExists(),
            "env_writable" => $envWritable,
            "developer_access_configured" => $developerAccess->configured(),
            "message" => $message,
        ];
    }

    private function developerAccessSetupState(
        Request $request,
        EnvironmentFileManager $environmentFileManager,
        DeveloperAccessManager $developerAccess
    ): array {
        $setupEnabled = (bool) config("developer_access.setup_ui_enabled", app_environment() !== "production");
        $localOnly = (bool) config("developer_access.setup_ui_local_only", true);
        $isLocalRequest = in_array($request->ip(), ["127.0.0.1", "::1"], true);
        $isLocalContext = !$localOnly || $isLocalRequest;
        $envWritable = $environmentFileManager->isWritable();
        $canSetup = $setupEnabled && $isLocalContext && $envWritable;
        $needsSetup = !$developerAccess->configured();

        $message = match (true) {
            $setupEnabled !== true => "Browser-based developer panel setup is disabled in this environment.",
            $isLocalContext !== true => "Browser-based developer panel setup is local-only. Open this page from the same machine as the project runtime.",
            $envWritable !== true => "The project .env file is not writable. Make .env or the project directory writable before activating the developer panel here.",
            default => "This project can generate its hidden developer panel directly from the maintenance surface.",
        };

        return [
            "enabled" => $setupEnabled,
            "local_only" => $localOnly,
            "is_local_request" => $isLocalRequest,
            "can_setup" => $canSetup,
            "needs_setup" => $needsSetup,
            "show_setup" => $needsSetup && $canSetup,
            "env_exists" => $environmentFileManager->envExists(),
            "env_writable" => $envWritable,
            "message" => $message,
        ];
    }

    private function sanitizeMaintenanceRedirectTarget(string $target): string
    {
        $target = trim($target);

        if ($target === "" || !str_starts_with($target, "/") || str_starts_with($target, "//") || str_contains($target, "\\")) {
            return "";
        }

        $parts = parse_url($target);

        if ($parts === false) {
            return "";
        }

        $path = (string) ($parts["path"] ?? "");

        if ($path === "" || !str_starts_with($path, "/")) {
            return "";
        }

        $query = isset($parts["query"]) && $parts["query"] !== "" ? "?" . $parts["query"] : "";

        return $path . $query;
    }

    private function maintenanceRedirectUrl(string $redirectTarget, string $fragment = ""): string
    {
        $base = route("maintenance.home");

        if ($redirectTarget !== "") {
            $base .= "?redirect=" . rawurlencode($redirectTarget);
        }

        return $base . $fragment;
    }

    private function notFoundResponse(): Response
    {
        return $this->view("pages/not-found", [
            "pageTitle" => "Not Found",
        ], 404);
    }
}
