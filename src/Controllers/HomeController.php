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
- Provides HTTP-facing controller behaviour for maintained framework flows and demos.
*/

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

final class HomeController extends Controller
{
    public function projectHome(
        Request $request,
        MaintenanceAccessManager $maintenanceAccess,
        DeveloperAccessManager $developerAccess,
        PageController $pages
    ): Response {
        if ($this->freshDeveloperOnboardingAvailable($request, $maintenanceAccess, $developerAccess)) {
            return $this->firstRunDeveloperSetup($request, $maintenanceAccess, $developerAccess);
        }

        return $pages->home($request);
    }

    public function developerSetupAlias(): Response
    {
        return $this->redirect(route("home"));
    }

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

    private function firstRunDeveloperSetup(
        Request $request,
        MaintenanceAccessManager $maintenanceAccess,
        DeveloperAccessManager $developerAccess
    ): Response {
        $environmentFileManager = app(EnvironmentFileManager::class);

        return $this->view("maintenance/index", [
            "pageTitle" => "Project Setup",
            "pageTitleSection" => "Developer Onboarding",
            "maintenanceAccess" => $maintenanceAccess->viewState(),
            "developerAccess" => $developerAccess->viewState(),
            "maintenanceSetup" => $this->maintenanceSetupState($request, $environmentFileManager, $maintenanceAccess, $developerAccess),
            "developerSetup" => $this->developerAccessSetupState($request, $environmentFileManager, $developerAccess),
            "projectSetup" => $this->projectSetupState(),
            "maintenanceLocked" => false,
        ]);
    }

    private function freshDeveloperOnboardingAvailable(
        Request $request,
        MaintenanceAccessManager $maintenanceAccess,
        DeveloperAccessManager $developerAccess
    ): bool {
        if ($maintenanceAccess->enabled() || $maintenanceAccess->configured() || $developerAccess->configured()) {
            return false;
        }

        $environmentFileManager = app(EnvironmentFileManager::class);
        $setupState = $this->developerAccessSetupState($request, $environmentFileManager, $developerAccess);

        return $setupState["can_setup"] === true && $setupState["needs_setup"] === true;
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
                "role" => "admin",
                "password_hash" => $developerPasswordHash,
            ];
            $environmentValues["DEVELOPER_ACCESS_ENABLED"] = "true";
            $environmentValues["DEVELOPER_ACCESS_EMAIL"] = $payload["developer_setup_email"];
            $environmentValues["DEVELOPER_ACCESS_PASSWORD"] = "";
            $environmentValues["DEVELOPER_ACCESS_PASSWORD_HASH"] = "";
            $environmentValues["DEVELOPER_ACCESS_USERS"] = $developerAccess->serializeAccounts([$developerAccount]);
            $environmentValues["DEVELOPER_OPERATIONS_NAV_MODE"] = "hidden";
            $developerAccessCreated = true;
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

