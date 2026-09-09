<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA OBSERVABILITY SOURCE
File: src\Observability\RuntimeIssueTracker.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

Purpose:
- Stores first-party runtime issue fingerprints for developer triage.
*/

namespace Fnlla\Php\Observability;

use Fnlla\Php\Http\HttpException;
use Fnlla\Php\Http\Request;
use Fnlla\Php\Support\LockedJsonStore;
use Throwable;

final class RuntimeIssueTracker
{
    public function enabled(): bool
    {
        return (bool) config("debug.runtime_issues.enabled", true);
    }

    public function record(Throwable $exception, Request $request): void
    {
        if (!$this->enabled()) {
            return;
        }

        $status = $exception instanceof HttpException ? $exception->statusCode() : 500;

        if ($status < 500) {
            return;
        }

        $route = trim((string) ($_SERVER["FNLLA_ROUTE_NAME"] ?? ""));
        $fingerprint = $this->fingerprint($exception, $route, $status);
        $issue = [
            "id" => $fingerprint,
            "fingerprint" => $fingerprint,
            "title" => $this->title($exception, $route, $status),
            "status" => "open",
            "severity" => $this->severity($status, 1),
            "http_status" => $status,
            "exception_type" => $this->safeClass($exception::class),
            "file" => $this->relativeFile($exception->getFile()),
            "line" => max(0, $exception->getLine()),
            "route" => $route !== "" ? $this->safeLabel($route, 120) : "unmatched",
            "method" => $this->safeMethod($request->method()),
            "first_seen_at" => gmdate(DATE_ATOM),
            "last_seen_at" => gmdate(DATE_ATOM),
            "last_request_id" => $request->requestId(),
            "occurrences" => 1,
            "debt_item_id" => "",
            "workspace_task_id" => "",
        ];

        try {
            $this->store()->update(function (array $state) use ($fingerprint, $issue): array {
                $state += ["schema" => "fnlla.runtime_issue_tracker.v1", "issues" => []];
                $existing = is_array($state["issues"][$fingerprint] ?? null) ? (array) $state["issues"][$fingerprint] : [];
                $occurrences = max(0, (int) ($existing["occurrences"] ?? 0)) + 1;
                $state["issues"][$fingerprint] = array_merge($issue, [
                    "first_seen_at" => (string) ($existing["first_seen_at"] ?? $issue["first_seen_at"]),
                    "occurrences" => $occurrences,
                    "severity" => $this->severity($issue["http_status"], $occurrences),
                    "status" => (string) ($existing["status"] ?? "open"),
                    "debt_item_id" => (string) ($existing["debt_item_id"] ?? ""),
                    "workspace_task_id" => (string) ($existing["workspace_task_id"] ?? ""),
                ]);
                $state["updated_at_utc"] = gmdate(DATE_ATOM);
                $state["issues"] = $this->trimIssues((array) $state["issues"]);

                return $state;
            });
        } catch (Throwable) {
            // Runtime issue tracking is diagnostic only and must not affect error rendering.
        }
    }

    public function issues(): array
    {
        $state = $this->store()->read();
        $issues = array_values(array_filter((array) ($state["issues"] ?? []), "is_array"));
        usort($issues, static function (array $left, array $right): int {
            $leftTime = strtotime((string) ($left["last_seen_at"] ?? "")) ?: 0;
            $rightTime = strtotime((string) ($right["last_seen_at"] ?? "")) ?: 0;

            return $rightTime <=> $leftTime;
        });

        return $issues;
    }

    public function openIssues(int $limit = 20): array
    {
        $issues = array_values(array_filter($this->issues(), static fn (array $issue): bool =>
            (string) ($issue["status"] ?? "open") !== "linked"
        ));

        return array_slice($issues, 0, max(1, $limit));
    }

    public function find(string $id): ?array
    {
        $state = $this->store()->read();
        $issue = $state["issues"][$id] ?? null;

        return is_array($issue) ? $issue : null;
    }

    public function markLinked(string $id, string $debtItemId, string $workspaceTaskId = ""): void
    {
        $this->store()->update(function (array $state) use ($id, $debtItemId, $workspaceTaskId): array {
            if (isset($state["issues"][$id]) && is_array($state["issues"][$id])) {
                $state["issues"][$id]["status"] = "linked";
                $state["issues"][$id]["debt_item_id"] = substr($debtItemId, 0, 128);
                $state["issues"][$id]["workspace_task_id"] = substr($workspaceTaskId, 0, 64);
                $state["issues"][$id]["linked_at"] = gmdate(DATE_ATOM);
            }

            return $state;
        });
    }

