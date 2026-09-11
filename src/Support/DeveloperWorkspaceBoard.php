<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA SUPPORT SOURCE
File: src\Support\DeveloperWorkspaceBoard.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

Purpose:
- Stores the lightweight project Kanban workspace state.
*/

namespace Fnlla\Php\Support;

final class DeveloperWorkspaceBoard
{
    private const COLUMNS = [
        "backlog" => "Backlog",
        "in_progress" => "In progress",
        "review" => "Review",
        "done" => "Done",
    ];

    private const PRIORITIES = [
        "low" => "Low",
        "normal" => "Normal",
        "high" => "High",
        "urgent" => "Urgent",
    ];

    private const TYPES = [
        "task" => "Task",
        "bug" => "Bug",
        "security" => "Security",
        "release" => "Release",
        "content" => "Content",
        "research" => "Research",
    ];

    private const COLORS = [
        "blue" => "Blue",
        "slate" => "Slate",
        "sky" => "Sky",
        "indigo" => "Indigo",
        "green" => "Green",
        "red" => "Red",
        "yellow" => "Yellow",
        "orange" => "Orange",
    ];

    public function state(array $developer = []): array
    {
        $state = $this->read();
        $tasks = $this->normaliseTasks((array) ($state["tasks"] ?? []));

        return $this->buildState($state, $tasks, $developer, "fnlla.developer_workspace.v1");
    }

    public function customerState(array $customer = []): array
    {
        $state = $this->read();
        $tasks = array_values(array_filter(
            $this->normaliseTasks((array) ($state["tasks"] ?? [])),
            static fn (array $task): bool => (bool) ($task["client_visible"] ?? true)
        ));

        return array_merge($this->buildState($state, $tasks, [], "fnlla.customer_workspace.v1"), [
            "customer" => [
                "email" => strtolower(trim((string) ($customer["email"] ?? ""))),
                "name" => trim((string) ($customer["name"] ?? "Customer")),
            ],
            "client_visible_tasks_count" => count($tasks),
        ]);
    }

    private function buildState(array $state, array $tasks, array $developer, string $schema): array
    {
        $currentEmail = strtolower(trim((string) ($developer["email"] ?? "")));
        $today = strtotime(gmdate("Y-m-d")) ?: time();
        $soon = strtotime("+7 days", $today) ?: $today;
        $summary = [
            "my_tasks_count" => 0,
            "open_tasks_count" => 0,
            "blocked_tasks_count" => 0,
            "due_soon_count" => 0,
            "done_tasks_count" => 0,
            "comments_count" => 0,
            "attachments_count" => 0,
        ];

        foreach ($tasks as $task) {
            $status = (string) ($task["status"] ?? "");

            if ($currentEmail !== "" && strtolower((string) ($task["assignee"] ?? "")) === $currentEmail) {
                $summary["my_tasks_count"]++;
            }

            if ($status === "done") {
                $summary["done_tasks_count"]++;
            } else {
                $summary["open_tasks_count"]++;
            }

            if (($task["blocked"] ?? false) === true) {
                $summary["blocked_tasks_count"]++;
            }

            $due = strtotime((string) ($task["due_date"] ?? ""));
            if ($due !== false && $due >= $today && $due <= $soon && $status !== "done") {
                $summary["due_soon_count"]++;
            }

            $summary["comments_count"] += count((array) ($task["comments"] ?? []));
            $summary["attachments_count"] += count((array) ($task["attachments"] ?? []));
        }

        return [
            "schema" => $schema,
            "updated_at_utc" => (string) ($state["updated_at_utc"] ?? ""),
            "columns" => self::COLUMNS,
            "priorities" => self::PRIORITIES,
            "types" => self::TYPES,
            "colors" => self::COLORS,
            "tasks" => $tasks,
            "columns_with_tasks" => $this->groupByStatus($tasks),
            "my_tasks_count" => $summary["my_tasks_count"],
            "open_tasks_count" => $summary["open_tasks_count"],
            "blocked_tasks_count" => $summary["blocked_tasks_count"],
            "due_soon_count" => $summary["due_soon_count"],
            "done_tasks_count" => $summary["done_tasks_count"],
            "comments_count" => $summary["comments_count"],
            "attachments_count" => $summary["attachments_count"],
            "completion_percent" => $tasks === [] ? 0 : (int) round(($summary["done_tasks_count"] / count($tasks)) * 100),
        ];
    }

