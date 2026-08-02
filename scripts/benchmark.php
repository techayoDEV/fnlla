<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA MAINTAINER SCRIPT
File: scripts\benchmark.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This script belongs to the maintained repository workflow for
the public MIT-licensed FNLLA framework.

Purpose:
- Measures local FNLLA CLI, HTTP, export and repository-footprint performance.
*/

use Fnlla\Php\Support\ProcessRunner;

define("FNLLA_RUNTIME_SKIP_AUTO_GUARD", true);

$root = dirname(__DIR__);
$productionMode = option_enabled("production");

if ($productionMode) {
    apply_benchmark_environment();
}

require $root . DIRECTORY_SEPARATOR . "bootstrap" . DIRECTORY_SEPARATOR . "common.php";

$iterations = max(1, (int) option_value("iterations", "5"));
$requests = max(1, (int) option_value("requests", "30"));
$includeExport = !option_enabled("no-export");
$includeHttp = !option_enabled("no-http");

if ($productionMode) {
    run_command($root, [PHP_BINARY, $root . DIRECTORY_SEPARATOR . "fnlla", "optimize"]);
}

/*
Benchmarking should measure the code path, not residue from a previous run.
The application cache also stores rate-limiter counters and health snapshots,
so clear it once before collecting HTTP timings.
*/
run_command($root, [PHP_BINARY, $root . DIRECTORY_SEPARATOR . "fnlla", "cache:clear"]);

fwrite(STDOUT, "FNLLA benchmark" . PHP_EOL);
fwrite(STDOUT, "Root: " . $root . PHP_EOL);
fwrite(STDOUT, "PHP: " . PHP_VERSION . PHP_EOL . PHP_EOL);
fwrite(STDOUT, "Mode: " . ($productionMode ? "production-like, optimized bootstrap" : "current environment") . PHP_EOL . PHP_EOL);

print_table("Footprint", footprint($root));
print_table("CLI", benchmark_cli($root, $iterations));

if ($includeHttp) {
    print_table("HTTP", benchmark_http($root, $requests));
}

if ($includeExport) {
    print_table("Export", benchmark_export($root));
}

function option_enabled(string $name): bool
{
    return in_array("--" . $name, $_SERVER["argv"] ?? [], true);
}

function option_value(string $name, string $default): string
{
    $argv = $_SERVER["argv"] ?? [];

    foreach ($argv as $index => $argument) {
        if ($argument === "--" . $name) {
            return (string) ($argv[$index + 1] ?? $default);
        }

        if (str_starts_with($argument, "--" . $name . "=")) {
            return substr($argument, strlen($name) + 3);
        }
    }

    return $default;
}

function apply_benchmark_environment(): void
{
    $values = [
        "APP_ENV" => "production",
        "APP_DEBUG" => "false",
        "APP_URL" => "http://127.0.0.1",
        "FNLLA_RUNTIME_ENFORCE" => "false",
        "FNLLA_RUNTIME_AUTO_SYNC" => "false",
        "FRAMEWORK_UPDATE_UI_ENABLED" => "false",
        "FRAMEWORK_UPDATE_GITHUB_ENABLED" => "false",
        "MAINTENANCE_MODE_ENABLED" => "false",
        "MAINTENANCE_SETUP_UI_ENABLED" => "false",
        "DEVELOPER_ACCESS_ENABLED" => "false",
        "DEVELOPER_ACCESS_SETUP_UI_ENABLED" => "false",
        "HEALTH_CACHE_TTL_SECONDS" => "30",
        "CACHE_SERIALIZER" => "json",
    ];

    foreach ($values as $key => $value) {
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
        putenv($key . "=" . $value);
    }
}

function footprint(string $root): array
{
    $paths = [
        "src",
        "bootstrap",
        "config",
        "routes",
        "views",
        "public",
        "public/vendor/fnlla-runtime",
        "tests",
        "docs",
        "scripts",
    ];
    $rows = [];

    foreach ($paths as $path) {
        $absolute = $root . DIRECTORY_SEPARATOR . str_replace("/", DIRECTORY_SEPARATOR, $path);

        if (!is_dir($absolute)) {
            continue;
        }

        [$files, $bytes] = count_files($absolute);
        $rows[] = [
            "path" => $path,
            "files" => (string) $files,
            "mb" => sprintf("%.2f", $bytes / 1048576),
        ];
    }

    return $rows;
}