    public function summary(): array
    {
        $issues = $this->issues();
        $open = array_values(array_filter($issues, static fn (array $issue): bool =>
            (string) ($issue["status"] ?? "open") !== "linked"
        ));
        $occurrences = array_sum(array_map(static fn (array $issue): int => max(0, (int) ($issue["occurrences"] ?? 0)), $open));
        $oldestOpen = null;

        foreach ($open as $issue) {
            $timestamp = strtotime((string) ($issue["first_seen_at"] ?? "")) ?: 0;

            if ($timestamp > 0 && ($oldestOpen === null || $timestamp < $oldestOpen)) {
                $oldestOpen = $timestamp;
            }
        }

        return [
            "monitor_name" => "FNLLA Error Monitor",
            "enabled" => $this->enabled(),
            "open" => count($open),
            "linked" => count($issues) - count($open),
            "occurrences" => $occurrences,
            "oldest_open_hours" => $oldestOpen !== null ? round(max(0, time() - $oldestOpen) / 3600, 2) : 0.0,
            "retention" => "latest " . max(1, min(500, (int) config("debug.runtime_issues.max_items", 100))) . " fingerprints",
            "by_route" => $this->topIssueMap($open, "route", 5),
            "by_exception" => $this->topIssueMap($open, "exception_type", 5),
            "by_severity" => $this->topIssueMap($open, "severity", 5),
            "latest" => $open[0] ?? null,
            "issues" => array_slice($open, 0, 10),
        ];
    }

    private function fingerprint(Throwable $exception, string $route, int $status): string
    {
        return hash("sha256", implode("|", [
            $exception::class,
            $this->relativeFile($exception->getFile()),
            (string) $exception->getLine(),
            $route,
            (string) $status,
        ]));
    }

    private function title(Throwable $exception, string $route, int $status): string
    {
        $class = $this->safeClass($exception::class);
        $route = $route !== "" ? $route : "unmatched route";

        return substr("Runtime {$status}: {$class} in {$route}", 0, 160);
    }

    private function severity(int $status, int $occurrences): string
    {
        if ($status >= 500 && $occurrences >= 5) {
            return "critical";
        }

        if ($status >= 500) {
            return "high";
        }

        return $occurrences >= 3 ? "medium" : "low";
    }

    private function topIssueMap(array $issues, string $field, int $limit): array
    {
        $counts = [];

        foreach ($issues as $issue) {
            $label = trim((string) ($issue[$field] ?? ""));
            $label = $label !== "" ? $label : "unknown";
            $counts[$label] = ($counts[$label] ?? 0) + max(1, (int) ($issue["occurrences"] ?? 1));
        }

        arsort($counts);
        $rows = [];

        foreach (array_slice($counts, 0, max(1, $limit), true) as $label => $count) {
            $rows[] = [
                "label" => $this->safeLabel((string) $label, 120),
                "count" => (int) $count,
            ];
        }

        return $rows;
    }

    private function relativeFile(string $file): string
    {
        $base = rtrim(base_path(), "\\/") . DIRECTORY_SEPARATOR;
        $normalizedFile = str_replace("\\", DIRECTORY_SEPARATOR, $file);
        $normalizedBase = str_replace("\\", DIRECTORY_SEPARATOR, $base);

        if (str_starts_with($normalizedFile, $normalizedBase)) {
            return str_replace("\\", "/", substr($normalizedFile, strlen($normalizedBase)));
        }

        return basename($file);
    }

    private function safeClass(string $class): string
    {
        $parts = explode("\\", $class);

        return $this->safeLabel((string) end($parts), 80);
    }

    private function safeMethod(string $method): string
    {
        $method = strtoupper($method);

        return in_array($method, ["GET", "HEAD", "POST", "PUT", "PATCH", "DELETE", "OPTIONS"], true) ? $method : "OTHER";
    }

    private function safeLabel(string $value, int $maxLength): string
    {
        $value = trim((string) preg_replace('/\s+/', ' ', strip_tags($value)));
        $value = preg_replace('/[^A-Za-z0-9_:\\\\\/\\.\\- ]/', "", $value) ?? "";

        return substr($value, 0, max(1, $maxLength));
    }

    private function trimIssues(array $issues): array
    {
        uasort($issues, static function (array $left, array $right): int {
            $leftTime = strtotime((string) ($left["last_seen_at"] ?? "")) ?: 0;
            $rightTime = strtotime((string) ($right["last_seen_at"] ?? "")) ?: 0;

            return $rightTime <=> $leftTime;
        });

        return array_slice($issues, 0, max(1, min(500, (int) config("debug.runtime_issues.max_items", 100))), true);
    }

    private function store(): LockedJsonStore
    {
        return new LockedJsonStore((string) config("debug.runtime_issues.path", storage_path("framework/developer/runtime-issues.json")));
    }
}