    public function create(array $payload, array $developer = []): array
    {
        return $this->mutate(fn (array $state): array => $this->createTask($state, $payload, $developer));
    }

    public function createRuntimeIssueTask(array $issue, string $debtItemId, array $developer = []): array
    {
        $fingerprint = trim((string) ($issue["fingerprint"] ?? $issue["id"] ?? $debtItemId));
        $taskId = "runtime-" . substr(hash("sha256", $fingerprint), 0, 16);
        $route = trim((string) ($issue["route"] ?? "unmatched"));
        $location = trim((string) ($issue["file"] ?? "")) . ":" . (string) max(0, (int) ($issue["line"] ?? 0));
        $occurrences = max(1, (int) ($issue["occurrences"] ?? 1));
        $payload = [
            "id" => $taskId,
            "title" => (string) ($issue["title"] ?? "Review runtime issue"),
            "notes" => "Runtime issue triage. Route: {$route}. Location: {$location}. Occurrences: {$occurrences}.",
            "status" => "backlog",
            "priority" => $occurrences >= 3 ? "urgent" : "high",
            "type" => "bug",
            "color" => "red",
            "assignee" => (string) ($developer["email"] ?? ""),
            "client_visible" => false,
            "checklist" => implode("\n", [
                "[ ] Reproduce the failing route",
                "[ ] Review the linked technical debt item",
                "[ ] Confirm the runtime issue stops recurring",
            ]),
            "attachment_label" => "Technical debt item",
            "attachment_url" => "#debt-" . substr(preg_replace('/[^A-Za-z0-9_-]/', "-", $debtItemId) ?: "runtime", 0, 64),
        ];

        $tasks = $this->mutate(function (array $state) use ($taskId, $payload, $developer): array {
            $tasks = $this->normaliseTasks((array) ($state["tasks"] ?? []));

            foreach ($tasks as $task) {
                if (($task["id"] ?? "") === $taskId) {
                    return $tasks;
                }
            }

            return $this->createTask(["tasks" => $tasks], $payload, $developer);
        });

        foreach ($tasks as $task) {
            if (($task["id"] ?? "") === $taskId) {
                return $task;
            }
        }

        return [];
    }

    private function createTask(array $state, array $payload, array $developer): array
    {
        $tasks = $this->normaliseTasks((array) ($state["tasks"] ?? []));
        $now = gmdate(DATE_ATOM);

        $tasks[] = [
            "id" => $this->clean((string) ($payload["id"] ?? bin2hex(random_bytes(8))), 32),
            "title" => $this->clean((string) ($payload["title"] ?? ""), 120),
            "notes" => $this->clean((string) ($payload["notes"] ?? ""), 280),
            "status" => $this->status((string) ($payload["status"] ?? "backlog")),
            "position" => $this->position($payload["position"] ?? (count($tasks) + 1) * 100),
            "priority" => $this->priority((string) ($payload["priority"] ?? "normal")),
            "type" => $this->type((string) ($payload["type"] ?? "task")),
            "color" => $this->color((string) ($payload["color"] ?? "blue")),
            "assignee" => strtolower(trim((string) ($payload["assignee"] ?? ($developer["email"] ?? "")))),
            "due_date" => $this->date((string) ($payload["due_date"] ?? "")),
            "estimate" => $this->clean((string) ($payload["estimate"] ?? ""), 24),
            "blocked" => (bool) ($payload["blocked"] ?? false),
            "client_visible" => (bool) ($payload["client_visible"] ?? true),
            "checklist" => is_array($payload["subtasks_text"] ?? null)
                ? $this->checklistFromStructured(
                    $payload["subtasks_text"],
                    (array) ($payload["subtasks_done"] ?? []),
                    (array) ($payload["subtasks_color"] ?? []),
                    (array) ($payload["subtasks_note"] ?? [])
                )
                : $this->checklist((string) ($payload["checklist"] ?? "")),
            "comments" => [],
            "attachments" => $this->attachmentsFromArray([
                $this->fileAttachmentFromPayload($payload, $developer),
            ]),
            "created_by" => strtolower(trim((string) ($developer["email"] ?? "developer"))),
            "updated_by" => strtolower(trim((string) ($developer["email"] ?? "developer"))),
            "created_at_utc" => $now,
            "updated_at_utc" => $now,
        ];

        return $tasks;
    }

