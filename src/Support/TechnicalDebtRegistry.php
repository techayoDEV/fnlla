<?php

declare(strict_types=1);

namespace Fnlla\Php\Support;

use InvalidArgumentException;
use RuntimeException;

final class TechnicalDebtRegistry
{
    public const STATUSES = ["open", "in_progress", "accepted", "resolved"];
    public const PRIORITIES = ["low", "normal", "high", "critical"];
    private LockedJsonStore $store;

    public function __construct(?string $path = null)
    {
        $this->store = new LockedJsonStore($path ?? (string) config("developer_tools.debt_path", storage_path("framework/developer/technical-debt.json")));
    }

    public function state(): array
    {
        return $this->store->read() + ["revision" => 0, "items" => [], "history" => []];
    }

    public function save(array $input, int $revision, string $actor): array
    {
        foreach (["id", "title", "status", "priority", "owner", "notes", "due_date"] as $field) {
            if (isset($input[$field]) && !is_string($input[$field])) {
                throw new InvalidArgumentException("Debt fields must contain text values.");
            }
        }
        $title = trim((string) ($input["title"] ?? ""));
        $status = (string) ($input["status"] ?? "open");
        $priority = (string) ($input["priority"] ?? "normal");
        $owner = trim((string) ($input["owner"] ?? ""));
        $notes = trim((string) ($input["notes"] ?? ""));
        $due = trim((string) ($input["due_date"] ?? ""));
        $date = $due === "" ? false : \DateTimeImmutable::createFromFormat("!Y-m-d", $due);
        if ($title === "" || strlen($title) > 160 || strlen($owner) > 160 || strlen($notes) > 2000
            || !in_array($status, self::STATUSES, true) || !in_array($priority, self::PRIORITIES, true)
            || ($due !== "" && ($date === false || $date->format("Y-m-d") !== $due))) {
            throw new InvalidArgumentException("Check the title, status, priority, owner, notes and due date.");
        }
        if ($status === "accepted" && $notes === "") {
            throw new InvalidArgumentException("Accepted debt requires a reason in the notes.");
        }
        return $this->mutate($revision, $actor, "save", function (array $state) use ($input, $title, $status, $priority, $owner, $notes, $due): array {
            $id = (string) ($input["id"] ?? "");
            if ($id !== "" && !isset($state["items"][$id])) {
                throw new InvalidArgumentException("Debt item no longer exists.");
            }
            $id = $id !== "" ? $id : bin2hex(random_bytes(16));
            $previous = $state["items"][$id] ?? ["id" => $id, "source" => "manual", "created_at" => gmdate(DATE_ATOM)];
            $state["items"][$id] = array_merge($previous, [
                "title" => $title, "status" => $status, "priority" => $priority, "owner" => $owner,
                "notes" => $notes, "due_date" => $due, "updated_at" => gmdate(DATE_ATOM),
            ]);
            return $state;
        });
    }

    public function synchronize(array $paths, int $revision, string $actor): array
    {
        return $this->mutate($revision, $actor, "scan", function (array $state) use ($paths): array {
            foreach ($state["items"] as &$item) {
                if (($item["source"] ?? "") === "marker") {
                    $item["observed"] = false;
                }
            }
            unset($item);
            foreach ($paths as $path) {
                $id = hash("sha256", "marker:" . $path);
                $state["items"][$id] ??= [
                    "id" => $id, "title" => "Review debt markers: " . $path, "source" => "marker",
                    "status" => "open", "priority" => "normal", "owner" => "", "notes" => "", "due_date" => "",
                    "created_at" => gmdate(DATE_ATOM),
                ];
                $state["items"][$id]["observed"] = true;
                $state["items"][$id]["last_seen_at"] = gmdate(DATE_ATOM);
            }
            $state["scanned_at"] = gmdate(DATE_ATOM);
            return $state;
        });
    }

    private function mutate(int $revision, string $actor, string $action, callable $change): array
    {
        return $this->store->update(function (array $state) use ($revision, $actor, $action, $change): array {
            $state += ["revision" => 0, "items" => [], "history" => []];
            if ($revision !== $state["revision"]) {
                throw new RuntimeException("This register changed. Reload before saving.");
            }
            $state = $change($state);
            if (count($state["items"]) > 500) {
                throw new RuntimeException("The register is limited to 500 items.");
            }
            $state["revision"]++;
            $state["schema"] = "fnlla.technical_debt_registry.v1";
            $state["history"][] = ["at" => gmdate(DATE_ATOM), "actor" => substr($actor, 0, 160), "action" => $action, "revision" => $state["revision"]];
            $state["history"] = array_slice($state["history"], -100);
            return $state;
        });
    }
}
