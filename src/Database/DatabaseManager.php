<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA DATABASE SOURCE
File: src\Database\DatabaseManager.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Implements the maintained MySQL data access and migration runtime.
*/

namespace Fnlla\Php\Database;

use PDO;
use PDOException;
use RuntimeException;

final class DatabaseManager
{
    private ?PDO $pdo = null;

    public function connection(): PDO
    {
        if ($this->pdo instanceof PDO) {
            return $this->pdo;
        }

        $default = (string) config("database.default", "mysql");
        $connection = config("database.connections.mysql", []);

        if (!is_array($connection)) {
            throw new RuntimeException("Database connection configuration is invalid.");
        }

        if (!extension_loaded("pdo_mysql")) {
            throw new RuntimeException("Database connection failed: the PDO MySQL driver (pdo_mysql) is not installed.");
        }

        try {
            $pdo = new PDO(
                sprintf(
                    "mysql:host=%s;port=%s;dbname=%s;charset=%s",
                    $connection["host"] ?? "127.0.0.1",
                    $connection["port"] ?? "3306",
                    $connection["database"] ?? "",
                    $connection["charset"] ?? "utf8mb4"
                ),
                (string) ($connection["username"] ?? ""),
                (string) ($connection["password"] ?? "")
            );
        } catch (PDOException $exception) {
            throw new RuntimeException("Database connection failed: " . $exception->getMessage(), 0, $exception);
        }

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        return $this->pdo = $pdo;
    }

    public function table(string $table): QueryBuilder
    {
        return new QueryBuilder($this->connection(), $table);
    }

    public function statement(string $sql, array $bindings = []): bool
    {
        $statement = $this->connection()->prepare($sql);

        return $statement->execute($bindings);
    }

    public function select(string $sql, array $bindings = []): array
    {
        $statement = $this->connection()->prepare($sql);
        $statement->execute($bindings);

        return $statement->fetchAll();
    }

    public function transaction(callable $callback): mixed
    {
        $pdo = $this->connection();
        $pdo->beginTransaction();

        try {
            $result = $callback($this);
            $pdo->commit();

            return $result;
        } catch (\Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }
}
