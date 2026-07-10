<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA PHP TEST CASE
File: tests\StarterUpdateCommandTest.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA PHP is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA PHP framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Validates the downstream framework update workflow against real exported
  application trees instead of only source-level assumptions.
*/

namespace Fnlla\Php\Tests;

use Fnlla\Php\Console\Commands\MakeProjectCommand;
use Fnlla\Php\Container\Container;
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

    public function testFrameworkUpdateCanCheckAndApplyFrameworkManagedChanges(): void
    {
        $projectRoot = $this->exportProject("Framework Update Test");
        $sourceClone = $this->cloneRepository();
        $managedFile = $sourceClone . DIRECTORY_SEPARATOR . "src" . DIRECTORY_SEPARATOR . "Support" . DIRECTORY_SEPARATOR . "PageMeta.php";

        file_put_contents($managedFile, (string) file_get_contents($managedFile) . PHP_EOL . "// framework update source marker" . PHP_EOL);

        [$checkExitCode, $checkOutput] = $this->runPhpScript(
            $projectRoot . DIRECTORY_SEPARATOR . "fnlla",
            ["framework:update", "--check", "--source", $sourceClone]
        );

        self::assertSame(0, $checkExitCode, $checkOutput);
        self::assertStringContainsString("Safe framework changes available: 1", $checkOutput);
        self::assertStringContainsString("[UPDATE] src/Support/PageMeta.php", $checkOutput);

        [$applyExitCode, $applyOutput] = $this->runPhpScript(
            $projectRoot . DIRECTORY_SEPARATOR . "fnlla",
            ["framework:update", "--apply", "--source", $sourceClone]
        );

        self::assertSame(0, $applyExitCode, $applyOutput);
        self::assertStringContainsString("Applied framework update changes: 1", $applyOutput);
        self::assertStringContainsString(
            "// framework update source marker",
            (string) file_get_contents($projectRoot . DIRECTORY_SEPARATOR . "src" . DIRECTORY_SEPARATOR . "Support" . DIRECTORY_SEPARATOR . "PageMeta.php")
        );
    }

    public function testFrameworkUpdateReportsConflictsWhenManagedFilesChangedLocallyAndUpstream(): void
    {
        $projectRoot = $this->exportProject("Framework Conflict Test");
        $sourceClone = $this->cloneRepository();
        $relativeManagedPath = "src" . DIRECTORY_SEPARATOR . "Support" . DIRECTORY_SEPARATOR . "PageMeta.php";
        $sourceManagedFile = $sourceClone . DIRECTORY_SEPARATOR . $relativeManagedPath;
        $projectManagedFile = $projectRoot . DIRECTORY_SEPARATOR . $relativeManagedPath;

        file_put_contents($sourceManagedFile, (string) file_get_contents($sourceManagedFile) . PHP_EOL . "// upstream framework marker" . PHP_EOL);
        file_put_contents($projectManagedFile, (string) file_get_contents($projectManagedFile) . PHP_EOL . "// local project marker" . PHP_EOL);

        [$checkExitCode, $checkOutput] = $this->runPhpScript(
            $projectRoot . DIRECTORY_SEPARATOR . "fnlla",
            ["framework:update", "--check", "--source", $sourceClone]
        );

        self::assertSame(1, $checkExitCode, $checkOutput);
        self::assertStringContainsString("[CONFLICT] src/Support/PageMeta.php", $checkOutput);
    }

    public function testLegacyStarterUpdateAliasStillRunsButStaysHiddenFromList(): void
    {
        $projectRoot = $this->exportProject("Framework Alias Test");

        [$listExitCode, $listOutput] = $this->runPhpScript(
            $projectRoot . DIRECTORY_SEPARATOR . "fnlla",
            ["list"]
        );

        self::assertSame(0, $listExitCode, $listOutput);
        self::assertStringContainsString("framework:update", $listOutput);
        self::assertFalse(str_contains($listOutput, "starter:update"));

        [$legacyExitCode, $legacyOutput] = $this->runPhpScript(
            $projectRoot . DIRECTORY_SEPARATOR . "fnlla",
            ["starter:update", "--check", "--source", base_path()]
        );

        self::assertSame(0, $legacyExitCode, $legacyOutput);
        self::assertStringContainsString("Framework base is already aligned with the provided source export.", $legacyOutput);
    }

    public function testFrameworkUpdateCanAutoDetectSiblingSourceRepository(): void
    {
        $workspaceRoot = $this->makeTempPath("fnlla-php-framework-update-workspace-");
        mkdir($workspaceRoot, 0777, true);

        $projectRoot = $workspaceRoot . DIRECTORY_SEPARATOR . "project";
        $sourceClone = $workspaceRoot . DIRECTORY_SEPARATOR . "fnlla-php";

        $this->exportProjectTo($projectRoot, "Framework Auto Detect Test");
        mkdir($sourceClone, 0777, true);
        $this->copyDirectory(base_path(), $sourceClone);

        [$checkExitCode, $checkOutput] = $this->runPhpScript(
            $projectRoot . DIRECTORY_SEPARATOR . "fnlla",
            ["framework:update", "--check"]
        );

        self::assertSame(0, $checkExitCode, $checkOutput);
        self::assertStringContainsString("Source repository: ", $checkOutput);
        self::assertTrue(
            str_contains($checkOutput, "auto-detected sibling repository")
            || str_contains($checkOutput, "auto-detected legacy sibling repository"),
            $checkOutput
        );
    }

    private function exportProject(string $appName): string
    {
        $targetPath = $this->makeTempPath("fnlla-php-framework-update-project-");
        $this->exportProjectTo($targetPath, $appName);

        return $targetPath;
    }

    private function exportProjectTo(string $targetPath, string $appName): void
    {
        $container = $GLOBALS["fnlla_php_container"] ?? null;
        self::assertInstanceOf(Container::class, $container);

        $command = new MakeProjectCommand($container);

        self::assertSame(0, $command->handle([$targetPath, $appName]));
    }

    private function cloneRepository(): string
    {
        $targetPath = $this->makeTempPath("fnlla-php-framework-update-source-");
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
