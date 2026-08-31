<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA SUPPORT SOURCE
File: src\Support\DeveloperOperationsReport.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

Purpose:
- Builds the privacy-light operational dashboard payload for developer panels.
*/

namespace Fnlla\Php\Support;

use Fnlla\Php\Ai\FionnRuntimeBridge;

final class DeveloperOperationsReport
{
    use DeveloperMetricsReportHelpers;

    public function build(): array
    {
        $metrics = $this->readMetrics();
        $backupBuilder = new BackupPlanBuilder();
        $backupPlan = $backupBuilder->build();
        $backupVerification = $backupBuilder->verify($backupPlan);
        $securityAudit = (new SecurityAuditReport())->build();
        $acceptance = (new ProjectAcceptanceReportBuilder())->build();

        return [
            "schema" => "fnlla.developer_operations.v1",
            "generated_at_utc" => gmdate(DATE_ATOM),
            "privacy" => [
                "mode" => "privacy-light",
                "raw_ip_addresses" => false,
                "raw_user_agents" => false,
                "referrers" => "host-only",
                "analytics_requires_consent" => true,
            ],
            "analytics" => $this->analytics($metrics),
            "performance" => $this->performanceProbes(),
            "forms" => $this->formInbox(),
            "audit_log" => [
                "items" => developer_activity()->recent(12),
            ],
            "release_readiness" => $this->releaseReadiness($securityAudit, $backupVerification, $acceptance),
            "integrations" => $this->integrations(),
            "heatmaps" => [
                "status" => (bool) config("integrations.heatmaps.enabled", false) ? "configured" : "disabled",
                "mode" => "opt-in adapter",
                "consent_event" => "fnlla:analytics-consent-granted",
                "core_recorder" => false,
                "provider" => (string) config("integrations.heatmaps.provider", ""),
                "notes" => "Heatmaps stay outside the framework core and should only load after analytics consent.",
            ],
        ];
    }

    private function analytics(array $metrics): array
    {
        $routeCounts = (array) ($metrics["page_route_counts"] ?? $metrics["route_counts"] ?? []);
        $referrerCounts = (array) ($metrics["referrer_counts"] ?? []);
        $consentEvents = (int) ($metrics["consent_events_total"] ?? 0);
        $analyticsConsentEvents = (int) ($metrics["consent_analytics_allowed"] ?? 0);

        return [
            "enabled" => (bool) config("observability.metrics.enabled", true),
            "page_views" => (int) ($metrics["page_views"] ?? 0),
            "total_requests" => (int) ($metrics["total_requests"] ?? 0),
            "average_response_ms" => $this->averageDuration($metrics),
            "top_routes" => $this->topMap($routeCounts, 5, false),
            "referrers" => $this->topMap($referrerCounts, 5, false),
            "status_counts" => $this->topMap((array) ($metrics["status_counts"] ?? []), 5, false),
            "errors" => $this->errorSummary(),
            "consent" => [
                "frontend_storage_key" => "fnlla_cookie_consent_v1",
                "ready_event" => "fnlla:cookie-consent-ready",
                "analytics_event" => "fnlla:analytics-consent-granted",
                "marketing_event" => "fnlla:marketing-consent-granted",
                "backend_rate" => $consentEvents > 0 ? round(($analyticsConsentEvents / $consentEvents) * 100, 2) : 0.0,
                "events" => $consentEvents,
                "counts" => $this->topMap((array) ($metrics["consent_counts"] ?? []), 5, false),
                "note" => "The framework exposes consent events; projects can record consent aggregates without storing raw IP addresses.",
            ],
        ];
    }

    private function performanceProbes(): array
    {
        $thresholdMs = max(1, (int) config("observability.slow_route_threshold_ms", 750));
        $rows = (new ApplicationProbe())->measure([
            "GET /" => [
                "uri" => "/",
                "expected_statuses" => [200, 302, 503],
                "server" => ["HTTP_ACCEPT" => "text/html"],
            ],
            "GET /api/health" => [
                "uri" => "/api/health",
                "expected_statuses" => [200, 503],
                "server" => ["HTTP_ACCEPT" => "application/json"],
            ],
        ], 1);

        foreach ($rows as $name => $row) {
            $rows[$name]["slow"] = isset($row["p95_ms"]) && (float) $row["p95_ms"] > $thresholdMs;
            $rows[$name]["threshold_ms"] = $thresholdMs;
        }

        return [
            "threshold_ms" => $thresholdMs,
            "probes" => $rows,
        ];
    }

