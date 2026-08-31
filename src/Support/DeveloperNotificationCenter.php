<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA SUPPORT SOURCE
File: src\Support\DeveloperNotificationCenter.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

Purpose:
- Builds actionable Developer Panel notifications from framework readiness data.
*/

namespace Fnlla\Php\Support;

final class DeveloperNotificationCenter
{
    public function build(array $developerAccess, array $developerDashboard, array $operationsReport, array $developerControl): array
    {
        $items = [];
        $currentSecurity = (array) ($developerAccess["security"] ?? []);
        $release = (array) ($operationsReport["release_readiness"] ?? []);
        $projectLeadership = (array) ($developerDashboard["project_leadership"] ?? project_leadership("admin"));
        $currentDeveloper = (array) ($developerAccess["current_developer"] ?? []);
        $currentEmail = strtolower(trim((string) ($currentDeveloper["email"] ?? "")));
        $leadEmail = strtolower(trim((string) ($projectLeadership["person_email"] ?? "")));
        $currentCapabilities = (array) ($developerAccess["current_capabilities"] ?? []);

        if (($developerControl["disabled"] ?? false) === true) {
            $items[] = $this->item("service-disabled", "critical", "Public service disabled", "Public routes currently show the developer service-disabled screen.", "Open access & preview");
        }

        if (($projectLeadership["configured"] ?? false) === true && ($projectLeadership["status"] ?? "") === "pending") {
            if ($leadEmail !== "" && $currentEmail !== "" && hash_equals($leadEmail, $currentEmail)) {
                $items[] = $this->item("project-leadership-confirmation", "warning", "Leadership confirmation required", "Confirm or reject the named product and delivery responsibility before it can be displayed publicly.", "Open identity");
            } elseif (in_array("project.identity.write", $currentCapabilities, true)) {
                $items[] = $this->item("project-leadership-pending", "info", "Project leadership pending", "The named person has not confirmed the product or delivery responsibility yet.", "Open identity");
            }
        }

        if (($projectLeadership["configured"] ?? false) === true && ($projectLeadership["status"] ?? "") === "rejected" && in_array("project.identity.write", $currentCapabilities, true)) {
            $items[] = $this->item("project-leadership-rejected", "warning", "Project leadership rejected", "The named person rejected this responsibility. Update the scope or assign the correct person.", "Open identity");
        }

        if (($developerDashboard["framework_lock"] ?? false) !== true) {
            $items[] = $this->item("framework-lock-missing", "warning", "Framework lock missing", "The project should keep a canonical .fnlla/framework-lock.json before production release.", "Open framework updates");
        }

        if (($currentSecurity["totp_enabled"] ?? false) !== true) {
            $items[] = $this->item("developer-totp-disabled", "warning", "Developer 2FA not enabled", "Enable an authenticator code for this developer account before production work.", "Open security");
        }

        if ((int) ($release["security_audit"]["failures"] ?? 0) > 0) {
            $items[] = $this->item("security-audit-failures", "critical", "Security audit has failures", "Strict production release should stop until security:audit is clean.", "Open readiness");
        }

        if ((bool) ($release["backup_restore"]["ok"] ?? true) !== true) {
            $items[] = $this->item("backup-restore-check", "warning", "Backup restore check needs review", "Production readiness expects a verified DB/storage recovery path.", "Open readiness");
        }

        if (($developerDashboard["observability_enabled"] ?? false) !== true) {
            $items[] = $this->item("metrics-disabled", "info", "Metrics disabled", "Privacy-light analytics and request trends need observability metrics enabled.", "Open analytics");
        }

        if ($items === []) {
            $items[] = $this->item("all-clear", "success", "Developer workspace clear", "No blocking Developer Panel notifications were detected.", "Dashboard");
        }

        $state = $this->state();
        $active = [];
        $archived = [];

        foreach ($items as $item) {
            $key = (string) ($item["key"] ?? "");
            $record = is_array($state[$key] ?? null) ? (array) $state[$key] : [];
            $item["acknowledged_at"] = (string) ($record["acknowledged_at"] ?? "");
            $item["acknowledged_by"] = (string) ($record["acknowledged_by"] ?? "");
            $item["archived_at"] = (string) ($record["archived_at"] ?? "");
            $item["archived_by"] = (string) ($record["archived_by"] ?? "");

            if ($item["archived_at"] !== "") {
                $archived[] = $item;
                continue;
            }

            $active[] = $item;
        }

        return [
            "schema" => "fnlla.developer_notifications.v1",
            "generated_at_utc" => gmdate(DATE_ATOM),
            "items" => $active,
            "archived_items" => $archived,
            "unread_count" => count(array_filter($active, static fn (array $item): bool => ($item["severity"] ?? "") !== "success" && (string) ($item["acknowledged_at"] ?? "") === "")),
            "acknowledged_count" => count(array_filter($active, static fn (array $item): bool => (string) ($item["acknowledged_at"] ?? "") !== "")),
            "archived_count" => count($archived),
        ];
    }

