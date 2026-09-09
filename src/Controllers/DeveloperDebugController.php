<?php

declare(strict_types=1);

namespace Fnlla\Php\Controllers;

use Fnlla\Php\Http\Request;
use Fnlla\Php\Http\Response;
use Fnlla\Php\Maintenance\DeveloperAccessManager;
use Fnlla\Php\Maintenance\MaintenanceAccessManager;
use Fnlla\Php\Observability\DebugToolbar;
use Fnlla\Php\Observability\RequestHistory;
use Fnlla\Php\Observability\RuntimeIssueTracker;
use Fnlla\Php\Support\Logger;
use Fnlla\Php\Support\RecentFileLines;
use Fnlla\Php\Support\TechnicalDebtRegistry;
use Fnlla\Php\Support\DeveloperWorkspaceBoard;

final class DeveloperDebugController extends DeveloperPanelController
{
    public function show(Request $request, DeveloperAccessManager $access, MaintenanceAccessManager $maintenance): Response
    {
        if (!$access->can("operations.view")) {
            return Response::text("Forbidden", 403);
        }
        $toolbar = new DebugToolbar();
        $history = new RequestHistory();
        $issues = new RuntimeIssueTracker();
        $historyEntries = $history->entries();

        return $this->renderDeveloperPanel($access, $maintenance, "developer/debug", "Error Monitor", "debug", [
            "debugAvailable" => $toolbar->available(),
            "debugEnabled" => $toolbar->enabled(),
            "canManageDebug" => $access->can("panel.settings.write"),
            "canManageDebt" => $access->can("workspace.write"),
            "historyEnabled" => $history->enabled(),
            "historyEntries" => $historyEntries,
            "debtState" => (new TechnicalDebtRegistry())->state(),
            "debugReport" => $this->debugReport($toolbar, $history, $issues, $historyEntries),
            "runtimeIssues" => $issues->openIssues(20),
        ]);
    }

    public function save(Request $request, DeveloperAccessManager $access): Response
    {
        if (!$access->can("operations.view") || !$access->can("panel.settings.write")) {
            return Response::text("Forbidden", 403);
        }
        if (!(new DebugToolbar())->available() && $request->input("enabled") === "1") {
            return Response::text("Debug toolbar is unavailable in this environment.", 403);
        }
        (new DebugToolbar())->setEnabled($request->input("enabled") === "1", (string) ($access->currentDeveloper()["email"] ?? ""));
        if ((new DebugToolbar())->authorized()) {
            (new RequestHistory())->configure($request->input("history_enabled") === "1", $request->input("clear_history") === "1");
        } elseif ($request->input("history_enabled") === "1") {
            return Response::text("Request history is unavailable in this environment.", 403);
        }
        return $this->redirect(route("developer.panel.debug"));
    }

    public function live(Request $request, DeveloperAccessManager $access): Response
    {
        if (!$access->can("operations.view")) {
            return Response::json(["error" => "Forbidden"], 403);
        }

        $toolbar = new DebugToolbar();
        $history = new RequestHistory();
        $issues = new RuntimeIssueTracker();
        $entries = $history->entries();

        return Response::json([
            "schema" => "fnlla.debug_live.v1",
            "report" => $this->debugReport($toolbar, $history, $issues, $entries),
            "history" => array_slice($entries, 0, 20),
            "runtime_issues" => $issues->summary(),
        ])->withHeader("Cache-Control", "private, no-store");
    }