    public function update(string $id, array $payload, array $developer = []): array
    {
        return $this->mutate(fn (array $state): array => $this->updateTask($state, $id, $payload, $developer));
    }

    private function updateTask(array $state, string $id, array $payload, array $developer): array
    {
        $tasks = $this->normaliseTasks((array) ($state["tasks"] ?? []));

        foreach ($tasks as $index => $task) {
            if (($task["id"] ?? "") !== $id) {
                continue;
            }

            $currentChecklist = $this->checklistFromArray((array) ($task["checklist"] ?? []));
            $currentChecklistText = $this->checklistText($currentChecklist);
            $incomingChecklistText = (string) ($payload["checklist"] ?? $currentChecklistText);
            $checklist = is_array($payload["subtasks_text"] ?? null)
                ? $this->checklistFromStructured(
                    $payload["subtasks_text"],
                    (array) ($payload["subtasks_done"] ?? []),
                    (array) ($payload["subtasks_color"] ?? []),
                    (array) ($payload["subtasks_note"] ?? [])
                )
                : ($incomingChecklistText === $currentChecklistText
                ? $currentChecklist
                : $this->checklist($incomingChecklistText));
            $subtask = $this->clean((string) ($payload["subtask"] ?? ""), 120);
            $subtaskNote = $this->clean((string) ($payload["subtask_note"] ?? ""), 280);

            if ($subtask !== "") {
                $checklist[] = [
                    "text" => $subtask,
                    "done" => false,
                    "color" => $this->color((string) ($payload["subtask_color"] ?? $payload["color"] ?? $task["color"] ?? "blue")),
                    "note" => $subtaskNote,
                ];
            }

            $toggleIndex = $this->indexValue($payload["toggle_subtask_index"] ?? null);
            if ($toggleIndex !== null && isset($checklist[$toggleIndex])) {
                $checklist[$toggleIndex]["done"] = !((bool) ($checklist[$toggleIndex]["done"] ?? false));
            }

            $editIndex = $this->indexValue($payload["edit_subtask_index"] ?? null);
            if ($editIndex !== null && isset($checklist[$editIndex])) {
                $editText = $this->clean((string) ($payload["edit_subtask_text"] ?? ""), 120);
                if ($editText !== "") {
                    $checklist[$editIndex]["text"] = $editText;
                    $checklist[$editIndex]["color"] = $this->color((string) ($payload["edit_subtask_color"] ?? $checklist[$editIndex]["color"] ?? "blue"));
                    $checklist[$editIndex]["note"] = $this->clean((string) ($payload["edit_subtask_note"] ?? $checklist[$editIndex]["note"] ?? ""), 280);
                }
            }

            $deleteIndex = $this->indexValue($payload["delete_subtask_index"] ?? null);
            if ($deleteIndex !== null && isset($checklist[$deleteIndex])) {
                unset($checklist[$deleteIndex]);
                $checklist = array_values($checklist);
            }

            $comments = $this->commentsFromArray((array) ($task["comments"] ?? []));
            $comment = $this->clean((string) ($payload["comment"] ?? ""), 360);
            if ($comment !== "") {
                $comments[] = [
                    "text" => $comment,
                    "author" => strtolower(trim((string) ($developer["email"] ?? "developer"))),
                    "created_at_utc" => gmdate(DATE_ATOM),
                ];
            }

            $attachments = $this->attachmentsFromArray((array) ($task["attachments"] ?? []));
            $attachmentLabel = $this->clean((string) ($payload["attachment_label"] ?? ""), 100);
            $attachmentUrl = trim((string) ($payload["attachment_url"] ?? ""));
            if ($attachmentLabel !== "" && filter_var($attachmentUrl, FILTER_VALIDATE_URL) !== false) {
                $attachments[] = [
                    "type" => "url",
                    "label" => $attachmentLabel,
                    "url" => $attachmentUrl,
                    "added_by" => strtolower(trim((string) ($payload["attachment_added_by"] ?? $developer["email"] ?? "developer"))),
                    "created_at_utc" => gmdate(DATE_ATOM),
                ];
            }

            $fileAttachment = $this->fileAttachmentFromPayload($payload, $developer);
            if ($fileAttachment !== null) {
                $attachments[] = $fileAttachment;
            }

            $tasks[$index] = array_merge($task, [
                "title" => $this->clean((string) ($payload["title"] ?? $task["title"]), 120),
                "notes" => $this->clean((string) ($payload["notes"] ?? $task["notes"]), 280),
                "status" => $this->status((string) ($payload["status"] ?? $task["status"])),
                "position" => $this->position($payload["position"] ?? $task["position"] ?? ($index + 1) * 100),
                "priority" => $this->priority((string) ($payload["priority"] ?? $task["priority"])),
                "type" => $this->type((string) ($payload["type"] ?? $task["type"] ?? "task")),
                "color" => $this->color((string) ($payload["color"] ?? $task["color"] ?? "blue")),
                "assignee" => strtolower(trim((string) ($payload["assignee"] ?? $task["assignee"]))),
                "due_date" => $this->date((string) ($payload["due_date"] ?? $task["due_date"] ?? "")),
                "estimate" => $this->clean((string) ($payload["estimate"] ?? $task["estimate"] ?? ""), 24),
                "blocked" => (bool) ($payload["blocked"] ?? $task["blocked"] ?? false),
                "client_visible" => (bool) ($payload["client_visible"] ?? $task["client_visible"] ?? true),
                "checklist" => array_slice($checklist, 0, 12),
                "comments" => array_slice($comments, -20),
                "attachments" => array_slice($attachments, -20),
                "updated_by" => strtolower(trim((string) ($developer["email"] ?? "developer"))),
                "updated_at_utc" => gmdate(DATE_ATOM),
            ]);

            break;
        }

        return $tasks;
    }

