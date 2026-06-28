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

    public function testExportedStarterIncludesVersionContractFilesAndChecks(): void
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

        [$exitCode, $output] = $this->runPhpScript(
            $this->targetPath . DIRECTORY_SEPARATOR . "scripts" . DIRECTORY_SEPARATOR . "validate-version-manifest.php"
        );

        self::assertSame(0, $exitCode, $output);
        self::assertStringContainsString("FNLLA PHP version manifest passed.", $output);
    }

    private function runPhpScript(string $scriptPath): array
    {
        $command = '"' . PHP_BINARY . '" "' . $scriptPath . '" 2>&1';
        $lines = [];
        $exitCode = 1;

        exec($command, $lines, $exitCode);

        return [$exitCode, implode(PHP_EOL, $lines)];
    }
}
