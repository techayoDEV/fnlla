<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA SUPPORT SOURCE
File: src\Support\BackupPlanBuilder.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

Purpose:
- Builds a redacted backup and restore plan for production FNLLA deployments.
*/

namespace Fnlla\Php\Support;

final class BackupPlanBuilder
{
    public function build(): array
    {
        $connection = (array) config("database.connections.mysql", []);
        $storageRoot = storage_path();

        return [
            "schema" => "fnlla.backup_plan.v1",
            "generated_at_utc" => gmdate(DATE_ATOM),
            "environment" => app_environment(),
            "database" => [
                "driver" => "mysql",
                "host" => (string) ($connection["host"] ?? "127.0.0.1"),
                "port" => (string) ($connection["port"] ?? "3306"),
                "database" => (string) ($connection["database"] ?? ""),
                "username_present" => trim((string) ($connection["username"] ?? "")) !== "",
                "password_redacted" => true,
                "recommended_dump" => "mysqldump --single-transaction --routines --triggers --default-character-set=utf8mb4 <database> > backups/database.sql",
            ],
            "storage" => [
                "root" => $storageRoot,
                "include" => [
                    "storage/database",
                    "storage/app",
                    "storage/uploads",
                ],
                "exclude" => [
                    "storage/framework/cache",
                    "storage/framework/sessions",
                    "storage/framework/queue",
                    "storage/logs",
                ],
            ],
            "application_state" => [
                "include" => [
                    ".env",
                    "MANIFEST.json",
                    "VERSION",
                    "public/vendor/fnlla-runtime/VERSION",
                    ".fnlla/framework-lock.json",
                ],
                "secrets_policy" => ".env must be backed up through a restricted secret store, not committed to Git.",
            ],
            "restore_order" => [
                "Deploy the exact Git tag or audited source archive.",
                "Restore .env from the restricted secret store.",
                "Restore MySQL from the verified dump.",
                "Restore persistent storage includes.",
                "Run php fnlla optimize:warm.",
                "Run php fnlla doctor and php fnlla security:audit --strict in the target environment.",
            ],
            "verification" => [
                "php fnlla doctor",
                "php fnlla security:audit --strict",
                "php scripts/validate-version-manifest.php",
                "php scripts/validate-fnlla-runtime.php",
            ],
        ];
    }
}
