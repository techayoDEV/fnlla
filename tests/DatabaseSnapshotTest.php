<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA TEST CASE
File: tests\DatabaseSnapshotTest.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

Purpose:
- Verifies the reusable database snapshot helper used by integration tests.
*/

namespace Fnlla\Php\Tests;

use Fnlla\Php\Database\DatabaseManager;
use Fnlla\Php\Testing\DatabaseSnapshot;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class DatabaseSnapshotTest extends TestCase
{
    public function testRejectsUnsafeTableNames(): void
    {
        $this->expectException(RuntimeException::class);

        new DatabaseSnapshot(app(DatabaseManager::class), ["users; DROP TABLE users"]);
    }

    public function testRestoresCapturedRowsWhenDatabaseIsAvailable(): void
    {
        $database = app(DatabaseManager::class);
        $table = "fnlla_snapshot_test_" . strtolower(bin2hex(random_bytes(3)));

        try {
            $database->statement("CREATE TABLE {$table} (id INT PRIMARY KEY, label VARCHAR(40) NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            $database->table($table)->insert(["id" => 1, "label" => "before"]);
            $snapshot = (new DatabaseSnapshot($database, [$table]))->capture();
            $database->table($table)->delete();
            $database->table($table)->insert(["id" => 2, "label" => "after"]);

            $snapshot->restore();
            $rows = $database->select("SELECT * FROM {$table} ORDER BY id ASC");

            self::assertSame(1, count($rows));
            self::assertSame(1, (int) ($rows[0]["id"] ?? 0));
            self::assertSame("before", (string) ($rows[0]["label"] ?? ""));
        } catch (RuntimeException $exception) {
            if (str_contains($exception->getMessage(), "Database connection failed") || str_contains($exception->getMessage(), "pdo_mysql")) {
                self::assertTrue(true);
                return;
            }

            throw $exception;
        } finally {
            try {
                $database->statement("DROP TABLE IF EXISTS {$table}");
            } catch (\Throwable) {
            }
        }
    }
}
