<?php

declare(strict_types=1);

require dirname(__DIR__) . "/src/ReleaseStore.php";

try {
    $action = $argv[1] ?? "";
    $root = $argv[2] ?? "";
    if ($root === "" || !in_array($action, ["stage", "activate", "rollback", "status"], true)) {
        throw new RuntimeException("Usage: php fnlla-deploy.php stage ROOT SOURCE ID | activate ROOT ID EXPECTED_CURRENT | rollback ROOT EXPECTED_CURRENT | status ROOT. Use - for no current release.");
    }
    $store = new \Fnlla\Deploy\ReleaseStore($root);
    $validate = static function (string $release): bool {
        $script = $release . "/.fnlla/deployment-check.php";
        if (!is_file($script)) { throw new RuntimeException("Artifact must supply .fnlla/deployment-check.php returning true after readiness checks."); }
        return (static fn (string $release): mixed => require $release . "/.fnlla/deployment-check.php")($release) === true;
    };
    $result = match ($action) {
        "stage" => ["path" => $store->stage($argv[3] ?? "", $argv[4] ?? "")],
        "activate" => $store->activate($argv[3] ?? "", ($argv[4] ?? "") === "-" ? null : ($argv[4] ?? ""), $validate),
        "rollback" => $store->rollback($argv[3] ?? "", $validate),
        "status" => $store->state(),
    };
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}
