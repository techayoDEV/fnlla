<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA TEST CASE
File: tests\FrameworkUpdateCommandTest.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Validates the downstream framework update workflow against real exported
  application trees instead of only source-level assumptions.
*/

namespace Fnlla\Php\Tests;

use Fnlla\Php\Console\Commands\MakeProjectCommand;
use Fnlla\Php\Container\Container;
use Fnlla\Php\Support\FrameworkUpdater;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class FrameworkUpdateCommandTest extends TestCase
{
    /** @var string[] */
    private array $tempPaths = [];

    protected function tearDown(): void
    {
        foreach ($this->tempPaths as $path) {
            $this->removeDirectory($path);
        }
    }

    public function testFrameworkUpdateRejectsLocalSourceOption(): void
    {
        $projectRoot = $this->exportProject("Framework Source Block Test");

        [$exitCode, $output] = $this->runPhpScript(
            $projectRoot . DIRECTORY_SEPARATOR . "fnlla",
            ["framework:update", "--check", "--source", base_path()]
        );

        self::assertSame(1, $exitCode, $output);
        self::assertStringContainsString("Local source updates are disabled", $output);
        self::assertStringContainsString("techayoDEV/fnlla GitHub release channel", $output);
    }

    public function testFrameworkUpdateRejectsRepositoryOverrideOption(): void
    {
        $projectRoot = $this->exportProject("Framework Fork Block Test");

        [$exitCode, $output] = $this->runPhpScript(
            $projectRoot . DIRECTORY_SEPARATOR . "fnlla",
            ["framework:update", "--check", "--repository", "someone/fnlla"]
        );

        self::assertSame(1, $exitCode, $output);
        self::assertStringContainsString("Repository overrides are disabled", $output);
    }

    public function testFrameworkUpdateDoesNotAutoDetectLocalSiblingRepository(): void
    {
        $workspaceRoot = $this->makeTempPath("fnlla-framework-update-workspace-");
        mkdir($workspaceRoot, 0777, true);

        $projectRoot = $workspaceRoot . DIRECTORY_SEPARATOR . "project";
        $sourceClone = $workspaceRoot . DIRECTORY_SEPARATOR . "fnlla";

        $this->exportProjectTo($projectRoot, "Framework Auto Detect Block Test");
        mkdir($sourceClone, 0777, true);
        $this->copyDirectory(base_path(), $sourceClone);

        $detection = FrameworkUpdater::detectSourceRoot($projectRoot);

        self::assertSame(null, $detection["resolved_path"]);
        self::assertSame("official GitHub release channel only", $detection["origin"]);
        self::assertSame([], $detection["candidates"]);
    }

    public function testExportedProjectExposesSupportedFrameworkUpdateCommand(): void
    {
        $projectRoot = $this->exportProject("Framework Alias Test");

        [$listExitCode, $listOutput] = $this->runPhpScript(
            $projectRoot . DIRECTORY_SEPARATOR . "fnlla",
            ["list"]
        );

        self::assertSame(0, $listExitCode, $listOutput);
        self::assertStringContainsString("framework:update", $listOutput);
        self::assertStringContainsString("fnlla-runtime:validate", $listOutput);
    }

    private function exportProject(string $appName): string
    {
        $targetPath = $this->makeTempPath("fnlla-framework-update-project-");
        $this->exportProjectTo($targetPath, $appName);

        return $targetPath;
    }

    private function exportProjectTo(string $targetPath, string $appName): void
    {
        $container = $GLOBALS["fnlla_container"] ?? $GLOBALS["fnlla_php_container"] ?? null;
        self::assertInstanceOf(Container::class, $container);

        $command = new MakeProjectCommand($container);

        self::assertSame(0, $command->handle([$targetPath, $appName]));
        file_put_contents(
            $targetPath . DIRECTORY_SEPARATOR . ".env",
            "APP_ENV=development" . PHP_EOL
            . "APP_DEBUG=false" . PHP_EOL
            . "FRAMEWORK_UPDATE_POST_INSTALL_CHECKS=false" . PHP_EOL
        );
    }

    private function cloneRepository(): string
    {
        $targetPath = $this->makeTempPath("fnlla-framework-update-source-");
        mkdir($targetPath, 0777, true);
        $this->copyDirectory(base_path(), $targetPath);

        return $targetPath;
    }

    private function makeTempPath(string $prefix): string
    {
        $path = rtrim(sys_get_temp_dir(), "\\/") . DIRECTORY_SEPARATOR . $prefix . bin2hex(random_bytes(4));
        $this->tempPaths[] = $path;

        return $path;
    }

    private function copyDirectory(string $sourceRoot, string $targetRoot): void
    {
        if ($this->copyDirectoryFastIfSupported($sourceRoot, $targetRoot)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($sourceRoot, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $relativePath = substr($item->getPathname(), strlen(rtrim($sourceRoot, "\\/")) + 1);

            if ($relativePath === false || $relativePath === ".git" || str_starts_with($relativePath, ".git" . DIRECTORY_SEPARATOR)) {
                continue;
            }

            $targetPath = $targetRoot . DIRECTORY_SEPARATOR . $relativePath;

            if ($item->isDir()) {
                if (!is_dir($targetPath)) {
                    mkdir($targetPath, 0777, true);
                }

                continue;
            }

            $directory = dirname($targetPath);

            if (!is_dir($directory)) {
                mkdir($directory, 0777, true);
            }

            copy($item->getPathname(), $targetPath);
        }
    }

    private function copyDirectoryFastIfSupported(string $sourceRoot, string $targetRoot): bool
    {
        if (DIRECTORY_SEPARATOR !== "\\" || !function_exists("exec")) {
            return false;
        }

        $robocopy = $this->findCommand("robocopy");

        if ($robocopy === null) {
            return false;
        }

        if (!is_dir($targetRoot) && !mkdir($targetRoot, 0777, true) && !is_dir($targetRoot)) {
            return false;
        }

        $command = $this->escapeShellArgument($robocopy)
            . " "
            . $this->escapeShellArgument($sourceRoot)
            . " "
            . $this->escapeShellArgument($targetRoot)
            . " /E /XD .git storage dist docs /NFL /NDL /NJH /NJS /NP 2>&1";
        $lines = [];
        $exitCode = 16;
        exec($command, $lines, $exitCode);

        return $exitCode <= 7;
    }

    private function findCommand(string $command): ?string
    {
        if (!function_exists("exec")) {
            return null;
        }

        $lines = [];
        $exitCode = 1;
        exec("where " . $command . " 2>nul", $lines, $exitCode);

        if ($exitCode !== 0) {
            return null;
        }

        foreach ($lines as $line) {
            $path = trim((string) $line);

            if ($path !== "" && is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    private function escapeShellArgument(string $argument): string
    {
        if (DIRECTORY_SEPARATOR === "\\") {
            return '"' . str_replace('"', '\"', $argument) . '"';
        }

        return escapeshellarg($argument);
    }

    private function removeDirectory(string $path): void
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
