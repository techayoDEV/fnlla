<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA PHP CONTROLLER SOURCE
File: src\Controllers\HomeController.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA PHP is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA PHP framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Provides HTTP-facing controller behavior for maintained framework flows and demos.
*/

namespace Fnlla\Php\Controllers;

use Fnlla\Php\Http\Request;
use Fnlla\Php\Http\Response;
use Fnlla\Php\Support\FrameworkReleaseChannel;
use Fnlla\Php\Support\FrameworkUpdater;
use Fnlla\Php\Support\VersionManifest;
use Fnlla\Php\Validation\ValidationException;

final class HomeController extends Controller
{
    public function home(Request $request): Response
    {
        $showDocsWorkspace = has_local_docs_workspace();

        return $this->view("pages/home", [
            "pageTitle" => "Home",
            "pageTitleHome" => true,
            "foundationCards" => [
                [
                    "title" => "Starter-first delivery",
                    "text" => "The public starter is the application base teams actually extend, instead of a separate framework demo that has to be replaced beside itself.",
                ],
                [
                    "title" => "FNLLA Web contract",
                    "text" => "The starter still ships on one supported UI runtime, so routes, views and delivery logic can change without splitting the visual foundation.",
                ],
                [
                    "title" => "Linked operator capabilities",
                    "text" => "Maintenance, health and framework updates remain available as attached operational surfaces instead of taking over the public information architecture.",
                ],
            ],
            "deliverySteps" => [
                [
                    "number" => "1",
                    "title" => "Shape the public IA",
                    "text" => "Start from the shipped public shell, then replace the starter sections, routes and copy with the real product structure for the project.",
                ],
                [
                    "number" => "2",
                    "title" => "Attach real delivery flows",
                    "text" => "Keep one working server-rendered form path, then expand into auth, data capture, dashboards or service workflows only where the project actually needs them.",
                ],
                [
                    "number" => "3",
                    "title" => "Keep framework work explicit",
                    "text" => "Use maintenance, health and validation commands as linked framework capabilities while the project surface grows independently.",
                ],
            ],
            "launchTracks" => [
                [
                    "number" => "1",
                    "title" => "Public experience",
                    "text" => "Turn the starter homepage into the real project story, calls to action and page map instead of treating it as disposable placeholder chrome.",
                ],
                [
                    "number" => "2",
                    "title" => "Application wiring",
                    "text" => "Routes, controllers, views, forms and persistence are the seam where the starter becomes the actual application.",
                ],
                [
                    "number" => "3",
                    "title" => "Operational confidence",
                    "text" => "Health, framework updates and version checks stay available without forcing operator concerns into the public navigation.",
                ],
                [
                    "number" => "4",
                    "title" => "Maintainer clarity",
                    "text" => "The `techayoDEV/fnlla-php` repository remains the framework source of truth, but the shipped starter is now the same application base downstream teams really edit.",
                ],
            ],
            "launchChecklist" => [
                "Replace placeholder routes, copy and sections with the real product structure early.",
                "Use /project/launch as the first delivery checklist for a new exported application.",
                "Keep /maintenance for framework upkeep rather than mixing those links into customer-facing IA.",
                "Run validation, tests and version checks before calling the starter reshaping work stable.",
            ],
            "showDocsWorkspace" => $showDocsWorkspace,
        ]);
    }

    public function projectLaunch(Request $request): Response
    {
        return $this->view("pages/project-launch", [
            "pageTitle" => "Project Launch",
            "launchTracks" => [
                [
                    "number" => "1",
                    "title" => "Keep the starter as the public base",
                    "text" => "Build the real web project by modifying the starter routes, views, copy and assets instead of constructing a second front beside it.",
                ],
                [
                    "number" => "2",
                    "title" => "Replace generic delivery content",
                    "text" => "Swap the placeholder sections, messages and proof points with the real product story and service structure as soon as the project starts.",
                ],
                [
                    "number" => "3",
                    "title" => "Attach real project workflows",
                    "text" => "Use the working form, routes and controller seams as the first bridge into CRM, auth, data capture or project-specific application logic.",
                ],
                [
                    "number" => "4",
                    "title" => "Leave framework upkeep linked, not embedded",
                    "text" => "Health and framework-update tooling should stay available through maintenance without turning the public starter into an operator dashboard.",
                ],
            ],
            "launchFiles" => [
                "routes/web.php",
                "src/Controllers/HomeController.php",
                "views/layouts/app.php",
                "views/pages/home.php",
                "views/pages/contact.php",
                "views/pages/project-launch.php",
                "public/assets/app.css",
                "config/app.php",
                "database/migrations/",
            ],
            "launchCommands" => [
                "php fnlla route:list",
                "php fnlla fnlla-web:validate",
                "php scripts/test.php",
                "php scripts/lint.php",
                "php scripts/validate-version-manifest.php",
            ],
        ]);
    }

