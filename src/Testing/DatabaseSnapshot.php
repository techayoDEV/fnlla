<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA TESTING SOURCE
File: src\Testing\DatabaseSnapshot.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

Purpose:
- Captures and restores selected database tables for integration tests that
  mutate shared MySQL state.
*/

namespace Fnlla\Php\Testing;

use Fnlla\Php\Database\DatabaseManager;
use RuntimeException;

final class DatabaseSnapshot
{
    private array $snapshots = [];
    private array $existingTables = [];

    public function __construct(private DatabaseManager $database, private array $tables)
    {
        $this->tables = array_values(array_map(
            fn (string $table): string => $this->normalizeIdentifier($table),
            $tables
        ));
    }

    public function capture(): self
    {
        $this->snapshots = [];
        $this->existingTables = [];

        foreach ($this->tables as $table) {
            if (!$this->tableExists($table)) {
                $this->snapshots[$table] = [];
                continue;
            }

            $this->existingTables[] = $table;
            $this->snapshots[$table] = $this->database->select("SELECT * FROM " . $this->quoteIdentifier($table));
        }

        return $this;
    }

    public function restore(): void
    {
        if ($this->snapshots === []) {
            return;
        }

        $this->database->statement("SET FOREIGN_KEY_CHECKS=0");

        try {
            foreach (array_reverse($this->existingTables) as $table) {
                $this->database->statement("DELETE FROM " . $this->quoteIdentifier($table));
            }

            foreach ($this->existingTables as $table) {
                foreach ($this->snapshots[$table] ?? [] as $row) {
                    $this->database->table($table)->insert($row);
                }
            }
        } finally {
            $this->database->statement("SET FOREIGN_KEY_CHECKS=1");
        }
    }

    public function snapshots(): array
    {
        return $this->snapshots;
    }

    private function tableExists(string $table): bool
    {
        $rows = $this->database->select("
            SELECT COUNT(*) AS count
            FROM INFORMATION_SCHEMA.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = :table
        ", [
            "table" => $table,
        ]);

        return (int) ($rows[0]["count"] ?? 0) > 0;
    }

    private function normalizeIdentifier(string $identifier): string
    {
        $identifier = trim($identifier);

        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier) !== 1) {
            throw new RuntimeException("Invalid database snapshot table: " . $identifier);
        }

        return $identifier;
    }

    private function quoteIdentifier(string $identifier): string
    {
        return "`" . str_replace("`", "``", $this->normalizeIdentifier($identifier)) . "`";
    }
}