    private function formInbox(): array
    {
        $items = $this->readJsonLines(storage_path("framework/developer/form-submissions.jsonl"), 8);
        $mailLogDirectory = storage_path((string) config("mail.log_path", "mail"));

        return [
            "schema" => "fnlla.form_inbox_summary.v1",
            "adapter_path" => "storage/framework/developer/form-submissions.jsonl",
            "recent_submissions" => $items,
            "recent_count" => count($items),
            "validation_issues" => $this->countLogNeedles(["validation", "invalid form", "form still needs"]),
            "failed_mail_delivery" => $this->countLogNeedles(["mail delivery failed", "native mail delivery failed", "http mail transport failed"]),
            "mail_driver" => (string) config("mail.default", "log"),
            "mail_log_ready" => is_dir($mailLogDirectory) || (string) config("mail.default", "log") !== "log",
        ];
    }

    private function releaseReadiness(array $securityAudit, array $backupVerification, array $acceptance): array
    {
        $securitySummary = (array) ($securityAudit["summary"] ?? []);
        $acceptanceSummary = (array) ($acceptance["summary"] ?? []);

        return [
            "security_audit" => [
                "failures" => (int) ($securitySummary["failures"] ?? 0),
                "warnings" => (int) ($securitySummary["warnings"] ?? 0),
                "passed" => (int) ($securitySummary["passed"] ?? 0),
            ],
            "backup_restore" => [
                "ok" => (bool) ($backupVerification["ok"] ?? false),
                "failures" => (int) ($backupVerification["failures"] ?? 0),
            ],
            "framework_drift" => $this->frameworkDrift(),
            "cache" => [
                "cache_writable" => is_dir(storage_path("framework/cache")) && is_writable(storage_path("framework/cache")),
                "sessions_writable" => is_dir(storage_path("framework/sessions")) && is_writable(storage_path("framework/sessions")),
                "queue_writable" => is_dir(storage_path("framework/queue")) && is_writable(storage_path("framework/queue")),
            ],
            "acceptance" => [
                "ok" => (bool) ($acceptance["ok"] ?? false),
                "failures" => (int) ($acceptanceSummary["failures"] ?? 0),
                "warnings" => (int) ($acceptanceSummary["warnings"] ?? 0),
            ],
        ];
    }

    private function integrations(): array
    {
        $fionn = (new FionnRuntimeBridge())->status();
        $techayoRemoteControl = (new TechAyoRemoteControlPlugin())->manifest();

        return [
            [
                "name" => "GA4",
                "status" => $this->integrationStatus((bool) config("integrations.ga4.enabled", false), (string) config("integrations.ga4.measurement_id", "")),
                "consent_event" => "fnlla:analytics-consent-granted",
                "external_calls" => (bool) config("integrations.ga4.enabled", false),
                "settings" => [
                    "measurement_id" => $this->safeLabel((string) config("integrations.ga4.measurement_id", "")),
                ],
            ],
            [
                "name" => "Microsoft Clarity",
                "status" => $this->integrationStatus((bool) config("integrations.clarity.enabled", false), (string) config("integrations.clarity.project_id", "")),
                "consent_event" => "fnlla:analytics-consent-granted",
                "external_calls" => (bool) config("integrations.clarity.enabled", false),
                "settings" => [
                    "project_id" => $this->safeLabel((string) config("integrations.clarity.project_id", "")),
                ],
            ],
            [
                "name" => "Sentry",
                "status" => $this->integrationStatus((bool) config("integrations.sentry.enabled", false), (string) config("integrations.sentry.dsn", "")),
                "consent_event" => "server-side policy",
                "external_calls" => (bool) config("integrations.sentry.enabled", false),
                "settings" => [
                    "dsn_configured" => trim((string) config("integrations.sentry.dsn", "")) !== "",
                    "environment" => $this->safeLabel((string) config("integrations.sentry.environment", app_environment())),
                ],
            ],
            [
                "name" => "Fionn",
                "status" => (string) ($fionn["integration_state"] ?? "available_opt_in"),
                "consent_event" => "server-side policy",
                "external_calls" => (bool) ($fionn["external_calls"] ?? false),
            ],
            [
                "name" => "Generic API hooks",
                "status" => $this->integrationStatus((bool) config("integrations.api_hooks.enabled", false), (string) config("integrations.api_hooks.endpoint", "")),
                "consent_event" => "fnlla:cookies-updated",
                "external_calls" => (bool) config("integrations.api_hooks.enabled", false),
                "settings" => [
                    "endpoint" => $this->redactUrl((string) config("integrations.api_hooks.endpoint", "")),
                ],
            ],
            [
                "name" => "TechAyo Remote Control",
                "status" => (string) ($techayoRemoteControl["status"] ?? "disabled"),
                "consent_event" => "server-side policy",
                "external_calls" => (bool) ($techayoRemoteControl["enabled"] ?? false),
                "contract" => $techayoRemoteControl,
            ],
        ];
    }

