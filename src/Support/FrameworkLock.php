<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA PHP SUPPORT SOURCE
File: src\Support\FrameworkLock.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA PHP is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA PHP framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Tracks the exported framework base so downstream applications can compare
  framework-managed files against a newer FNLLA PHP export safely.
*/

namespace Fnlla\Php\Support;

use JsonException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

final class FrameworkLock
{
    public const LOCK_FILE = ".fnlla/framework-lock.json";
    public const LEGACY_LOCK_FILE = ".fnlla/starter-lock.json";

    public static function path(string $projectRoot): string
    {
        return rtrim($projectRoot, "\\/") . DIRECTORY_SEPARATOR . str_replace("/", DIRECTORY_SEPARATOR, self::LOCK_FILE);
    }

    public static function legacyPath(string $projectRoot): string
    {
        return rtrim($projectRoot, "\\/") . DIRECTORY_SEPARATOR . str_replace("/", DIRECTORY_SEPARATOR, self::LEGACY_LOCK_FILE);
    }

    public static function write(string $projectRoot, string $sourceRoot, string $appName, string $packageSlug): array
    {
        $lock = self::build($projectRoot, $sourceRoot, $appName, $packageSlug);
        $legacyLock = self::buildLegacy($lock);
        $directory = dirname(self::path($projectRoot));

        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new RuntimeException("Unable to create framework lock directory: " . $directory);
        }

        file_put_contents(self::path($projectRoot), self::encode($lock, "framework lock"));
        file_put_contents(self::legacyPath($projectRoot), self::encode($legacyLock, "legacy framework lock"));

