<?php

declare(strict_types=1);

// Offline, file-only drill. Never boots the supplied application or runs its workers.
$options = getopt("", ["source:", "output:", "rpo-seconds:", "rto-seconds:", "quiesced"]);
if (!isset($options["source"], $options["output"], $options["quiesced"])) {
    fwrite(STDERR, "Usage: php scripts/recovery-drill.php --source=PROJECT --output=NEW_PRIVATE_DIRECTORY --quiesced [--rpo-seconds=86400] [--rto-seconds=3600]\nStop source writers first. Includes .env; use a restricted directory outside the web root. No database or external effects are restored.\n");
    exit(1);
}

function inventory(string $root): array
{
    $files = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        $relative = str_replace("\\", "/", substr($file->getPathname(), strlen($root) + 1));
        if (preg_match('#^(\.git|vendor|\.fnlla/update-transaction|storage/(framework/(cache|sessions|queue)|logs))/#', $relative) === 1) {
            continue;
        }
        if ($file->isLink()) { throw new RuntimeException("Drill refuses symbolic links."); }
        if (!$file->isFile()) { continue; }
        $hash = hash_file("sha256", $file->getPathname());
        if ($hash === false) { throw new RuntimeException("Cannot hash source file."); }
        $files[$relative] = $hash;
    }
    ksort($files);
    return $files;
}

function copyInventory(string $source, string $target, array $files): void
{
    foreach ($files as $relative => $hash) {
        $destination = $target . "/" . $relative;
        if (!is_dir(dirname($destination)) && !mkdir(dirname($destination), 0700, true)) {
            throw new RuntimeException("Cannot create restore directory.");
        }
        if (!copy($source . "/" . $relative, $destination) || hash_file("sha256", $destination) !== $hash) {
            throw new RuntimeException("Copy integrity check failed.");
        }
    }
}

try {
    $source = realpath((string) $options["source"]);
    $requested = rtrim(str_replace("\\", "/", (string) $options["output"]), "/");
    $parent = realpath(dirname($requested));
    $leaf = basename($requested);
    if (!$source || !$parent || !is_file($source . "/fnlla") || !preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]+$/D', $leaf)) {
        throw new RuntimeException("Supply an existing FNLLA project and an existing private output parent.");
    }
    $source = str_replace("\\", "/", $source);
    $output = str_replace("\\", "/", $parent) . "/" . $leaf;
    if (file_exists($output) || is_link($output) || str_starts_with(strtolower($output . "/"), strtolower($source . "/"))) {
        throw new RuntimeException("Output must be new and outside the source project.");
    }
    $rpo = filter_var($options["rpo-seconds"] ?? 86400, FILTER_VALIDATE_INT, ["options" => ["min_range" => 0]]);
    $rto = filter_var($options["rto-seconds"] ?? 3600, FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]);
    if ($rpo === false || $rto === false) { throw new RuntimeException("Invalid RPO/RTO."); }
    if (!mkdir($output, 0700)) { throw new RuntimeException("Cannot create private drill directory."); }
    $before = inventory($source);
    copyInventory($source, $output . "/snapshot", $before);
    if (inventory($source) !== $before) { throw new RuntimeException("Source changed while snapshotting. Stop writers and repeat."); }
    $snapshotAt = time();
    $start = hrtime(true);
    $incidentAt = time();
    copyInventory($output . "/snapshot", $output . "/restored", $before);
    $restored = inventory($output . "/restored");
    $unchanged = inventory($source) === $before;
    $seconds = (hrtime(true) - $start) / 1e9;
    $passed = $restored === $before && $unchanged && $seconds <= $rto && $incidentAt - $snapshotAt <= $rpo;
    $report = [
        "schema" => "fnlla.file_recovery_drill.v1", "generated_at_utc" => gmdate(DATE_ATOM),
        "passed" => $passed, "files_verified" => count($before), "source_unchanged" => $unchanged,
        "policy" => ["rpo_seconds" => $rpo, "rto_seconds" => $rto],
        "measured_restore_seconds" => round($seconds, 4), "snapshot_age_at_simulated_incident_seconds" => $incidentAt - $snapshotAt,
        "scope" => "Quiesced file snapshot restored to a new isolated directory; includes secrets but excludes vendor, sessions, queues, cache and logs.",
        "not_verified" => ["database restore", "business reconciliation", "scheduled backup freshness", "offsite retrieval", "production availability", "secret-store recovery"],
        "external_effects" => "No application boot, queue replay, payment, webhook or email was executed.",
    ];
    $json = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
    if (file_put_contents($output . "/report.json", $json) !== strlen($json)) { throw new RuntimeException("Cannot save drill report."); }
    echo $json;
    exit($passed ? 0 : 1);
} catch (Throwable $error) {
    fwrite(STDERR, "Recovery drill failed: " . $error->getMessage() . "\n");
    exit(1);
}
