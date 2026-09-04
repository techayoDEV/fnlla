<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA MAINTENANCE SOURCE
File: src\Maintenance\DeveloperActivityLog.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

Purpose:
- Records project-wide developer actions so every developer session sees the
  same global changes made through the private developer panel.
*/

namespace Fnlla\Php\Maintenance;

use Fnlla\Php\Support\RecentFileLines;

final class DeveloperActivityLog
{
    public function record(string $action, string $title, string $text, array $developer = []): void
    {
        $previousHash = $this->latestHash();
        $payload = [
            "schema" => "fnlla.developer_activity.v1",
            "time" => gmdate("c"),
            "action" => $this->normalise($action, 80),
            "title" => $this->normalise($title, 120),
            "text" => $this->normalise($text, 240),
            "request_id" => $this->normalise((string) ($_SERVER["HTTP_X_REQUEST_ID"] ?? $_SERVER["FNLLA_REQUEST_ID"] ?? ""), 120),
            "ip_hash" => $this->ipHash(),
            "previous_hash" => $previousHash,
            "developer" => [
                "email" => $this->normalise((string) ($developer["email"] ?? ""), 160),
                "name" => $this->normalise((string) ($developer["name"] ?? "Developer"), 100),
                "role" => $this->normalise((string) ($developer["role"] ?? "lead_developer"), 40),
            ],
        ];
        $payload["event_hash"] = $this->eventHash($payload);

        if ($this->driver() === "database") {
            $this->recordDatabase($payload);
            return;
        }

        $path = $this->path();
        $directory = dirname($path);

        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        file_put_contents(
            $path,
            json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }

    public function recent(int $limit = 8): array
    {
        if ($this->driver() === "database") {
            return $this->recentDatabase($limit);
        }

        return $this->recentFileItems($limit);
    }

    public function all(int $limit = 500): array
    {
        if ($this->driver() === "database") {
            return array_reverse($this->recentDatabase($limit));
        }

        return array_reverse($this->recentFileItems($limit));
    }

    public function exportPayload(int $limit = 500): array
    {
        return [
            "schema" => "fnlla.developer_activity_export.v1",
            "generated_at_utc" => gmdate("c"),
            "source" => "developer-panel",
            "limit" => max(1, $limit),
            "items" => $this->all($limit),
        ];
    }

    public function exportCsv(int $limit = 500): string
    {
        $handle = fopen("php://temp", "r+");

        if (!is_resource($handle)) {
            return "";
        }

        fputcsv($handle, ["time", "action", "title", "developer_email", "developer_role", "request_id", "previous_hash", "event_hash"], ",", "\"", "");

        foreach ($this->all($limit) as $item) {
            $developer = (array) ($item["developer"] ?? []);
            fputcsv($handle, [
                (string) ($item["time"] ?? ""),
                (string) ($item["action"] ?? ""),
                (string) ($item["title"] ?? ""),
                (string) ($developer["email"] ?? ""),
                (string) ($developer["role"] ?? ""),
                (string) ($item["request_id"] ?? ""),
                (string) ($item["previous_hash"] ?? ""),
                (string) ($item["event_hash"] ?? ""),
            ], ",", "\"", "");
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return is_string($csv) ? $csv : "";
    }

    private function path(): string
    {
        return storage_path((string) config("developer_control.activity_log_path", "framework/developer/activity.jsonl"));
    }

    private function driver(): string
    {
        return strtolower(trim((string) config("developer_control.activity_log_driver", "file"))) === "database"
            ? "database"
            : "file";
    }

    private function recordDatabase(array $payload): void
    {
        $this->ensureDatabaseTable();
        db()->table($this->table())->insert([
            "event_time" => (string) ($payload["time"] ?? gmdate("c")),
            "action" => (string) ($payload["action"] ?? ""),
            "developer_email" => (string) ($payload["developer"]["email"] ?? ""),
            "request_id" => (string) ($payload["request_id"] ?? ""),
            "previous_hash" => (string) ($payload["previous_hash"] ?? ""),
            "event_hash" => (string) ($payload["event_hash"] ?? ""),
            "payload" => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        ]);
    }

    private function recentDatabase(int $limit): array
    {
        $this->ensureDatabaseTable();
        $rows = db()->select(
            "SELECT payload FROM " . $this->quoteIdentifier($this->table()) . " ORDER BY id DESC LIMIT " . max(1, min(1000, $limit))
        );
        $items = [];

        foreach ($rows as $row) {
            $decoded = json_decode((string) ($row["payload"] ?? ""), true);

            if (is_array($decoded)) {
                $items[] = $decoded;
            }
        }

        return $items;
    }

    private function recentFileItems(int $limit): array
    {
        $items = [];

        foreach (RecentFileLines::read($this->path(), max(1, $limit)) as $line) {
            $decoded = json_decode($line, true);

            if (is_array($decoded)) {
                $items[] = $decoded;
            }
        }

        return $items;
    }

    private function ensureDatabaseTable(): void
    {
        $table = $this->quoteIdentifier($this->table());
        db()->statement(
            "CREATE TABLE IF NOT EXISTS {$table} (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    private function latestHash(): string
    {
        $latest = $this->driver() === "database"
            ? ($this->recentDatabase(1)[0] ?? [])
            : ($this->recent(1)[0] ?? []);

        return is_array($latest) ? $this->normalise((string) ($latest["event_hash"] ?? ""), 128) : "";
    }

    private function eventHash(array $payload): string
    {
        $hashPayload = $payload;
        unset($hashPayload["event_hash"]);

        return hash("sha256", json_encode($hashPayload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    private function ipHash(): string
    {
        $ip = trim((string) ($_SERVER["REMOTE_ADDR"] ?? ""));

        return $ip === "" ? "" : hash("sha256", $ip . "|" . (string) config("app.name", "fnlla"));
    }

    private function table(): string
    {
        $table = trim((string) config("developer_control.activity_log_table", "fnlla_developer_activity_log"));

        return preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $table) === 1 ? $table : "fnlla_developer_activity_log";
    }

    private function quoteIdentifier(string $identifier): string
    {
        return "`" . str_replace("`", "``", $identifier) . "`";
    }

    private function normalise(string $value, int $maxLength): string
    {
        $value = trim((string) preg_replace('/\s+/', ' ', $value));

        if (strlen($value) <= $maxLength) {
            return $value;
        }

        return substr($value, 0, $maxLength);
    }
}
