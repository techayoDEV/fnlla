<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA PHP TEST CASE
File: tests\MakeProjectCommandTest.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA PHP is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA PHP framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Validates maintained framework behavior inside the repository-local test harness.
*/

namespace Fnlla\Php\Tests;

use Fnlla\Php\Console\Commands\MakeProjectCommand;
use Fnlla\Php\Container\Container;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class MakeProjectCommandTest extends TestCase
{
    private string $targetPath;

    protected function setUp(): void
    {
        $this->targetPath = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . "fnlla-php-make-project-test-"
            . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        if (!is_dir($this->targetPath)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->targetPath, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($this->targetPath);
    }

    public function testExportedStarterIncludesProjectSurfaceWithoutMaintainerResidue(): void
    {
        $container = $GLOBALS["fnlla_php_container"] ?? null;
        self::assertInstanceOf(Container::class, $container);

        $command = new MakeProjectCommand($container);

        self::assertSame(0, $command->handle([$this->targetPath, "Starter Test"]));
        self::assertFileExists($this->targetPath . DIRECTORY_SEPARATOR . "LICENSE.md");
        self::assertFileExists($this->targetPath . DIRECTORY_SEPARATOR . "SUPPORT.md");
        self::assertFileExists($this->targetPath . DIRECTORY_SEPARATOR . "TRADEMARKS.md");
        self::assertFileExists($this->targetPath . DIRECTORY_SEPARATOR . "VERSION");
        self::assertFileExists($this->targetPath . DIRECTORY_SEPARATOR . "MANIFEST.json");
        self::assertFileExists($this->targetPath . DIRECTORY_SEPARATOR . ".fnlla" . DIRECTORY_SEPARATOR . "framework-lock.json");
        self::assertFileExists($this->targetPath . DIRECTORY_SEPARATOR . ".fnlla" . DIRECTORY_SEPARATOR . "starter-lock.json");
        self::assertFileExists($this->targetPath . DIRECTORY_SEPARATOR . "config" . DIRECTORY_SEPARATOR . "framework_update.php");
        self::assertFileExists($this->targetPath . DIRECTORY_SEPARATOR . "routes" . DIRECTORY_SEPARATOR . "maintenance.php");
        self::assertFileExists($this->targetPath . DIRECTORY_SEPARATOR . "views" . DIRECTORY_SEPARATOR . "maintenance" . DIRECTORY_SEPARATOR . "framework-update.php");
        self::assertFalse(is_dir($this->targetPath . DIRECTORY_SEPARATOR . "docs"));
        self::assertFalse(is_file($this->targetPath . DIRECTORY_SEPARATOR . "scripts" . DIRECTORY_SEPARATOR . "build-docs.php"));
        self::assertFalse(is_file($this->targetPath . DIRECTORY_SEPARATOR . "database" . DIRECTORY_SEPARATOR . "factories" . DIRECTORY_SEPARATOR . "UserFactory.php"));
        self::assertFalse(is_file($this->targetPath . DIRECTORY_SEPARATOR . "database" . DIRECTORY_SEPARATOR . "migrations" . DIRECTORY_SEPARATOR . "20260627180000_create_users_table.php"));
        self::assertFalse(is_file($this->targetPath . DIRECTORY_SEPARATOR . "database" . DIRECTORY_SEPARATOR . "migrations" . DIRECTORY_SEPARATOR . "20260627200000_add_role_to_users_table.php"));
        self::assertFalse(is_file($this->targetPath . DIRECTORY_SEPARATOR . "src" . DIRECTORY_SEPARATOR . "Controllers" . DIRECTORY_SEPARATOR . "AuthController.php"));
        self::assertFalse(is_file($this->targetPath . DIRECTORY_SEPARATOR . "src" . DIRECTORY_SEPARATOR . "Console" . DIRECTORY_SEPARATOR . "Commands" . DIRECTORY_SEPARATOR . "MakeCommandCommand.php"));
        self::assertFalse(is_file($this->targetPath . DIRECTORY_SEPARATOR . "src" . DIRECTORY_SEPARATOR . "Console" . DIRECTORY_SEPARATOR . "Commands" . DIRECTORY_SEPARATOR . "MakeProjectCommand.php"));
        self::assertFalse(is_file($this->targetPath . DIRECTORY_SEPARATOR . "tests" . DIRECTORY_SEPARATOR . "MakeProjectCommandTest.php"));
        self::assertFalse(is_file($this->targetPath . DIRECTORY_SEPARATOR . "tests" . DIRECTORY_SEPARATOR . "FnllaWebSyncCommandTest.php"));
        self::assertFalse(is_file($this->targetPath . DIRECTORY_SEPARATOR . "tests" . DIRECTORY_SEPARATOR . "FrameworkExtensionsTest.php"));
        self::assertFalse(is_file($this->targetPath . DIRECTORY_SEPARATOR . "tests" . DIRECTORY_SEPARATOR . "ValidationTest.php"));
        self::assertFalse(is_file($this->targetPath . DIRECTORY_SEPARATOR . "views" . DIRECTORY_SEPARATOR . "pages" . DIRECTORY_SEPARATOR . "platform.php"));
        self::assertFalse(is_file($this->targetPath . DIRECTORY_SEPARATOR . "views" . DIRECTORY_SEPARATOR . "pages" . DIRECTORY_SEPARATOR . "about.php"));
        self::assertFalse(is_file($this->targetPath . DIRECTORY_SEPARATOR . "views" . DIRECTORY_SEPARATOR . "pages" . DIRECTORY_SEPARATOR . "login.php"));
        self::assertFalse(is_file($this->targetPath . DIRECTORY_SEPARATOR . "storage" . DIRECTORY_SEPARATOR . "logs" . DIRECTORY_SEPARATOR . "app.log"));
        self::assertFalse(is_file($this->targetPath . DIRECTORY_SEPARATOR . "storage" . DIRECTORY_SEPARATOR . "framework" . DIRECTORY_SEPARATOR . "fnlla-web-guard.json"));
        self::assertSame(
            [],
            glob($this->targetPath . DIRECTORY_SEPARATOR . "storage" . DIRECTORY_SEPARATOR . "framework" . DIRECTORY_SEPARATOR . "cache" . DIRECTORY_SEPARATOR . "*.cache") ?: []
        );
        self::assertSame(
            [],
            glob($this->targetPath . DIRECTORY_SEPARATOR . "storage" . DIRECTORY_SEPARATOR . "framework" . DIRECTORY_SEPARATOR . "sessions" . DIRECTORY_SEPARATOR . "sess_*") ?: []
        );
        self::assertFileExists($this->targetPath . DIRECTORY_SEPARATOR . "storage" . DIRECTORY_SEPARATOR . "framework" . DIRECTORY_SEPARATOR . "sessions" . DIRECTORY_SEPARATOR . ".gitignore");
        self::assertStringContainsString(
            'validate-version-manifest.php',
            (string) file_get_contents($this->targetPath . DIRECTORY_SEPARATOR . "lint-project.cmd")
        );
        self::assertStringContainsString(
            "php scripts/validate-version-manifest.php",
            (string) file_get_contents($this->targetPath . DIRECTORY_SEPARATOR . "README.md")
        );
        self::assertStringContainsString(
            "SUPPORT.md",
            (string) file_get_contents($this->targetPath . DIRECTORY_SEPARATOR . "README.md")
        );
        self::assertStringContainsString(
            "TRADEMARKS.md",
            (string) file_get_contents($this->targetPath . DIRECTORY_SEPARATOR . "README.md")
        );
        self::assertStringContainsString(
            "does not copy the full maintainer workspace",
            (string) file_get_contents($this->targetPath . DIRECTORY_SEPARATOR . "README.md")
        );
        self::assertStringContainsString(
            "The application base keeps only the project-facing scripts, smoke tests and commands",
            (string) file_get_contents($this->targetPath . DIRECTORY_SEPARATOR . "README.md")
        );
        self::assertStringContainsString(
            ".fnlla/framework-lock.json",
            (string) file_get_contents($this->targetPath . DIRECTORY_SEPARATOR . "README.md")
        );
        self::assertStringContainsString(
            "/maintenance/framework-update",
            (string) file_get_contents($this->targetPath . DIRECTORY_SEPARATOR . "README.md")
        );
        self::assertStringContainsString(
            "/project/launch",
            (string) file_get_contents($this->targetPath . DIRECTORY_SEPARATOR . "README.md")
        );
        self::assertStringContainsString(
            "The export intentionally leaves `make:*`, `make:project`",
            (string) file_get_contents($this->targetPath . DIRECTORY_SEPARATOR . "README.md")
        );
        self::assertStringContainsString(
            "php fnlla framework:update --check --github",
            (string) file_get_contents($this->targetPath . DIRECTORY_SEPARATOR . "README.md")
        );
        self::assertStringContainsString(
            "php fnlla framework:update --check [--source <path-to-fnlla-php>]",
            (string) file_get_contents($this->targetPath . DIRECTORY_SEPARATOR . "README.md")
        );
        self::assertStringContainsString(
            'assertFalse(class_exists("Database\\\\Factories\\\\UserFactory"))',
            (string) file_get_contents($this->targetPath . DIRECTORY_SEPARATOR . "tests" . DIRECTORY_SEPARATOR . "BootstrapAutoloadTest.php")
        );
        $frameworkLock = json_decode(
            (string) file_get_contents($this->targetPath . DIRECTORY_SEPARATOR . ".fnlla" . DIRECTORY_SEPARATOR . "framework-lock.json"),
            true
        );
        self::assertTrue(is_array($frameworkLock));
        self::assertArrayNotHasKey(
            "tests/BootstrapAutoloadTest.php",
            (array) ($frameworkLock["framework_base"]["managed_files"] ?? [])
        );

        [$exitCode, $output] = $this->runPhpScript(
            $this->targetPath . DIRECTORY_SEPARATOR . "scripts" . DIRECTORY_SEPARATOR . "validate-version-manifest.php"
        );

        self::assertSame(0, $exitCode, $output);
        self::assertStringContainsString("FNLLA PHP version manifest passed.", $output);

        [$listExitCode, $listOutput] = $this->runPhpScript(
            $this->targetPath . DIRECTORY_SEPARATOR . "fnlla",
            ["list"]
        );

        self::assertSame(0, $listExitCode, $listOutput);
        self::assertStringContainsString("framework:update", $listOutput);
        self::assertStringContainsString("fnlla-web:validate", $listOutput);
        self::assertFalse(str_contains($listOutput, "starter:update"));
        self::assertFalse(str_contains($listOutput, "make:project"));
        self::assertFalse(str_contains($listOutput, "make:controller"));
        self::assertFalse(str_contains($listOutput, "make:migration"));

        [$projectTestExitCode, $projectTestOutput] = $this->runPhpScript(
            $this->targetPath . DIRECTORY_SEPARATOR . "scripts" . DIRECTORY_SEPARATOR . "test.php"
        );

        self::assertSame(0, $projectTestExitCode, $projectTestOutput);
        self::assertStringContainsString("OK (", $projectTestOutput);

        [$updateCheckExitCode, $updateCheckOutput] = $this->runPhpScript(
            $this->targetPath . DIRECTORY_SEPARATOR . "fnlla",
            ["framework:update", "--check", "--source", base_path()]
        );

        self::assertSame(0, $updateCheckExitCode, $updateCheckOutput);
        self::assertStringContainsString("Framework base is already aligned with the provided source export.", $updateCheckOutput);

        [$legacyUpdateCheckExitCode, $legacyUpdateCheckOutput] = $this->runPhpScript(
            $this->targetPath . DIRECTORY_SEPARATOR . "fnlla",
            ["starter:update", "--check", "--source", base_path()]
        );

        self::assertSame(0, $legacyUpdateCheckExitCode, $legacyUpdateCheckOutput);
        self::assertStringContainsString("Framework base is already aligned with the provided source export.", $legacyUpdateCheckOutput);

        [$routeListExitCode, $routeListOutput] = $this->runPhpScript(
            $this->targetPath . DIRECTORY_SEPARATOR . "fnlla",
            ["route:list"]
        );

        self::assertSame(0, $routeListExitCode, $routeListOutput);
        self::assertStringContainsString("GET     /", $routeListOutput);
        self::assertStringContainsString("GET     /project/launch", $routeListOutput);
        self::assertStringContainsString("GET     /contact", $routeListOutput);
        self::assertStringContainsString("GET     /maintenance", $routeListOutput);
        self::assertStringContainsString("GET     /maintenance/health", $routeListOutput);
        self::assertStringContainsString("GET     /maintenance/framework-update", $routeListOutput);
        self::assertStringContainsString("GET     /health", $routeListOutput);
        self::assertStringContainsString("GET     /api/health", $routeListOutput);
        self::assertFalse(str_contains($routeListOutput, "/starter/update"));
        self::assertFalse(str_contains($routeListOutput, "/platform"));
        self::assertFalse(str_contains($routeListOutput, "/about"));
        self::assertFalse(str_contains($routeListOutput, "/login"));
        self::assertFalse(str_contains($routeListOutput, "/dashboard"));
    }

    private function runPhpScript(string $scriptPath, array $arguments = []): array
    {
        $escapedArguments = array_map(
            static fn (string $argument): string => '"' . str_replace('"', '\"', $argument) . '"',
            $arguments
        );
        $command = '"' . PHP_BINARY . '" "' . $scriptPath . '"'
            . ($escapedArguments !== [] ? ' ' . implode(' ', $escapedArguments) : '')
            . ' 2>&1';
        $lines = [];
        $exitCode = 1;

        exec($command, $lines, $exitCode);

        return [$exitCode, implode(PHP_EOL, $lines)];
    }
}