    public function contact(Request $request): Response
    {
        return $this->view("pages/contact", [
            "pageTitle" => "Contact",
            "contactTopics" => [
                "New website",
                "Portal or application",
                "Operations or support",
            ],
            "deliverySteps" => [
                [
                    "number" => "1",
                    "title" => "Scope the request",
                    "text" => "Capture the page map, auth boundary, data needs and operational constraints before deeper implementation work starts.",
                ],
                [
                    "number" => "2",
                    "title" => "Build on the working starter flow",
                    "text" => "Keep the starter form logic, validation and redirect-after-post pattern, then adapt the copy, fields and destination to the real project.",
                ],
                [
                    "number" => "3",
                    "title" => "Validate before release",
                    "text" => "Use lint, tests, FNLLA Web validation and version checks before treating any starter customization as ready.",
                ],
            ],
        ]);
    }

    public function maintenanceHome(Request $request): Response
    {
        $health = $this->buildHealthPayload($request);
        $releaseChannel = (array) ($health["release_channel"] ?? []);
        $readiness = (array) ($health["readiness"] ?? []);

        return $this->view("maintenance/index", [
            "pageTitle" => "Maintenance",
            "pageTitleSection" => "Operations",
            "maintenanceCards" => [
                [
                    "title" => "Framework updates",
                    "text" => "Review framework drift and apply only framework-managed changes through the linked maintenance workflow.",
                    "href" => route("maintenance.framework_update"),
                    "action" => "Open framework updates",
                ],
                [
                    "title" => "Health view",
                    "text" => "Inspect the human-readable operator status page that summarizes the same payload exposed by the machine-facing API endpoint.",
                    "href" => route("health"),
                    "action" => "Open health view",
                ],
                [
                    "title" => "Raw API status",
                    "text" => "Use the JSON health contract for automation, probes, deployment checks or other machine-facing diagnostics.",
                    "href" => route("api.health"),
                    "action" => "Open /api/health",
                ],
            ],
            "maintenanceHighlights" => [
                [
                    "label" => "Published release cache",
                    "value" => trim((string) ($releaseChannel["latest_cached_tag"] ?? "")) !== ""
                        ? (string) ($releaseChannel["latest_cached_tag"] ?? "")
                        : "Not cached yet",
                ],
                [
                    "label" => "Release channel",
                    "value" => (string) ($readiness["release_channel"] ?? "unknown"),
                ],
                [
                    "label" => "Storage readiness",
                    "value" => (string) ($readiness["storage"] ?? "unknown"),
                ],
                [
                    "label" => "Version contract",
                    "value" => (string) ($readiness["version_contract"] ?? "unknown"),
                ],
            ],
        ]);
    }

    public function redirectHealthToMaintenance(Request $request): Response
    {
        return $this->redirect(route("health"));
    }

    public function healthPage(Request $request): Response
    {
        $health = $this->buildHealthPayload($request);
        $checkItems = [
            [
                "label" => "Version contract",
                "status" => (string) ($health["readiness"]["version_contract"] ?? "unknown"),
                "text" => "Confirms whether VERSION, MANIFEST.json and the vendored FNLLA Web version still match the maintained repository contract.",
            ],
            [
                "label" => "Runtime assets",
                "status" => (string) ($health["readiness"]["vendored_runtime"] ?? "unknown"),
                "text" => "Shows whether the project still ships the local FNLLA Web runtime files expected by the application shell.",
            ],
            [
                "label" => "Storage readiness",
                "status" => (string) ($health["readiness"]["storage"] ?? "unknown"),
                "text" => "Confirms whether the framework storage directories used by cache, sessions and update snapshots are writable now.",
            ],
            [
                "label" => "Release channel",
                "status" => (string) ($health["readiness"]["release_channel"] ?? "unknown"),
                "text" => "Reports whether the published release channel is enabled and whether the local cache already has a prepared baseline snapshot.",
            ],
        ];

        return $this->view("pages/health", [
            "pageTitle" => "Health",
            "pageTitleSection" => "Operations",
            "health" => $health,
            "checkItems" => $checkItems,
        ]);
    }

    public function healthApi(Request $request): Response
    {
        return Response::json($this->buildHealthPayload($request));
    }

