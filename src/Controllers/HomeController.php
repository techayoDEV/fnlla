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
use Fnlla\Php\Support\FrameworkReleaseChannel;
use Fnlla\Php\Support\FrameworkUpdater;
use Fnlla\Php\Support\VersionManifest;

final class HomeController extends Controller
{
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
                "text" => "Confirms whether VERSION, MANIFEST.json and the built-in runtime version still match the maintained repository contract.",
            ],
            [
                "label" => "Runtime assets",
                "status" => (string) ($health["readiness"]["vendored_runtime"] ?? "unknown"),
                "text" => "Shows whether the project still ships the local built-in runtime files expected by the application shell.",
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
