<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA TEST CASE
File: tests\FnllaRuntimeSyncCommandTest.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Validates that the FNLLA Runtime sync command forwards supported CLI options into
  the maintained runtime sync workflow.
*/

namespace Fnlla\Php\Tests;

use Fnlla\Php\Console\Commands\MakeProjectCommand;
use Fnlla\Php\Container\Container;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class FnllaRuntimeSyncCommandTest extends TestCase
{
    /** @var string[] */
    private array $tempPaths = [];

    protected function tearDown(): void
    {
        foreach ($this->tempPaths as $path) {
            $this->removeDirectory($path);
        }
    }

    public function testFnllaRuntimeSyncAcceptsLocalSourceOverride(): void
    {
        $projectRoot = $this->exportProject("FNLLA Runtime Sync Test");
        $runtimeExport = $this->createRuntimeExport("9.9.9");

        [$exitCode, $output] = $this->runPhpScript(
            $projectRoot . DIRECTORY_SEPARATOR . "fnlla",
            ["fnlla-runtime:sync", "--source", $runtimeExport]
        );

        self::assertSame(0, $exitCode, $output);
        self::assertStringContainsString("FNLLA built-in runtime sync completed.", $output);
        self::assertSame(
            "9.9.9",
            trim((string) strtok((string) file_get_contents($projectRoot . DIRECTORY_SEPARATOR . "public" . DIRECTORY_SEPARATOR . "vendor" . DIRECTORY_SEPARATOR . "fnlla-runtime" . DIRECTORY_SEPARATOR . "VERSION"), "\r\n"))
        );
    }

    public function testFnllaRuntimeSyncRejectsUnknownOptions(): void
    {
        $projectRoot = $this->exportProject("FNLLA Runtime Sync Option Error Test");

        [$exitCode, $output] = $this->runPhpScript(
            $projectRoot . DIRECTORY_SEPARATOR . "fnlla",
            ["fnlla-runtime:sync", "--unknown-option"]
        );

        self::assertSame(1, $exitCode, $output);
        self::assertStringContainsString("Unknown option for fnlla-runtime:sync: --unknown-option", $output);
    }

    private function exportProject(string $appName): string
    {
        $targetPath = $this->makeTempPath("fnlla-fnlla-runtime-sync-project-");
        $container = $GLOBALS["fnlla_container"] ?? $GLOBALS["fnlla_php_container"] ?? null;

        self::assertInstanceOf(Container::class, $container);

        $command = new MakeProjectCommand($container);

        self::assertSame(0, $command->handle([$targetPath, $appName]));

        return $targetPath;
    }

    private function createRuntimeExport(string $version): string
    {
        $runtimeRoot = $this->makeTempPath("fnlla-fnlla-runtime-runtime-");
        $cssPath = $runtimeRoot . DIRECTORY_SEPARATOR . "assets" . DIRECTORY_SEPARATOR . "css";
        $jsPath = $runtimeRoot . DIRECTORY_SEPARATOR . "assets" . DIRECTORY_SEPARATOR . "js";
        $iconsPath = $runtimeRoot . DIRECTORY_SEPARATOR . "assets" . DIRECTORY_SEPARATOR . "icons";

        mkdir($cssPath, 0777, true);
        mkdir($jsPath, 0777, true);
        mkdir($iconsPath, 0777, true);

        file_put_contents($runtimeRoot . DIRECTORY_SEPARATOR . "VERSION", $version . PHP_EOL . "Runtime test build" . PHP_EOL);
        file_put_contents($runtimeRoot . DIRECTORY_SEPARATOR . "MANIFEST.json", json_encode([
            "product" => [
                "name" => "FNLLA Runtime",
                "version" => $version,
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
        file_put_contents($runtimeRoot . DIRECTORY_SEPARATOR . "README.md", "# FNLLA Runtime Test Export" . PHP_EOL);
        file_put_contents($runtimeRoot . DIRECTORY_SEPARATOR . "LICENSE.md", "MIT License" . PHP_EOL);
        file_put_contents($runtimeRoot . DIRECTORY_SEPARATOR . "SUPPORT.md", "Support Policy" . PHP_EOL);
        file_put_contents($runtimeRoot . DIRECTORY_SEPARATOR . "TRADEMARKS.md", "Trademark Notice" . PHP_EOL);
        file_put_contents($cssPath . DIRECTORY_SEPARATOR . "fnlla-runtime.css", "/* test runtime */" . PHP_EOL);
        file_put_contents($jsPath . DIRECTORY_SEPARATOR . "fnlla-runtime.js", "window.FNLLARUNTIME = window.FNLLARUNTIME || {};" . PHP_EOL);
        file_put_contents($iconsPath . DIRECTORY_SEPARATOR . "test.svg", "<svg xmlns=\"http://www.w3.org/2000/svg\"></svg>" . PHP_EOL);

        return $runtimeRoot;
    }

    private function makeTempPath(string $prefix): string
    {
        $path = rtrim(sys_get_temp_dir(), "\\/") . DIRECTORY_SEPARATOR . $prefix . bin2hex(random_bytes(4));
        $this->tempPaths[] = $path;

        return $path;
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
