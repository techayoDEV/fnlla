<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA PHP CONSOLE SOURCE
File: src\Console\Commands\MakeProjectCommand.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA PHP is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA PHP framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Implements the maintained CLI surface and scheduler-oriented console behavior.
*/

namespace Fnlla\Php\Console\Commands;

use Fnlla\Php\Console\Command;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

final class MakeProjectCommand extends Command
{
    public function name(): string
    {
        return "make:project";
    }

    public function description(): string
    {
        return "Export a clean FNLLA PHP starter into a new project directory.";
    }

    public function handle(array $arguments): int
    {
        $targetArgument = trim((string) ($arguments[0] ?? ""));
        $appNameArgument = trim(implode(" ", array_slice($arguments, 1)));

        if ($targetArgument === "") {
            $this->error("Usage: make:project <target-path> [App Name]");

            return 1;
        }

        $sourceRoot = $this->normalizePath(base_path());
        $targetPath = $this->resolveTargetPath($targetArgument);

        if ($this->pathsEqual($sourceRoot, $targetPath)) {
            $this->error("Target path cannot be the FNLLA PHP source repository itself.");

            return 1;
        }

        if ($this->isChildPath($targetPath, $sourceRoot)) {
            $this->error("Target path must be outside the FNLLA PHP source repository to avoid recursive copies.");

            return 1;
        }

        $appName = $appNameArgument !== "" ? $appNameArgument : $this->guessAppName($targetPath);
        $packageSlug = $this->slugify($appName);

        if ($packageSlug === "") {
            $this->error("Unable to derive a valid project slug from the provided name or path.");

            return 1;
        }

        try {
            $this->prepareTargetDirectory($targetPath);
            $this->copyStarterTree($sourceRoot, $targetPath);
            $this->customizeExport($targetPath, $appName, $packageSlug);
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return 1;
        }

        $this->line("Exported FNLLA PHP starter to: " . $targetPath);
        $this->line("Application name: " . $appName);
        $this->line("");
        $this->line("Next steps:");
        $this->line("1. Open the new project directory.");
        $this->line("2. Copy .env.example to .env and set APP_URL plus MySQL credentials.");
        $this->line("3. Review routes/web.php, src/Controllers/ and views/pages/ and replace the demo surface with your project pages.");
        $this->line("4. Run php fnlla fnlla-ui:validate, php scripts/test.php, php scripts/lint.php and php scripts/validate-version-manifest.php.");
        $this->line("5. Initialize a separate Git repository for the new website or application.");

        return 0;
    }

    private function prepareTargetDirectory(string $targetPath): void
    {
        if (is_dir($targetPath)) {
            $entries = scandir($targetPath);

            if ($entries === false) {
                throw new RuntimeException("Unable to inspect target directory: " . $targetPath);
            }

            $visibleEntries = array_values(array_diff($entries, [".", ".."]));

            if ($visibleEntries !== []) {
                throw new RuntimeException("Target directory must be empty: " . $targetPath);
            }

            return;
        }

        if (file_exists($targetPath) && !is_dir($targetPath)) {
            throw new RuntimeException("Target path already exists and is not a directory: " . $targetPath);
        }

        if (!mkdir($targetPath, 0777, true) && !is_dir($targetPath)) {
            throw new RuntimeException("Unable to create target directory: " . $targetPath);
        }
    }

    private function copyStarterTree(string $sourceRoot, string $targetRoot): void
    {
        $iterator = new FilesystemIterator($sourceRoot, FilesystemIterator::SKIP_DOTS);

        foreach ($iterator as $fileInfo) {
            $name = $fileInfo->getFilename();

            if ($this->shouldSkipRootEntry($name)) {
                continue;
            }

            $sourcePath = $fileInfo->getPathname();
            $targetPath = $targetRoot . DIRECTORY_SEPARATOR . $name;

            $this->copyPath($sourcePath, $targetPath);
        }
    }

    private function shouldSkipRootEntry(string $name): bool
    {
        return in_array($name, [
            ".git",
            ".github",
            "CODE_OF_CONDUCT.md",
            "README.md",
            "SECURITY.md",
        ], true);
    }