    public function promoteRuntimeIssue(Request $request, DeveloperAccessManager $access): Response
    {
        if (!$access->can("operations.view") || !$access->can("workspace.write")) {
            return Response::text("Forbidden", 403);
        }

        $issueId = trim((string) $request->input("runtime_issue_id", ""));
        $tracker = new RuntimeIssueTracker();
        $issue = $issueId !== "" ? $tracker->find($issueId) : null;

        if ($issue === null) {
            flash_set("status", [
                "variant" => "warning",
                "title" => "Runtime issue was not promoted",
                "text" => "The selected runtime issue is no longer available.",
                "toast" => true,
            ]);
            regenerate_csrf_token();

            return $this->redirect(route("developer.panel.debug") . "#runtime-issues");
        }

        $registry = new TechnicalDebtRegistry();
        $revision = (int) $request->input("revision", -1);
        $debtItemId = TechnicalDebtRegistry::runtimeIssueDebtId((string) ($issue["fingerprint"] ?? $issueId));
        $createWorkspaceTask = (string) $request->input("runtime_issue_create_workspace_task", "0") === "1";
        $workspaceTaskId = "";

        try {
            $registry->promoteRuntimeIssue($issue, $revision, (string) ($access->currentDeveloper()["email"] ?? ""));
            if ($createWorkspaceTask) {
                $workspaceTask = (new DeveloperWorkspaceBoard())->createRuntimeIssueTask($issue, $debtItemId, $access->currentDeveloper());
                $workspaceTaskId = (string) ($workspaceTask["id"] ?? "");
            }
            $tracker->markLinked($issueId, $debtItemId, $workspaceTaskId);
        } catch (\InvalidArgumentException $exception) {
            return Response::text($exception->getMessage(), 422);
        } catch (\RuntimeException $exception) {
            return Response::text($exception->getMessage(), 409);
        }

        developer_activity()->record(
            "runtime_issue",
            $createWorkspaceTask ? "Runtime issue promoted to technical debt and Kanban" : "Runtime issue promoted to technical debt",
            $createWorkspaceTask ? "A recurring runtime issue candidate was linked to technical debt and added to the shared Kanban backlog." : "A recurring runtime issue candidate was linked to the technical debt register.",
            $access->currentDeveloper()
        );

        flash_set("status", [
            "variant" => "success",
            "title" => "Runtime issue promoted",
            "text" => $createWorkspaceTask ? "The issue candidate is now tracked in technical debt and the Kanban backlog." : "The issue candidate is now tracked in the technical debt register.",
            "toast" => true,
        ]);
        regenerate_csrf_token();

        return $this->redirect(route("developer.panel.technical_debt"));
    }

    private function debugReport(DebugToolbar $toolbar, RequestHistory $history, RuntimeIssueTracker $issues, array $historyEntries): array
    {
        $metrics = $this->readMetrics();
        $totalRequests = (int) ($metrics["total_requests"] ?? 0);
        $statusCounts = (array) ($metrics["status_counts"] ?? []);
        $errorRequests = 0;

        foreach ($statusCounts as $status => $count) {
            if (is_numeric($status) && (int) $status >= 400) {
                $errorRequests += max(0, (int) $count);
            }
        }

        return [
            "schema" => "fnlla.debug_report.v1",
            "generated_at_utc" => gmdate(DATE_ATOM),
            "environment" => [
                "app_env" => app_environment(),
                "app_debug" => app_debug(),
                "toolbar_available" => $toolbar->available(),
                "toolbar_enabled" => $toolbar->enabled(),
                "history_enabled" => $history->enabled(),
                "runtime_issues_enabled" => $issues->enabled(),
            ],
            "metrics" => [
                "enabled" => (bool) config("observability.metrics.enabled", true),
                "total_requests" => $totalRequests,
                "average_response_ms" => $this->averageDuration($metrics),
                "max_response_ms" => (float) ($metrics["max_duration_ms"] ?? 0.0),
                "error_requests" => $errorRequests,
                "error_rate" => $totalRequests > 0 ? round(($errorRequests / $totalRequests) * 100, 2) : 0.0,
                "last_request" => $this->lastRequestSummary((array) ($metrics["last_request"] ?? [])),
                "status_counts" => $this->topMap($statusCounts, 8),
                "method_counts" => $this->topMap((array) ($metrics["method_counts"] ?? []), 8),
                "slow_routes" => $this->topMap((array) ($metrics["slow_route_counts"] ?? []), 8),
            ],
            "history" => [
                "entries" => count($historyEntries),
                "latest" => $historyEntries[0] ?? null,
            ],
            "runtime_issues" => $issues->summary(),
            "logs" => [
                "path" => $this->relativeStoragePath(Logger::configuredPath()),
                "recent_errors" => $this->recentErrorLog(5),
            ],
            "storage" => [
                "history_path" => $this->relativeStoragePath((string) config("debug.history.path", storage_path("framework/developer/request-history.json"))),
                "runtime_issues_path" => $this->relativeStoragePath((string) config("debug.runtime_issues.path", storage_path("framework/developer/runtime-issues.json"))),
            ],
        ];
    }