    public function delete(string $id): array
    {
        return $this->mutate(fn (array $state): array => array_values(array_filter(
            $this->normaliseTasks((array) ($state["tasks"] ?? [])),
            static fn (array $task): bool => ($task["id"] ?? "") !== $id
        )));
    }

    private function mutate(callable $change): array
    {
        if ($this->driver() === "database") {
            $this->ensureDatabaseTable();
            return db()->transaction(function () use ($change): array {
                // The upsert serializes first writers too, when the state row is absent.
                db()->statement("INSERT INTO " . $this->quoteIdentifier($this->table())
                    . " (state_key, payload) VALUES (:state_key, :payload) ON DUPLICATE KEY UPDATE state_key = state_key",
                    ["state_key" => "default", "payload" => json_encode(["schema" => "fnlla.developer_workspace.v1",
                        "tasks" => $this->starterTasks()], JSON_THROW_ON_ERROR)]);
                $tasks = $this->normaliseTasks($change($this->readDatabase(true)));
                $this->writeDatabase($tasks);
                return $tasks;
            });
        }
        $state = (new LockedJsonStore($this->path()))->update(function (array $state) use ($change): array {
            if ($state === []) {
                $state = ["tasks" => $this->starterTasks()];
            }
            return ["schema" => "fnlla.developer_workspace.v1", "updated_at_utc" => gmdate(DATE_ATOM),
                "tasks" => $this->normaliseTasks($change($state))];
        });
        return $state["tasks"];
    }