    public function sendContact(Request $request): Response
    {
        $payload = [
            "name" => trim((string) $request->input("name", "")),
            "company" => trim((string) $request->input("company", "")),
            "email" => trim((string) $request->input("email", "")),
            "topic" => trim((string) $request->input("topic", "")),
            "message" => trim((string) $request->input("message", "")),
        ];

        try {
            $this->validate($payload, [
                "name" => ["required", "string", "min:2", "max:120"],
                "company" => ["nullable", "string", "max:120"],
                "email" => ["required", "email", "max:160"],
                "topic" => ["required", "in:New website,Portal or application,Operations or support"],
                "message" => ["required", "string", "min:12", "max:3000"],
            ]);
        } catch (ValidationException $exception) {
            flash_set("old", $payload);
            flash_set("errors", $exception->errors());
            flash_set("status", [
                "variant" => "warning",
                "title" => "A few fields still need attention",
                "text" => "Review the highlighted inputs and submit the form again.",
                "toast" => false,
            ]);
            regenerate_csrf_token();

            return $this->redirect(route("contact") . "#contact-form");
        }

        flash_set("status", [
            "variant" => "success",
            "title" => "Request captured",
            "text" => "The starter processed the form successfully and flashed the confirmation into the next request.",
            "toast" => true,
        ]);
        mailer()->to((string) env("CONTACT_NOTIFICATION_EMAIL", "team@example.com"))->send(
            "New contact form submission",
            "<p><strong>Name:</strong> " . h($payload["name"]) . "</p><p><strong>Email:</strong> " . h($payload["email"]) . "</p><p><strong>Topic:</strong> " . h($payload["topic"]) . "</p><p><strong>Message:</strong> " . nl2br(h($payload["message"])) . "</p>",
            "Name: {$payload["name"]}\nEmail: {$payload["email"]}\nTopic: {$payload["topic"]}\nMessage: {$payload["message"]}"
        );
        event("contact.form.submitted", [
            "payload" => $payload,
        ]);
        regenerate_csrf_token();

        return $this->redirect(route("contact") . "#contact-form");
    }

    private function buildHealthPayload(Request $request): array
    {
        $sourceDetection = FrameworkUpdater::detectSourceRoot(base_path(), (string) config("framework_update.source_path", ""));
        $versionStatus = VersionManifest::status();
        $frameworkVersion = $this->readVersionLine(base_path("VERSION"));
        $uiVersion = $this->readVersionLine(public_path("vendor/fnlla-web/VERSION"));
        $frameworkLockPresent = is_file(base_path(".fnlla/framework-lock.json"));
        $cachedRelease = FrameworkReleaseChannel::readCachedReleaseSummary(base_path()) ?? [];
        $releaseChannelEnabled = (bool) config("framework_update.github_enabled", true);
        $frameworkStoragePath = storage_path("framework");
        $updatesStoragePath = storage_path("framework/updates");
        $storageReady = $this->isWritableDirectory($frameworkStoragePath) && $this->isWritableDirectory($updatesStoragePath);
        $releaseCacheReady = trim((string) ($cachedRelease["tag"] ?? "")) !== "";
        $vendoredRuntimeReady = $uiVersion !== null
            && is_file(public_path("vendor/fnlla-web/assets/css/fnlla-web.css"))
            && is_file(public_path("vendor/fnlla-web/assets/js/fnlla-web.js"));
        $versionContractReady = (bool) ($versionStatus["version_contract_ok"] ?? false);
        $sourceAvailable = is_string($sourceDetection["resolved_path"] ?? null) && $sourceDetection["resolved_path"] !== "";
        $releaseReadiness = !$releaseChannelEnabled
            ? "disabled"
            : ($releaseCacheReady ? "ready" : "standby");
        $operatorNotes = [
            "This page is the operator-facing companion to /api/health. Automations should continue to consume the JSON endpoint directly.",
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
                "description" => "FNLLA PHP starter health status.",
            ],
            "versions" => [
                "fnlla_php" => $frameworkVersion,
                "fnlla_web" => $uiVersion,
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
                "vendored_fnlla_web" => $vendoredRuntimeReady ? "ok" : "missing",
                "framework_update_ui" => config("framework_update.ui_enabled", false) ? "enabled" : "disabled",
                "auto_detected_source" => $sourceAvailable ? "available" : "not_detected",
            ],
            "readiness" => [
                "version_contract" => $versionContractReady ? "ready" : "attention",
                "vendored_runtime" => $vendoredRuntimeReady ? "ready" : "attention",
                "storage" => $storageReady ? "ready" : "attention",
                "release_channel" => $releaseReadiness,
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
                "cache_path" => (string) ($cachedRelease["cache_path"] ?? storage_path("framework/updates/fnlla-php")),
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
                "maintenance" => route("maintenance.home"),
                "health" => route("health"),
                "api_health" => route("api.health"),
                "project_launch" => route("project.launch"),
                "contact" => route("contact"),
                "framework_updates" => route("maintenance.framework_update"),
            ],
        ];
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
}
