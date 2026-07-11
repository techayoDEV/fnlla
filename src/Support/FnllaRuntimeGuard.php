<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA SUPPORT SOURCE
File: src\Support\FnllaRuntimeGuard.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Implements shared helpers, environment loading, metadata and framework support behavior.
*/

namespace Fnlla\Php\Support;

use RuntimeException;

final class FnllaRuntimeGuard
{
    /*
    The guard is intentionally centralized here so FNLLA can enforce the
    official FNLLA Runtime dependency boundary from one place during both HTTP and
    CLI bootstrap.
    */
    public static function enforce(): void
    {
        $config = self::config();

        if (!($config["enforce"] ?? false)) {
            return;
        }

        self::validateLocalContract($config);

        if (!($config["auto_sync"] ?? false)) {
            return;
        }

        self::syncIfDue($config, false);
    }

    public static function validateOnly(): void
    {
        self::validateLocalContract(self::config());
    }

    public static function syncNow(): void
    {
        self::syncNowWithOptions([]);
    }

    public static function syncNowWithOptions(array $options): void
    {
        $config = self::config();

        self::runSync($config, $options);
        self::validateLocalContract($config);
    }

    private static function config(): array
    {
        $config = config("fnlla_runtime", []);

        if (!is_array($config)) {
            throw new RuntimeException("FNLLA Runtime configuration is invalid.");
        }

        return $config;
    }

    private static function validateLocalContract(array $config): void
    {
        /* Validation checks focus on local repository shape, required runtime files and unsupported UI drift. */
        self::assertRuntimeFilesExist((array) ($config["required_runtime_files"] ?? []));
        self::assertLayoutContract(
            (string) ($config["layout_path"] ?? base_path("views/layouts/app.php")),
            (array) ($config["required_layout_markers"] ?? [])
        );
        self::assertPageContracts(
            (string) ($config["page_view_glob"] ?? base_path("views/pages/*.php")),
            (array) ($config["required_page_markers"] ?? [])
        );
        self::assertRequiredTextMarkers((array) ($config["required_text_markers"] ?? []));
        self::assertForbiddenTextMarkers((array) ($config["forbidden_text_markers"] ?? []));
        self::assertForbiddenMarkers(
            (array) ($config["forbidden_markers"] ?? []),
            (array) ($config["scan_paths"] ?? [])
        );
    }

    private static function syncIfDue(array $config, bool $force): void
    {
        /*
        Sync cadence is stateful on purpose:
        - the framework still checks often enough during development
        - bootstrap avoids recloning FNLLA Runtime on every single request
        */
        $statePath = (string) ($config["state_path"] ?? storage_path("framework/fnlla-runtime-guard.json"));
        $state = self::loadState($statePath);
        $interval = max(0, (int) ($config["check_interval_seconds"] ?? 900));
        $currentVersion = self::readLocalVersion((string) ($config["version_file"] ?? public_path("vendor/fnlla-runtime/VERSION")));
        $lastCheckedAt = (int) ($state["last_checked_at"] ?? 0);
        $lastKnownVersion = (string) ($state["local_version"] ?? "");
        $hasValidRecentState = !$force
            && $currentVersion !== ""
            && $currentVersion === $lastKnownVersion
            && ($lastCheckedAt > 0)
            && ((time() - $lastCheckedAt) < $interval);

        if ($hasValidRecentState) {
            return;
        }

        if (!$force && $currentVersion !== "") {
            self::saveState($statePath, [
                "last_checked_at" => time(),
                "local_version" => $currentVersion,
            ]);

            return;
        }

        self::runSync($config, []);
        $syncedVersion = self::readLocalVersion((string) ($config["version_file"] ?? public_path("vendor/fnlla-runtime/VERSION")));

        self::saveState($statePath, [
            "last_checked_at" => time(),
            "local_version" => $syncedVersion,
        ]);
    }

    private static function runSync(array $config, array $options): void
    {
        /* GitHub-driven sync is delegated to the maintained PowerShell script so clone safety stays in one place. */
        $scriptPath = base_path((string) ($config["sync_script"] ?? "scripts/sync-fnlla-runtime.ps1"));

        if (!is_file($scriptPath)) {
            throw new RuntimeException("FNLLA Runtime sync script is missing: " . $scriptPath);
        }

        $commandArguments = self::buildSyncScriptArguments($scriptPath, $options);
        $commands = [];

        if (DIRECTORY_SEPARATOR === "\\") {
            $commands[] = array_merge(["powershell", "-ExecutionPolicy", "Bypass"], $commandArguments);
        }

        $commands[] = array_merge(["pwsh", "-ExecutionPolicy", "Bypass"], $commandArguments);
        $errors = [];

        foreach ($commands as $command) {
            $output = [];
            $exitCode = 0;
            $commandString = implode(" ", array_map("escapeshellarg", $command)) . " 2>&1";

            exec($commandString, $output, $exitCode);

            if ($exitCode === 0) {
                return;
            }

            $errors[] = trim(implode(PHP_EOL, $output)) ?: ("Command failed with exit code " . $exitCode);
        }

        throw new RuntimeException("FNLLA Runtime sync failed. " . implode(" | ", $errors));
    }

