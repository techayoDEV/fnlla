<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA PHP SUPPORT SOURCE
File: src\Support\VersionManifest.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA PHP is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA PHP framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Supports framework maintenance, validation, release hygiene or repository hardening.
*/

namespace Fnlla\Php\Support;

use JsonException;
use RuntimeException;

final class VersionManifest
{
    private const ROOT_MANIFEST_FILE = "MANIFEST.json";
    private const ROOT_VERSION_FILE = "VERSION";
    private const ROOT_LICENSE_FILE = "LICENSE.md";
    private const ROOT_SUPPORT_FILE = "SUPPORT.md";
    private const ROOT_TRADEMARKS_FILE = "TRADEMARKS.md";
    private const UI_VERSION_FILE = "public/vendor/fnlla-web/VERSION";
    private const SEMVER_PATTERN = '/^\d+\.\d+\.\d+$/';

    public static function repositoryManifestPath(): string
    {
        return base_path(self::ROOT_MANIFEST_FILE);
    }

    public static function repositoryVersionPath(): string
    {
        return base_path(self::ROOT_VERSION_FILE);
    }

    public static function vendoredUiVersionPath(): string
    {
        return base_path(self::UI_VERSION_FILE);
    }

    public static function syncRepositoryManifest(): array
    {
        $manifest = self::buildRepositoryManifest();
        file_put_contents(self::repositoryManifestPath(), self::encodeManifest($manifest));

        return $manifest;
    }

    public static function buildRepositoryManifest(): array
    {
        $frameworkVersion = self::readVersionValue(self::repositoryVersionPath());
        $uiVersion = self::readVersionValue(self::vendoredUiVersionPath());
        $uiMajor = (int) explode(".", $uiVersion)[0];

        return [
            "schema_version" => 1,
            "product" => [
                "name" => "FNLLA",
                "slug" => "fnlla",
                "version" => $frameworkVersion,
                "owner" => "TechAyo LTD (techayo.co.uk)",
                "origin" => "Finella Gardens in Dundee, UK",
                "repository" => "https://github.com/techayoDEV/fnlla.git",
                "source_of_truth" => "github",
            ],
            "runtime" => [
                "php" => "8.3",
                "database" => "mysql",
                "public_entrypoints" => [
                    "public/index.php",
                    "public/router.php",
                ],
            ],
            "ui_runtime" => [
                "name" => "FNLLA Web",
                "slug" => "fnlla-web",
                "repository" => "https://github.com/techayoDEV/fnlla-web.git",
                "source_of_truth" => "github",
                "version_path" => self::UI_VERSION_FILE,
                "vendored_version" => $uiVersion,
                "validated_version" => $uiVersion,
                "expected_major" => $uiMajor,
            ],
            "release" => [
                "channel" => "stable",
                "state_files" => [
                    "MANIFEST.json",
                    "README.md",
                    "VERSION",
                    "LICENSE.md",
                    "SUPPORT.md",
                    "TRADEMARKS.md",
                ],
            ],
        ];
    }

    public static function loadRepositoryManifest(): array
    {
        return self::readJsonFile(self::repositoryManifestPath());
    }

    public static function status(): array
    {
        $frameworkVersion = self::safeReadVersion(self::repositoryVersionPath());
        $uiVersion = self::safeReadVersion(self::vendoredUiVersionPath());
        $repositoryManifestExists = is_file(self::repositoryManifestPath());
        $errors = self::validateRepositoryManifest();

        return [
            "framework_version" => $frameworkVersion,
            "vendored_ui_version" => $uiVersion,
            "repository_manifest_exists" => $repositoryManifestExists,
            "version_contract_ok" => $errors === [],
            "errors" => $errors,
        ];
    }

