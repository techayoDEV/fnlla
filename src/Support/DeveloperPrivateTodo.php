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
    private const COLORS = ["blue", "slate", "sky", "indigo", "green", "red", "yellow", "orange"];

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
            "subtasks_count" => 0,
            "completed_subtasks_count" => 0,
            "attachments_count" => 0,
        ];

        foreach ($items as $item) {
            $subtasks = array_values((array) ($item["subtasks"] ?? []));
            $attachments = array_values((array) ($item["attachments"] ?? []));

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

            $summary["subtasks_count"] += count($subtasks);
            $summary["completed_subtasks_count"] += count(array_filter($subtasks, static fn (array $subtask): bool => (bool) ($subtask["done"] ?? false)));
            $summary["attachments_count"] += count($attachments);
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
            "subtasks_count" => $summary["subtasks_count"],
            "completed_subtasks_count" => $summary["completed_subtasks_count"],
            "attachments_count" => $summary["attachments_count"],
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
                "color" => $this->color((string) ($payload["color"] ?? "blue")),
                "subtasks" => $this->subtasksFromPayload($payload["subtasks"] ?? "", (array) ($payload["subtasks_done"] ?? [])),
                "attachments" => $this->attachmentsFromArray([
                    $this->urlAttachmentFromPayload($payload),
                    $this->fileAttachmentFromPayload($payload),
                ]),
                "done" => false,
                "created_at_utc" => $now,
                "updated_at_utc" => $now,
            ];

            return $items;
        });
    }

    public function update(string $id, array $payload, array $developer = []): array
    {
        $id = $this->clean($id, 32);

        return $this->mutate($developer, function (array $items) use ($id, $payload): array {
            $found = false;
            foreach ($items as $index => $item) {
                if (($item["id"] ?? "") !== $id) {
                    continue;
                }

                $found = true;
                $title = $this->clean((string) ($payload["title"] ?? $item["title"] ?? ""), 160);
                if ($title === "") {
                    throw new \InvalidArgumentException("Private to-do item needs a title.");
                }

                $attachments = $this->attachmentsFromArray((array) ($item["attachments"] ?? []));
                $newAttachments = $this->attachmentsFromArray([
                    $this->urlAttachmentFromPayload($payload),
                    $this->fileAttachmentFromPayload($payload),
                ]);

                $items[$index] = [
                    "id" => $id,
                    "title" => $title,
                    "notes" => $this->clean((string) ($payload["notes"] ?? ""), 700),
                    "priority" => $this->priority((string) ($payload["priority"] ?? $item["priority"] ?? "normal")),
                    "due_date" => $this->date((string) ($payload["due_date"] ?? "")),
                    "color" => $this->color((string) ($payload["color"] ?? $item["color"] ?? "blue")),
                    "subtasks" => array_key_exists("subtasks", $payload)
                        ? $this->subtasksFromPayload($payload["subtasks"], (array) ($payload["subtasks_done"] ?? []))
                        : $this->subtasksFromArray((array) ($item["subtasks"] ?? [])),
                    "attachments" => array_slice(array_merge($attachments, $newAttachments), -10),
                    "done" => (bool) ($item["done"] ?? false),
                    "created_at_utc" => $this->clean((string) ($item["created_at_utc"] ?? ""), 80),
                    "updated_at_utc" => gmdate(DATE_ATOM),
                ];
                break;
            }

            if (!$found) {
                throw new \InvalidArgumentException("Private to-do item was not found.");
            }

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
                "color" => $this->color((string) ($item["color"] ?? "blue")),
                "subtasks" => $this->subtasksFromArray((array) ($item["subtasks"] ?? [])),
                "attachments" => $this->attachmentsFromArray((array) ($item["attachments"] ?? [])),
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

    private function color(string $value): string
    {
        $value = strtolower(trim($value));

        return in_array($value, self::COLORS, true) ? $value : "blue";
    }

    private function date(string $value): string
    {
        $value = trim($value);

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? $value : "";
    }

    private function subtasks(string $value): array
    {
        $items = [];

        foreach (preg_split('/\R+/', $value) ?: [] as $line) {
            $line = trim((string) $line);

            if ($line === "") {
                continue;
            }

            $done = false;
            if (preg_match('/^\[(x|X|\s)\]\s*(.+)$/', $line, $matches) === 1) {
                $done = strtolower((string) $matches[1]) === "x";
                $line = (string) $matches[2];
            }

            $text = $this->clean($line, 140);
            if ($text === "") {
                continue;
            }

            $items[] = [
                "text" => $text,
                "done" => $done,
            ];

            if (count($items) >= 20) {
                break;
            }
        }

        return $items;
    }

    private function subtasksFromPayload(mixed $value, array $doneIndexes = []): array
    {
        if (!is_array($value)) {
            return $this->subtasks((string) $value);
        }

        $items = [];
        $doneLookup = [];
        foreach ($doneIndexes as $doneIndex) {
            if (is_scalar($doneIndex)) {
                $doneLookup[(string) $doneIndex] = true;
            }
        }

        foreach ($value as $index => $line) {
            if (is_array($line)) {
                $text = $this->clean((string) ($line["text"] ?? ""), 140);
                $done = (bool) ($line["done"] ?? false);
            } else {
                $text = $this->clean((string) $line, 140);
                $done = isset($doneLookup[(string) $index]);
            }

            if ($text === "") {
                continue;
            }

            $items[] = [
                "text" => $text,
                "done" => $done,
            ];

            if (count($items) >= 20) {
                break;
            }
        }

        return $items;
    }

    private function subtasksFromArray(array $subtasks): array
    {
        $items = [];

        foreach ($subtasks as $subtask) {
            if (is_string($subtask)) {
                $subtask = ["text" => $subtask, "done" => false];
            }

            if (!is_array($subtask)) {
                continue;
            }

            $text = $this->clean((string) ($subtask["text"] ?? ""), 140);
            if ($text === "") {
                continue;
            }

            $items[] = [
                "text" => $text,
                "done" => (bool) ($subtask["done"] ?? false),
            ];

            if (count($items) >= 20) {
                break;
            }
        }

        return $items;
    }

    private function urlAttachmentFromPayload(array $payload): ?array
    {
        $url = trim((string) ($payload["attachment_url"] ?? ""));

        if ($url === "" || !$this->validAttachmentUrl($url)) {
            return null;
        }

        $label = $this->clean((string) ($payload["attachment_label"] ?? ""), 100);
        if ($label === "") {
            $label = (string) (parse_url($url, PHP_URL_HOST) ?: "Attachment link");
        }

        return [
            "type" => "url",
            "label" => $label,
            "url" => $url,
            "added_by" => $this->clean((string) ($payload["attachment_added_by"] ?? "developer"), 160),
            "created_at_utc" => gmdate(DATE_ATOM),
        ];
    }

    private function fileAttachmentFromPayload(array $payload): ?array
    {
        $attachment = $payload["attachment_file"] ?? null;

        return is_array($attachment) ? $attachment : null;
    }

    private function attachmentsFromArray(array $attachments): array
    {
        $items = [];

        foreach ($attachments as $attachment) {
            if (!is_array($attachment)) {
                continue;
            }

            $url = trim((string) ($attachment["url"] ?? ""));
            if ($url === "" || !$this->validAttachmentUrl($url)) {
                continue;
            }

            $type = strtolower(trim((string) ($attachment["type"] ?? "url")));
            $type = $type === "file" ? "file" : "url";
            $label = $this->clean((string) ($attachment["label"] ?? ""), 100);
            if ($label === "") {
                $label = $type === "file" ? "Uploaded attachment" : "Attachment link";
            }

            $items[] = [
                "type" => $type,
                "label" => $label,
                "url" => $url,
                "added_by" => $this->clean((string) ($attachment["added_by"] ?? "developer"), 160),
                "created_at_utc" => $this->clean((string) ($attachment["created_at_utc"] ?? gmdate(DATE_ATOM)), 80),
                "original_name" => $this->clean((string) ($attachment["original_name"] ?? ""), 140),
                "mime_type" => $this->clean((string) ($attachment["mime_type"] ?? ""), 120),
                "size_bytes" => max(0, (int) ($attachment["size_bytes"] ?? 0)),
            ];

            if (count($items) >= 10) {
                break;
            }
        }

        return $items;
    }

    private function validAttachmentUrl(string $url): bool
    {
        return str_starts_with($url, "/uploads/developer-private-todo-attachments/")
            || filter_var($url, FILTER_VALIDATE_URL) !== false;
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