    private function readMetrics(): array
    {
        $path = storage_path(ltrim((string) config("observability.metrics.path", "framework/metrics.json"), "\\/"));

        if (!is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    private function lastRequestSummary(array $request): array
    {
        if ($request === []) {
            return [];
        }

        return [
            "method" => $this->safeLabel((string) ($request["method"] ?? "")),
            "route" => $this->safeLabel((string) ($request["route"] ?? "")),
            "status" => max(0, (int) ($request["status"] ?? 0)),
            "duration_ms" => round(max(0.0, (float) ($request["duration_ms"] ?? 0.0)), 3),
            "recorded_at_utc" => $this->safeLabel((string) ($request["recorded_at_utc"] ?? "")),
        ];
    }

    private function averageDuration(array $metrics): float
    {
        return round((float) ($metrics["total_duration_ms"] ?? 0.0) / max(1, (int) ($metrics["total_requests"] ?? 0)), 3);
    }

    private function topMap(array $map, int $limit): array
    {
        $normalized = [];

        foreach ($map as $label => $count) {
            $label = trim((string) $label);

            if ($label === "") {
                continue;
            }

            $normalized[$this->safeLabel($label)] = max(0, (int) $count);
        }

        arsort($normalized);
        $items = [];

        foreach (array_slice($normalized, 0, max(1, $limit), true) as $label => $count) {
            $items[] = ["label" => $label, "count" => $count];
        }

        return $items;
    }

    private function recentErrorLog(int $limit): array
    {
        $items = [];

        foreach (RecentFileLines::read(Logger::configuredPath(), 250) as $line) {
            $decoded = json_decode($line, true);

            if (!is_array($decoded) || !in_array(strtoupper((string) ($decoded["level"] ?? "")), ["ERROR", "CRITICAL", "ALERT", "EMERGENCY"], true)) {
                continue;
            }

            $context = is_array($decoded["context"] ?? null) ? (array) $decoded["context"] : [];
            $items[] = [
                "time" => $this->safeLabel((string) ($decoded["timestamp"] ?? "")),
                "level" => $this->safeLabel((string) ($decoded["level"] ?? "ERROR")),
                "message" => $this->safeLabel((string) ($decoded["message"] ?? "Application error")),
                "request_id" => $this->safeLabel((string) ($context["request_id"] ?? "")),
            ];

            if (count($items) >= max(1, $limit)) {
                break;
            }
        }

        return $items;
    }

    private function relativeStoragePath(string $path): string
    {
        $storage = rtrim(storage_path(), "\\/") . DIRECTORY_SEPARATOR;
        $normalizedPath = str_replace("\\", DIRECTORY_SEPARATOR, $path);
        $normalizedStorage = str_replace("\\", DIRECTORY_SEPARATOR, $storage);

        if (str_starts_with($normalizedPath, $normalizedStorage)) {
            return "storage/" . str_replace("\\", "/", substr($normalizedPath, strlen($normalizedStorage)));
        }

        return str_replace("\\", "/", $path);
    }

    private function safeLabel(string $value): string
    {
        $value = trim((string) preg_replace('/\s+/', ' ', strip_tags($value)));

        return substr($value, 0, 180);
    }
}