    private function copyPath(string $sourcePath, string $targetPath): void
    {
        if (is_dir($sourcePath)) {
            if (!is_dir($targetPath) && !mkdir($targetPath, 0777, true) && !is_dir($targetPath)) {
                throw new RuntimeException("Unable to create directory during export: " . $targetPath);
            }

            $iterator = new FilesystemIterator($sourcePath, FilesystemIterator::SKIP_DOTS);

            foreach ($iterator as $fileInfo) {
                $name = $fileInfo->getFilename();
                $childSource = $fileInfo->getPathname();
                $childTarget = $targetPath . DIRECTORY_SEPARATOR . $name;
                $relativeSource = $this->normalizeSeparators(substr($childSource, strlen(base_path()) + 1));

                if ($this->shouldSkipRelativeEntry($relativeSource)) {
                    continue;
                }

                $this->copyPath($childSource, $childTarget);
            }

            return;
        }

        if (!copy($sourcePath, $targetPath)) {
            throw new RuntimeException("Unable to copy file during export: " . $sourcePath);
        }
    }

    private function shouldSkipRelativeEntry(string $relativePath): bool
    {
        return in_array($relativePath, [
            "scripts/apply-techayo-metadata.ps1",
            "test-fnlla-php.cmd",
            "lint-fnlla-php.cmd",
        ], true);
    }

    private function customizeExport(string $targetRoot, string $appName, string $packageSlug): void
    {
        $this->rewriteAppConfig($targetRoot, $appName);
        $this->rewriteComposerMetadata($targetRoot, $appName, $packageSlug);
        $this->rewriteStarterReadme($targetRoot, $appName);
        $this->rewriteProjectLaunchers($targetRoot);
    }

    private function rewriteAppConfig(string $targetRoot, string $appName): void
    {
        $path = $targetRoot . DIRECTORY_SEPARATOR . "config" . DIRECTORY_SEPARATOR . "app.php";
        $contents = (string) file_get_contents($path);
        $replacement = "'name' => " . var_export($appName, true) . ",";
        $updated = preg_replace("/\"name\" => \"FNLLA PHP\",/", $replacement, $contents, 1);

        if (!is_string($updated)) {
            throw new RuntimeException("Unable to update config/app.php for exported project.");
        }

        file_put_contents($path, $updated);
    }

    private function rewriteComposerMetadata(string $targetRoot, string $appName, string $packageSlug): void
    {
        $path = $targetRoot . DIRECTORY_SEPARATOR . "composer.json";
        $decoded = json_decode((string) file_get_contents($path), true);

        if (!is_array($decoded)) {
            throw new RuntimeException("Unable to decode composer.json for exported project.");
        }

        $decoded["name"] = "project/" . $packageSlug;
        $decoded["description"] = $appName . " built on FNLLA PHP and FNLLA UI.";

        file_put_contents(
            $path,
            json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL
        );
    }

    private function rewriteStarterReadme(string $targetRoot, string $appName): void
    {
        $readme = <<<MD
# {$appName}

This repository is a working project starter exported from `fnlla/php`.

It is intended to be the beginning of a new server-rendered website or web application built on:

- FNLLA PHP
- FNLLA UI
- PHP 8.3
- MySQL

## What is already included

- the FNLLA PHP application core
- the vendored FNLLA UI runtime under `public/vendor/fnlla-ui/`
- machine-readable release metadata in `MANIFEST.json`
- root legal and policy files: `LICENSE.md`, `SUPPORT.md`, `TRADEMARKS.md`
- routes, controllers and views
- sessions, cookies, CSRF and auth foundations
- MySQL migrations, seeders and factories
- local lint, test, version metadata and FNLLA UI validation scripts

## How to start working

1. Copy `.env.example` to `.env`.
2. Set `APP_URL` and your MySQL credentials.
3. Run:

```bash
php fnlla fnlla-ui:validate
php scripts/test.php
php scripts/lint.php
php scripts/validate-version-manifest.php
```

4. Start the local server:

```bash
php -S 127.0.0.1:8080 -t public public/router.php
```

5. Open `http://127.0.0.1:8080` in your browser.

For Apache environments, use `public/` as the document root.
The exported project already includes `public/.htaccess`.

## First files to replace or review

- `routes/web.php`
- `src/Controllers/`
- `views/pages/`
- `public/assets/app.css`
- `database/migrations/`
- `config/app.php`

## Important note

The exported project still contains a working demonstration surface so the starter runs immediately.

That demo is a starting point, not the final product. Replace the placeholder pages, routes and content with the real website or application flow for this project.

Use `LICENSE.md`, `SUPPORT.md` and `TRADEMARKS.md` to understand the upstream FNLLA code license, support boundary and branding rules that came with this starter.

## Useful commands

```bash
php fnlla list
php fnlla fnlla-ui:sync
php fnlla fnlla-ui:validate
php fnlla migrate
php fnlla migrate:rollback
php fnlla migrate:status
php fnlla version:status
php fnlla version:sync
php scripts/test.php
php scripts/lint.php
php scripts/validate-version-manifest.php
```

On Windows, the starter also includes:

```cmd
test-project.cmd
lint-project.cmd
update-fnlla-ui.cmd
```
MD;

        file_put_contents($targetRoot . DIRECTORY_SEPARATOR . "README.md", $readme . PHP_EOL);
    }