    private function groupByStatus(array $tasks): array
    {
        $grouped = array_fill_keys(array_keys(self::COLUMNS), []);

        foreach ($tasks as $task) {
            $status = $this->status((string) ($task["status"] ?? "backlog"));
            $grouped[$status][] = $task;
        }

        foreach ($grouped as $status => $items) {
            usort($items, static function (array $left, array $right): int {
                $positionComparison = ((float) ($left["position"] ?? 0.0)) <=> ((float) ($right["position"] ?? 0.0));

                return $positionComparison !== 0
                    ? $positionComparison
                    : strcmp((string) ($right["updated_at_utc"] ?? ""), (string) ($left["updated_at_utc"] ?? ""));
            });
            $grouped[$status] = $items;
        }

        return $grouped;
    }

    private function normaliseTasks(array $tasks): array
    {
        $normalised = [];

        foreach ($tasks as $task) {
            if (!is_array($task)) {
                continue;
            }

            $title = $this->clean((string) ($task["title"] ?? ""), 120);

            if ($title === "") {
                continue;
            }

            $normalised[] = [
                "id" => $this->clean((string) ($task["id"] ?? bin2hex(random_bytes(8))), 32),
                "title" => $title,
                "notes" => $this->clean((string) ($task["notes"] ?? ""), 280),
                "status" => $this->status((string) ($task["status"] ?? "backlog")),
                "position" => $this->position($task["position"] ?? (count($normalised) + 1) * 100),
                "priority" => $this->priority((string) ($task["priority"] ?? "normal")),
                "type" => $this->type((string) ($task["type"] ?? "task")),
                "color" => $this->color((string) ($task["color"] ?? "blue")),
                "assignee" => strtolower(trim((string) ($task["assignee"] ?? ""))),
                "due_date" => $this->date((string) ($task["due_date"] ?? "")),
                "estimate" => $this->clean((string) ($task["estimate"] ?? ""), 24),
                "blocked" => (bool) ($task["blocked"] ?? false),
                "client_visible" => (bool) ($task["client_visible"] ?? true),
                "checklist" => $this->checklistFromArray((array) ($task["checklist"] ?? [])),
                "comments" => $this->commentsFromArray((array) ($task["comments"] ?? [])),
                "attachments" => $this->attachmentsFromArray((array) ($task["attachments"] ?? [])),
                "created_by" => strtolower(trim((string) ($task["created_by"] ?? "developer"))),
                "updated_by" => strtolower(trim((string) ($task["updated_by"] ?? ""))),
                "created_at_utc" => $this->clean((string) ($task["created_at_utc"] ?? ""), 80),
                "updated_at_utc" => $this->clean((string) ($task["updated_at_utc"] ?? ""), 80),
            ];
        }

        usort($normalised, static function (array $left, array $right): int {
            $statusComparison = strcmp($left["status"], $right["status"]);
            if ($statusComparison !== 0) {
                return $statusComparison;
            }

            $positionComparison = $left["position"] <=> $right["position"];

            return $positionComparison !== 0
                ? $positionComparison
                : strcmp($right["updated_at_utc"], $left["updated_at_utc"]);
        });

        return $normalised;
    }

    private function read(): array
    {
        if ($this->driver() === "database") {
            return $this->readDatabase();
        }

        $path = $this->path();

        if (!is_file($path)) {
            return [
                "schema" => "fnlla.developer_workspace.v1",
                "tasks" => $this->starterTasks(),
            ];
        }

        return (new LockedJsonStore($path))->read();
    }

