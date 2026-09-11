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
    public static function sourceFor(array $item): string
    {
        $key = strtolower((string) ($item["key"] ?? ""));

        if (str_starts_with($key, "activity:")) {
            return "Developer activity";
        }

        if (self::containsAny($key, ["framework", "update"])) {
            return "Release & readiness";
        }

        if (self::containsAny($key, ["totp", "access", "security"])) {
            return "Access & security";
        }

        if (self::containsAny($key, ["readiness", "backup", "audit"])) {
            return "Release & readiness";
        }

        if (self::containsAny($key, ["analytics", "heatmap", "metric"])) {
            return "Observability";
        }

        if (self::containsAny($key, ["preview", "service"])) {
            return "Operations";
        }

        if (self::containsAny($key, ["leadership", "identity"])) {
            return "Workspace";
        }

        return "Developer Panel";
    }

    public static function routeFor(array $item, array $developerLinks): string
    {
        $key = strtolower((string) ($item["key"] ?? ""));
        $action = strtolower((string) ($item["action"] ?? ""));

        if (str_starts_with($key, "activity:")) {
            return (string) ($developerLinks["project_logs"] ?? route("developer.panel.project_logs"));
        }

        if (self::containsAny($key, ["framework", "update"])) {
            return (string) ($developerLinks["framework_updates"] ?? route("developer.panel.framework_updates"));
        }

        if (self::containsAny($key, ["totp", "access", "security"])) {
            return (string) ($developerLinks["security"] ?? route("developer.panel.access"));
        }

        if (self::containsAny($key, ["readiness", "backup", "audit"]) || str_contains($action, "readiness")) {
            return (string) ($developerLinks["release_readiness"] ?? route("developer.panel.release_readiness"));
        }

        if (self::containsAny($key, ["analytics", "metrics"]) || str_contains($action, "analytics")) {
            return (string) ($developerLinks["analytics"] ?? route("developer.panel.analytics"));
        }

        if (str_contains($key, "heatmap")) {
            return (string) ($developerLinks["heatmap"] ?? route("developer.panel.heatmap"));
        }

        if (self::containsAny($key, ["preview", "service"])) {
            return (string) ($developerLinks["project_settings"] ?? route("developer.panel.project_identity") . "#developer-access-preview");
        }

        if (self::containsAny($key, ["leadership", "identity"])) {
            return (string) ($developerLinks["identity"] ?? route("developer.panel.project_identity")) . "#project-leadership";
        }

        return (string) ($developerLinks["notifications"] ?? route("developer.panel.notifications"));
    }

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
            $items[] = $this->item("metrics-disabled", "info", "Metrics disabled", "Privacy-light analytics and request trends need observability metrics enabled.", "Open traffic analytics");
        }

        foreach ($this->activityItems($currentEmail) as $activityItem) {
            $items[] = $activityItem;
        }

        if ($items === []) {
            $items[] = $this->item("all-clear", "success", "Developer workspace clear", "No blocking Developer Panel notifications were detected.", "Dashboard");
        }

        $state = $this->state($currentEmail);
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
            "schema" => "fnlla.developer_notifications.v2",
            "generated_at_utc" => gmdate(DATE_ATOM),
            "viewer" => $currentEmail,
            "items" => $active,
            "archived_items" => $archived,
            "unread_count" => count(array_filter($active, static fn (array $item): bool => ($item["severity"] ?? "") !== "success" && $item["acknowledged_at"] === "")),
            "acknowledged_count" => count(array_filter($active, static fn (array $item): bool => $item["acknowledged_at"] !== "")),
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
        ], (string) ($developer["email"] ?? ""));
    }

    public function archive(string $key, array $developer = []): void
    {
        $this->change($key, [
            "acknowledged_at" => gmdate(DATE_ATOM),
            "acknowledged_by" => (string) ($developer["email"] ?? "developer"),
            "archived_at" => gmdate(DATE_ATOM),
            "archived_by" => (string) ($developer["email"] ?? "developer"),
        ], (string) ($developer["email"] ?? ""));
    }

    public function records(array $developer = []): array
    {
        return $this->state((string) ($developer["email"] ?? ""));
    }

    public function restore(string $key, array $developer = []): void
    {
        $this->change($key, [
            "archived_at" => "",
            "archived_by" => "",
            "restored_at" => gmdate(DATE_ATOM),
            "restored_by" => (string) ($developer["email"] ?? "developer"),
        ], (string) ($developer["email"] ?? ""));
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

    private function activityItems(string $currentEmail): array
    {
        if ($currentEmail === "") {
            return [];
        }

        $items = [];
        foreach (\developer_activity()->recent(24) as $event) {
            $developer = (array) ($event["developer"] ?? []);
            $actorEmail = strtolower(trim((string) ($developer["email"] ?? "")));
            $action = strtolower(trim((string) ($event["action"] ?? "")));
            $hash = trim((string) ($event["event_hash"] ?? ""));

            if ($hash === "" || $actorEmail === "" || hash_equals($currentEmail, $actorEmail) || !$this->notifiableActivity($action)) {
                continue;
            }

            $actorName = trim((string) ($developer["name"] ?? ""));
            $actor = $actorName !== "" ? $actorName : $actorEmail;
            $title = trim((string) ($event["title"] ?? ""));
            $text = trim((string) ($event["text"] ?? ""));
            $item = $this->item(
                "activity:" . $hash,
                $this->activitySeverity($action),
                $title !== "" ? $title : "Developer change recorded",
                "Changed by " . $actor . ($text !== "" ? ". " . $text : "."),
                "Open activity"
            );
            $item["kind"] = "activity";
            $item["source"] = "Developer activity";
            $item["source_event_hash"] = $hash;
            $item["actor_email"] = $actorEmail;
            $item["actor_name"] = $actor;
            $item["activity_action"] = $action;
            $item["time"] = (string) ($event["time"] ?? $item["time"]);
            $items[] = $item;
        }

        return $items;
    }

    private function notifiableActivity(string $action): bool
    {
        return self::containsAny($action, [
            "service_control",
            "client_preview",
            "project_identity",
            "runtime_environment",
            "project_leadership",
            "developer_access",
            "developer_integrations",
            "developer_workspace",
            "developer_ai_settings",
            "analytics_settings",
            "heatmap_settings",
            "framework_update",
            "technical_debt",
        ]);
    }

    private function activitySeverity(string $action): string
    {
        if (self::containsAny($action, ["service_control", "developer_access", "framework_update"])) {
            return "warning";
        }

        return "info";
    }

    private static function containsAny(string $value, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($value, (string) $needle)) {
                return true;
            }
        }

        return false;
    }

    private function change(string $key, array $changes, string $viewerEmail): void
    {
        $key = $this->cleanKey($key);

        if ($key === "") {
            return;
        }

        $storageKey = $this->storageKey($key, $viewerEmail);
        $merge = fn (array $record): array => $this->normaliseRecord(array_merge($record, $changes, [
            "key" => $key,
            "viewer" => $this->viewerKey($viewerEmail),
            "viewer_email" => $this->clean($viewerEmail, 160),
            "updated_at" => gmdate(DATE_ATOM),
        ]));
        if ($this->driver() === "database") {
            $this->ensureDatabaseTable();
            db()->transaction(function () use ($key, $storageKey, $merge): void {
                $table = $this->quoteIdentifier($this->table());
                db()->statement("INSERT INTO {$table} (notification_key, severity, title, payload) VALUES (:key, 'state', :title, '{}')"
                    . " ON DUPLICATE KEY UPDATE notification_key = notification_key", ["key" => $storageKey, "title" => $key]);
                $rows = db()->select("SELECT payload FROM {$table} WHERE notification_key = :key FOR UPDATE", ["key" => $storageKey]);
                $record = json_decode((string) $rows[0]["payload"], true, 512, JSON_THROW_ON_ERROR);
                if (!is_array($record)) {
                    throw new \RuntimeException("Invalid notification state; refusing to overwrite it.");
                }
                $this->writeDatabase([$storageKey => $merge($record)]);
            });
            return;
        }
        (new LockedJsonStore($this->path()))->update(static function (array $document) use ($storageKey, $merge): array {
            $records = $document["records"] ?? [];
            if (!is_array($records) || (isset($records[$storageKey]) && !is_array($records[$storageKey]))) {
                throw new \RuntimeException("Invalid notification state; refusing to overwrite it.");
            }
            $records[$storageKey] = $merge($records[$storageKey] ?? []);
            return ["schema" => "fnlla.developer_notification_state.v1", "updated_at_utc" => gmdate(DATE_ATOM), "records" => $records];
        });
    }

    private function state(string $viewerEmail = ""): array
    {
        if ($this->driver() === "database") {
            return $this->viewerState($this->readDatabase(), $viewerEmail);
        }

        $path = $this->path();

        if (!is_file($path)) {
            return [];
        }

        $decoded = (new LockedJsonStore($path))->read();
        $records = $decoded["records"] ?? [];
        if (!is_array($records)) {
            throw new \RuntimeException("Invalid notification records.");
        }
        $state = [];

        foreach ($records as $key => $record) {
            if (is_array($record)) {
                $state[$this->cleanKey((string) $key)] = $this->normaliseRecord($record);
            }
        }

        return $this->viewerState(array_filter($state, static fn (array $record, string $key): bool => $key !== "", ARRAY_FILTER_USE_BOTH), $viewerEmail);
    }

    private function readDatabase(): array
    {
        $this->ensureDatabaseTable();
        $rows = db()->select("SELECT notification_key, payload FROM " . $this->quoteIdentifier($this->table()));
        $state = [];

        foreach ($rows as $row) {
            $key = $this->cleanKey((string) ($row["notification_key"] ?? ""));
            $decoded = json_decode((string) ($row["payload"] ?? ""), true, 512, JSON_THROW_ON_ERROR);

            if ($key !== "" && is_array($decoded)) {
                $state[$key] = $this->normaliseRecord($decoded);
            }
        }

        return $state;
    }

    private function viewerState(array $records, string $viewerEmail): array
    {
        $viewerPrefix = $this->viewerKey($viewerEmail) . ":";
        $state = [];

        foreach ($records as $storageKey => $record) {
            if (!is_array($record)) {
                continue;
            }

            $storageKey = $this->cleanKey((string) $storageKey);
            $record = $this->normaliseRecord($record);
            $notificationKey = $record["key"];

            if ($notificationKey === "" && str_contains($storageKey, ":")) {
                $notificationKey = $this->cleanKey(substr($storageKey, strpos($storageKey, ":") + 1));
            } elseif ($notificationKey === "") {
                $notificationKey = $storageKey;
            }

            if ($notificationKey === "") {
                continue;
            }

            if (str_starts_with($storageKey, $viewerPrefix)) {
                $record["key"] = $notificationKey;
                $state[$notificationKey] = $record;
                continue;
            }

            if (!str_contains($storageKey, ":") && !isset($state[$notificationKey])) {
                $record["key"] = $notificationKey;
                $state[$notificationKey] = $record;
            }
        }

        return $state;
    }

    private function writeDatabase(array $state): void
    {
        $this->ensureDatabaseTable();

        foreach ($state as $storageKey => $record) {
            $record = $this->normaliseRecord((array) $record);
            db()->statement(
                "UPDATE " . $this->quoteIdentifier($this->table()) . " SET severity = :severity, title = :title, payload = :payload, acknowledged_at = :acknowledged_at, archived_at = :archived_at, updated_by = :updated_by WHERE notification_key = :notification_key",
                [
                    "notification_key" => $storageKey,
                    "severity" => "state",
                    "title" => (string) ($record["key"] ?? $storageKey),
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
        if (db()->connection()->inTransaction()) {
            return;
        }
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
            "viewer" => $this->cleanKey((string) ($record["viewer"] ?? "")),
            "viewer_email" => $this->clean((string) ($record["viewer_email"] ?? ""), 160),
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

    private function storageKey(string $key, string $viewerEmail): string
    {
        return $this->viewerKey($viewerEmail) . ":" . $this->cleanKey($key);
    }

    private function viewerKey(string $viewerEmail): string
    {
        $viewerEmail = strtolower(trim($viewerEmail));

        return $viewerEmail !== "" ? "developer-" . substr(hash("sha256", $viewerEmail), 0, 24) : "anonymous";
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
