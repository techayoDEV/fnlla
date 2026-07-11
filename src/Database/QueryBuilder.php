<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA DATABASE SOURCE
File: src\Database\QueryBuilder.php
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
use RuntimeException;

final class QueryBuilder
{
    private const ALLOWED_OPERATORS = [
        "=",
        "!=",
        "<>",
        ">",
        ">=",
        "<",
        "<=",
        "LIKE",
        "NOT LIKE",
    ];

    private array $columns = ["*"];
    private array $wheres = [];
    private array $bindings = [];
    private ?int $limit = null;
    private array $orders = [];

    public function __construct(private PDO $pdo, private string $table)
    {
        $this->table = $this->quoteIdentifier($table);
    }

    public function select(array|string $columns): self
    {
        $selectedColumns = is_array($columns) ? $columns : [$columns];
        $this->columns = array_map(
            fn (string $column): string => $this->quoteIdentifier($column, true),
            $selectedColumns
        );

        return $this;
    }

    public function where(string $column, mixed $operatorOrValue, mixed $value = null): self
    {
        $operator = $value === null ? "=" : $this->normalizeOperator((string) $operatorOrValue);
        $resolvedValue = $value === null ? $operatorOrValue : $value;
        $parameter = "where_" . count($this->bindings);

        $this->wheres[] = sprintf("%s %s :%s", $this->quoteIdentifier($column), $operator, $parameter);
        $this->bindings[$parameter] = $resolvedValue;

        return $this;
    }

    public function orderBy(string $column, string $direction = "asc"): self
    {
        $direction = strtoupper($direction) === "DESC" ? "DESC" : "ASC";
        $this->orders[] = $this->quoteIdentifier($column) . " " . $direction;

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
            implode(", ", array_map(fn (string $column): string => $this->quoteIdentifier($column), $columns)),
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
            $assignments[] = $this->quoteIdentifier($column) . " = :" . $parameter;
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
        $aggregateColumn = $column === "*"
            ? "*"
            : $this->quoteIdentifier($column);
        $sql = sprintf("SELECT COUNT(%s) AS aggregate FROM %s%s", $aggregateColumn, $this->table, $this->compileWhereClause());
        $statement = $this->pdo->prepare($sql);
        $statement->execute($this->bindings);
        $result = $statement->fetch();

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

    private function normalizeOperator(string $operator): string
    {
        $normalized = strtoupper(trim($operator));

        if (!in_array($normalized, self::ALLOWED_OPERATORS, true)) {
            throw new RuntimeException("Unsupported query operator: " . $operator);
        }

        return $normalized;
    }

    private function quoteIdentifier(string $identifier, bool $allowWildcard = false): string
    {
        $identifier = trim($identifier);

        if ($allowWildcard && $identifier === "*") {
            return "*";
        }

        if ($identifier === "") {
            throw new RuntimeException("SQL identifier cannot be empty.");
        }

        $segments = explode(".", $identifier);
        $quoted = [];

        foreach ($segments as $index => $segment) {
            $segment = trim($segment);
            $isWildcardSegment = $allowWildcard && $segment === "*" && $index === count($segments) - 1;

            if ($isWildcardSegment) {
                $quoted[] = "*";
                continue;
            }

            if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $segment) !== 1) {
                throw new RuntimeException("Invalid SQL identifier: " . $identifier);
            }

            $quoted[] = "`" . $segment . "`";
        }

        return implode(".", $quoted);
    }
}
