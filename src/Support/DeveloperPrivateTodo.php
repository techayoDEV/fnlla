<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA SUPPORT SOURCE
File: src\Support\DeveloperPrivateTodo.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

Purpose:
- Stores the private per-developer to-do list outside the shared Kanban board.
*/

namespace Fnlla\Php\Support;

final class DeveloperPrivateTodo
{
    public function state(array $developer = []): array
    {
        $owner = $this->ownerKey($developer);
        $state = $this->store()->read();
        $bucket = is_array($state["lists"][$owner] ?? null) ? (array) $state["lists"][$owner] : [];
        $items = $this->normaliseItems((array) ($bucket["items"] ?? []));
        $today = gmdate("Y-m-d");
        $soon = gmdate("Y-m-d", time() + 7 * 24 * 60 * 60);
        $summary = [
            "open_count" => 0,
            "done_count" => 0,
            "due_soon_count" => 0,
            "overdue_count" => 0,
            "notes_count" => 0,
        ];

        foreach ($items as $item) {
            if ((bool) ($item["done"] ?? false)) {
                $summary["done_count"]++;
            } else {
                $summary["open_count"]++;
            }

            $dueDate = (string) ($item["due_date"] ?? "");
            if ($dueDate !== "" && !(bool) ($item["done"] ?? false)) {
                if ($dueDate < $today) {
                    $summary["overdue_count"]++;
                } elseif ($dueDate <= $soon) {
                    $summary["due_soon_count"]++;
                }
            }

            if (trim((string) ($item["notes"] ?? "")) !== "") {
                $summary["notes_count"]++;
            }
        }

        return [
            "schema" => "fnlla.developer_private_todo.v1",
            "owner" => $this->ownerLabel($developer),
            "updated_at_utc" => (string) ($bucket["updated_at_utc"] ?? ""),
            "items" => $items,
            "open_count" => $summary["open_count"],
            "done_count" => $summary["done_count"],
            "due_soon_count" => $summary["due_soon_count"],
            "overdue_count" => $summary["overdue_count"],
            "notes_count" => $summary["notes_count"],
        ];
    }

    public function create(array $payload, array $developer = []): array
    {
        return $this->mutate($developer, function (array $items) use ($payload): array {
            $title = $this->clean((string) ($payload["title"] ?? ""), 160);

            if ($title === "") {
                throw new \InvalidArgumentException("Private to-do item needs a title.");
            }

            $now = gmdate(DATE_ATOM);
            $items[] = [
                "id" => bin2hex(random_bytes(8)),
                "title" => $title,
                "notes" => $this->clean((string) ($payload["notes"] ?? ""), 700),
                "priority" => $this->priority((string) ($payload["priority"] ?? "normal")),
                "due_date" => $this->date((string) ($payload["due_date"] ?? "")),
                "done" => false,
                "created_at_utc" => $now,
                "updated_at_utc" => $now,
            ];

            return $items;
        });
    }

    public function toggle(string $id, array $developer = []): array
    {
        return $this->mutate($developer, function (array $items) use ($id): array {
            foreach ($items as $index => $item) {
                if (($item["id"] ?? "") !== $id) {
                    continue;
                }

                $items[$index]["done"] = !((bool) ($item["done"] ?? false));
                $items[$index]["updated_at_utc"] = gmdate(DATE_ATOM);
                break;
            }

            return $items;
        });
    }

    public function delete(string $id, array $developer = []): array
    {
        return $this->mutate($developer, static fn (array $items): array => array_values(array_filter(
            $items,
            static fn (array $item): bool => ($item["id"] ?? "") !== $id
        )));
    }

    private function mutate(array $developer, callable $change): array
    {
        $owner = $this->ownerKey($developer);

        $state = $this->store()->update(function (array $state) use ($owner, $developer, $change): array {
            $state += ["schema" => "fnlla.developer_private_todo.v1", "lists" => []];
            $bucket = is_array($state["lists"][$owner] ?? null) ? (array) $state["lists"][$owner] : [];
            $items = $this->normaliseItems((array) ($bucket["items"] ?? []));
            $items = $this->normaliseItems($change($items));
            $state["lists"][$owner] = [
                "owner" => $this->ownerLabel($developer),
                "updated_at_utc" => gmdate(DATE_ATOM),
                "items" => array_slice($items, 0, 150),
            ];
            $state["updated_at_utc"] = gmdate(DATE_ATOM);
            $state["lists"] = array_slice((array) $state["lists"], -100, null, true);

            return $state;
        });

        return $this->state($developer) + ["raw" => $state];
    }

    private function normaliseItems(array $items): array
    {
        $normalised = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $title = $this->clean((string) ($item["title"] ?? ""), 160);

            if ($title === "") {
                continue;
            }

            $normalised[] = [
                "id" => $this->clean((string) ($item["id"] ?? bin2hex(random_bytes(8))), 32),
                "title" => $title,
                "notes" => $this->clean((string) ($item["notes"] ?? ""), 700),
                "priority" => $this->priority((string) ($item["priority"] ?? "normal")),
                "due_date" => $this->date((string) ($item["due_date"] ?? "")),
                "done" => (bool) ($item["done"] ?? false),
                "created_at_utc" => $this->clean((string) ($item["created_at_utc"] ?? ""), 80),
                "updated_at_utc" => $this->clean((string) ($item["updated_at_utc"] ?? ""), 80),
            ];
        }

        usort($normalised, static function (array $left, array $right): int {
            $doneComparison = ((int) $left["done"]) <=> ((int) $right["done"]);
            if ($doneComparison !== 0) {
                return $doneComparison;
            }

            $leftDue = (string) $left["due_date"];
            $rightDue = (string) $right["due_date"];
            if ($leftDue !== $rightDue) {
                if ($leftDue === "") {
                    return 1;
                }
                if ($rightDue === "") {
                    return -1;
                }

                return strcmp($leftDue, $rightDue);
            }

            return strcmp((string) $right["updated_at_utc"], (string) $left["updated_at_utc"]);
        });

        return $normalised;
    }

    private function ownerKey(array $developer): string
    {
        $email = strtolower(trim((string) ($developer["email"] ?? "")));

        return hash("sha256", $email !== "" ? $email : "developer");
    }

    private function ownerLabel(array $developer): string
    {
        $email = strtolower(trim((string) ($developer["email"] ?? "")));

        return $email !== "" ? $email : "developer";
    }

    private function priority(string $value): string
    {
        $value = strtolower(trim($value));

        return in_array($value, ["low", "normal", "high"], true) ? $value : "normal";
    }

    private function date(string $value): string
    {
        $value = trim($value);

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? $value : "";
    }

    private function clean(string $value, int $maxLength): string
    {
        $value = trim((string) preg_replace('/\s+/', ' ', strip_tags($value)));

        return strlen($value) <= $maxLength ? $value : substr($value, 0, $maxLength);
    }

    private function store(): LockedJsonStore
    {
        return new LockedJsonStore($this->path());
    }

    private function path(): string
    {
        $path = (string) config("developer_workspace.private_todo_path", "framework/developer/private-todos.json");

        if (preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1 || str_starts_with($path, "/") || str_starts_with($path, "\\")) {
            return $path;
        }

        return storage_path(ltrim($path, "\\/"));
    }
}