        return $lock;
    }

    public static function load(string $projectRoot): array
    {
        $path = self::existingPath($projectRoot);

        if ($path === null) {
            throw new RuntimeException(
                "Framework lock is missing. This project needs " . self::LOCK_FILE . " before framework updates can be checked."
            );
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException("Unable to read framework lock: " . $path);
        }

        try {
            $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException("Framework lock is not valid JSON: " . $exception->getMessage(), 0, $exception);
        }

        if (!is_array($decoded)) {
            throw new RuntimeException("Framework lock must decode to a JSON object.");
        }

        return self::normalize($decoded);
    }

    public static function syncFromExport(string $exportRoot, string $projectRoot): void
    {
        foreach ([
            self::path($exportRoot) => self::path($projectRoot),
            self::legacyPath($exportRoot) => self::legacyPath($projectRoot),
        ] as $sourcePath => $targetPath) {
            if (!is_file($sourcePath)) {
                continue;
            }

            $directory = dirname($targetPath);

            if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
                throw new RuntimeException("Unable to create framework lock directory: " . $directory);
            }

            if (!copy($sourcePath, $targetPath)) {
                throw new RuntimeException("Unable to copy framework lock file: " . $targetPath);
            }
        }
    }

    public static function build(string $projectRoot, string $sourceRoot, string $appName, string $packageSlug): array
    {
        return [
            "schema_version" => 2,
            "framework_base" => [
                "application" => [
                    "name" => $appName,
                    "package_slug" => $packageSlug,
                ],
                "framework" => [
                    "name" => "FNLLA PHP",
                    "slug" => "fnlla-php",
                    "version" => self::readVersion($sourceRoot . DIRECTORY_SEPARATOR . "VERSION"),
                    "repository" => "https://github.com/techayoDEV/fnlla-php.git",
                ],
                "ui_runtime" => [
                    "name" => "FNLLA Web",
                    "slug" => "fnlla-web",
                    "version" => self::readVersion($sourceRoot . DIRECTORY_SEPARATOR . "public" . DIRECTORY_SEPARATOR . "vendor" . DIRECTORY_SEPARATOR . "fnlla-web" . DIRECTORY_SEPARATOR . "VERSION"),
                    "repository" => "https://github.com/techayoDEV/fnlla-web.git",
                ],
                "lock_file" => self::LOCK_FILE,
                "managed_files" => self::managedFileHashes($projectRoot),
                "generated_at_utc" => gmdate(DATE_ATOM),
            ],
        ];
    }

    public static function managedFileHashes(string $projectRoot): array
    {
        $projectRoot = rtrim($projectRoot, "\\/");
        $hashes = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($projectRoot, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $item) {
            if (!$item->isFile()) {
                continue;
            }

            $path = $item->getPathname();
            $relativePath = self::normalizeSeparators(substr($path, strlen($projectRoot) + 1));

            if (!self::isFrameworkManagedPath($relativePath)) {
                continue;
            }

            $hash = hash_file("sha256", $path);

            if (!is_string($hash) || $hash === "") {
                throw new RuntimeException("Unable to hash framework-managed file: " . $path);
            }

            $hashes[$relativePath] = $hash;
        }

        ksort($hashes);

        return $hashes;
    }

    public static function isFrameworkManagedPath(string $relativePath): bool
    {
        $relativePath = self::normalizeSeparators($relativePath);

        if (in_array($relativePath, [self::LOCK_FILE, self::LEGACY_LOCK_FILE], true)) {
            return false;
        }

        if (str_starts_with($relativePath, "views/maintenance/")) {
            return true;
        }

        foreach ([
            ".fnlla/",
            "database/factories/",
            "database/migrations/",
            "lang/",
            "public/assets/",
            "public/vendor/fnlla-web/",
            "storage/",
            "views/",
        ] as $prefix) {
            if (str_starts_with($relativePath, $prefix)) {
                return false;
            }
        }

        return !in_array($relativePath, [
            ".env.example",
            "README.md",
            "MANIFEST.json",
            "VERSION",
            "composer.json",
            "database/seeders/DatabaseSeeder.php",
            "routes/console.php",
            "routes/web.php",
            "src/Controllers/HomeController.php",
            "tests/BootstrapAutoloadTest.php",
        ], true);
    }

    private static function existingPath(string $projectRoot): ?string
    {
        $path = self::path($projectRoot);

        if (is_file($path)) {
            return $path;
        }

        $legacyPath = self::legacyPath($projectRoot);

        return is_file($legacyPath) ? $legacyPath : null;
    }

    private static function normalize(array $decoded): array
    {
        if (isset($decoded["framework_base"]) && is_array($decoded["framework_base"])) {
            return $decoded;
        }

        $legacy = is_array($decoded["starter"] ?? null) ? $decoded["starter"] : [];

        return [
            "schema_version" => 2,
            "framework_base" => [
                "application" => (array) ($legacy["app"] ?? []),
                "framework" => (array) ($legacy["framework"] ?? []),
                "ui_runtime" => (array) ($legacy["ui_runtime"] ?? []),
                "lock_file" => self::LOCK_FILE,
                "managed_files" => (array) ($legacy["managed_files"] ?? []),
                "generated_at_utc" => (string) ($legacy["generated_at_utc"] ?? ""),
            ],
        ];
    }

    private static function buildLegacy(array $lock): array
    {
        $base = (array) ($lock["framework_base"] ?? []);

        return [
            "schema_version" => 1,
            "starter" => [
                "app" => (array) ($base["application"] ?? []),
                "framework" => (array) ($base["framework"] ?? []),
                "ui_runtime" => (array) ($base["ui_runtime"] ?? []),
                "lock_file" => self::LEGACY_LOCK_FILE,
                "managed_files" => (array) ($base["managed_files"] ?? []),
                "generated_at_utc" => (string) ($base["generated_at_utc"] ?? ""),
            ],
        ];
    }

    private static function readVersion(string $path): string
    {
        $contents = file($path, FILE_IGNORE_NEW_LINES);

        if (!is_array($contents)) {
            throw new RuntimeException("Unable to read version file for framework lock: " . $path);
        }

        $version = trim((string) ($contents[0] ?? ""));

        if ($version === "") {
            throw new RuntimeException("Version file has an empty first line: " . $path);
        }

        return $version;
    }

    private static function encode(array $lock, string $label): string
    {
        try {
            return json_encode($lock, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
        } catch (JsonException $exception) {
            throw new RuntimeException("Unable to encode {$label} JSON: " . $exception->getMessage(), 0, $exception);
        }
    }

    private static function normalizeSeparators(string $path): string
    {
        return str_replace("\\", "/", $path);
    }
}