    private function rewriteProjectLaunchers(string $targetRoot): void
    {
        $legacyLaunchers = [
            $targetRoot . DIRECTORY_SEPARATOR . "test-fnlla-php.cmd",
            $targetRoot . DIRECTORY_SEPARATOR . "lint-fnlla-php.cmd",
        ];

        foreach ($legacyLaunchers as $legacyLauncher) {
            if (is_file($legacyLauncher)) {
                unlink($legacyLauncher);
            }
        }

        $testLauncher = <<<'CMD'
@echo off
REM ============================================================================
REM FNLLA PROJECT LAUNCHER
REM File: test-project.cmd
REM Purpose: Runs the local FNLLA PHP starter test suite for this project.
REM ============================================================================
setlocal
php "%~dp0scripts\test.php" %*
CMD;

        $lintLauncher = <<<'CMD'
@echo off
REM ============================================================================
REM FNLLA PROJECT LAUNCHER
REM File: lint-project.cmd
REM Purpose: Runs syntax lint and FNLLA UI validation for this project.
REM ============================================================================
setlocal
php "%~dp0scripts\lint.php" || exit /b %ERRORLEVEL%
php "%~dp0scripts\validate-fnlla-ui.php" || exit /b %ERRORLEVEL%
php "%~dp0scripts\validate-version-manifest.php" || exit /b %ERRORLEVEL%
CMD;

        file_put_contents($targetRoot . DIRECTORY_SEPARATOR . "test-project.cmd", $testLauncher . PHP_EOL);
        file_put_contents($targetRoot . DIRECTORY_SEPARATOR . "lint-project.cmd", $lintLauncher . PHP_EOL);
    }

    private function resolveTargetPath(string $targetArgument): string
    {
        if (preg_match('/^[A-Za-z]:[\\\\\\/]/', $targetArgument) === 1 || str_starts_with($targetArgument, "\\\\") || str_starts_with($targetArgument, "/")) {
            return $this->normalizePath($targetArgument);
        }

        return $this->normalizePath((string) getcwd() . DIRECTORY_SEPARATOR . $targetArgument);
    }

    private function normalizePath(string $path): string
    {
        $path = str_replace(["/", "\\"], DIRECTORY_SEPARATOR, $path);
        $segments = [];
        $prefix = "";

        if (preg_match('/^[A-Za-z]:/', $path) === 1) {
            $prefix = strtoupper(substr($path, 0, 2));
            $path = substr($path, 2);
        } elseif (str_starts_with($path, DIRECTORY_SEPARATOR . DIRECTORY_SEPARATOR)) {
            $prefix = DIRECTORY_SEPARATOR . DIRECTORY_SEPARATOR;
            $path = substr($path, 2);
        }

        $isAbsolute = str_starts_with($path, DIRECTORY_SEPARATOR);
        $parts = preg_split('/[\\\\\\/]+/', $path) ?: [];

        foreach ($parts as $part) {
            if ($part === "" || $part === ".") {
                continue;
            }

            if ($part === "..") {
                if ($segments !== [] && end($segments) !== "..") {
                    array_pop($segments);
                } elseif (!$isAbsolute) {
                    $segments[] = $part;
                }

                continue;
            }

            $segments[] = $part;
        }

        $normalized = implode(DIRECTORY_SEPARATOR, $segments);

        if ($prefix !== "") {
            return $prefix . DIRECTORY_SEPARATOR . $normalized;
        }

        return ($isAbsolute ? DIRECTORY_SEPARATOR : "") . $normalized;
    }

    private function normalizeSeparators(string $path): string
    {
        return str_replace("\\", "/", $path);
    }

    private function pathsEqual(string $left, string $right): bool
    {
        return strcasecmp(rtrim($left, "\\/"), rtrim($right, "\\/")) === 0;
    }

    private function isChildPath(string $childPath, string $parentPath): bool
    {
        $child = rtrim(strtolower($childPath), "\\/");
        $parent = rtrim(strtolower($parentPath), "\\/");

        return str_starts_with($child, $parent . DIRECTORY_SEPARATOR);
    }

    private function guessAppName(string $targetPath): string
    {
        $basename = basename(rtrim($targetPath, "\\/"));
        $basename = preg_replace('/[-_]+/', " ", $basename);

        return ucwords(trim((string) $basename));
    }

    private function slugify(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', "-", $value);

        return trim((string) $value, "-");
    }
}
