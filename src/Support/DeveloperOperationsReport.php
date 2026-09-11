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

final class DeveloperOperationsReport
{
    use DeveloperMetricsReportHelpers;

    public function build(): array
    {
        $metrics = $this->readMetrics();
        $telemetryPolicy = DeveloperPanelPolicy::telemetryPolicy();
        $backupBuilder = new BackupPlanBuilder();
        $backupPlan = $backupBuilder->build();
        $backupVerification = $backupBuilder->verify($backupPlan);
        $securityAudit = (new SecurityAuditReport())->build();
        $acceptance = (new ProjectAcceptanceReportBuilder())->build();

        return [
            "schema" => "fnlla.developer_operations.v1",
            "generated_at_utc" => gmdate(DATE_ATOM),
            "privacy" => [
                "mode" => (string) $telemetryPolicy["mode"],
                "profile" => (string) $telemetryPolicy["profile"],
                "regulated" => (bool) $telemetryPolicy["regulated"],
                "raw_ip_addresses" => false,
                "raw_user_agents" => false,
                "referrers" => "host-only",
                "analytics_requires_consent" => true,
                "form_fields_recorded" => false,
                "query_strings_tracked" => (bool) $telemetryPolicy["query_strings_tracked"],
                "retention_days" => (int) $telemetryPolicy["retention_days"],
                "excluded_paths" => (array) $telemetryPolicy["excluded_paths"],
            ],
            "analytics" => $this->analytics($metrics, $telemetryPolicy),
            "performance" => $this->performanceProbes(),
            "forms" => $this->formInbox(),
            "audit_log" => [
                "items" => developer_activity()->recent(12),
            ],
            "release_readiness" => $this->releaseReadiness($securityAudit, $backupVerification, $acceptance),
            "integrations" => (new DeveloperIntegrationRegistry())->all(),
            "heatmaps" => [
                "status" => ((bool) config("observability.heatmap.enabled", true) && (bool) $telemetryPolicy["heatmap_allowed"]) ? "active" : "disabled",
                "mode" => "first-party aggregate heatmap",
                "consent_event" => "fnlla:analytics-consent-granted",
                "core_recorder" => true,
                "provider" => "fnlla",
                "regulated_allowed" => (bool) $telemetryPolicy["heatmap_allowed"],
                "notes" => (bool) $telemetryPolicy["heatmap_allowed"]
                    ? "FNLLA records aggregate click zones and scroll depth locally after analytics consent."
                    : "Regulated telemetry policy keeps heatmap disabled until the project explicitly opts in.",
            ],
        ];
    }

    private function analytics(array $metrics, array $telemetryPolicy): array
    {
        $routeCounts = (array) ($metrics["page_route_counts"] ?? $metrics["route_counts"] ?? []);
        $referrerCounts = (array) ($metrics["referrer_counts"] ?? []);
        $consentEvents = (int) ($metrics["consent_events_total"] ?? 0);
        $analyticsConsentEvents = (int) ($metrics["consent_analytics_allowed"] ?? 0);

        return [
            "enabled" => (bool) config("observability.metrics.enabled", true) && (bool) config("observability.analytics.enabled", true),
            "policy_profile" => (string) $telemetryPolicy["profile"],
            "regulated" => (bool) $telemetryPolicy["regulated"],
            "query_strings_tracked" => (bool) $telemetryPolicy["query_strings_tracked"],
            "retention_days" => (int) $telemetryPolicy["retention_days"],
            "excluded_paths" => (array) $telemetryPolicy["excluded_paths"],
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
