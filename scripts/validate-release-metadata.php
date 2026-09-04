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
        'docs/BUSINESS-APP-REFERENCE.md',
        'docs/PRODUCTION-CHECKLIST.md',
        'docs/ENVIRONMENT.md',
        'resources/business-reference/2.1/',
        'docs/TECH-DEBT-AND-FUTURE-PROOFING.md',
    ],
    'CHANGELOG.md' => [
        '## 2.1.3 - 2026-09-04',
        'tech-debt:update',
        'Developer Operations Panel',
        '## 2.1.1 - 2026-08-28',
        '## 2.1.0 - 2026-08-28',
        'project:acceptance',
        'ops:backup-plan',
        'security:audit --strict',
    ],
    'docs/PUBLIC-API.md' => [
        'ops:backup-plan',
        'project:acceptance',
        'tech-debt:update',
        'db()',
        'QueryBuilder::paginate()',
    ],
    'docs/BUSINESS-APP-REFERENCE.md' => [
        'resources/business-reference/2.1/blueprint.json',
        'admin',
        'operator',
        'client',
        'security:audit --strict',
    ],
    'docs/PRODUCTION-CHECKLIST.md' => [
        'security:audit --strict',
        'ops:backup-plan',
        'project:acceptance --json',
        'APP_DEBUG=false',
    ],
    'docs/TECH-DEBT-AND-FUTURE-PROOFING.md' => [
        'php fnlla tech-debt:update',
        'fnlla.technical_debt_report.v1',
        'FNLLA_TECH_DEBT_REPORT:BEGIN',
    ],
    'docs/PUBLIC-API.lock.json' => [
        'tech-debt:update',
        'fnlla.technical_debt_report.v1',
        'fnlla.technical_debt_update.v1',
    ],
    'docs/ENVIRONMENT.md' => [
        '.env.full.example',
        'CLIENT_PREVIEW_ENABLED',
        'Fionn Bridge',
        'AI_FIONN_ENDPOINT',
    ],
    'docs/UPGRADE-2.1.1.md' => [
        '2.1.0 to 2.1.1',
        'project:acceptance --json',
        'ops:backup-plan --verify',
    ],
    'docs/UPGRADE-2.1.md' => [
        '2.0.x to 2.1.0',
        'framework:update --dry-run',
        'ops:backup-plan',
    ],
    'resources/business-reference/2.1/MANIFEST.json' => [
        '"schema": "fnlla.business_reference.v1"',
        '"version": "2.1.1"',
        '"maintenance_preview"',
    ],
    'resources/business-reference/2.1/blueprint.json' => [
        '"schema": "fnlla.business_app_blueprint.v1"',
        '"admin.access"',
        '"client.records.view"',
    ],
    'resources/performance-baselines/2.1-policy.json' => [
        '"schema": "fnlla.performance_baseline_policy.v1"',
        '"project.export"',
        '"http.health"',
    ],
    'MANIFEST.json' => [
        'https://github.com/techayoDEV/fnlla.git',
    ],
    'public/vendor/fnlla-runtime/MANIFEST.json' => [
        '"distribution_root": "."',
        'https://github.com/techayoDEV/fnlla.git',
    ],
    'resources/fnlla-ai-runtime/MANIFEST.json' => [
        '"slug": "fnlla-ai-runtime"',
        '"external_calls": false',
        '"prompt_registry_path": "prompts/registry.json"',
        '"evals_directory": "evals"',
        'https://github.com/techayoDEV/fnlla.git',
    ],
    'resources/fnlla-ai-runtime/README.md' => [
        'resources/fnlla-ai-runtime',
        'AI_RUNTIME_LOAD_INTEGRATED',
        'prompts/registry.json',
    ],
    'resources/fnlla-ai-runtime/prompts/registry.json' => [
        '"schema": "fnlla.ai_prompt_registry.v1"',
        '"id": "review.release-risk"',
    ],
    'resources/fnlla-ai-runtime/evals/runtime-commands.json' => [
        '"schema": "fnlla.ai_eval_fixture.v1"',
        '"id": "ai-ask-release-readiness"',
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

$forbiddenPublishedMarkers = array_map(
    static fn (array $parts): string => '/\b' . preg_quote(implode('', $parts), '/') . '\b/i',
    [
        ['Co', 'dex'],
        ['Chat', 'G', 'PT'],
        ['Open', 'A', 'I'],
        ['Clau', 'de'],
        ['Anth', 'ropic'],
        ['Google', ' ', 'Gem', 'ini'],
        ['Gem', 'ini', ' ', 'A', 'I'],
    ]
);

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

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS)
);

foreach ($iterator as $item) {
    if (!$item->isFile()) {
        continue;
    }

    $relativePath = str_replace('\\', '/', substr($item->getPathname(), strlen($root) + 1));

    if (preg_match('#^(\.git|dist|storage|vendor)/#', $relativePath) === 1) {
        continue;
    }

    if (preg_match('/\.(png|jpe?g|gif|webp|ico|pdf|zip|phar)$/i', $relativePath) === 1) {
        continue;
    }

    $contents = (string) file_get_contents($item->getPathname());

    foreach ($forbiddenPublishedMarkers as $pattern) {
        if (preg_match($pattern, $contents) === 1) {
            $errors[] = "{$relativePath}: forbidden assistant-vendor marker {$pattern}";
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