function count_files(string $directory): array
{
    $files = 0;
    $bytes = 0;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)
    );

    foreach ($iterator as $item) {
        if (!$item->isFile()) {
            continue;
        }

        $files++;
        $bytes += $item->getSize();
    }

    return [$files, $bytes];
}

function benchmark_cli(string $root, int $iterations): array
{
    $commands = [
        ["label" => "php fnlla list", "args" => ["list"]],
        ["label" => "php fnlla route:list", "args" => ["route:list"]],
        ["label" => "php fnlla fnlla-runtime:validate", "args" => ["fnlla-runtime:validate"]],
        ["label" => "php fnlla version:status", "args" => ["version:status"]],
    ];
    $rows = [];

    foreach ($commands as $command) {
        $times = [];

        for ($i = 0; $i < $iterations; $i++) {
            $started = microtime(true);
            run_command($root, array_merge([PHP_BINARY, $root . DIRECTORY_SEPARATOR . "fnlla"], $command["args"]));
            $times[] = (microtime(true) - $started) * 1000;
        }

        $rows[] = timing_row((string) $command["label"], $times);
    }

    return $rows;
}

function benchmark_http(string $root, int $requests): array
{
    $port = random_int(18080, 22000);
    $command = [
        PHP_BINARY,
        "-S",
        "127.0.0.1:" . $port,
        "-t",
        "public",
        "public/router.php",
    ];
    $descriptors = [
        0 => ["pipe", "r"],
        1 => ["file", null_device(), "a"],
        2 => ["file", null_device(), "a"],
    ];
    $process = proc_open($command, $descriptors, $pipes, $root);

    if (!is_resource($process)) {
        return [[
            "name" => "built-in server",
            "avg_ms" => "n/a",
            "min_ms" => "n/a",
            "max_ms" => "n/a",
        ]];
    }

    try {
        wait_for_http("http://127.0.0.1:" . $port . "/api/health?format=json");
        $paths = ["/", "/about", "/api/health?format=json", "/assets/app.css"];
        $rows = [];

        foreach ($paths as $path) {
            $times = [];
            $bytes = 0;
            $failures = 0;
            $statuses = [];

            for ($i = 0; $i < $requests; $i++) {
                $started = microtime(true);
                $result = http_get("http://127.0.0.1:" . $port . $path);
                $times[] = (microtime(true) - $started) * 1000;
                $bytes += strlen($result["body"]);
                $statuses[] = (string) $result["status"];

                if ($result["status"] < 200 || $result["status"] >= 400) {
                    $failures++;
                }
            }

            $row = timing_row($path, $times);
            $row["status"] = implode("/", array_values(array_unique($statuses)));
            $row["failures"] = (string) $failures;
            $row["kb_total"] = sprintf("%.2f", $bytes / 1024);
            $rows[] = $row;
        }

        return $rows;
    } finally {
        terminate_process($process);
        foreach ($pipes as $pipe) {
            fclose($pipe);
        }
        proc_close($process);
    }
}

function wait_for_http(string $url): void
{
    $deadline = microtime(true) + 5;

    while (microtime(true) < $deadline) {
        if (@file_get_contents($url) !== false) {
            return;
        }

        usleep(100000);
    }
}

function http_get(string $url): array
{
    $headers = [];
    $context = stream_context_create([
        "http" => [
            "method" => "GET",
            "timeout" => 5,
            "ignore_errors" => true,
            "header" => "Accept: */*\r\n",
        ],
    ]);
    $body = @file_get_contents($url, false, $context);
    $status = 0;

    foreach ($http_response_header ?? [] as $header) {
        if (preg_match('/^HTTP\/\S+\s+(\d{3})/', (string) $header, $matches) === 1) {
            $status = (int) $matches[1];
            break;
        }

        $headers[] = (string) $header;
    }

    return [
        "status" => $status,
        "body" => is_string($body) ? $body : "",
        "headers" => $headers,
    ];
}