    public function acknowledge(string $key, array $developer = []): void
    {
        $this->change($key, [
            "acknowledged_at" => gmdate(DATE_ATOM),
            "acknowledged_by" => (string) ($developer["email"] ?? "developer"),
            "archived_at" => "",
            "archived_by" => "",
        ]);
    }

    public function archive(string $key, array $developer = []): void
    {
        $this->change($key, [
            "acknowledged_at" => gmdate(DATE_ATOM),
            "acknowledged_by" => (string) ($developer["email"] ?? "developer"),
            "archived_at" => gmdate(DATE_ATOM),
            "archived_by" => (string) ($developer["email"] ?? "developer"),
        ]);
    }

    public function restore(string $key, array $developer = []): void
    {
        $this->change($key, [
            "archived_at" => "",
            "archived_by" => "",
            "restored_at" => gmdate(DATE_ATOM),
            "restored_by" => (string) ($developer["email"] ?? "developer"),
        ]);
    }

    private function item(string $key, string $severity, string $title, string $text, string $action): array
    {
        return [
            "key" => $key,
            "severity" => $severity,
            "title" => $title,
            "text" => $text,
            "action" => $action,
            "time" => gmdate(DATE_ATOM),
        ];
    }

    private function change(string $key, array $changes): void
    {
        $key = $this->cleanKey($key);

        if ($key === "") {
            return;
        }

        $state = $this->state();
        $state[$key] = array_merge(is_array($state[$key] ?? null) ? (array) $state[$key] : [], $changes, [
            "key" => $key,
            "updated_at" => gmdate(DATE_ATOM),
        ]);

        $this->write($state);
    }