    private function starterTasks(): array
    {
        $now = gmdate(DATE_ATOM);

        return [
            [
                "id" => "starter-identity",
                "title" => "Confirm project identity",
                "notes" => "Set project name, URL and browser-title slogan before sharing the build.",
                "status" => "backlog",
                "position" => 100,
                "priority" => "normal",
                "type" => "task",
                "color" => "blue",
                "assignee" => "",
                "due_date" => "",
                "estimate" => "15m",
                "blocked" => false,
                "client_visible" => true,
                "checklist" => [
                    ["text" => "Set app name", "done" => false, "color" => "blue"],
                    ["text" => "Set public URL", "done" => false, "color" => "blue"],
                ],
                "comments" => [],
                "attachments" => [],
                "created_by" => "fnlla",
                "updated_by" => "fnlla",
                "created_at_utc" => $now,
                "updated_at_utc" => $now,
            ],
            [
                "id" => "starter-preview",
                "title" => "Prepare client preview access",
                "notes" => "Rotate the preview password and confirm whether public routes should stay open.",
                "status" => "backlog",
                "position" => 100,
                "priority" => "normal",
                "type" => "release",
                "color" => "sky",
                "assignee" => "",
                "due_date" => "",
                "estimate" => "30m",
                "blocked" => false,
                "client_visible" => true,
                "checklist" => [
                    ["text" => "Set preview password", "done" => false, "color" => "sky"],
                    ["text" => "Confirm public lock policy", "done" => false, "color" => "sky"],
                ],
                "comments" => [],
                "attachments" => [],
                "created_by" => "fnlla",
                "updated_by" => "fnlla",
                "created_at_utc" => $now,
                "updated_at_utc" => $now,
            ],
            [
                "id" => "starter-release",
                "title" => "Run release readiness checks",
                "notes" => "Review security audit, backup status, framework drift and performance probes.",
                "status" => "review",
                "position" => 100,
                "priority" => "high",
                "type" => "release",
                "color" => "indigo",
                "assignee" => "",
                "due_date" => "",
                "estimate" => "45m",
                "blocked" => false,
                "client_visible" => false,
                "checklist" => [
                    ["text" => "Run security audit", "done" => false, "color" => "indigo"],
                    ["text" => "Review backup status", "done" => false, "color" => "indigo"],
                    ["text" => "Check framework drift", "done" => false, "color" => "indigo"],
                ],
                "comments" => [],
                "attachments" => [],
                "created_by" => "fnlla",
                "updated_by" => "fnlla",
                "created_at_utc" => $now,
                "updated_at_utc" => $now,
            ],
        ];
    }

    private function status(string $value): string
    {
        $value = strtolower(str_replace([" ", "-"], "_", trim($value)));

        if ($value === "todo") {
            return "backlog";
        }

        return array_key_exists($value, self::COLUMNS) ? $value : "backlog";
    }

    private function priority(string $value): string
    {
        $value = strtolower(str_replace([" ", "-"], "_", trim($value)));

        return array_key_exists($value, self::PRIORITIES) ? $value : "normal";
    }

    private function type(string $value): string
    {
        $value = strtolower(str_replace([" ", "-"], "_", trim($value)));

        return array_key_exists($value, self::TYPES) ? $value : "task";
    }

    private function color(string $value): string
    {
        $value = strtolower(str_replace([" ", "-"], "_", trim($value)));

        return array_key_exists($value, self::COLORS) ? $value : "blue";
    }

    private function date(string $value): string
    {
        $value = trim($value);

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? $value : "";
    }

    private function position(mixed $value): float
    {
        if (is_numeric($value)) {
            return round((float) $value, 3);
        }

        return 0.0;
    }

