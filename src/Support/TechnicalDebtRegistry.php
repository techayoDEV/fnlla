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

    public static function runtimeIssueDebtId(string $fingerprint): string
    {
        return hash("sha256", "runtime-issue:" . $fingerprint);
    }

    public function save(array $input, int $revision, string $actor): array
    {
        foreach (["id", "title", "status", "priority", "owner", "notes", "due_date", "accepted_until", "issue_ref", "adr_ref", "evidence_ref"] as $field) {
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
        $acceptedUntil = trim((string) ($input["accepted_until"] ?? ""));
        $issueRef = $this->cleanReference((string) ($input["issue_ref"] ?? ""));
        $adrRef = $this->cleanReference((string) ($input["adr_ref"] ?? ""));
        $evidenceRef = $this->cleanReference((string) ($input["evidence_ref"] ?? ""));
        $date = $due === "" ? false : \DateTimeImmutable::createFromFormat("!Y-m-d", $due);
        $acceptedUntilDate = $acceptedUntil === "" ? false : \DateTimeImmutable::createFromFormat("!Y-m-d", $acceptedUntil);
        if ($title === "" || strlen($title) > 160 || strlen($owner) > 160 || strlen($notes) > 2000
            || !in_array($status, self::STATUSES, true) || !in_array($priority, self::PRIORITIES, true)
            || ($due !== "" && ($date === false || $date->format("Y-m-d") !== $due))
            || ($acceptedUntil !== "" && ($acceptedUntilDate === false || $acceptedUntilDate->format("Y-m-d") !== $acceptedUntil))) {
            throw new InvalidArgumentException("Check the title, status, priority, owner, notes, links and dates.");
        }
        if ($status === "accepted" && ($notes === "" || $acceptedUntil === "")) {
            throw new InvalidArgumentException("Accepted debt requires a reason in the notes and an expiry date.");
        }
        return $this->mutate($revision, $actor, "save", function (array $state) use ($input, $title, $status, $priority, $owner, $notes, $due, $acceptedUntil, $issueRef, $adrRef, $evidenceRef): array {
            $id = (string) ($input["id"] ?? "");
            if ($id !== "" && !isset($state["items"][$id])) {
                throw new InvalidArgumentException("Debt item no longer exists.");
            }
            $id = $id !== "" ? $id : bin2hex(random_bytes(16));
            $previous = $state["items"][$id] ?? ["id" => $id, "source" => "manual", "created_at" => gmdate(DATE_ATOM)];
            $state["items"][$id] = array_merge($previous, [
                "title" => $title, "status" => $status, "priority" => $priority, "owner" => $owner,
                "notes" => $notes, "due_date" => $due, "accepted_until" => $acceptedUntil,
                "issue_ref" => $issueRef, "adr_ref" => $adrRef, "evidence_ref" => $evidenceRef,
                "updated_at" => gmdate(DATE_ATOM),
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
                    "accepted_until" => "", "issue_ref" => "", "adr_ref" => "", "evidence_ref" => "",
                    "created_at" => gmdate(DATE_ATOM),
                ];
                $state["items"][$id]["observed"] = true;
                $state["items"][$id]["last_seen_at"] = gmdate(DATE_ATOM);
            }
            $state["scanned_at"] = gmdate(DATE_ATOM);
            return $state;
        });
    }

    public function promoteRuntimeIssue(array $issue, int $revision, string $actor): array
    {
        $fingerprint = trim((string) ($issue["fingerprint"] ?? $issue["id"] ?? ""));

        if ($fingerprint === "" || strlen($fingerprint) > 128) {
            throw new InvalidArgumentException("Runtime issue fingerprint is invalid.");
        }

        $id = self::runtimeIssueDebtId($fingerprint);
        $title = trim((string) ($issue["title"] ?? "Review runtime issue"));
        $title = $title !== "" ? substr($title, 0, 160) : "Review runtime issue";
        $occurrences = max(1, (int) ($issue["occurrences"] ?? 1));
        $priority = "high";
        $notes = substr(implode("\n", array_filter([
            "Promoted from the runtime issue tracker.",
            "Fingerprint: " . $fingerprint,
            "Occurrences: " . (string) $occurrences,
            "Route: " . trim((string) ($issue["route"] ?? "")),
            "Location: " . trim((string) ($issue["file"] ?? "")) . ":" . (string) max(0, (int) ($issue["line"] ?? 0)),
            "Last request: " . trim((string) ($issue["last_request_id"] ?? "")),
        ], static fn (string $line): bool => trim($line) !== "")), 0, 2000);

        return $this->mutate($revision, $actor, "promote_runtime_issue", function (array $state) use ($id, $fingerprint, $title, $priority, $notes, $issue, $occurrences): array {
            $previous = is_array($state["items"][$id] ?? null) ? (array) $state["items"][$id] : [
                "id" => $id,
                "source" => "runtime_issue",
                "created_at" => gmdate(DATE_ATOM),
                "status" => "open",
                "priority" => $priority,
                "owner" => "",
                "notes" => $notes,
                "due_date" => "",
                "accepted_until" => "",
                "issue_ref" => "",
                "adr_ref" => "",
                "evidence_ref" => "",
            ];

            $state["items"][$id] = array_merge($previous, [
                "id" => $id,
                "title" => $title,
                "source" => "runtime_issue",
                "runtime_issue_id" => $fingerprint,
                "runtime_issue_last_seen_at" => (string) ($issue["last_seen_at"] ?? ""),
                "runtime_issue_occurrences" => $occurrences,
                "updated_at" => gmdate(DATE_ATOM),
            ]);

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

    private function cleanReference(string $value): string
    {
        $value = trim((string) preg_replace('/\s+/', ' ', strip_tags($value)));
        if (strlen($value) > 240) {
            $value = substr($value, 0, 240);
        }

        if ($value === "") {
            return "";
        }

        if (preg_match('/^(https?:\/\/|\/|#|docs\/|\.github\/)[^\s<>"]+$/i', $value) !== 1) {
            throw new InvalidArgumentException("Debt references must be HTTPS/HTTP URLs, internal paths, anchors or docs/.github references.");
        }

        return $value;
    }
}
