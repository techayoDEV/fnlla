<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA SUPPORT SOURCE
File: src\Support\VersionManifest.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA framework released under the MIT License and its related delivery scripts, tests,
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
    private const UI_MANIFEST_FILE = "public/vendor/fnlla-runtime/MANIFEST.json";
    private const UI_README_FILE = "public/vendor/fnlla-runtime/README.md";
    private const UI_JS_FILE = "public/vendor/fnlla-runtime/assets/js/fnlla-runtime.js";
    private const UI_VERSION_FILE = "public/vendor/fnlla-runtime/VERSION";
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
        $version = self::readVersionValue(self::repositoryVersionPath());
        self::syncIntegratedRuntimeMetadata($version);
        $existingManifest = is_file(self::repositoryManifestPath())
            ? self::readJsonFile(self::repositoryManifestPath())
            : [];
        $manifest = self::isClaimedProjectManifest($existingManifest)
            ? self::buildClaimedProjectManifest(self::identityFromClaimedManifest($existingManifest), base_path())
            : self::buildRepositoryManifest();
        file_put_contents(self::repositoryManifestPath(), self::encodeManifest($manifest));

        return $manifest;
    }

    public static function buildRepositoryManifest(): array
    {
        $frameworkVersion = self::readVersionValue(self::repositoryVersionPath());

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
                "name" => "Integrated FNLLA UI surface",
                "slug" => "integrated-ui-surface",
                "repository" => "https://github.com/techayoDEV/fnlla.git",
                "source_of_truth" => "github",
                "version_path" => self::UI_VERSION_FILE,
                "distribution_path" => "public/vendor/fnlla-runtime",
                "version" => $frameworkVersion,
                "version_model" => "shared_with_product",
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

    public static function buildClaimedProjectManifest(array $identity, ?string $projectRoot = null): array
    {
        $projectRoot = rtrim($projectRoot ?? base_path(), "\\/");
        $frameworkVersion = self::readVersionValue($projectRoot . DIRECTORY_SEPARATOR . self::ROOT_VERSION_FILE);
        $runtimeVersion = self::readVersionValue($projectRoot . DIRECTORY_SEPARATOR . self::UI_VERSION_FILE);

        return [
            "schema_version" => 2,
            "manifest_type" => "claimed_project",
            "product" => [
                "identifier" => (string) $identity["id"],
                "name" => (string) $identity["product"],
                "slug" => (string) $identity["slug"],
                "version" => $frameworkVersion,
                "summary" => (string) $identity["summary"],
                "claimed" => true,
                "owner" => [
                    "name" => (string) $identity["owner"],
                    "roles" => [
                        "owner",
                        "system_owner",
                    ],
                ],
                "funder" => [
                    "name" => (string) $identity["funder"],
                    "roles" => [
                        "funder",
                    ],
                ],
                "client" => [
                    "name" => (string) $identity["client"],
                    "roles" => [
                        "client",
                    ],
                ],
                "developer" => [
                    "name" => (string) $identity["developer"],
                    "roles" => [
                        "developer",
                        "implementation_provider",
                    ],
                ],
                "maintenance_provider" => [
                    "name" => (string) $identity["maintainer"],
                    "roles" => [
                        "maintenance_provider",
                        "update_provider",
                    ],
                ],
            ],
            "framework" => [
                "name" => (string) $identity["runtime"],
                "slug" => "fnlla",
                "version" => $frameworkVersion,
                "creator" => (string) $identity["runtime_creator"],
                "repository" => "https://github.com/techayoDEV/fnlla.git",
                "lock_file" => ".fnlla/framework-lock.json",
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
                "name" => "Integrated FNLLA UI surface",
                "slug" => "integrated-ui-surface",
                "repository" => "https://github.com/techayoDEV/fnlla.git",
                "source_of_truth" => "github",
                "version_path" => self::UI_VERSION_FILE,
                "distribution_path" => "public/vendor/fnlla-runtime",
                "version" => $runtimeVersion,
                "version_model" => "shared_with_framework",
                "creator" => (string) $identity["runtime_creator"],
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

    public static function status(): array
    {
        $fnllaVersion = self::safeReadVersion(self::repositoryVersionPath());
        $uiVersion = self::safeReadVersion(self::vendoredUiVersionPath());
        $repositoryManifestExists = is_file(self::repositoryManifestPath());
        $errors = self::validateRepositoryManifest();
        $integratedRuntimeSynced = $fnllaVersion !== null
            && $uiVersion !== null
            && $fnllaVersion === $uiVersion;

        return [
            "fnlla_version" => $fnllaVersion,
            "integrated_runtime_version" => $uiVersion,
            "integrated_runtime_synced" => $integratedRuntimeSynced,
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
            $errors[] = "public/vendor/fnlla-runtime/VERSION: missing file";
        } else {
            if ($uiVersion === "") {
                $errors[] = "public/vendor/fnlla-runtime/VERSION: first line is empty";
            } elseif (!preg_match(self::SEMVER_PATTERN, $uiVersion)) {
                $errors[] = "public/vendor/fnlla-runtime/VERSION: '{$uiVersion}' is not a semantic version";
            } elseif ($frameworkVersion !== "" && $frameworkVersion !== $uiVersion) {
                $errors[] = "public/vendor/fnlla-runtime/VERSION: must match VERSION ({$uiVersion} vs {$frameworkVersion})";
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
            ["Trademark Notice", "MIT License", "TechAyo LTD", "does not grant trademark rights", "official FNLLA project"],
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
            } else {
                try {
                    $actualManifest = json_decode($actualManifestContent, true, 512, JSON_THROW_ON_ERROR);
                } catch (JsonException $exception) {
                    $actualManifest = null;
                    $errors[] = "MANIFEST.json: invalid JSON (" . $exception->getMessage() . ")";
                }

                if (is_array($actualManifest) && self::isClaimedProjectManifest($actualManifest)) {
                    self::validateClaimedProjectManifest($actualManifest, $frameworkVersion, $uiVersion, $errors);
                } elseif (self::normalizeNewlines($actualManifestContent) !== self::normalizeNewlines($expectedManifestContent)) {
                    $errors[] = "MANIFEST.json: repository manifest is out of sync. Run php scripts/sync-version-manifest.php";
                }
            }
        }

        try {
            $expectedRuntimeManifest = self::buildIntegratedRuntimeManifest($frameworkVersion);
            $expectedRuntimeManifestContent = self::encodeManifest($expectedRuntimeManifest);
        } catch (RuntimeException $exception) {
            $errors[] = $exception->getMessage();
            $expectedRuntimeManifestContent = null;
        }

        $runtimeManifestPath = base_path(self::UI_MANIFEST_FILE);

        if (!is_file($runtimeManifestPath)) {
            $errors[] = self::UI_MANIFEST_FILE . ": missing file";
        } elseif ($expectedRuntimeManifestContent !== null) {
            $actualRuntimeManifestContent = file_get_contents($runtimeManifestPath);

            if ($actualRuntimeManifestContent === false) {
                $errors[] = self::UI_MANIFEST_FILE . ": unable to read file";
            } elseif (self::normalizeNewlines($actualRuntimeManifestContent) !== self::normalizeNewlines($expectedRuntimeManifestContent)) {
                $errors[] = self::UI_MANIFEST_FILE . ": integrated runtime manifest is out of sync. Run php scripts/sync-version-manifest.php";
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

    private static function syncIntegratedRuntimeMetadata(string $version): void
    {
        self::syncVersionFile(self::vendoredUiVersionPath(), $version);
        self::syncRuntimeReadme(base_path(self::UI_README_FILE), $version);
        self::syncRuntimeJavascriptVersion(base_path(self::UI_JS_FILE), $version);
        file_put_contents(
            base_path(self::UI_MANIFEST_FILE),
            self::encodeManifest(self::buildIntegratedRuntimeManifest($version))
        );
    }

    private static function syncVersionFile(string $path, string $version): void
    {
        $lines = self::safeReadFileLines($path);

        if ($lines === null) {
            throw new RuntimeException(str_replace("\\", "/", self::relativePath($path)) . ": missing file");
        }

        $lines[0] = $version;
        file_put_contents($path, implode(PHP_EOL, $lines) . PHP_EOL);
    }

    private static function syncRuntimeReadme(string $path, string $version): void
    {
        if (!is_file($path)) {
            throw new RuntimeException(str_replace("\\", "/", self::relativePath($path)) . ": missing file");
        }

        $content = (string) file_get_contents($path);
        $content = str_replace(
            "This directory is the built-in FNLLA UI/runtime surface handoff for downstream projects.",
            "This directory is the integrated FNLLA UI surface handoff for downstream projects.",
            $content
        );

        if (!str_contains($content, "## Version")) {
            $versionBlock = "## Version" . PHP_EOL . PHP_EOL . $version . PHP_EOL . PHP_EOL;

            if (str_contains($content, "## Maintainer notes")) {
                $content = str_replace("## Maintainer notes", $versionBlock . "## Maintainer notes", $content);
            } else {
                $content = rtrim($content) . PHP_EOL . PHP_EOL . $versionBlock;
            }
        }

        $count = 0;
        $updated = preg_replace_callback(
            '/(## Version\s*\R\R)([^\r\n]+)/',
            static fn (array $matches): string => $matches[1] . $version,
            $content,
            1,
            $count
        );

        if (!is_string($updated) || $count !== 1) {
            throw new RuntimeException(str_replace("\\", "/", self::relativePath($path)) . ": unable to sync the embedded version section");
        }

        file_put_contents($path, $updated);
    }

    private static function buildIntegratedRuntimeManifest(string $version): array
    {
        return [
            "schema_version" => 1,
            "product" => [
                "name" => "FNLLA",
                "slug" => "fnlla",
                "version" => $version,
                "owner" => "TechAyo LTD (techayo.co.uk)",
                "origin" => "Finella Gardens in Dundee, UK",
                "repository" => "https://github.com/techayoDEV/fnlla.git",
                "source_of_truth" => "github",
            ],
            "distribution" => [
                "name" => "Integrated FNLLA UI surface",
                "slug" => "integrated-ui-surface",
                "distribution_root" => ".",
                "version_path" => "VERSION",
                "css_entrypoint" => "assets/css/fnlla-runtime.css",
                "js_entrypoint" => "assets/js/fnlla-runtime.js",
                "icons_directory" => "assets/icons",
                "release_files" => [
                    "MANIFEST.json",
                    "LICENSE.md",
                    "SUPPORT.md",
                    "TRADEMARKS.md",
                    "VERSION",
                ],
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

    private static function isClaimedProjectManifest(array $manifest): bool
    {
        return ($manifest["manifest_type"] ?? null) === "claimed_project"
            || (bool) ($manifest["product"]["claimed"] ?? false);
    }

    private static function identityFromClaimedManifest(array $manifest): array
    {
        return [
            "id" => (string) ($manifest["product"]["identifier"] ?? ""),
            "product" => (string) ($manifest["product"]["name"] ?? ""),
            "slug" => (string) ($manifest["product"]["slug"] ?? ""),
            "summary" => (string) ($manifest["product"]["summary"] ?? ""),
            "owner" => (string) ($manifest["product"]["owner"]["name"] ?? ""),
            "funder" => (string) ($manifest["product"]["funder"]["name"] ?? ($manifest["product"]["owner"]["name"] ?? "")),
            "client" => (string) ($manifest["product"]["client"]["name"] ?? ($manifest["product"]["owner"]["name"] ?? "")),
            "system_owner" => (string) ($manifest["product"]["owner"]["name"] ?? ""),
            "developer" => (string) ($manifest["product"]["developer"]["name"] ?? ""),
            "maintainer" => (string) ($manifest["product"]["maintenance_provider"]["name"] ?? ($manifest["product"]["developer"]["name"] ?? "")),
            "runtime" => (string) ($manifest["framework"]["name"] ?? "FNLLA"),
            "runtime_creator" => (string) ($manifest["framework"]["creator"] ?? "TechAyo LTD (techayo.co.uk)"),
        ];
    }

    private static function validateClaimedProjectManifest(array $manifest, string $frameworkVersion, string $uiVersion, array &$errors): void
    {
        foreach ([
            "product.identifier",
            "product.name",
            "product.slug",
            "product.summary",
            "product.owner.name",
            "product.funder.name",
            "product.client.name",
            "product.developer.name",
            "product.maintenance_provider.name",
            "framework.name",
            "framework.version",
            "framework.creator",
            "framework.lock_file",
            "ui_runtime.version",
            "ui_runtime.creator",
        ] as $path) {
            if (self::manifestValue($manifest, $path) === "") {
                $errors[] = "MANIFEST.json: claimed project manifest is missing " . $path;
            }
        }

        if (($manifest["product"]["claimed"] ?? null) !== true) {
            $errors[] = "MANIFEST.json: claimed project manifest must set product.claimed=true";
        }

        if ((string) ($manifest["product"]["version"] ?? "") !== $frameworkVersion) {
            $errors[] = "MANIFEST.json: product.version must match VERSION";
        }

        if ((string) ($manifest["framework"]["version"] ?? "") !== $frameworkVersion) {
            $errors[] = "MANIFEST.json: framework.version must match VERSION";
        }

        if ((string) ($manifest["ui_runtime"]["version"] ?? "") !== $uiVersion) {
            $errors[] = "MANIFEST.json: ui_runtime.version must match public/vendor/fnlla-runtime/VERSION";
        }

        if ((string) ($manifest["framework"]["lock_file"] ?? "") !== ".fnlla/framework-lock.json") {
            $errors[] = "MANIFEST.json: framework.lock_file must be .fnlla/framework-lock.json";
        }
    }

    private static function manifestValue(array $manifest, string $path): string
    {
        $value = $manifest;

        foreach (explode(".", $path) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return "";
            }

            $value = $value[$segment];
        }

        return trim((string) $value);
    }

    private static function syncRuntimeJavascriptVersion(string $path, string $version): void
    {
        if (!is_file($path)) {
            throw new RuntimeException(str_replace("\\", "/", self::relativePath($path)) . ": missing file");
        }

        $content = (string) file_get_contents($path);

        if (!str_contains($content, 'var fnllaRuntimeVersion = "')) {
            $content = 'var fnllaRuntimeVersion = "' . $version . '";' . PHP_EOL . $content;
        }

        $count = 0;
        $updated = preg_replace_callback(
            '/(var fnllaRuntimeVersion = ")([^"]+)(";\s*)/',
            static fn (array $matches): string => $matches[1] . $version . $matches[3],
            $content,
            1,
            $count
        );

        if (!is_string($updated) || $count !== 1) {
            throw new RuntimeException(str_replace("\\", "/", self::relativePath($path)) . ": unable to sync the runtime JavaScript version marker");
        }

        file_put_contents($path, $updated);
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
