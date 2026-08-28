<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA SUPPORT SOURCE
File: src\Support\ProjectAcceptanceReportBuilder.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

Purpose:
- Builds a machine-readable acceptance smoke report for FNLLA project bases.
*/

namespace Fnlla\Php\Support;

final class ProjectAcceptanceReportBuilder
{
    public function build(): array
    {
        $checks = array_merge(
            $this->requiredFileChecks(),
            $this->storageChecks(),
            $this->routeChecks()
        );
        $failures = count(array_filter($checks, static fn (array $check): bool => ($check["status"] ?? null) === "fail"));
        $warnings = count(array_filter($checks, static fn (array $check): bool => ($check["status"] ?? null) === "warn"));

        return [
            "schema" => "fnlla.project_acceptance.v1",
            "generated_at_utc" => gmdate(DATE_ATOM),
            "environment" => app_environment(),
            "project_root" => base_path(),
            "framework_version" => $this->readFirstLine(base_path("VERSION")),
            "exported_project" => is_file(base_path(".fnlla/framework-lock.json")),
            "ok" => $failures === 0,
            "summary" => [
                "checks" => count($checks),
                "failures" => $failures,
                "warnings" => $warnings,
            ],
            "checks" => $checks,
        ];
    }

    private function requiredFileChecks(): array
    {
        $required = [
            "root.version" => "VERSION",
            "root.manifest" => "MANIFEST.json",
            "root.launcher" => "fnlla",
            "bootstrap.common" => "bootstrap/common.php",
            "bootstrap.router" => "bootstrap/router.php",
            "public.index" => "public/index.php",
            "runtime.version" => "public/vendor/fnlla-runtime/VERSION",
            "runtime.css" => "public/vendor/fnlla-runtime/assets/css/fnlla-runtime.css",
            "runtime.js" => "public/vendor/fnlla-runtime/assets/js/fnlla-runtime.js",
            "routes.web" => "routes/web.php",
            "routes.maintenance" => "routes/maintenance.php",
            "tests.smoke" => "tests/BootstrapAutoloadTest.php",
            "scripts.test" => "scripts/test.php",
            "scripts.lint" => "scripts/lint.php",
        ];
        $checks = [];

        foreach ($required as $id => $relativePath) {
            $checks[] = $this->check(
                $id,
                is_file(base_path($relativePath)),
                $relativePath . " present",
                $relativePath . " is required for a commercial project base exported by make:project."
            );
        }

        $checks[] = $this->check(
            "export.framework_lock",
            is_file(base_path(".fnlla/framework-lock.json")),
            ".fnlla/framework-lock.json present",
            "Maintainer repository runs are allowed to warn here; exported projects should keep the framework lock for update readiness.",
            is_file(base_path(".fnlla/framework-lock.json")) ? "pass" : "warn"
        );

        return $checks;
    }

    private function storageChecks(): array
    {
        $directories = [
            "storage.root" => "storage",
            "storage.cache" => "storage/framework/cache",
            "storage.sessions" => "storage/framework/sessions",
            "storage.queue" => "storage/framework/queue",
            "storage.logs" => "storage/logs",
        ];
        $checks = [];

        foreach ($directories as $id => $relativePath) {
            $path = base_path($relativePath);
            $checks[] = $this->check(
                $id,
                is_dir($path) && is_writable($path),
                $relativePath . " writable",
                $relativePath . " must exist and be writable by the PHP runtime."
            );
        }

        return $checks;
    }

    private function routeChecks(): array
    {
        $probe = new ApplicationProbe();
        $requests = [
            "http.home" => [
                "uri" => "/",
                "expected_statuses" => [200, 302],
                "server" => ["HTTP_ACCEPT" => "text/html"],
            ],
            "http.api_health" => [
                "uri" => "/api/health",
                "expected_statuses" => [200, 503],
                "server" => ["HTTP_ACCEPT" => "application/json"],
            ],
            "http.maintenance" => [
                "uri" => "/maintenance",
                "expected_statuses" => [200, 302],
                "server" => ["HTTP_ACCEPT" => "text/html"],
            ],
        ];
        $checks = [];

        foreach ($probe->measure($requests, 1) as $id => $row) {
            $checks[] = [
                "id" => $id,
                "status" => ($row["ok"] ?? false) ? "pass" : "fail",
                "label" => "Probe " . (string) ($row["uri"] ?? $id),
                "detail" => isset($row["error"])
                    ? (string) $row["error"]
                    : "Status " . implode(",", array_map("strval", (array) ($row["statuses"] ?? []))),
                "data" => $row,
            ];
        }

        return $checks;
    }

    private function check(string $id, bool $passes, string $label, string $detail, ?string $status = null): array
    {
        return [
            "id" => $id,
            "status" => $status ?? ($passes ? "pass" : "fail"),
            "label" => $label,
            "detail" => $passes ? $label : $detail,
        ];
    }

    private function readFirstLine(string $path): ?string
    {
        if (!is_file($path)) {
            return null;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES);
        $firstLine = is_array($lines) ? trim((string) ($lines[0] ?? "")) : "";

        return $firstLine !== "" ? $firstLine : null;
    }
}
