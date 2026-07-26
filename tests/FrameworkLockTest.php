<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA TEST CASE
File: tests\FrameworkLockTest.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Validates framework lock path behavior for exported downstream projects.
*/

namespace Fnlla\Php\Tests;

use Fnlla\Php\Support\FrameworkLock;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class FrameworkLockTest extends TestCase
{
    private array $frameworkUpdateConfigBackup = [];

    protected function setUp(): void
    {
        $this->frameworkUpdateConfigBackup = (array) config("framework_update", []);
    }

    protected function tearDown(): void
    {
        config_set("framework_update", $this->frameworkUpdateConfigBackup);
    }

    public function testFrameworkLockDefaultsStayInFnllaDirectory(): void
    {
        self::assertSame(".fnlla/framework-lock.json", FrameworkLock::lockFile());
        self::assertSame(".fnlla/legacy-framework-lock.json", FrameworkLock::migrationLockFile());
        self::assertSame(
            base_path(".fnlla" . DIRECTORY_SEPARATOR . "framework-lock.json"),
            FrameworkLock::path(base_path())
        );
    }

    public function testFrameworkLockPathCanBeConfiguredWithoutMovingDefaultDirectory(): void
    {
        config_set("framework_update", array_merge((array) config("framework_update", []), [
            "lock_file" => ".fnlla/custom-framework-lock.json",
            "migration_lock_file" => ".fnlla/custom-migration-lock.json",
        ]));

        self::assertSame(".fnlla/custom-framework-lock.json", FrameworkLock::lockFile());
        self::assertSame(".fnlla/custom-migration-lock.json", FrameworkLock::migrationLockFile());
        self::assertSame(
            base_path(".fnlla" . DIRECTORY_SEPARATOR . "custom-framework-lock.json"),
            FrameworkLock::path(base_path())
        );
    }

    public function testFrameworkLockDoesNotTrackLocalOrSecretArtifacts(): void
    {
        foreach ([
            ".env",
            ".env.local",
            ".git/config",
            "docs/CLIENT_HANDOVER.md",
            "node_modules/package/index.js",
            "output/report.html",
            "playwright-report/index.html",
            "test-results/result.json",
            "tmp/browser-profile/Preferences",
            "vendor/autoload.php",
        ] as $path) {
            self::assertFalse(FrameworkLock::isFrameworkManagedPath($path), $path);
        }
    }

    public function testOutdatedLockJsonWithoutFrameworkBaseIsRejected(): void
    {
        $projectRoot = $this->makeTempDirectory("fnlla-lock-legacy-");

        try {
            $lockDirectory = $projectRoot . DIRECTORY_SEPARATOR . ".fnlla";

            if (!mkdir($lockDirectory, 0777, true) && !is_dir($lockDirectory)) {
                self::fail("Unable to create temporary lock directory.");
            }

            file_put_contents($lockDirectory . DIRECTORY_SEPARATOR . "legacy-framework-lock.json", json_encode([
                "schema_version" => 1,
                "project" => [
                    "app" => [
                        "name" => "old-app",
                        "package_slug" => "old-app",
                    ],
                    "framework" => [
                        "name" => "FNLLA",
                        "version" => "1.0.0",
                    ],
                    "managed_files" => [],
                    "generated_at_utc" => "2026-01-01T00:00:00+00:00",
                ],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            $this->expectException(RuntimeException::class);
            FrameworkLock::load($projectRoot);
        } finally {
            $this->removeDirectory($projectRoot);
        }
    }

    public function testFrameworkLockRejectsAbsoluteConfiguredPaths(): void
    {
        config_set("framework_update", array_merge((array) config("framework_update", []), [
            "lock_file" => "C:/outside/framework-lock.json",
            "migration_lock_file" => "/outside/migration-lock.json",
        ]));

        self::assertSame(".fnlla/framework-lock.json", FrameworkLock::lockFile());
        self::assertSame(".fnlla/legacy-framework-lock.json", FrameworkLock::migrationLockFile());
    }

    public function testFrameworkLockWritesOnlyCanonicalProjectLock(): void
    {
        $projectRoot = $this->makeTempDirectory("fnlla-lock-project-");
        $sourceRoot = $this->makeTempDirectory("fnlla-lock-source-");

        try {
            $runtimeDirectory = $sourceRoot . DIRECTORY_SEPARATOR . "public" . DIRECTORY_SEPARATOR . "vendor" . DIRECTORY_SEPARATOR . "fnlla-runtime";

            if (!mkdir($runtimeDirectory, 0777, true) && !is_dir($runtimeDirectory)) {
                self::fail("Unable to create temporary runtime directory.");
            }

            file_put_contents($sourceRoot . DIRECTORY_SEPARATOR . "VERSION", "1.2.3" . PHP_EOL);
            file_put_contents($runtimeDirectory . DIRECTORY_SEPARATOR . "VERSION", "1.2.3" . PHP_EOL);

            FrameworkLock::write($projectRoot, $sourceRoot, "FNLLA_PROJECT", "fnlla-project");

            self::assertFileExists($projectRoot . DIRECTORY_SEPARATOR . ".fnlla" . DIRECTORY_SEPARATOR . "framework-lock.json");
            self::assertFalse(is_file($projectRoot . DIRECTORY_SEPARATOR . ".fnlla" . DIRECTORY_SEPARATOR . "legacy-framework-lock.json"));
        } finally {
            $this->removeDirectory($projectRoot);
            $this->removeDirectory($sourceRoot);
        }
    }

    private function makeTempDirectory(string $prefix): string
    {
        $path = rtrim(sys_get_temp_dir(), "\\/") . DIRECTORY_SEPARATOR . $prefix . bin2hex(random_bytes(8));

        if (!mkdir($path, 0777, true) && !is_dir($path)) {
            self::fail("Unable to create temporary directory: " . $path);
        }

        return $path;
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = scandir($path);

        if (!is_array($items)) {
            return;
        }

        foreach ($items as $item) {
            if ($item === "." || $item === "..") {
                continue;
            }

            $itemPath = $path . DIRECTORY_SEPARATOR . $item;

            if (is_dir($itemPath)) {
                $this->removeDirectory($itemPath);
                continue;
            }

            unlink($itemPath);
        }

        rmdir($path);
    }
}