    private function integrationStatus(bool $enabled, string $requiredValue): string
    {
        if (!$enabled) {
            return "disabled";
        }

        return trim($requiredValue) !== "" ? "configured" : "needs configuration";
    }

    private function redactUrl(string $url): string
    {
        if (trim($url) === "") {
            return "";
        }

        $parts = parse_url($url);

        if (!is_array($parts)) {
            return "";
        }

        $scheme = (string) ($parts["scheme"] ?? "");
        $host = (string) ($parts["host"] ?? "");
        $path = (string) ($parts["path"] ?? "");

        return $scheme !== "" && $host !== "" ? $scheme . "://" . $host . $path : "";
    }

    private function errorSummary(): array
    {
        $count = 0;
        $recent = [];

        foreach ($this->recentLogLines() as $line) {
            $decoded = json_decode($line, true);
            $level = strtoupper((string) ($decoded["level"] ?? ""));

            if (!in_array($level, ["ERROR", "CRITICAL", "ALERT", "EMERGENCY"], true)) {
                continue;
            }

            $count++;

            if (count($recent) < 5) {
                $recent[] = [
                    "time" => $this->safeLabel((string) ($decoded["timestamp"] ?? "")),
                    "message" => $this->safeLabel((string) ($decoded["message"] ?? "Application error")),
                ];
            }
        }

        return [
            "count" => $count,
            "recent" => $recent,
        ];
    }

    private function countLogNeedles(array $needles): int
    {
        $count = 0;
        $needles = array_map("strtolower", $needles);

        foreach ($this->recentLogLines() as $line) {
            $haystack = strtolower($line);

            foreach ($needles as $needle) {
                if ($needle !== "" && str_contains($haystack, $needle)) {
                    $count++;
                    break;
                }
            }
        }

        return $count;
    }

    private function recentLogLines(): array
    {
        return RecentFileLines::read(Logger::configuredPath(), 250);
    }

    private function readJsonLines(string $path, int $limit): array
    {
        $items = [];

        foreach (RecentFileLines::read($path, 250) as $line) {
            $decoded = json_decode($line, true);

            if (!is_array($decoded)) {
                continue;
            }

            $items[] = [
                "time" => $this->safeLabel((string) ($decoded["time"] ?? $decoded["created_at"] ?? "")),
                "form" => $this->safeLabel((string) ($decoded["form"] ?? "form")),
                "status" => $this->safeLabel((string) ($decoded["status"] ?? "received")),
            ];

            if (count($items) >= max(1, $limit)) {
                break;
            }
        }

        return $items;
    }

    private function frameworkDrift(): array
    {
        $lockPath = base_path(".fnlla/framework-lock.json");
        $lock = [];

        if (is_file($lockPath)) {
            $decoded = json_decode((string) file_get_contents($lockPath), true);
            $lock = is_array($decoded) ? $decoded : [];
        }

        return [
            "framework_version" => $this->readFirstLine(base_path("VERSION")) ?? "unknown",
            "runtime_version" => $this->readFirstLine(public_path("vendor/fnlla-runtime/VERSION")) ?? "unknown",
            "lock_present" => is_file($lockPath),
            "lock_version" => (string) ($lock["framework_version"] ?? $lock["version"] ?? ""),
        ];
    }

    private function readFirstLine(string $path): ?string
    {
        if (!is_file($path)) {
            return null;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES);
        $line = is_array($lines) ? trim((string) ($lines[0] ?? "")) : "";

        return $line !== "" ? $line : null;
    }

}
