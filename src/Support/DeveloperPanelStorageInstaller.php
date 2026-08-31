<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA SUPPORT SOURCE
File: src\Support\DeveloperPanelStorageInstaller.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

Purpose:
- Installs the optional database-backed storage tables for the Developer Panel.
*/

namespace Fnlla\Php\Support;

final class DeveloperPanelStorageInstaller
{
    public function install(): array
    {
        foreach ($this->statements() as $statement) {
            db()->statement($statement);
        }

        return [
            "schema" => "fnlla.developer_storage_install.v1",
            "installed_at_utc" => gmdate(DATE_ATOM),
            "tables" => $this->tables(),
        ];
    }

    public function statements(): array
    {
        $activity = $this->quote($this->activityTable());
        $workspace = $this->quote($this->workspaceTable());
        $notifications = $this->quote($this->notificationsTable());
        $analytics = $this->quote($this->analyticsTable());

        return [
            "CREATE TABLE IF NOT EXISTS {$activity} (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                event_time VARCHAR(80) NOT NULL,
                action VARCHAR(80) NOT NULL,
                developer_email VARCHAR(160) NOT NULL,
                request_id VARCHAR(120) NOT NULL DEFAULT '',
                previous_hash VARCHAR(128) NOT NULL DEFAULT '',
                event_hash VARCHAR(128) NOT NULL,
                payload JSON NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_fnlla_developer_activity_event_time (event_time),
                INDEX idx_fnlla_developer_activity_action (action),
                INDEX idx_fnlla_developer_activity_email (developer_email),
                INDEX idx_fnlla_developer_activity_hash (event_hash)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS {$workspace} (
                state_key VARCHAR(80) NOT NULL PRIMARY KEY,
                payload JSON NOT NULL,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS {$notifications} (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS {$analytics} (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                event_time VARCHAR(80) NOT NULL,
                event_name VARCHAR(120) NOT NULL,
                route_name VARCHAR(160) NOT NULL DEFAULT '',
                referrer_host VARCHAR(160) NOT NULL DEFAULT '',
                consent_state VARCHAR(80) NOT NULL DEFAULT '',
                payload JSON NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_fnlla_developer_analytics_event_time (event_time),
                INDEX idx_fnlla_developer_analytics_event_name (event_name),
                INDEX idx_fnlla_developer_analytics_route (route_name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        ];
    }

    public function tables(): array
    {
        return [
            "activity_log" => $this->activityTable(),
            "workspace_state" => $this->workspaceTable(),
            "notifications" => $this->notificationsTable(),
            "analytics_events" => $this->analyticsTable(),
        ];
    }

    private function activityTable(): string
    {
        return $this->table((string) config("developer_control.activity_log_table", "fnlla_developer_activity_log"), "fnlla_developer_activity_log");
    }

    private function workspaceTable(): string
    {
        return $this->table((string) config("developer_workspace.table", "fnlla_developer_workspace_state"), "fnlla_developer_workspace_state");
    }

    private function notificationsTable(): string
    {
        return $this->table((string) config("developer_workspace.notifications_table", "fnlla_developer_notifications"), "fnlla_developer_notifications");
    }

    private function analyticsTable(): string
    {
        return $this->table((string) config("developer_workspace.analytics_table", "fnlla_developer_analytics_events"), "fnlla_developer_analytics_events");
    }

    private function table(string $table, string $fallback): string
    {
        $table = trim($table);

        return preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $table) === 1 ? $table : $fallback;
    }

    private function quote(string $identifier): string
    {
        return "`" . str_replace("`", "``", $identifier) . "`";
    }
}
