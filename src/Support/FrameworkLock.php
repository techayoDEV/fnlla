<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA SUPPORT SOURCE
File: src\Support\FrameworkLock.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Tracks the exported framework base so downstream applications can compare
  framework-managed files against a newer FNLLA export safely.
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
    private const STARTER_SURFACE_MANAGED_PATHS = [
        "public/assets/app.css",
        "routes/web.php",
        "src/Controllers/PageController.php",
        "views/layouts/app.php",
        "views/pages/about.php",
        "views/pages/api-health.php",
        "views/pages/contact.php",
        "views/pages/docs.php",
        "views/pages/error.php",
        "views/pages/health.php",
        "views/pages/home.php",
        "views/pages/not-found.php",
        "views/pages/services.php",
    ];
    private const LEGACY_UNTRACKED_MANAGED_HASHES = [
        "1.0.18" => [
            "public/assets/app.css" => "033d403648c515625a8ac2617c27ec6fc4a86e419d48750dd6edb9bf504ae9ba",
            "routes/web.php" => "3146de20fb78986a9ed0c61ed856f552e88bc3bed42686a952c941bbe4d34a0c",
            "src/Controllers/PageController.php" => "10ac57288b1c07335501d6f61056fbb7493ee26bad3adfea5ae60c2a30b0821a",
            "views/layouts/app.php" => "51f7110b622fd9c35e6d94cac72a8135488bba4ebec9504702095d111529bd91",
            "views/pages/about.php" => "dcd2d376e3f56d3ecc6ad3423524cb2071645b04f91d39364ce6a60ae74ad5ab",
            "views/pages/contact.php" => "2634d65eb571c82578f2304e68c8241abd1fef030fc56d036832ac8cc2648d9c",
            "views/pages/docs.php" => "032372397f0595abe9afb3bb8a39ffa3f7c830e4c81a7dceb3b5ed39ccd4211f",
            "views/pages/error.php" => "20ba77b2b013401b97e5667e3144cb9c5bd523ea82e1dd6b6e66445510562fa3",
            "views/pages/health.php" => "a775d0f35236f95a9f30545bfefc47bcef39b53bcc740c1809599ed8be0c2de6",
            "views/pages/home.php" => "62ade0d125bc276a9a49e09bdb4655b69c05ca9f3113eb8c3185d80b667028d2",
            "views/pages/not-found.php" => "edf833a1bfab1dc1a8a35c65a094a9f6125ede7ee4daa0e6b2b585902a06023c",
            "views/pages/services.php" => "d9fd32df570cc0bff9c79b3616205e14c128945f6ed2fdcf80d831277fe42c3e",
        ],
        "1.0.19" => [
            "public/assets/app.css" => "9ad7853c2664d4cf03bcb223d0aee1f015bfe28616eed2e9905de3cdb18f17f2",
            "routes/web.php" => "3b18dba0915a10df7f6603838f4f977df1d166533ca95258e809d14d5d2ce91a",
            "src/Controllers/PageController.php" => "640cba89999fda6a1073135048e7f0ce3a52f2d41237b22931257043650169b3",
            "views/layouts/app.php" => "67dbfbbed54ed47dc19eb826da8ad6f10baef56f3057f9ecf5b3b184d0e6ecfa",
            "views/pages/about.php" => "a2c9db10aa648b4d13e181b94133f0cf7a1d7a90f8e7d3c3f643c3d2d1d61db3",
            "views/pages/api-health.php" => "ada636ad0debb3c99abf841271fdb57a68c44fced7501658920b966a22d1917d",
            "views/pages/docs.php" => "f72acf96fc36ee77ef3faf3c8958f219127d81f0be934da5349a533ee9ab2e4b",
            "views/pages/error.php" => "8f5ed24ca8f9e7470bb8cad6e7f9bd1639c403e1f212297452f1497592d7a632",
            "views/pages/health.php" => "7502ad8592de4605136717742d4636297af30edd044964fa5bc1f217e5328317",
            "views/pages/home.php" => "4630d329f45b84825db422a8b704265bf594045939747627787acd34c4d7f75f",
            "views/pages/not-found.php" => "6eb9285780f4f1e7dfef284a7894a3672385b0c4491dbb64a9d27d071bbb2343",
            "views/pages/services.php" => "9191aa115b3f388f85cda5a442e5b8a100c144e1e3d3a41df29326ed8da37a34",
        ],
    ];

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
                    "name" => "FNLLA",
                    "slug" => "fnlla",
                    "version" => self::readVersion($sourceRoot . DIRECTORY_SEPARATOR . "VERSION"),
                    "repository" => "https://github.com/techayoDEV/fnlla.git",
                ],
                "ui_runtime" => [
                    "name" => "Integrated FNLLA UI surface",
                    "slug" => "fnlla-runtime",
                    "version" => self::readVersion($sourceRoot . DIRECTORY_SEPARATOR . "public" . DIRECTORY_SEPARATOR . "vendor" . DIRECTORY_SEPARATOR . "fnlla-runtime" . DIRECTORY_SEPARATOR . "VERSION"),
                    "repository" => "https://github.com/techayoDEV/fnlla.git",
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

    public static function legacyUntrackedManagedHashes(string $frameworkVersion): array
    {
        return self::LEGACY_UNTRACKED_MANAGED_HASHES[$frameworkVersion] ?? [];
    }

    public static function isFrameworkManagedPath(string $relativePath): bool
    {
        $relativePath = self::normalizeSeparators($relativePath);

        if (in_array($relativePath, [self::LOCK_FILE, self::LEGACY_LOCK_FILE], true)) {
            return false;
        }

        if (in_array($relativePath, self::STARTER_SURFACE_MANAGED_PATHS, true)) {
            return true;
        }

        if (str_starts_with($relativePath, "views/maintenance/")) {
            return true;
        }

        if (str_starts_with($relativePath, "views/developer/")) {
            return true;
        }

        foreach ([
            ".fnlla/",
            "database/factories/",
            "database/migrations/",
            "lang/",
            "public/assets/",
            "public/vendor/fnlla-runtime/",
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
            "src/Controllers/PageController.php",
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