    private function checklist(string $value): array
    {
        $items = [];

        foreach (preg_split('/\R/', $value) ?: [] as $line) {
            $line = trim($line);

            if ($line === "") {
                continue;
            }

            $done = preg_match('/^\[(x|X)\]\s+/', $line) === 1;
            $line = (string) preg_replace('/^\[( |x|X)\]\s+/', "", $line);

            $items[] = [
                "text" => $this->clean($line, 120),
                "done" => $done,
                "color" => "blue",
                "note" => "",
            ];

            if (count($items) >= 12) {
                break;
            }
        }

        return array_values(array_filter($items, static fn (array $item): bool => $item["text"] !== ""));
    }

    private function checklistFromArray(array $items): array
    {
        $normalised = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $text = $this->clean((string) ($item["text"] ?? ""), 120);

            if ($text === "") {
                continue;
            }

            $normalised[] = [
                "text" => $text,
                "done" => (bool) ($item["done"] ?? false),
                "color" => $this->color((string) ($item["color"] ?? "blue")),
                "note" => $this->clean((string) ($item["note"] ?? ""), 280),
            ];

            if (count($normalised) >= 12) {
                break;
            }
        }

        return $normalised;
    }

    private function checklistFromStructured(array $texts, array $done, array $colors, array $notes): array
    {
        $items = [];

        foreach ($texts as $index => $value) {
            $text = $this->clean((string) $value, 120);

            if ($text === "") {
                continue;
            }

            $items[$index] = [
                "text" => $text,
                "done" => array_key_exists($index, $done),
                "color" => $this->color((string) ($colors[$index] ?? "blue")),
                "note" => $this->clean((string) ($notes[$index] ?? ""), 280),
            ];

            if (count($items) >= 12) {
                break;
            }
        }

        return $items;
    }

    private function checklistText(array $items): string
    {
        $lines = [];

        foreach ($this->checklistFromArray($items) as $item) {
            $lines[] = (($item["done"] ?? false) ? "[x] " : "[ ] ") . (string) ($item["text"] ?? "");
        }

        return implode(PHP_EOL, $lines);
    }

    private function indexValue(mixed $value): ?int
    {
        if ($value === null || $value === "") {
            return null;
        }

        if (!is_numeric($value)) {
            return null;
        }

        $index = (int) $value;

        return $index >= 0 ? $index : null;
    }

    private function commentsFromArray(array $items): array
    {
        $normalised = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $text = $this->clean((string) ($item["text"] ?? ""), 360);

            if ($text === "") {
                continue;
            }

            $normalised[] = [
                "text" => $text,
                "author" => strtolower(trim((string) ($item["author"] ?? "developer"))),
                "created_at_utc" => $this->clean((string) ($item["created_at_utc"] ?? ""), 80),
            ];

            if (count($normalised) >= 20) {
                break;
            }
        }

        return $normalised;
    }

    private function attachmentsFromArray(array $items): array
    {
        $normalised = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $url = trim((string) ($item["url"] ?? ""));
            $type = (string) ($item["type"] ?? "url");
            $originalName = $this->clean((string) ($item["original_name"] ?? ""), 160);
            $label = $this->clean((string) ($item["label"] ?? ""), 100);

            if ($type === "file" && $originalName !== "") {
                $label = $this->clean($originalName, 100);
            }

            if ($label === "" || !$this->validAttachmentUrl($url)) {
                continue;
            }

            $normalised[] = [
                "type" => in_array($type, ["url", "file"], true) ? $type : "url",
                "label" => $label,
                "url" => $url,
                "added_by" => strtolower(trim((string) ($item["added_by"] ?? "developer"))),
                "created_at_utc" => $this->clean((string) ($item["created_at_utc"] ?? ""), 80),
                "original_name" => $originalName,
                "mime_type" => $this->clean((string) ($item["mime_type"] ?? ""), 120),
                "size_bytes" => max(0, (int) ($item["size_bytes"] ?? 0)),
            ];

            if (count($normalised) >= 20) {
                break;
            }
        }

        return $normalised;
    }

    private function fileAttachmentFromPayload(array $payload, array $developer = []): ?array
    {
        $file = $payload["attachment_file"] ?? null;

        if (!is_array($file)) {
            return null;
        }

        $originalName = $this->clean((string) ($file["original_name"] ?? ""), 160);
        $label = $originalName !== ""
            ? $this->clean($originalName, 100)
            : $this->clean((string) ($file["label"] ?? ""), 100);
        $url = trim((string) ($file["url"] ?? ""));

        if ($label === "" || !$this->validAttachmentUrl($url)) {
            return null;
        }

        return [
            "type" => "file",
            "label" => $label,
            "url" => $url,
            "added_by" => strtolower(trim((string) ($file["added_by"] ?? $payload["attachment_added_by"] ?? $developer["email"] ?? "developer"))),
            "created_at_utc" => $this->clean((string) ($file["created_at_utc"] ?? gmdate(DATE_ATOM)), 80),
            "original_name" => $originalName,
            "mime_type" => $this->clean((string) ($file["mime_type"] ?? ""), 120),
            "size_bytes" => max(0, (int) ($file["size_bytes"] ?? 0)),
        ];
    }

    private function validAttachmentUrl(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false || str_starts_with($url, "/uploads/");
    }

    private function clean(string $value, int $maxLength): string
    {
        $value = trim((string) preg_replace('/\s+/', ' ', strip_tags($value)));

        return strlen($value) <= $maxLength ? $value : substr($value, 0, $maxLength);
    }

    private function path(): string
    {
        $path = (string) config("developer_workspace.path", "framework/developer/workspace.json");

        if (preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1 || str_starts_with($path, "/") || str_starts_with($path, "\\")) {
            return $path;
        }

        return storage_path(ltrim($path, "\\/"));
    }

    private function driver(): string
    {
        return strtolower(trim((string) config("developer_workspace.driver", "file"))) === "database"
            ? "database"
            : "file";
    }

    private function readDatabase(bool $forUpdate = false): array
    {
        $this->ensureDatabaseTable();
        $rows = db()->select(
            "SELECT payload FROM " . $this->quoteIdentifier($this->table()) . " WHERE state_key = :state_key LIMIT 1" . ($forUpdate ? " FOR UPDATE" : ""),
            ["state_key" => "default"]
        );
        if ($rows !== []) {
            $decoded = json_decode((string) $rows[0]["payload"], true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($decoded) || !is_array($decoded["tasks"] ?? null)) {
                throw new \RuntimeException("Invalid workspace state; refusing to overwrite it.");
            }
            return $decoded;
        }

        return [
            "schema" => "fnlla.developer_workspace.v1",
            "tasks" => $this->starterTasks(),
        ];
    }

    private function writeDatabase(array $tasks): void
    {
        $this->ensureDatabaseTable();
        db()->statement(
            "UPDATE " . $this->quoteIdentifier($this->table()) . " SET payload = :payload, updated_at = NOW() WHERE state_key = :state_key",
            [
                "state_key" => "default",
                "payload" => json_encode([
                    "schema" => "fnlla.developer_workspace.v1",
                    "updated_at_utc" => gmdate(DATE_ATOM),
                    "tasks" => $this->normaliseTasks($tasks),
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            ]
        );
    }

    private function ensureDatabaseTable(): void
    {
        // MySQL DDL commits implicitly. A caller-owned transaction requires a migrated table.
        if (db()->connection()->inTransaction()) {
            return;
        }
        $table = $this->quoteIdentifier($this->table());
        db()->statement(
            "CREATE TABLE IF NOT EXISTS {$table} (
                state_key VARCHAR(80) NOT NULL PRIMARY KEY,
                payload JSON NOT NULL,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    private function table(): string
    {
        $table = trim((string) config("developer_workspace.table", "fnlla_developer_workspace_state"));

        return preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $table) === 1 ? $table : "fnlla_developer_workspace_state";
    }

    private function quoteIdentifier(string $identifier): string
    {
        return "`" . str_replace("`", "``", $identifier) . "`";
    }
}
