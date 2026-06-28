<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA PHP DATABASE SOURCE
File: src\Database\QueryBuilder.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA PHP is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA PHP framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Implements the maintained MySQL data access and migration runtime.
*/

namespace Fnlla\Php\Database;

use PDO;
use RuntimeException;

final class QueryBuilder
{
    private array $columns = ["*"];
    private array $wheres = [];
    private array $bindings = [];
    private ?int $limit = null;
    private array $orders = [];

    public function __construct(private PDO $pdo, private string $table)
    {
    }

    public function select(array|string $columns): self
    {
        $this->columns = is_array($columns) ? $columns : [$columns];

        return $this;
    }

    public function where(string $column, mixed $operatorOrValue, mixed $value = null): self
    {
        $operator = $value === null ? "=" : (string) $operatorOrValue;
        $resolvedValue = $value === null ? $operatorOrValue : $value;
        $parameter = "where_" . count($this->bindings);

        $this->wheres[] = sprintf("%s %s :%s", $column, $operator, $parameter);
        $this->bindings[$parameter] = $resolvedValue;

        return $this;
    }

    public function orderBy(string $column, string $direction = "asc"): self
    {
        $direction = strtoupper($direction) === "DESC" ? "DESC" : "ASC";
        $this->orders[] = $column . " " . $direction;

        return $this;
    }

    public function limit(int $limit): self
    {
        $this->limit = max(1, $limit);

        return $this;
    }

    public function get(): array
    {
        [$sql, $bindings] = $this->compileSelect();
        $statement = $this->pdo->prepare($sql);
        $statement->execute($bindings);

        return $statement->fetchAll();
    }

    public function first(): ?array
    {
        $clone = clone $this;
        $clone->limit(1);
        $results = $clone->get();

        return $results[0] ?? null;
    }

    public function insert(array $values): bool
    {
        if ($values === []) {
            throw new RuntimeException("Insert values cannot be empty.");
        }

        $columns = array_keys($values);
        $placeholders = array_map(static fn (string $column): string => ":" . $column, $columns);
        $sql = sprintf(
            "INSERT INTO %s (%s) VALUES (%s)",
            $this->table,
            implode(", ", $columns),
            implode(", ", $placeholders)
        );

        $statement = $this->pdo->prepare($sql);

        return $statement->execute($values);
    }

    public function insertGetId(array $values): int
    {
        $this->insert($values);

        return (int) $this->pdo->lastInsertId();
    }

    public function update(array $values): bool
    {
        if ($values === []) {
            throw new RuntimeException("Update values cannot be empty.");
        }

        $assignments = [];
        $bindings = $this->bindings;

        foreach ($values as $column => $value) {
            $parameter = "set_" . $column;
            $assignments[] = $column . " = :" . $parameter;
            $bindings[$parameter] = $value;
        }

        $sql = sprintf("UPDATE %s SET %s%s", $this->table, implode(", ", $assignments), $this->compileWhereClause());
        $statement = $this->pdo->prepare($sql);

        return $statement->execute($bindings);
    }

    public function delete(): bool
    {
        $sql = sprintf("DELETE FROM %s%s", $this->table, $this->compileWhereClause());
        $statement = $this->pdo->prepare($sql);

        return $statement->execute($this->bindings);
    }

    public function count(string $column = "*"): int
    {
        $clone = clone $this;
        $clone->columns = ["COUNT(" . $column . ") AS aggregate"];
        $clone->orders = [];
        $clone->limit = null;
        $result = $clone->first();

        return (int) ($result["aggregate"] ?? 0);
    }

    public function exists(): bool
    {
        return $this->count() > 0;
    }

    private function compileSelect(): array
    {
        $sql = sprintf("SELECT %s FROM %s%s", implode(", ", $this->columns), $this->table, $this->compileWhereClause());

        if ($this->orders !== []) {
            $sql .= " ORDER BY " . implode(", ", $this->orders);
        }

        if ($this->limit !== null) {
            $sql .= " LIMIT " . $this->limit;
        }

        return [$sql, $this->bindings];
    }

    private function compileWhereClause(): string
    {
        if ($this->wheres === []) {
            return "";
        }

        return " WHERE " . implode(" AND ", $this->wheres);
    }
}
