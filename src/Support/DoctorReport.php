<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA SUPPORT SOURCE
File: src\Support\DoctorReport.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

Purpose:
- Builds local environment diagnostics for the `doctor` command.
*/

namespace Fnlla\Php\Support;

final class DoctorReport
{
    public function build(): array
    {
        $checks = [
            $this->check("php_version", version_compare(PHP_VERSION, "8.3.0", ">="), "PHP " . PHP_VERSION, "FNLLA requires PHP 8.3 or newer."),
            $this->check("json_extension", extension_loaded("json"), "JSON extension", "JSON is required for cache, manifests and API responses."),
            $this->check("pdo_extension", extension_loaded("pdo"), "PDO extension", "PDO is required for database access."),
            $this->check("curl_or_streams", function_exists("curl_init") || filter_var(ini_get("allow_url_fopen"), FILTER_VALIDATE_BOOL), "HTTP client transport", "Release checks need cURL or allow_url_fopen."),
            $this->check("storage_writable", $this->writable(storage_path()), "Writable storage", "storage/ must be writable by the PHP process."),
            $this->check("cache_writable", $this->writable(storage_path("framework/cache")), "Writable cache", "storage/framework/cache must be writable."),
            $this->check("sessions_writable", $this->writable(storage_path("framework/sessions")), "Writable sessions", "storage/framework/sessions must be writable."),
            $this->check("logs_writable", $this->writable(storage_path("logs")), "Writable logs", "storage/logs must be writable."),
            $this->check("runtime_contract", is_file(public_path("vendor/fnlla-runtime/VERSION")), "FNLLA runtime present", "Vendored FNLLA runtime metadata should be present."),
            $this->check("manifest_present", is_file(base_path("MANIFEST.json")), "MANIFEST.json present", "Release metadata should be present."),
        ];

        return [
            "schema" => "fnlla.doctor.v1",
            "generated_at_utc" => gmdate(DATE_ATOM),
            "environment" => app_environment(),
            "checks" => $checks,
            "summary" => [
                "ok" => count(array_filter($checks, static fn (array $check): bool => $check["status"] === "ok")),
                "warnings" => count(array_filter($checks, static fn (array $check): bool => $check["status"] === "warning")),
                "failures" => count(array_filter($checks, static fn (array $check): bool => $check["status"] === "fail")),
            ],
        ];
    }

    private function check(string $id, bool $ok, string $label, string $detail): array
    {
        return [
            "id" => $id,
            "status" => $ok ? "ok" : "fail",
            "label" => $label,
            "detail" => $detail,
        ];
    }

    private function writable(string $path): bool
    {
        return is_dir($path) && is_writable($path);
    }
}