    public static function validateRepositoryManifest(): array
    {
        $errors = [];
        $frameworkVersionLines = self::safeReadFileLines(self::repositoryVersionPath());
        $uiVersionLines = self::safeReadFileLines(self::vendoredUiVersionPath());
        $frameworkVersion = trim((string) ($frameworkVersionLines[0] ?? ""));
        $uiVersion = trim((string) ($uiVersionLines[0] ?? ""));

        if ($frameworkVersionLines === null) {
            $errors[] = "VERSION: missing file";
        } else {
            if ($frameworkVersion === "") {
                $errors[] = "VERSION: first line is empty";
            } elseif (!preg_match(self::SEMVER_PATTERN, $frameworkVersion)) {
                $errors[] = "VERSION: '{$frameworkVersion}' is not a semantic version";
            }

            foreach (["TechAyo LTD (techayo.co.uk)", "Finella Gardens, Dundee, UK"] as $requiredText) {
                if (!str_contains(implode("\n", $frameworkVersionLines), $requiredText)) {
                    $errors[] = "VERSION: missing required text '{$requiredText}'";
                }
            }
        }

        if ($uiVersionLines === null) {
            $errors[] = "public/vendor/fnlla-web/VERSION: missing file";
        } else {
            if ($uiVersion === "") {
                $errors[] = "public/vendor/fnlla-web/VERSION: first line is empty";
            } elseif (!preg_match(self::SEMVER_PATTERN, $uiVersion)) {
                $errors[] = "public/vendor/fnlla-web/VERSION: '{$uiVersion}' is not a semantic version";
            }
        }

        self::validateRequiredTextFile(
            self::ROOT_LICENSE_FILE,
            ["MIT License", "Permission is hereby granted", 'THE SOFTWARE IS PROVIDED "AS IS"'],
            $errors
        );
        self::validateRequiredTextFile(
            self::ROOT_SUPPORT_FILE,
            ["Support Policy", "MIT License", "TechAyo LTD", "does not promise", "release cadence"],
            $errors
        );
        self::validateRequiredTextFile(
            self::ROOT_TRADEMARKS_FILE,
            ["Trademark Notice", "MIT License", "TechAyo LTD", "does not grant trademark rights", "official FNLLA PHP project"],
            $errors
        );

        try {
            $expectedManifest = self::buildRepositoryManifest();
            $expectedManifestContent = self::encodeManifest($expectedManifest);
        } catch (RuntimeException $exception) {
            $errors[] = $exception->getMessage();
            $expectedManifest = null;
            $expectedManifestContent = null;
        }

        if (!is_file(self::repositoryManifestPath())) {
            $errors[] = "MANIFEST.json: missing file";
        } elseif ($expectedManifestContent !== null) {
            $actualManifestContent = file_get_contents(self::repositoryManifestPath());

            if ($actualManifestContent === false) {
                $errors[] = "MANIFEST.json: unable to read file";
            } elseif (self::normalizeNewlines($actualManifestContent) !== self::normalizeNewlines($expectedManifestContent)) {
                $errors[] = "MANIFEST.json: repository manifest is out of sync. Run php scripts/sync-version-manifest.php";
            }
        }

        if ($expectedManifest !== null && is_array($expectedManifest)) {
            $expectedUiMajor = (int) (($expectedManifest["ui_runtime"]["expected_major"] ?? 0));
            $actualUiMajor = (int) explode(".", $uiVersion !== "" ? $uiVersion : "0.0.0")[0];

            if ($expectedUiMajor !== $actualUiMajor) {
                $errors[] = "MANIFEST.json: ui_runtime.expected_major does not match the vendored FNLLA Web major version";
            }
        }

        return $errors;
    }

    public static function encodeManifest(array $manifest): string
    {
        try {
            return json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
        } catch (JsonException $exception) {
            throw new RuntimeException("Unable to encode MANIFEST.json: " . $exception->getMessage(), 0, $exception);
        }
    }

    private static function readVersionValue(string $path): string
    {
        $lines = self::safeReadFileLines($path);

        if ($lines === null) {
            throw new RuntimeException(str_replace("\\", "/", self::relativePath($path)) . ": missing file");
        }

        $version = trim((string) ($lines[0] ?? ""));

        if ($version === "") {
            throw new RuntimeException(str_replace("\\", "/", self::relativePath($path)) . ": first line is empty");
        }

        if (!preg_match(self::SEMVER_PATTERN, $version)) {
            throw new RuntimeException(str_replace("\\", "/", self::relativePath($path)) . ": '{$version}' is not a semantic version");
        }

        return $version;
    }

    private static function readJsonFile(string $path): array
    {
        $content = file_get_contents($path);

        if ($content === false) {
            throw new RuntimeException(str_replace("\\", "/", self::relativePath($path)) . ": unable to read file");
        }

        try {
            $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException(str_replace("\\", "/", self::relativePath($path)) . ": invalid JSON (" . $exception->getMessage() . ")", 0, $exception);
        }

        if (!is_array($decoded)) {
            throw new RuntimeException(str_replace("\\", "/", self::relativePath($path)) . ": expected a JSON object");
        }

        return $decoded;
    }

    private static function safeReadVersion(string $path): ?string
    {
        try {
            return self::readVersionValue($path);
        } catch (RuntimeException) {
            return null;
        }
    }

    private static function validateRequiredTextFile(string $relativePath, array $requiredTexts, array &$errors): void
    {
        $path = base_path($relativePath);

        if (!is_file($path)) {
            $errors[] = str_replace("\\", "/", $relativePath) . ": missing file";
            return;
        }

        $content = file_get_contents($path);

        if ($content === false) {
            $errors[] = str_replace("\\", "/", $relativePath) . ": unable to read file";
            return;
        }

        foreach ($requiredTexts as $requiredText) {
            if (!str_contains($content, $requiredText)) {
                $errors[] = str_replace("\\", "/", $relativePath) . ": missing required text '{$requiredText}'";
            }
        }
    }

    private static function safeReadFileLines(string $path): ?array
    {
        if (!is_file($path)) {
            return null;
        }

        $content = file_get_contents($path);

        if ($content === false) {
            throw new RuntimeException(str_replace("\\", "/", self::relativePath($path)) . ": unable to read file");
        }

        return preg_split('/\r\n|\r|\n/', $content) ?: [];
    }

    private static function normalizeNewlines(string $content): string
    {
        return str_replace(["\r\n", "\r"], "\n", $content);
    }

    private static function relativePath(string $path): string
    {
        $base = base_path();
        $normalizedPath = str_replace("/", DIRECTORY_SEPARATOR, $path);

        if (str_starts_with($normalizedPath, $base . DIRECTORY_SEPARATOR)) {
            return substr($normalizedPath, strlen($base) + 1);
        }

        return $normalizedPath;
    }
}
