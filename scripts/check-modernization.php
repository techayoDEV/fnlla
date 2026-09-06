<?php

declare(strict_types=1);

$root = dirname(__DIR__);
try {
    $ledger = json_decode((string) file_get_contents($root . "/resources/modernization-tasks.json"), true, 512, JSON_THROW_ON_ERROR);
    if (($ledger["schema"] ?? "") !== "fnlla.modernization_tasks.v1" || !is_array($ledger["tasks"] ?? null)) {
        throw new RuntimeException("Invalid modernization ledger.");
    }
    $safeFile = static function (mixed $path) use ($root): void {
        if (!is_string($path) || preg_match('~^(?:[A-Za-z0-9_.-]+/)*[A-Za-z0-9_.-]+$~D', $path) !== 1
            || in_array("..", explode("/", $path), true) || !is_file($root . "/" . $path)) {
            throw new RuntimeException("Missing or unsafe evidence/source file.");
        }
    };
    foreach ($ledger["sources"] as $source) { $safeFile($source); }
    $counts = ["done" => 0, "partial" => 0, "open" => 0, "blocked" => 0];
    $ids = [];
    foreach ($ledger["tasks"] as $task) {
        $id = $task["id"] ?? "";
        $status = $task["status"] ?? "";
        if (!is_string($id) || preg_match('/^[a-z][a-z0-9-]+$/D', $id) !== 1 || isset($ids[$id])
            || !isset($counts[$status]) || trim((string) ($task["acceptance"] ?? "")) === ""
            || !is_array($task["evidence"] ?? null) || $task["evidence"] === []) {
            throw new RuntimeException("Invalid task or missing acceptance/evidence.");
        }
        if ($status !== "done" && trim((string) ($task["remaining"] ?? "")) === "") {
            throw new RuntimeException("Unfinished task must explain remaining work: " . $id);
        }
        foreach ($task["evidence"] as $path) { $safeFile($path); }
        $ids[$id] = true;
        $counts[$status]++;
    }
    echo json_encode(["schema" => "fnlla.modernization_check.v1", "counts" => $counts,
        "unfinished" => array_values(array_filter($ledger["tasks"], static fn (array $task): bool => $task["status"] !== "done"))],
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
    // Schema/evidence checks are not a substitute for executing the test suites.
    exit(in_array("--require-complete", $argv, true) && $counts["done"] !== count($ids) ? 1 : 0);
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}
