<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA CONSOLE SOURCE
File: src\Console\Commands\MakeProjectCommand.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Implements the maintained CLI surface and scheduler-oriented console behavior.
*/

namespace Fnlla\Php\Console\Commands;

use Fnlla\Php\Console\Command;
use Fnlla\Php\Support\FrameworkLock;
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
        return "Export a clean FNLLA starter into a new project directory.";
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
            $this->error("Target path cannot be the FNLLA source repository itself.");

            return 1;
        }

        if ($this->isChildPath($targetPath, $sourceRoot)) {
            $this->error("Target path must be outside the FNLLA source repository to avoid recursive copies.");

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

        $this->line("Exported FNLLA starter to: " . $targetPath);
        $this->line("Application name: " . $appName);
        $this->line("");
        $this->line("Next steps:");
        $this->line("1. Open the new project directory.");
        $this->line("2. Copy .env.example to .env, or open /maintenance locally and let the starter create .env while configuring the first maintenance password.");
        $this->line("3. Review routes/web.php, src/Controllers/PageController.php and views/pages/ and reshape the starter surface into your real project pages.");
        $this->line("4. Run php fnlla fnlla-runtime:validate, php scripts/test.php, php scripts/lint.php and php scripts/validate-version-manifest.php.");
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

            if (!$this->shouldExportRootEntry($name)) {
                continue;
            }

            $sourcePath = $fileInfo->getPathname();
            $targetPath = $targetRoot . DIRECTORY_SEPARATOR . $name;

            $this->copyPath($sourcePath, $targetPath);
        }
    }

    private function shouldExportRootEntry(string $name): bool
    {
        return in_array($name, [
            ".editorconfig",
            ".env.example",
            ".gitattributes",
            ".gitignore",
            "LICENSE.md",
            "MANIFEST.json",
            "SUPPORT.md",
            "TRADEMARKS.md",
            "VERSION",
            "bootstrap",
            "composer.json",
            "config",
            "database",
            "fnlla",
            "fnlla.cmd",
            "lang",
            "public",
            "routes",
            "scripts",
            "src",
            "storage",
            "tests",
            "update-fnlla-runtime.cmd",
            "views",
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
        if ($relativePath === "docs" || str_starts_with($relativePath, "docs/")) {
            return true;
        }

        if ($this->isRuntimeStatePath($relativePath)) {
            return true;
        }

        return in_array($relativePath, [
            "database/factories/UserFactory.php",
            "database/migrations/20260627180000_create_users_table.php",
            "database/migrations/20260627200000_add_role_to_users_table.php",
            "scripts/apply-techayo-metadata.ps1",
            "scripts/build-docs.php",
            "src/Console/Commands/MakeCommandCommand.php",
            "src/Console/Commands/MakeControllerCommand.php",
            "src/Console/Commands/MakeFactoryCommand.php",
            "src/Console/Commands/MakeMiddlewareCommand.php",
            "src/Console/Commands/MakeMigrationCommand.php",
            "src/Console/Commands/MakeProjectCommand.php",
            "src/Console/Commands/MakeSeederCommand.php",
            "src/Controllers/AuthController.php",
            "tests/ApplicationTest.php",
            "tests/AuthTest.php",
            "tests/EnvironmentConfigTest.php",
            "tests/FnllaRuntimeGuardTest.php",
            "tests/FnllaRuntimeSyncCommandTest.php",
            "tests/FrameworkExtensionsTest.php",
            "tests/MakeProjectCommandTest.php",
            "tests/PageMetaTest.php",
            "tests/RequestTest.php",
            "tests/RouterTest.php",
            "tests/FrameworkUpdateCommandTest.php",
            "tests/ValidationTest.php",
            "test-fnlla.cmd",
            "lint-fnlla.cmd",
            "views/pages/about.php",
            "views/pages/admin.php",
            "views/pages/dashboard.php",
            "views/pages/login.php",
            "views/pages/platform.php",
        ], true);
    }

    private function isRuntimeStatePath(string $relativePath): bool
    {
        if ($relativePath === "storage/framework/fnlla-runtime-guard.json") {
            return true;
        }

        foreach ([
            "storage/database/",
            "storage/logs/",
            "storage/framework/cache/",
            "storage/framework/queue/",
            "storage/framework/sessions/",
        ] as $prefix) {
            if (str_starts_with($relativePath, $prefix)) {
                return basename($relativePath) !== ".gitignore";
            }
        }

        return false;
    }

    private function customizeExport(string $targetRoot, string $appName, string $packageSlug): void
    {
        $this->sanitizeExportedStorage($targetRoot);
        $this->rewriteAppConfig($targetRoot, $appName);
        $this->rewriteComposerMetadata($targetRoot, $appName, $packageSlug);
        $this->rewriteStarterReadme($targetRoot, $appName);
        $this->rewriteApplicationSurface($targetRoot, $appName);
        $this->rewriteDatabaseSurface($targetRoot);
        $this->rewriteProjectTests($targetRoot);
        $this->rewriteConsoleLaunchers($targetRoot);
        $this->rewriteProjectLaunchers($targetRoot);
        FrameworkLock::write($targetRoot, base_path(), $appName, $packageSlug);
    }

    private function sanitizeExportedStorage(string $targetRoot): void
    {
        $keepFiles = [
            "storage/database/.gitignore" => "*\n!.gitignore\n",
            "storage/framework/cache/.gitignore" => "# Keep the cache directory in the repository while ignoring runtime cache files.\n*\n!.gitignore\n",
            "storage/framework/queue/.gitignore" => "# Keep the queue directory in the repository while ignoring runtime queue files.\n*\n!.gitignore\n",
            "storage/framework/sessions/.gitignore" => "# Keep the sessions directory in the repository while ignoring runtime session files.\n*\n!.gitignore\n",
            "storage/logs/.gitignore" => "*\n!.gitignore\n",
        ];

        foreach ($keepFiles as $relativePath => $contents) {
            $absolutePath = $targetRoot . DIRECTORY_SEPARATOR . str_replace("/", DIRECTORY_SEPARATOR, $relativePath);
            $directory = dirname($absolutePath);

            if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
                throw new RuntimeException("Unable to create storage directory during export: " . $directory);
            }

            file_put_contents($absolutePath, $contents);
        }
    }

    private function rewriteAppConfig(string $targetRoot, string $appName): void
    {
        $path = $targetRoot . DIRECTORY_SEPARATOR . "config" . DIRECTORY_SEPARATOR . "app.php";
        $contents = (string) file_get_contents($path);
        $replacement = "'name' => " . var_export($appName, true) . ",";
        $updated = preg_replace("/\"name\" => \"FNLLA\",/", $replacement, $contents, 1);

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
        $decoded["description"] = $appName . " built on FNLLA and its integrated UI surface.";

        file_put_contents(
            $path,
            json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL
        );
    }

    private function rewriteStarterReadme(string $targetRoot, string $appName): void
    {
        $readme = <<<MD
# {$appName}

This repository is a working application export generated from `techayoDEV/fnlla`.

It is intended to be the beginning of a new server-rendered website or web application built on:

- FNLLA
- the integrated FNLLA UI surface
- PHP 8.3
- MySQL

## What is already included

- the FNLLA application core
- the integrated FNLLA UI surface under `public/vendor/fnlla-runtime/`
- machine-readable release metadata in `MANIFEST.json`
- framework update baseline metadata in `.fnlla/framework-lock.json`
- a legacy compatibility lock in `.fnlla/starter-lock.json` for older update flows
- root legal and policy files: `LICENSE.md`, `SUPPORT.md`, `TRADEMARKS.md`
- a starter application skeleton with public pages for home, about and services
- an optional password-protected maintenance access screen for client preview or staged review sessions
- sessions, cookies, CSRF, auth foundations and the rest of the core runtime under `src/`
- database directories ready for project-specific migrations and seeders
- local lint, test, version metadata and integrated UI surface validation scripts
- a local-first framework maintenance page at `/maintenance/framework-update`

## How to start working

1. Copy `.env.example` to `.env`.
2. Set `APP_URL` and your MySQL credentials.
3. Run:

```bash
php fnlla fnlla-runtime:validate
php scripts/test.php
php scripts/lint.php
php scripts/validate-version-manifest.php
```

4. Start the local server:

```bash
php -S 127.0.0.1:8080 -t public public/router.php
```

5. Open `http://127.0.0.1:8080` in your browser and review the starter pages at `/`, `/about` and `/services`.
6. Use `http://127.0.0.1:8080/maintenance/framework-update` when you want a browser-based framework update check or safe apply flow.
7. When client preview should stay private, either:

   - open `/maintenance` locally and use the built-in "Save and enable maintenance" setup form on a fresh starter, or
   - set `MAINTENANCE_MODE_ENABLED=true` and `MAINTENANCE_ACCESS_PASSWORD=<your-password>` in `.env`

The maintenance page is controlled through `FRAMEWORK_UPDATE_UI_ENABLED`, `FRAMEWORK_UPDATE_UI_LOCAL_ONLY`, `FRAMEWORK_UPDATE_UI_APPLY_ENABLED`, `FRAMEWORK_UPDATE_GITHUB_ENABLED`, `FRAMEWORK_UPDATE_SOURCE_PATH`, `MAINTENANCE_MODE_ENABLED`, `MAINTENANCE_SETUP_UI_ENABLED`, `MAINTENANCE_SETUP_UI_LOCAL_ONLY` and the related `MAINTENANCE_ACCESS_*` variables in `.env`.

For Apache environments, use `public/` as the document root.
The exported project already includes `public/.htaccess`.

The exported `.env.example` starts with local-development defaults so sessions work over plain HTTP on `127.0.0.1`.
Before production deployment, switch the environment back to production-safe values and enable HTTPS.

## What the export intentionally leaves behind

This exported application does not copy the full maintainer workspace from `techayoDEV/fnlla`.

It intentionally leaves behind:

- framework-only browser docs under `docs/`
- the maintainer docs builder `scripts/build-docs.php`
- repository governance and contribution files such as `.git/`, `.github/`, `CODE_OF_CONDUCT.md` and `SECURITY.md`
- local runtime residue such as logs, cache entries, queue files, session files and integrated UI surface guard state

That keeps the downstream project focused on application delivery rather than framework maintenance.

## First files to replace or review

- `routes/web.php`
- `src/Controllers/PageController.php`
- `views/pages/`
- `public/assets/app.css`
- `database/migrations/`
- `config/app.php`

## Important note

The exported project still contains a working application surface so the application runs immediately.

That surface is a starting point, not the final product. Replace the placeholder pages, routes and content with the real website or application flow for this project.

Use `LICENSE.md`, `SUPPORT.md` and `TRADEMARKS.md` to understand the upstream FNLLA code license, support boundary and branding rules that came with this application base.

## Useful commands

The application base keeps only the project-facing scripts, smoke tests and commands:

- `php scripts/test.php` runs the project-local smoke test harness kept under `tests/`
- `php scripts/lint.php` runs PHP syntax lint across the maintained project tree
- `php scripts/validate-fnlla-runtime.php` checks that the exported project still respects FNLLA's integrated UI surface contract
- `php scripts/validate-version-manifest.php` checks that `VERSION`, `MANIFEST.json` and the integrated UI surface metadata stay aligned on one FNLLA version
- `php fnlla framework:update --check --github` checks the latest published FNLLA release from GitHub and caches the release source locally before comparing drift
- `php fnlla framework:update --check [--source <path-to-fnlla>]` checks framework drift against a maintained FNLLA source repository when a local maintainer checkout is preferred
- `/maintenance/framework-update` provides the same framework-update workflow through a local-first maintenance page with GitHub-backed check/apply and a local source override
- `php fnlla version:sync` regenerates `MANIFEST.json` and re-syncs integrated UI surface metadata after an intentional FNLLA version change
- `php fnlla fnlla-runtime:sync` or `update-fnlla-runtime.cmd` refresh the integrated FNLLA UI surface through the official publish -> sync workflow

The export intentionally leaves `make:*`, `make:project` and broader framework-internal test coverage in the upstream `techayoDEV/fnlla` repository.

The full framework documentation remains in the upstream `techayoDEV/fnlla` repository.

The GitHub-backed framework-update flow only prepares diffs or apply runs when the published FNLLA release is actually newer than the framework base already locked into this application, so the browser and CLI workflow do not suggest downgrades over equal or ahead-of-release starter builds.

```bash
php fnlla list
php fnlla fnlla-runtime:sync
php fnlla fnlla-runtime:validate
php fnlla framework:update --check --github
php fnlla framework:update --check --source ..\fnlla  # optional local override
php fnlla route:list
php fnlla migrate
php fnlla migrate:rollback
php fnlla migrate:status
php fnlla version:status
php fnlla version:sync
php scripts/test.php
php scripts/lint.php
php scripts/validate-version-manifest.php
```

On Windows, the application export also includes:

```cmd
test-project.cmd
lint-project.cmd
update-fnlla-runtime.cmd
```
MD;

        file_put_contents($targetRoot . DIRECTORY_SEPARATOR . "README.md", $readme . PHP_EOL);
    }

    private function rewriteApplicationSurface(string $targetRoot, string $appName): void
    {
        $this->synchronizeStarterSurfaceFiles($targetRoot, [
            "routes/web.php",
            "src/Controllers/HomeController.php",
            "src/Controllers/PageController.php",
            "views/layouts/app.php",
            "views/pages/home.php",
            "views/pages/about.php",
            "views/pages/services.php",
            "public/assets/app.css",
        ]);

        $legacyProjectLaunchView = $targetRoot . DIRECTORY_SEPARATOR . "views" . DIRECTORY_SEPARATOR . "pages" . DIRECTORY_SEPARATOR . "project-launch.php";

        if (is_file($legacyProjectLaunchView)) {
            unlink($legacyProjectLaunchView);
        }
    }

    private function synchronizeStarterSurfaceFiles(string $targetRoot, array $relativePaths): void
    {
        foreach ($relativePaths as $relativePath) {
            if (!is_string($relativePath) || $relativePath === "") {
                continue;
            }

            $sourcePath = base_path($relativePath);

            if (!is_file($sourcePath)) {
                throw new RuntimeException("Starter surface source file is missing: " . $sourcePath);
            }

            $targetPath = $targetRoot . DIRECTORY_SEPARATOR . str_replace("/", DIRECTORY_SEPARATOR, $relativePath);
            $targetDirectory = dirname($targetPath);

            if (!is_dir($targetDirectory) && !mkdir($targetDirectory, 0777, true) && !is_dir($targetDirectory)) {
                throw new RuntimeException("Unable to prepare export directory: " . $targetDirectory);
            }

            $contents = file_get_contents($sourcePath);

            if ($contents === false) {
                throw new RuntimeException("Unable to read starter surface source file: " . $sourcePath);
            }

            file_put_contents($targetPath, $contents);
        }
    }

    private function rewriteDatabaseSurface(string $targetRoot): void
    {
        $seeder = <<<'PHP'
<?php

declare(strict_types=1);

/*
===============================================================================
PROJECT DATABASE SEEDER
File: database\seeders\DatabaseSeeder.php
Purpose:
- Keeps the exported project ready for project-specific seed data without shipping demo users by default.
===============================================================================
*/

namespace Database\Seeders;

use Fnlla\Php\Database\Seeders\Seeder;

final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Add project-specific seed data here when the application needs it.
    }
}
PHP;

        file_put_contents($targetRoot . DIRECTORY_SEPARATOR . "database" . DIRECTORY_SEPARATOR . "seeders" . DIRECTORY_SEPARATOR . "DatabaseSeeder.php", $seeder . PHP_EOL);
    }

    private function rewriteProjectTests(string $targetRoot): void
    {
        $bootstrapAutoloadTest = <<<'PHP'
<?php

declare(strict_types=1);

/*
===============================================================================
PROJECT TEST CASE
File: tests\BootstrapAutoloadTest.php
Purpose:
- Confirms the exported project can autoload the PSR-4 namespaces it actually ships.
===============================================================================
*/

namespace Fnlla\Php\Tests;

use PHPUnit\Framework\TestCase;

final class BootstrapAutoloadTest extends TestCase
{
    public function testFallbackAutoloaderResolvesExportedProjectNamespacesWithoutVendorAutoload(): void
    {
        self::assertFalse(is_file(base_path("vendor/autoload.php")));
        self::assertTrue(class_exists("Database\\Seeders\\DatabaseSeeder"));
        self::assertFalse(class_exists("Database\\Factories\\UserFactory"));
    }
}
PHP;

        file_put_contents($targetRoot . DIRECTORY_SEPARATOR . "tests" . DIRECTORY_SEPARATOR . "BootstrapAutoloadTest.php", $bootstrapAutoloadTest . PHP_EOL);
    }

    private function rewriteConsoleLaunchers(string $targetRoot): void
    {
        $launcher = <<<'PHP'
#!/usr/bin/env php
<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA PROJECT LAUNCHER
File: fnlla
Purpose:
- Boots the exported FNLLA project console and exposes downstream-safe commands.
===============================================================================
*/

use Fnlla\Php\Console\Commands\CacheClearCommand;
use Fnlla\Php\Console\Commands\FnllaRuntimeSyncCommand;
use Fnlla\Php\Console\Commands\FnllaRuntimeValidateCommand;
use Fnlla\Php\Console\Commands\FrameworkUpdateCommand;
use Fnlla\Php\Console\Commands\MigrateCommand;
use Fnlla\Php\Console\Commands\MigrateRollbackCommand;
use Fnlla\Php\Console\Commands\MigrateStatusCommand;
use Fnlla\Php\Console\Commands\QueueWorkCommand;
use Fnlla\Php\Console\Commands\RouteListCommand;
use Fnlla\Php\Console\Commands\ScheduleRunCommand;
use Fnlla\Php\Console\Commands\SeedCommand;
use Fnlla\Php\Console\Commands\StarterUpdateCommand;
use Fnlla\Php\Console\Commands\VersionStatusCommand;
use Fnlla\Php\Console\Commands\VersionSyncCommand;

if (in_array($_SERVER["argv"][1] ?? "", ["fnlla-runtime:sync", "fnlla-runtime:validate", "framework:update", "starter:update", "version:status", "version:sync"], true) && !defined("FNLLA_RUNTIME_SKIP_AUTO_GUARD")) {
    define("FNLLA_RUNTIME_SKIP_AUTO_GUARD", true);
}

$container = require __DIR__ . DIRECTORY_SEPARATOR . "bootstrap" . DIRECTORY_SEPARATOR . "console.php";

$console = $container->make(\Fnlla\Php\Console\Application::class);
$console->register(CacheClearCommand::class);
$console->register(FnllaRuntimeSyncCommand::class);
$console->register(FnllaRuntimeValidateCommand::class);
$console->register(FrameworkUpdateCommand::class);
$console->register(SeedCommand::class);
$console->register(MigrateRollbackCommand::class);
$console->register(MigrateCommand::class);
$console->register(MigrateStatusCommand::class);
$console->register(QueueWorkCommand::class);
$console->register(RouteListCommand::class);
$console->register(ScheduleRunCommand::class);
$console->register(StarterUpdateCommand::class);
$console->register(VersionStatusCommand::class);
$console->register(VersionSyncCommand::class);

exit($console->run($_SERVER["argv"] ?? []));
PHP;

        $windowsLauncher = <<<'CMD'
@echo off
REM ============================================================================
REM FNLLA PROJECT LAUNCHER
REM File: fnlla.cmd
REM Purpose: Provides a Windows launcher for downstream-safe FNLLA project commands.
REM ============================================================================
setlocal
php "%~dp0fnlla" %*
CMD;

        file_put_contents($targetRoot . DIRECTORY_SEPARATOR . "fnlla", $launcher . PHP_EOL);
        file_put_contents($targetRoot . DIRECTORY_SEPARATOR . "fnlla.cmd", $windowsLauncher . PHP_EOL);
    }

    private function rewriteProjectLaunchers(string $targetRoot): void
    {
        $legacyLaunchers = [
            $targetRoot . DIRECTORY_SEPARATOR . "test-fnlla.cmd",
            $targetRoot . DIRECTORY_SEPARATOR . "lint-fnlla.cmd",
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
REM Purpose: Runs the local FNLLA project test suite for this application.
REM ============================================================================
setlocal
php "%~dp0scripts\test.php" %*
CMD;

        $lintLauncher = <<<'CMD'
@echo off
REM ============================================================================
REM FNLLA PROJECT LAUNCHER
REM File: lint-project.cmd
REM Purpose: Runs syntax lint and integrated UI surface validation for this project.
REM ============================================================================
setlocal
php "%~dp0scripts\lint.php" || exit /b %ERRORLEVEL%
php "%~dp0scripts\validate-fnlla-runtime.php" || exit /b %ERRORLEVEL%
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