    private static function buildSyncScriptArguments(string $scriptPath, array $options): array
    {
        $arguments = ["-File", $scriptPath];
        $optionMap = [
            "source" => "SourcePath",
            "repo_url" => "RepoUrl",
            "repository" => "Repository",
            "working_clone_path" => "WorkingClonePath",
            "ref" => "Ref",
        ];

        foreach ($optionMap as $optionKey => $parameterName) {
            $value = $options[$optionKey] ?? null;

            if (!is_string($value)) {
                continue;
            }

            $value = trim($value);

            if ($value === "") {
                continue;
            }

            $arguments[] = "-" . $parameterName;
            $arguments[] = $value;
        }

        return $arguments;
    }

    private static function assertRuntimeFilesExist(array $requiredFiles): void
    {
        foreach ($requiredFiles as $path) {
            if (!is_string($path) || $path === "") {
                continue;
            }

            if (!is_file($path) && !is_dir($path)) {
                throw new RuntimeException("FNLLA runtime is incomplete. Missing: " . $path);
            }
        }
    }

    private static function assertLayoutContract(string $layoutPath, array $markers): void
    {
        if (!is_file($layoutPath)) {
            throw new RuntimeException("FNLLA Runtime layout file is missing: " . $layoutPath);
        }

        $contents = (string) file_get_contents($layoutPath);

        foreach ($markers as $marker) {
            if (!is_string($marker) || $marker === "") {
                continue;
            }

            if (!str_contains($contents, $marker)) {
                throw new RuntimeException("FNLLA built-in runtime contract violation in layout. Missing marker: " . $marker);
            }
        }
    }

    private static function assertPageContracts(string $globPattern, array $markers): void
    {
        $files = glob($globPattern);

        if ($files === false) {
            return;
        }

        foreach ($files as $file) {
            if (!is_file($file)) {
                continue;
            }

            $contents = (string) file_get_contents($file);

            foreach ($markers as $marker) {
                if (!is_string($marker) || $marker === "") {
                    continue;
                }

                if (!str_contains($contents, $marker)) {
                    throw new RuntimeException("FNLLA built-in runtime contract violation in view {$file}. Missing marker: " . $marker);
                }
            }
        }
    }

    private static function assertForbiddenMarkers(array $patterns, array $scanPaths): void
    {
        /* These checks reject unsupported alternate framework imports instead of trying to auto-merge style systems. */
        foreach ($scanPaths as $scanPath) {
            if (!is_string($scanPath) || $scanPath === "") {
                continue;
            }

            $files = glob($scanPath);

            if ($files === false) {
                continue;
            }

            foreach ($files as $file) {
                if (!is_file($file)) {
                    continue;
                }

                $contents = (string) file_get_contents($file);

                foreach ($patterns as $pattern) {
                    if (!is_string($pattern) || $pattern === "") {
                        continue;
                    }

                    if (preg_match($pattern, $contents) === 1) {
                        throw new RuntimeException("Forbidden UI marker detected in {$file}: {$pattern}");
                    }
                }
            }
        }
    }

    private static function assertRequiredTextMarkers(array $requiredByPath): void
    {
        foreach ($requiredByPath as $path => $markers) {
            if (!is_string($path) || $path === "") {
                continue;
            }

            if (!is_file($path)) {
                throw new RuntimeException("FNLLA runtime metadata file is missing: " . $path);
            }

            $contents = (string) file_get_contents($path);

            foreach ((array) $markers as $marker) {
                if (!is_string($marker) || $marker === "") {
                    continue;
                }

                if (!str_contains($contents, $marker)) {
                    throw new RuntimeException("FNLLA runtime metadata drift detected in {$path}. Missing marker: {$marker}");
                }
            }
        }
    }

    private static function assertForbiddenTextMarkers(array $forbiddenByPath): void
    {
        foreach ($forbiddenByPath as $path => $patterns) {
            if (!is_string($path) || $path === "") {
                continue;
            }

            if (!is_file($path)) {
                throw new RuntimeException("FNLLA runtime metadata file is missing: " . $path);
            }

            $contents = (string) file_get_contents($path);

            foreach ((array) $patterns as $pattern) {
                if (!is_string($pattern) || $pattern === "") {
                    continue;
                }

                if (preg_match($pattern, $contents) === 1) {
                    throw new RuntimeException("FNLLA runtime metadata drift detected in {$path}. Forbidden marker: {$pattern}");
                }
            }
        }
    }

    private static function loadState(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }

        $contents = file_get_contents($path);

        if (!is_string($contents) || trim($contents) === "") {
            return [];
        }

        $decoded = json_decode($contents, true);

        return is_array($decoded) ? $decoded : [];
    }

    private static function saveState(string $path, array $state): void
    {
        $directory = dirname($path);

        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        file_put_contents($path, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
    }

    private static function readLocalVersion(string $path): string
    {
        if (!is_file($path)) {
            return "";
        }

        $contents = (string) file_get_contents($path);
        $firstLine = strtok($contents, "\r\n");

        return trim($firstLine === false ? $contents : $firstLine);
    }
}
