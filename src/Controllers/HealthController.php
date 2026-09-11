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

final class HealthController extends Controller
{
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
        ], 200, "layouts/developer");
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


    private function notFoundResponse(): Response
    {
        return $this->view("pages/not-found", [
            "pageTitle" => "Not Found",
        ], 404);
    }

}
