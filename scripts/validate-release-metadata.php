<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA MAINTAINER SCRIPT
File: scripts\validate-release-metadata.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- validates release-facing repository metadata, ownership links and official
  source-of-truth references before publication work
*/

$root = dirname(__DIR__);
$errors = [];

$requiredContains = [
    '.github/ISSUE_TEMPLATE/config.yml' => [
        'https://github.com/techayoDEV/fnlla/blob/main/.github/CONTRIBUTING.md',
        'https://github.com/techayoDEV/fnlla/releases',
    ],
    '.github/CONTRIBUTING.md' => [
        'techayoDEV/fnlla',
        'public/vendor/fnlla-runtime/',
    ],
    '.github/RELEASE_TEMPLATE.md' => [
        'public/vendor/fnlla-runtime/',
    ],
    'README.md' => [
        'techayoDEV/fnlla',
        'public/vendor/fnlla-runtime/',
    ],
    'MANIFEST.json' => [
        'https://github.com/techayoDEV/fnlla.git',
    ],
    'public/vendor/fnlla-runtime/MANIFEST.json' => [
        '"distribution_root": "."',
        'https://github.com/techayoDEV/fnlla.git',
    ],
];

$forbiddenPatterns = [
    '.github/ISSUE_TEMPLATE/config.yml' => [
        '/github\.com\/fnlla\/php/i',
        '/releases\/tag\/v1\.0\.14/i',
    ],
    '.github/CONTRIBUTING.md' => [
        '/techayoDEV\/fnlla-runtime/i',
    ],
    'README.md' => [
        '/techayoDEV\/fnlla-runtime/i',
    ],
    'public/vendor/fnlla-runtime/README.md' => [
        '/publish-fnlla-runtime\.mjs/i',
        '/dist\/fnlla-runtime/i',
    ],
];

foreach ($requiredContains as $relativePath => $needles) {
    $absolutePath = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    if (!is_file($absolutePath)) {
        $errors[] = "{$relativePath}: missing file";
        continue;
    }

    $contents = (string) file_get_contents($absolutePath);
    foreach ($needles as $needle) {
        if (!str_contains($contents, $needle)) {
            $errors[] = "{$relativePath}: missing required marker {$needle}";
        }
    }
}

foreach ($forbiddenPatterns as $relativePath => $patterns) {
    $absolutePath = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    if (!is_file($absolutePath)) {
        $errors[] = "{$relativePath}: missing file";
        continue;
    }

    $contents = (string) file_get_contents($absolutePath);
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $contents) === 1) {
            $errors[] = "{$relativePath}: forbidden release metadata marker {$pattern}";
        }
    }
}

if ($errors !== []) {
    fwrite(STDERR, "FNLLA release metadata audit failed." . PHP_EOL);
    foreach ($errors as $error) {
        fwrite(STDERR, "- {$error}" . PHP_EOL);
    }
    exit(1);
}

fwrite(STDOUT, "FNLLA release metadata passed." . PHP_EOL);
