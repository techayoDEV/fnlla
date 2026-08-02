<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA MAINTAINER SCRIPT
File: scripts\static-analysis.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Provides a dependency-light static analysis profile and delegates to PHPStan
  or Psalm when maintainers install them locally.
*/

define("FNLLA_RUNTIME_SKIP_AUTO_GUARD", true);

$root = dirname(__DIR__);
$phpstan = $root . DIRECTORY_SEPARATOR . "vendor" . DIRECTORY_SEPARATOR . "bin" . DIRECTORY_SEPARATOR . (DIRECTORY_SEPARATOR === "\\" ? "phpstan.bat" : "phpstan");
$psalm = $root . DIRECTORY_SEPARATOR . "vendor" . DIRECTORY_SEPARATOR . "bin" . DIRECTORY_SEPARATOR . (DIRECTORY_SEPARATOR === "\\" ? "psalm.bat" : "psalm");

if (is_file($phpstan)) {
    passthru(escapeshellarg($phpstan) . " analyse src bootstrap routes config scripts tests --no-progress", $exitCode);
    exit((int) $exitCode);
}

if (is_file($psalm)) {
    passthru(escapeshellarg($psalm), $exitCode);
    exit((int) $exitCode);
}

$errors = [];
$files = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root . DIRECTORY_SEPARATOR . "src", RecursiveDirectoryIterator::SKIP_DOTS)
);

foreach ($iterator as $item) {
    if ($item->isFile() && $item->getExtension() === "php") {
        $files[] = $item->getPathname();
    }
}

foreach ($files as $file) {
    $contents = file_get_contents($file);

    if (!is_string($contents)) {
        continue;
    }

    if (!str_contains($contents, "declare(strict_types=1);")) {
        $errors[] = $file . " is missing strict_types.";
    }

    if (preg_match('/\b(var_dump|print_r|dd)\s*\(/', $contents) === 1) {
        $errors[] = $file . " contains debug output helpers.";
    }
}

if ($errors !== []) {
    foreach ($errors as $error) {
        fwrite(STDERR, $error . PHP_EOL);
    }

    exit(1);
}

fwrite(STDOUT, "Static analysis baseline passed. Install PHPStan or Psalm locally for deeper checks." . PHP_EOL);