    private function state(): array
    {
        if ($this->driver() === "database") {
            return $this->readDatabase();
        }

        $path = $this->path();

        if (!is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        $records = is_array($decoded) ? (array) ($decoded["records"] ?? []) : [];
        $state = [];

        foreach ($records as $key => $record) {
            if (is_array($record)) {
                $state[$this->cleanKey((string) $key)] = $this->normaliseRecord($record);
            }
        }

        return array_filter($state, static fn (array $record, string $key): bool => $key !== "", ARRAY_FILTER_USE_BOTH);
    }

    private function write(array $state): void
    {
        $normalised = [];

        foreach ($state as $key => $record) {
            if (is_array($record)) {
                $cleanKey = $this->cleanKey((string) $key);

                if ($cleanKey !== "") {
                    $normalised[$cleanKey] = $this->normaliseRecord($record);
                }
            }
        }

        if ($this->driver() === "database") {
            $this->writeDatabase($normalised);
            return;
        }

        $path = $this->path();
        $directory = dirname($path);

        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        file_put_contents($path, json_encode([
            "schema" => "fnlla.developer_notification_state.v1",
            "updated_at_utc" => gmdate(DATE_ATOM),
            "records" => $normalised,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL, LOCK_EX);
    }

    private function readDatabase(): array
    {
        $this->ensureDatabaseTable();
        $rows = db()->select("SELECT notification_key, payload FROM " . $this->quoteIdentifier($this->table()));
        $state = [];

        foreach ($rows as $row) {
            $key = $this->cleanKey((string) ($row["notification_key"] ?? ""));
            $decoded = json_decode((string) ($row["payload"] ?? ""), true);

            if ($key !== "" && is_array($decoded)) {
                $state[$key] = $this->normaliseRecord($decoded);
            }
        }

        return $state;
    }

    private function writeDatabase(array $state): void
    {
        $this->ensureDatabaseTable();

        foreach ($state as $key => $record) {
            $record = $this->normaliseRecord((array) $record);
            db()->statement(
                "REPLACE INTO " . $this->quoteIdentifier($this->table()) . " (notification_key, severity, title, payload, acknowledged_at, archived_at, updated_by) VALUES (:notification_key, :severity, :title, :payload, :acknowledged_at, :archived_at, :updated_by)",
                [
                    "notification_key" => $key,
                    "severity" => "state",
                    "title" => $key,
                    "payload" => json_encode($record, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                    "acknowledged_at" => $record["acknowledged_at"] !== "" ? $record["acknowledged_at"] : null,
                    "archived_at" => $record["archived_at"] !== "" ? $record["archived_at"] : null,
                    "updated_by" => (string) (($record["archived_by"] ?? "") ?: ($record["acknowledged_by"] ?? "")),
                ]
            );
        }
    }

    private function ensureDatabaseTable(): void
    {
        $table = $this->quoteIdentifier($this->table());
        db()->statement(
            "CREATE TABLE IF NOT EXISTS {$table} (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                notification_key VARCHAR(160) NOT NULL,
                severity VARCHAR(40) NOT NULL,
                title VARCHAR(160) NOT NULL,
                payload JSON NOT NULL,
                acknowledged_at VARCHAR(80) NULL DEFAULT NULL,
                archived_at VARCHAR(80) NULL DEFAULT NULL,
                updated_by VARCHAR(160) NOT NULL DEFAULT '',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_fnlla_developer_notification_key (notification_key),
                INDEX idx_fnlla_developer_notification_severity (severity)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    private function normaliseRecord(array $record): array
    {
        return [
            "key" => $this->cleanKey((string) ($record["key"] ?? "")),
            "acknowledged_at" => $this->clean((string) ($record["acknowledged_at"] ?? ""), 80),
            "acknowledged_by" => $this->clean((string) ($record["acknowledged_by"] ?? ""), 160),
            "archived_at" => $this->clean((string) ($record["archived_at"] ?? ""), 80),
            "archived_by" => $this->clean((string) ($record["archived_by"] ?? ""), 160),
            "restored_at" => $this->clean((string) ($record["restored_at"] ?? ""), 80),
            "restored_by" => $this->clean((string) ($record["restored_by"] ?? ""), 160),
            "updated_at" => $this->clean((string) ($record["updated_at"] ?? ""), 80),
        ];
    }

    private function cleanKey(string $key): string
    {
        $key = strtolower(trim($key));
        $key = (string) preg_replace('/[^a-z0-9_.:-]+/', "-", $key);

        return substr(trim($key, "-"), 0, 160);
    }

    private function clean(string $value, int $maxLength): string
    {
        $value = trim((string) preg_replace('/\s+/', ' ', strip_tags($value)));

        return strlen($value) <= $maxLength ? $value : substr($value, 0, $maxLength);
    }

    private function path(): string
    {
        return storage_path((string) config("developer_workspace.notifications_state_path", "framework/developer/notifications-state.json"));
    }

    private function driver(): string
    {
        return strtolower(trim((string) config("developer_workspace.driver", "file"))) === "database"
            ? "database"
            : "file";
    }

    private function table(): string
    {
        $table = trim((string) config("developer_workspace.notifications_table", "fnlla_developer_notifications"));

        return preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $table) === 1 ? $table : "fnlla_developer_notifications";
    }

    private function quoteIdentifier(string $identifier): string
    {
        return "`" . str_replace("`", "``", $identifier) . "`";
    }
}