function benchmark_export(string $root): array
{
    $target = rtrim(sys_get_temp_dir(), "\\/") . DIRECTORY_SEPARATOR . "fnlla-benchmark-export-" . bin2hex(random_bytes(4));
    $started = microtime(true);

    try {
        run_command($root, [
            PHP_BINARY,
            $root . DIRECTORY_SEPARATOR . "fnlla",
            "make:project",
            $target,
            "Benchmark Export",
        ]);

        [$files, $bytes] = count_files($target);
        $elapsedMs = (microtime(true) - $started) * 1000;

        return [[
            "name" => "make:project",
            "avg_ms" => sprintf("%.2f", $elapsedMs),
            "min_ms" => sprintf("%.2f", $elapsedMs),
            "max_ms" => sprintf("%.2f", $elapsedMs),
            "files" => (string) $files,
            "mb" => sprintf("%.2f", $bytes / 1048576),
        ]];
    } finally {
        remove_directory($target);
    }
}

function timing_row(string $name, array $times): array
{
    sort($times);

    return [
        "name" => $name,
        "avg_ms" => sprintf("%.2f", array_sum($times) / count($times)),
        "p50_ms" => sprintf("%.2f", percentile($times, 50)),
        "p95_ms" => sprintf("%.2f", percentile($times, 95)),
        "min_ms" => sprintf("%.2f", min($times)),
        "max_ms" => sprintf("%.2f", max($times)),
    ];
}

function run_command(string $workingDirectory, array $command): void
{
    $result = ProcessRunner::run($command, $workingDirectory, 120);

    if ($result["exit_code"] !== 0) {
        throw new RuntimeException("Benchmark command failed: " . ProcessRunner::describe($command) . PHP_EOL . $result["output"]);
    }
}

function terminate_process(mixed $process): void
{
    if (!is_resource($process)) {
        return;
    }

    $status = proc_get_status($process);
    $pid = (int) ($status["pid"] ?? 0);

    if ($pid > 0 && DIRECTORY_SEPARATOR === "\\") {
        $taskkillPath = ProcessRunner::findExecutable("taskkill");

        if ($taskkillPath !== null) {
            ProcessRunner::run([$taskkillPath, "/PID", (string) $pid, "/T", "/F"], null, 10);
            return;
        }
    }

    proc_terminate($process);
}

function percentile(array $sortedTimes, int $percentile): float
{
    if ($sortedTimes === []) {
        return 0.0;
    }

    $index = (int) ceil(($percentile / 100) * count($sortedTimes)) - 1;
    $index = max(0, min(count($sortedTimes) - 1, $index));

    return (float) $sortedTimes[$index];
}

function print_table(string $title, array $rows): void
{
    fwrite(STDOUT, $title . PHP_EOL);

    if ($rows === []) {
        fwrite(STDOUT, "  n/a" . PHP_EOL . PHP_EOL);
        return;
    }

    $columns = array_keys($rows[0]);
    $widths = [];

    foreach ($columns as $column) {
        $widths[$column] = strlen($column);
    }

    foreach ($rows as $row) {
        foreach ($columns as $column) {
            $widths[$column] = max($widths[$column], strlen((string) ($row[$column] ?? "")));
        }
    }

    fwrite(STDOUT, "  " . implode("  ", array_map(
        static fn (string $column): string => str_pad($column, $widths[$column]),
        $columns
    )) . PHP_EOL);

    foreach ($rows as $row) {
        fwrite(STDOUT, "  " . implode("  ", array_map(
            static fn (string $column): string => str_pad((string) ($row[$column] ?? ""), $widths[$column]),
            $columns
        )) . PHP_EOL);
    }

    fwrite(STDOUT, PHP_EOL);
    fflush(STDOUT);
}

function null_device(): string
{
    return DIRECTORY_SEPARATOR === "\\" ? "NUL" : "/dev/null";
}

function remove_directory(string $path): void
{
    if (!is_dir($path)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($iterator as $item) {
        if ($item->isDir()) {
            rmdir($item->getPathname());
        } else {
            unlink($item->getPathname());
        }
    }

    rmdir($path);
}