        if ($developerAccessCreated) {
            config_set("developer_access", array_merge((array) config("developer_access", []), [
                "enabled" => true,
                "email" => (string) $environmentValues["DEVELOPER_ACCESS_EMAIL"],
                "password" => "",
                "password_hash" => "",
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
            $developerAccess->grantAccess($developerAccount ?? null);
            $maintenanceAccess->lock();
            flash_set("developer_access_notice", [
                "title" => "Named developer account created",
                "text" => "The developer session is ready at the standard /developer address and uses email plus password sign-in.",
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
                    ? "The developer panel is already configured for this project."
                    : (string) $setupState["message"],
                "toast" => false,
            ]);
            regenerate_csrf_token();

            return $this->redirect($this->developerSetupRedirectTarget($maintenanceAccess));
        }

        $payload = [
            "project_name" => $this->normalizeProjectName((string) $request->input("project_name", (string) config("app.name", "FNLLA Project"))),
            "project_tagline" => $this->normalizeProjectName((string) $request->input("project_tagline", (string) config("app.tagline", ""))),
            "project_url" => trim((string) $request->input("project_url", (string) config("app.base_url", ""))),
            "project_leadership_organization" => $this->normalizeProjectName((string) $request->input("project_leadership_organization", "")),
            "project_leadership_person_name" => $this->normalizeProjectName((string) $request->input("project_leadership_person_name", "")),
            "project_leadership_person_email" => strtolower(trim((string) $request->input("project_leadership_person_email", ""))),
            "project_leadership_person_role" => $this->normalizeProjectName((string) $request->input("project_leadership_person_role", "")),
            "project_leadership_responsibility" => $this->normalizeProjectName((string) $request->input("project_leadership_responsibility", "")),
            "project_leadership_profile_url" => trim((string) $request->input("project_leadership_profile_url", "")),
            "project_leadership_visibility" => (new ProjectLeadership())->visibility((string) $request->input("project_leadership_visibility", ProjectLeadership::VISIBILITY_DISABLED)),
            "developer_setup_email" => strtolower(trim((string) $request->input("developer_setup_email", ""))),
            "developer_setup_password" => trim((string) $request->input("developer_setup_password", "")),
            "developer_setup_password_confirmation" => trim((string) $request->input("developer_setup_password_confirmation", "")),
        ];
        $leadershipEnabled = $payload["project_leadership_visibility"] !== ProjectLeadership::VISIBILITY_DISABLED;

        try {
            $this->validate($payload, [
                "project_name" => ["required", "string", "min:2", "max:80"],
                "project_tagline" => ["nullable", "string", "max:120"],
                "project_url" => ["nullable", "string", "url", "max:2048"],
                "project_leadership_organization" => [$leadershipEnabled ? "required" : "nullable", "string", "max:120"],
                "project_leadership_person_name" => [$leadershipEnabled ? "required" : "nullable", "string", "max:120"],
                "project_leadership_person_email" => [$leadershipEnabled ? "required" : "nullable", "email", "max:160"],
                "project_leadership_person_role" => [$leadershipEnabled ? "required" : "nullable", "string", "max:120"],
                "project_leadership_responsibility" => [$leadershipEnabled ? "required" : "nullable", "string", "max:240"],
                "project_leadership_profile_url" => ["nullable", "string", "url", "max:2048"],
                "project_leadership_visibility" => ["required", "string"],
                "developer_setup_email" => ["required", "email", "max:160"],
                "developer_setup_password" => ["required", "string", "min:8", "max:255", "confirmed"],
            ]);
        } catch (ValidationException $exception) {
            flash_set("errors", $exception->errors());
            flash_set("status", [
                "variant" => "warning",
                "title" => "Developer panel setup still needs attention",
                "text" => "Review the developer email and password fields before activating the developer panel.",
                "toast" => false,
            ]);
            regenerate_csrf_token();

            return $this->redirect($this->developerSetupRedirectTarget($maintenanceAccess));
        }

        $developerPasswordHash = password_hash($payload["developer_setup_password"], PASSWORD_DEFAULT);
        $developerAccount = [
            "email" => $payload["developer_setup_email"],
            "name" => "Developer",
            "role" => "admin",
            "password_hash" => $developerPasswordHash,
        ];
        $environmentValues = [
            "APP_NAME" => $payload["project_name"],
            "APP_TAGLINE" => $payload["project_tagline"],
            "APP_URL" => $payload["project_url"],
            "PROJECT_LEADERSHIP_ORGANIZATION" => $leadershipEnabled ? $payload["project_leadership_organization"] : "",
            "PROJECT_LEADERSHIP_PERSON_NAME" => $leadershipEnabled ? $payload["project_leadership_person_name"] : "",
            "PROJECT_LEADERSHIP_PERSON_EMAIL" => $leadershipEnabled ? $payload["project_leadership_person_email"] : "",
            "PROJECT_LEADERSHIP_PERSON_ROLE" => $leadershipEnabled ? $payload["project_leadership_person_role"] : "",
            "PROJECT_LEADERSHIP_RESPONSIBILITY" => $leadershipEnabled ? $payload["project_leadership_responsibility"] : "",
            "PROJECT_LEADERSHIP_PROFILE_URL" => $leadershipEnabled ? $payload["project_leadership_profile_url"] : "",
            "PROJECT_LEADERSHIP_VISIBILITY" => $leadershipEnabled ? $payload["project_leadership_visibility"] : ProjectLeadership::VISIBILITY_DISABLED,
            "PROJECT_LEADERSHIP_STATUS" => ProjectLeadership::STATUS_PENDING,
            "PROJECT_LEADERSHIP_CONFIRMED_BY" => "",
            "PROJECT_LEADERSHIP_CONFIRMED_AT" => "",
            "DEVELOPER_ACCESS_ENABLED" => "true",
            "DEVELOPER_ACCESS_EMAIL" => $payload["developer_setup_email"],
            "DEVELOPER_ACCESS_PASSWORD" => "",
            "DEVELOPER_ACCESS_PASSWORD_HASH" => "",
            "DEVELOPER_ACCESS_USERS" => $developerAccess->serializeAccounts([$developerAccount]),
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

            return $this->redirect($this->developerSetupRedirectTarget($maintenanceAccess));
        }

        config_set("developer_access", array_merge((array) config("developer_access", []), [
            "enabled" => true,
            "email" => $payload["developer_setup_email"],
            "password" => "",
            "password_hash" => "",
            "users" => (string) $environmentValues["DEVELOPER_ACCESS_USERS"],
            "operations_nav_mode" => "hidden",
        ]));
        config_set("app", array_merge((array) config("app", []), [
            "name" => $payload["project_name"],
            "tagline" => $payload["project_tagline"],
            "base_url" => rtrim($payload["project_url"], "/"),
            "project_leadership" => [
                "organization" => $leadershipEnabled ? $payload["project_leadership_organization"] : "",
                "person_name" => $leadershipEnabled ? $payload["project_leadership_person_name"] : "",
                "person_email" => $leadershipEnabled ? $payload["project_leadership_person_email"] : "",
                "person_role" => $leadershipEnabled ? $payload["project_leadership_person_role"] : "",
                "responsibility" => $leadershipEnabled ? $payload["project_leadership_responsibility"] : "",
                "profile_url" => $leadershipEnabled ? $payload["project_leadership_profile_url"] : "",
                "visibility" => $leadershipEnabled ? $payload["project_leadership_visibility"] : ProjectLeadership::VISIBILITY_DISABLED,
                "status" => ProjectLeadership::STATUS_PENDING,
                "confirmed_by" => "",
                "confirmed_at" => "",
            ],
        ]));
        $developerAccess->grantAccess($developerAccount);
        Logger::write("notice", "Developer access created", [
            "event" => "developer_access_created",
            "email" => $payload["developer_setup_email"],
        ]);
        $maintenanceAccess->lock();
        flash_set("developer_access_notice", [
            "title" => "Named developer account created",
            "text" => "The developer session is ready at the standard /developer address and uses email plus password sign-in.",
        ]);
        flash_set("status", [
            "variant" => "success",
            "title" => "Developer panel activated",
            "text" => "The developer panel was added to this project and the current browser session can use it immediately. Maintenance stays optional until you enable it from the panel.",
            "toast" => true,
        ]);
        regenerate_csrf_token();

        return $this->redirect(route("developer.panel"));
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

    public function redirectHealthToMaintenance(Request $request, DeveloperAccessManager $developerAccess): Response
    {
        if (!$developerAccess->canAccessOperations()) {
            return $this->notFoundResponse();
        }

        return $this->redirect(route("health"));
    }

    public function healthPage(Request $request): Response
    {
        $health = $this->healthPayload($request);

        return $this->view("pages/health", [
            "pageTitle" => "Health Check",
            "pageTitleSection" => "Operations",
            "health" => $health,
        ]);
    }

    public function healthApi(Request $request): Response
    {
        $health = $this->healthPayload($request);

        if ($this->healthApiWantsJson($request)) {
            return Response::json($health);
        }

        return $this->view("pages/api-health", [
            "pageTitle" => "API Health",
            "pageTitleSection" => "Operations",
            "health" => $health,
        ]);
    }

    public function profileApi(): array
    {
        return [
            "meta" => [
                "name" => config("app.name"),
                "version" => "1.0",
                "supports" => ["routing", "middleware", "auth", "queues"],
            ],
        ];
    }

    public function healthPayload(Request $request): array
    {
        return $this->buildHealthPayload($request);
    }

    private function buildHealthPayload(Request $request): array
    {
        $level = $this->healthLevel($request);
        $cached = $level === "live" ? $this->liveHealthSnapshot() : $this->cachedHealthSnapshot($level);

        return array_replace_recursive($cached, [
            "service" => [
                "timestamp" => gmdate(DATE_ATOM),
                "level" => $level,
            ],
            "request" => [
                "id" => request_id(),
                "method" => $request->method(),
                "path" => $request->path(),
                "secure" => app_request_is_secure(),
                "ip" => $request->ip(),
            ],
        ]);
    }

    private function liveHealthSnapshot(): array
    {
        return [
            "service" => [
                "name" => config("app.name"),
                "slug" => $this->slugifyServiceName((string) config("app.name")),
                "status" => "ok",
                "environment" => app_environment(),
                "timestamp" => gmdate(DATE_ATOM),
                "level" => "live",
            ],
            "request" => [
                "id" => "",
                "method" => "",
                "path" => "",
                "secure" => app_request_is_secure(),
                "ip" => "",
            ],
        ];
    }

    private function cachedHealthSnapshot(string $level): array
    {
        $ttl = max(0, (int) config("health.cache_ttl_seconds", 10));

        if ($ttl <= 0) {
            return $this->buildCachedHealthSnapshot(0, $level);
        }

        return cache()->remember(
            "fnlla:health:snapshot:" . app_environment() . ":" . $level,
            $ttl,
            fn (): array => $this->buildCachedHealthSnapshot($ttl, $level)
        );
    }

    private function buildCachedHealthSnapshot(int $ttlSeconds, string $level): array
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
        $cachePath = (string) config("cache.stores.file.path", storage_path("framework/cache"));
        $queuePath = storage_path((string) config("queue.connections.file.path", "framework/queue"));
        $maintenanceAccess = maintenance_access();
        $maintenanceEnabled = $maintenanceAccess->enabled();
        $secureRequest = app_request_is_secure();
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
                : "Published release checks are disabled in this environment, so operators must re-enable the official GitHub channel before framework updates can run.",
        ];

        $payload = [
            "service" => [
                "name" => config("app.name"),
                "slug" => $this->slugifyServiceName((string) config("app.name")),
                "status" => "ok",
                "environment" => app_environment(),
                "timestamp" => gmdate(DATE_ATOM),
                "health_cache_ttl_seconds" => $ttlSeconds,
                "health_snapshot_generated_at" => gmdate(DATE_ATOM),
                "level" => $level,
                "description" => "FNLLA project application health status.",
            ],
            "versions" => [
                "fnlla" => $frameworkVersion,
                "fnlla_runtime" => $uiVersion,
            ],
            "runtime" => [
                "php_version" => PHP_VERSION,
                "sapi" => PHP_SAPI,
                "secure_request" => $secureRequest,
                "timezone" => (string) date_default_timezone_get(),
            ],
            "request" => [
                "id" => "",
                "method" => "",
                "path" => "",
                "secure" => $secureRequest,
                "ip" => "",
            ],
            "checks" => [
                "framework_lock" => $frameworkLockPresent ? "ok" : "missing",
                "vendored_fnlla_runtime" => $vendoredRuntimeReady ? "ok" : "missing",
                "framework_update_ui" => config("framework_update.ui_enabled", false) ? "enabled" : "disabled",
                "auto_detected_source" => $sourceAvailable ? "available" : "not_detected",
                "maintenance_mode" => $maintenanceEnabled
                    ? ($maintenanceAccess->isUnlocked() ? "unlocked" : "locked")
                    : "disabled",
            ],
            "readiness" => [
                "version_contract" => $versionContractReady ? "ready" : "attention",
                "vendored_runtime" => $vendoredRuntimeReady ? "ready" : "attention",
                "storage" => $storageReady ? "ready" : "attention",
                "release_channel" => $releaseReadiness,
                "maintenance_mode" => $maintenanceEnabled ? "restricted" : "open",
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
            "cache" => [
                "default_store" => (string) config("cache.default", "file"),
                "serializer" => (string) config("cache.serializer", "json"),
                "path" => $cachePath,
                "writable" => $this->isWritableDirectory($cachePath),
            ],
            "observability" => [
                "access_log_enabled" => (bool) config("observability.access_log.enabled", true),
                "metrics_enabled" => (bool) config("observability.metrics.enabled", true),
                "metrics_path" => storage_path((string) config("observability.metrics.path", "framework/metrics.json")),
                "response_time_header_enabled" => (bool) config("observability.response_time_header.enabled", true),
            ],
            "queue" => [
                "default_connection" => (string) config("queue.default", "file"),
                "path" => $queuePath,
                "pending_jobs" => $this->countFiles($queuePath, "*.job"),
                "failed_jobs" => $this->countFiles($queuePath . DIRECTORY_SEPARATOR . "failed", "*.failed.job"),
            ],
            "migrations" => [
                "table" => (string) config("database.migrations_table", "migrations"),
                "files" => $this->countFiles(base_path("database/migrations"), "*.php"),
                "pdo_mysql" => extension_loaded("pdo_mysql") ? "available" : "unavailable",
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
                "contact" => route("contact"),
                "maintenance" => route("maintenance.home"),
                "health" => route("health"),
                "api_health" => route("api.health"),
                "api_health_json" => route("api.health") . "?format=json",
                "framework_updates" => route("maintenance.framework_update"),
            ],
        ];

        if ($level !== "deep") {
            unset($payload["dependencies"], $payload["storage"], $payload["cache"], $payload["queue"], $payload["migrations"]);
        }

        return $payload;
    }

    private function healthLevel(Request $request): string
    {
        $level = strtolower(trim((string) $request->query("level", "ready")));

        return in_array($level, ["live", "ready", "deep"], true) ? $level : "ready";
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

    private function countFiles(string $directory, string $pattern): int
    {
        if (!is_dir($directory)) {
            return 0;
        }

        $files = glob(rtrim($directory, "\\/") . DIRECTORY_SEPARATOR . $pattern);

        return is_array($files) ? count(array_filter($files, "is_file")) : 0;
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
            default => "This project setup flow can save maintenance credentials directly into the project environment file.",
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

    private function projectSetupState(): array
    {
        return [
            "name" => (string) config("app.name", "FNLLA Project"),
            "tagline" => (string) config("app.tagline", ""),
            "url" => (string) config("app.base_url", ""),
        ];
    }

    private function developerSetupRedirectTarget(MaintenanceAccessManager $maintenanceAccess): string
    {
        if (!$maintenanceAccess->enabled() && !$maintenanceAccess->configured()) {
            return route("home") . "#developer-panel-setup";
        }

        return route("maintenance.home") . "#developer-panel-setup";
    }

    private function normalizeProjectName(string $value): string
    {
        return trim((string) preg_replace('/\s+/', ' ', $value));
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
            default => "This project can create developer access directly from the maintenance surface.",
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

    private function maintenanceLockedAccessFragment(MaintenanceAccessManager $maintenanceAccess): string
    {
        $clientPreviewState = $this->clientPreviewState();

        return $clientPreviewState["active"] && $maintenanceAccess->configured()
            ? "#client-preview-access"
            : "#maintenance-access";
    }

    private function clientPreviewState(): array
    {
        $enabled = (bool) config("client_preview.enabled", false);
        $timezone = new DateTimeZone((string) config("app.timezone", "UTC"));
        $restoreAt = $this->parseClientPreviewDateTime((string) config("client_preview.restore_at", ""), $timezone);
        $startedAt = $this->parseClientPreviewDateTime((string) config("client_preview.started_at", ""), $timezone);
        $lastUpdatedValue = trim((string) config("client_preview.last_updated_value", ""));

        if ($lastUpdatedValue === "" && $startedAt instanceof DateTimeImmutable) {
            $lastUpdatedValue = $startedAt->format("j F Y \\a\\t H:i");
        }

        $countdownEnabled = $restoreAt instanceof DateTimeImmutable;
        $progressEnabled = (bool) config("client_preview.progress_enabled", true)
            && $restoreAt instanceof DateTimeImmutable
            && $startedAt instanceof DateTimeImmutable
            && $startedAt->getTimestamp() < $restoreAt->getTimestamp();

        $secondsRemaining = $countdownEnabled ? max(0, $restoreAt->getTimestamp() - time()) : 0;
        $totalSeconds = $progressEnabled ? max(1, $restoreAt->getTimestamp() - $startedAt->getTimestamp()) : 0;
        $elapsedSeconds = $progressEnabled ? max(0, min($totalSeconds, time() - $startedAt->getTimestamp())) : 0;
        $progressPercent = $progressEnabled ? (int) round(($elapsedSeconds / $totalSeconds) * 100) : 0;

        return [
            "active" => $enabled,
            "login_disabled" => (bool) config("client_preview.login_disabled", false),
            "kicker" => (string) config("client_preview.kicker", "Private Client Preview"),
            "title" => (string) config("client_preview.title", "Private client preview is active"),
            "show_last_updated" => (bool) config("client_preview.show_last_updated", true),
            "last_updated_label" => (string) config("client_preview.last_updated_label", "Last updated"),
            "last_updated_value" => $lastUpdatedValue,
            "status_title" => (string) config("client_preview.status_title", ""),
            "status_body" => (string) config("client_preview.status_body", ""),
            "countdown_label" => (string) config("client_preview.countdown_label", "Preview window closes in"),
            "countdown_enabled" => $countdownEnabled,
            "countdown" => $this->formatClientPreviewCountdown($secondsRemaining),
            "restore_at_timestamp" => $restoreAt?->getTimestamp() ?? 0,
            "started_at_timestamp" => $startedAt?->getTimestamp() ?? 0,
            "progress_enabled" => $progressEnabled,
            "progress_label" => (string) config("client_preview.progress_label", "Preview window progress"),
            "progress_percent" => $progressPercent,
            "message" => (string) config("client_preview.message", ""),
            "support_heading" => (string) config("client_preview.support_heading", "Need assistance?"),
            "support_email" => (string) config("client_preview.support_email", ""),
            "unlock_button_label" => (string) config("client_preview.unlock_button_label", "Unlock preview"),
            "locked_notice" => (string) config("client_preview.locked_notice", ""),
        ];
    }

    private function parseClientPreviewDateTime(string $value, DateTimeZone $timezone): ?DateTimeImmutable
    {
        $trimmed = trim($value);

        if ($trimmed === "") {
            return null;
        }

        try {
            return new DateTimeImmutable($trimmed, $timezone);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array{hours:string,minutes:string,seconds:string}
     */
    private function formatClientPreviewCountdown(int $seconds): array
    {
        $safeSeconds = max(0, $seconds);
        $hours = intdiv($safeSeconds, 3600);
        $minutes = intdiv($safeSeconds % 3600, 60);
        $remainingSeconds = $safeSeconds % 60;

        return [
            "hours" => sprintf("%02d", $hours),
            "minutes" => sprintf("%02d", $minutes),
            "seconds" => sprintf("%02d", $remainingSeconds),
        ];
    }

    private function notFoundResponse(): Response
    {
        return $this->view("pages/not-found", [
            "pageTitle" => "Not Found",
        ], 404);
    }
}
