<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA PHP SUPPORT SOURCE
File: src\Support\FrameworkUpdater.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA PHP is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA PHP framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Provides the shared framework-update engine used by both CLI and the optional
  maintenance page.
*/

namespace Fnlla\Php\Support;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

final class FrameworkUpdater
{
    public static function checkLatestRelease(string $projectRoot, ?string $appName = null, ?string $releaseTag = null): array
    {
        $releaseSource = FrameworkReleaseChannel::prepareReleaseSource($projectRoot, $releaseTag);

        if (self::githubReleaseIsNotNewer($releaseSource)) {
            return self::enrichReportFromReleaseSource(
                self::buildGitHubReleaseNoOpReport($projectRoot, $releaseSource),
                $releaseSource
            );
        }

        $report = self::check($projectRoot, (string) $releaseSource["source_root"], $appName);

        return self::enrichReportFromReleaseSource($report, $releaseSource);
    }

    public static function applyLatestRelease(string $projectRoot, ?string $appName = null, ?string $releaseTag = null): array
    {
        $releaseSource = FrameworkReleaseChannel::prepareReleaseSource($projectRoot, $releaseTag);

        if (self::githubReleaseIsNotNewer($releaseSource)) {
            throw new RuntimeException(self::githubReleaseNoOpReason($releaseSource));
        }

        $report = self::apply($projectRoot, (string) $releaseSource["source_root"], $appName);

        return self::enrichReportFromReleaseSource($report, $releaseSource);
    }

    public static function detectSourceRoot(string $projectRoot, string $preferredSource = ""): array
    {
        $projectRoot = rtrim($projectRoot, "\\/");
        $workspaceRoot = dirname($projectRoot);
        $candidates = [];

        if (trim($preferredSource) !== "") {
            $candidates[] = self::buildSourceCandidate($preferredSource, $projectRoot, "configured source path");
        }

        foreach ([
            $workspaceRoot . DIRECTORY_SEPARATOR . "fnlla" => "auto-detected sibling repository",
            $workspaceRoot . DIRECTORY_SEPARATOR . "fnlla-php" => "auto-detected legacy sibling repository",
            $workspaceRoot . DIRECTORY_SEPARATOR . "fnlla" . DIRECTORY_SEPARATOR . "php" => "auto-detected nested sibling repository",
        ] as $candidatePath => $origin) {
            $candidate = self::buildSourceCandidate($candidatePath, $projectRoot, $origin);

            $alreadyTracked = array_filter(
                $candidates,
                static fn (array $tracked): bool => ($tracked["resolved_path"] ?? null) === $candidate["resolved_path"]
            ) !== [];

            if ($alreadyTracked) {
                continue;
            }

            $candidates[] = $candidate;
        }

        foreach ($candidates as $candidate) {
            if (($candidate["valid"] ?? false) === true) {
                return [
                    "resolved_path" => (string) $candidate["resolved_path"],
                    "origin" => (string) $candidate["origin"],
                    "candidates" => $candidates,
                ];
            }
        }

        return [
            "resolved_path" => null,
            "origin" => "manual input required",
            "candidates" => $candidates,
        ];
    }

    public static function check(string $projectRoot, string $source, ?string $appName = null): array
    {
        [$report, $workspace] = self::prepare($projectRoot, $source, $appName);

        try {
            return $report;
        } finally {
            self::removeDirectory($workspace);
        }
    }

    public static function apply(string $projectRoot, string $source, ?string $appName = null): array
    {
        [$report, $workspace, $exportRoot] = self::prepare($projectRoot, $source, $appName);

        try {
            if ($report["conflicts"] !== []) {
                throw new RuntimeException("Framework updates were not applied because conflicts need manual review first.");
            }

            $appliedChanges = self::applyReport($report, $projectRoot, $exportRoot);
            FrameworkLock::syncFromExport($exportRoot, $projectRoot);
            $report["applied_changes"] = $appliedChanges;
            $report["post_install_checks"] = self::runPostInstallChecks($projectRoot);
            $report["post_install_ok"] = self::postInstallChecksPassed((array) $report["post_install_checks"]);

            return $report;
        } finally {
            self::removeDirectory($workspace);
        }
    }

    private static function prepare(string $projectRoot, string $source, ?string $appName): array
    {
        $projectRoot = rtrim($projectRoot, "\\/");
        $currentLock = FrameworkLock::load($projectRoot);
        $resolvedAppName = $appName !== null && trim($appName) !== ""
            ? trim($appName)
            : (string) ($currentLock["framework_base"]["application"]["name"] ?? "FNLLA PHP Project");
        [$sourceRoot, $sourceOrigin] = self::resolveSourceRoot($source, $projectRoot);
        $workspace = self::createTempWorkspace();

        try {
            $exportRoot = $workspace . DIRECTORY_SEPARATOR . "source-export";
            self::exportSourceProject($sourceRoot, $exportRoot, $resolvedAppName);
            $sourceLock = FrameworkLock::load($exportRoot);
            $report = self::buildReport($currentLock, $sourceLock, $projectRoot);
            $report["source_root"] = $sourceRoot;
            $report["source_origin"] = $sourceOrigin;

            return [$report, $workspace, $exportRoot];
        } catch (RuntimeException $exception) {
            self::removeDirectory($workspace);

            throw $exception;
        }
    }

    private static function resolveSourceRoot(string $source, string $projectRoot): array
    {
        $source = trim($source);

        if ($source === "") {
            $detected = self::detectSourceRoot($projectRoot);

            if (is_string($detected["resolved_path"] ?? null) && $detected["resolved_path"] !== "") {
                return [(string) $detected["resolved_path"], (string) ($detected["origin"] ?? "auto-detected source path")];
            }

            throw new RuntimeException(
                "framework:update could not auto-detect a maintained techayoDEV/fnlla repository. "
                . "Set FRAMEWORK_UPDATE_SOURCE_PATH, use the browser maintenance page, or pass --source <path-to-fnlla>."
            );
        }

        $resolved = self::isAbsolutePath($source)
            ? self::normalizePath($source)
            : self::normalizePath($projectRoot . DIRECTORY_SEPARATOR . $source);

        if (!is_dir($resolved)) {
            throw new RuntimeException("framework:update source directory does not exist: " . $resolved);
        }

        if (!self::isMaintainedSourceRoot($resolved)) {
            throw new RuntimeException("framework:update source must be a maintained techayoDEV/fnlla repository: " . $resolved);
        }

        return [$resolved, "manual source path"];
    }

    private static function createTempWorkspace(): string
    {
        $workspace = rtrim(sys_get_temp_dir(), "\\/") . DIRECTORY_SEPARATOR . "fnlla-framework-update-" . bin2hex(random_bytes(8));

        if (!mkdir($workspace, 0777, true) && !is_dir($workspace)) {
            throw new RuntimeException("Unable to create temporary framework update workspace.");
        }

        return $workspace;
    }

    private static function exportSourceProject(string $sourceRoot, string $targetRoot, string $appName): void
    {
        if (!function_exists("exec")) {
            throw new RuntimeException("framework:update requires the PHP exec() function to export a fresh project baseline.");
        }

        $command = self::escapeArgument(PHP_BINARY)
            . " "
            . self::escapeArgument($sourceRoot . DIRECTORY_SEPARATOR . "fnlla")
            . " make:project "
            . self::escapeArgument($targetRoot)
            . " "
            . self::escapeArgument($appName)
            . " 2>&1";

        $lines = [];
        $exitCode = 1;

        exec($command, $lines, $exitCode);

        if ($exitCode !== 0) {
            throw new RuntimeException(
                "Unable to export a fresh project baseline from the provided source repository." . PHP_EOL . implode(PHP_EOL, $lines)
            );
        }
    }

    private static function buildReport(array $currentLock, array $sourceLock, string $projectRoot): array
    {
        $baseManagedFiles = (array) ($currentLock["framework_base"]["managed_files"] ?? []);
        $sourceManagedFiles = (array) ($sourceLock["framework_base"]["managed_files"] ?? []);
        $paths = array_values(array_unique(array_merge(array_keys($baseManagedFiles), array_keys($sourceManagedFiles))));
        sort($paths);

        $updates = [];
        $conflicts = [];
        $localOnlyChanges = [];

        foreach ($paths as $path) {
            $baseHash = $baseManagedFiles[$path] ?? null;
            $sourceHash = $sourceManagedFiles[$path] ?? null;
            $currentHash = self::hashIfFileExists($projectRoot . DIRECTORY_SEPARATOR . str_replace("/", DIRECTORY_SEPARATOR, $path));

            if ($sourceHash === $baseHash) {
                if ($currentHash !== $baseHash) {
                    $localOnlyChanges[$path] = [
                        "base_hash" => $baseHash,
                        "current_hash" => $currentHash,
                        "reason" => $currentHash === null
                            ? "removed locally while upstream stayed the same"
                            : "modified locally while upstream stayed the same",
                    ];
                }

                continue;
            }

            if ($currentHash === $baseHash) {
                $updates[$path] = [
                    "action" => $baseHash === null ? "add" : ($sourceHash === null ? "remove" : "update"),
                    "base_hash" => $baseHash,
                    "source_hash" => $sourceHash,
                    "current_hash" => $currentHash,
                ];
                continue;
            }

            if ($currentHash === $sourceHash) {
                continue;
            }

            $conflicts[$path] = [
                "base_hash" => $baseHash,
                "source_hash" => $sourceHash,
                "current_hash" => $currentHash,
                "reason" => "framework-managed file changed both locally and upstream",
            ];
        }

        return [
            "current_framework_version" => (string) ($currentLock["framework_base"]["framework"]["version"] ?? "unknown"),
            "source_framework_version" => (string) ($sourceLock["framework_base"]["framework"]["version"] ?? "unknown"),
            "current_ui_version" => (string) ($currentLock["framework_base"]["ui_runtime"]["version"] ?? "unknown"),
            "source_ui_version" => (string) ($sourceLock["framework_base"]["ui_runtime"]["version"] ?? "unknown"),
            "tracked_managed_files" => count($baseManagedFiles),
            "source_managed_files" => count($sourceManagedFiles),
            "updates" => $updates,
            "conflicts" => $conflicts,
            "local_only_changes" => $localOnlyChanges,
        ];
    }

    private static function applyReport(array $report, string $projectRoot, string $sourceExportRoot): int
    {
        $changes = 0;

        foreach ($report["updates"] as $path => $update) {
            $targetPath = $projectRoot . DIRECTORY_SEPARATOR . str_replace("/", DIRECTORY_SEPARATOR, $path);
            $sourcePath = $sourceExportRoot . DIRECTORY_SEPARATOR . str_replace("/", DIRECTORY_SEPARATOR, $path);

            if ($update["action"] === "remove") {
                if (is_file($targetPath) && !unlink($targetPath)) {
                    throw new RuntimeException("Unable to remove framework-managed file: " . $targetPath);
                }

                $changes++;
                continue;
            }

            $directory = dirname($targetPath);

            if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
                throw new RuntimeException("Unable to create directory for framework update: " . $directory);
            }

            if (!copy($sourcePath, $targetPath)) {
                throw new RuntimeException("Unable to copy framework-managed file during update: " . $path);
            }

            $changes++;
        }

        return $changes;
    }

    private static function runPostInstallChecks(string $projectRoot): array
    {
        $checks = [];

        foreach ([
            "fnlla_web_contract" => [
                "label" => "FNLLA Web contract",
                "type" => "script",
                "path" => "scripts/validate-fnlla-web.php",
            ],
            "tests" => [
                "label" => "Project tests",
                "type" => "script",
                "path" => "scripts/test.php",
            ],
            "lint" => [
                "label" => "PHP lint",
                "type" => "script",
                "path" => "scripts/lint.php",
            ],
            "version_manifest" => [
                "label" => "Version manifest",
                "type" => "script",
                "path" => "scripts/validate-version-manifest.php",
            ],
        ] as $key => $check) {
            $checks[$key] = self::runProjectCheck($projectRoot, (string) $check["path"], (string) $check["label"]);
        }

        return $checks;
    }

    private static function runProjectCheck(string $projectRoot, string $relativePath, string $label): array
    {
        $scriptPath = $projectRoot . DIRECTORY_SEPARATOR . str_replace("/", DIRECTORY_SEPARATOR, $relativePath);

        if (!is_file($scriptPath)) {
            return [
                "label" => $label,
                "path" => $relativePath,
                "status" => "missing",
                "exit_code" => null,
                "ok" => false,
                "output" => "Missing check script: " . $relativePath,
            ];
        }

        if (!function_exists("exec")) {
            return [
                "label" => $label,
                "path" => $relativePath,
                "status" => "skipped",
                "exit_code" => null,
                "ok" => false,
                "output" => "Post-install checks require the PHP exec() function to be enabled.",
            ];
        }

        $command = self::escapeArgument(PHP_BINARY) . " " . self::escapeArgument($scriptPath) . " 2>&1";
        $lines = [];
        $exitCode = 1;

        exec($command, $lines, $exitCode);

        return [
            "label" => $label,
            "path" => $relativePath,
            "status" => $exitCode === 0 ? "passed" : "failed",
            "exit_code" => $exitCode,
            "ok" => $exitCode === 0,
            "output" => implode(PHP_EOL, $lines),
        ];
    }

    private static function postInstallChecksPassed(array $checks): bool
    {
        foreach ($checks as $check) {
            if (!is_array($check) || ($check["ok"] ?? false) !== true) {
                return false;
            }
        }

        return true;
    }

    private static function hashIfFileExists(string $path): ?string
    {
        if (!is_file($path)) {
            return null;
        }

        $hash = hash_file("sha256", $path);

        if (!is_string($hash) || $hash === "") {
            throw new RuntimeException("Unable to hash project file while checking framework drift: " . $path);
        }

        return $hash;
    }

    private static function buildSourceCandidate(string $source, string $projectRoot, string $origin): array
    {
        $source = trim($source);
        $resolved = $source === ""
            ? null
            : (
                self::isAbsolutePath($source)
                    ? self::normalizePath($source)
                    : self::normalizePath($projectRoot . DIRECTORY_SEPARATOR . $source)
            );

        return [
            "input" => $source,
            "resolved_path" => $resolved,
            "origin" => $origin,
            "valid" => is_string($resolved) && is_dir($resolved) && self::isMaintainedSourceRoot($resolved),
        ];
    }

    private static function enrichReportFromReleaseSource(array $report, array $releaseSource): array
    {
        $report["source_root"] = (string) ($releaseSource["source_root"] ?? ($report["source_root"] ?? ""));
        $report["source_origin"] = (string) ($releaseSource["source_origin"] ?? ($report["source_origin"] ?? "manual source path"));
        $report["source_strategy"] = "github_release";
        $report["download_cache_root"] = (string) ($releaseSource["cache_root"] ?? "");
        $report["download_cache_path"] = (string) ($releaseSource["cache_path"] ?? "");
        $report["downloaded_now"] = (bool) ($releaseSource["downloaded_now"] ?? false);
        $report["github_release"] = is_array($releaseSource["github_release"] ?? null) ? $releaseSource["github_release"] : [];

        return $report;
    }

    private static function buildGitHubReleaseNoOpReport(string $projectRoot, array $releaseSource): array
    {
        $currentLock = FrameworkLock::load($projectRoot);
        $frameworkMeta = (array) ($currentLock["framework_base"]["framework"] ?? []);
        $uiMeta = (array) ($currentLock["framework_base"]["ui_runtime"] ?? []);
        $managedFiles = (array) ($currentLock["framework_base"]["managed_files"] ?? []);
        $githubRelease = is_array($releaseSource["github_release"] ?? null) ? (array) $releaseSource["github_release"] : [];
        $sourceRoot = (string) ($releaseSource["source_root"] ?? "");

        return [
            "current_framework_version" => (string) ($frameworkMeta["version"] ?? "unknown"),
            "source_framework_version" => self::readVersionLine($sourceRoot . DIRECTORY_SEPARATOR . "VERSION")
                ?? (string) ($githubRelease["version"] ?? (string) ($frameworkMeta["version"] ?? "unknown")),
            "current_ui_version" => (string) ($uiMeta["version"] ?? "unknown"),
            "source_ui_version" => self::readVersionLine($sourceRoot . DIRECTORY_SEPARATOR . "public" . DIRECTORY_SEPARATOR . "vendor" . DIRECTORY_SEPARATOR . "fnlla-web" . DIRECTORY_SEPARATOR . "VERSION")
                ?? (string) ($uiMeta["version"] ?? "unknown"),
            "tracked_managed_files" => count($managedFiles),
            "source_managed_files" => count($managedFiles),
            "updates" => [],
            "conflicts" => [],
            "local_only_changes" => [],
            "release_skip_reason" => self::githubReleaseNoOpReason($releaseSource),
        ];
    }

    private static function githubReleaseIsNotNewer(array $releaseSource): bool
    {
        $githubRelease = is_array($releaseSource["github_release"] ?? null) ? (array) $releaseSource["github_release"] : [];

        return ($githubRelease["comparison"] ?? "unknown") !== "newer";
    }

    private static function githubReleaseNoOpReason(array $releaseSource): string
    {
        $githubRelease = is_array($releaseSource["github_release"] ?? null) ? (array) $releaseSource["github_release"] : [];
        $tag = (string) ($githubRelease["tag"] ?? "the selected release");
        $currentVersion = (string) ($githubRelease["current_version"] ?? "the current framework base");
        $comparison = (string) ($githubRelease["comparison"] ?? "unknown");

        if ($comparison === "same") {
            return "GitHub release {$tag} matches the current framework version ({$currentVersion}). FNLLA PHP skipped the diff so the GitHub update flow does not suggest a no-op or downgrade over an already current base.";
        }

        if ($comparison === "older") {
            return "GitHub release {$tag} is older than the current framework base ({$currentVersion}). FNLLA PHP skipped the diff so the GitHub update flow does not suggest a downgrade.";
        }

        return "FNLLA PHP could not prove that the selected GitHub release is newer than the current framework base, so the GitHub update flow stopped before diff generation.";
    }

    private static function isMaintainedSourceRoot(string $path): bool
    {
        $launcher = $path . DIRECTORY_SEPARATOR . "fnlla";
        $makeProjectCommand = $path . DIRECTORY_SEPARATOR . "src" . DIRECTORY_SEPARATOR . "Console" . DIRECTORY_SEPARATOR . "Commands" . DIRECTORY_SEPARATOR . "MakeProjectCommand.php";

        return is_file($launcher) && is_file($makeProjectCommand);
    }

    private static function removeDirectory(string $path): void
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

    private static function isAbsolutePath(string $path): bool
    {
        return preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1
            || str_starts_with($path, "\\\\")
            || str_starts_with($path, "/");
    }

    private static function normalizePath(string $path): string
    {
        $path = str_replace(["/", "\\"], DIRECTORY_SEPARATOR, $path);
        $segments = [];
        $prefix = "";

        if (preg_match('/^[A-Za-z]:/', $path) === 1) {
            $prefix = strtoupper(substr($path, 0, 2));
            $path = substr($path, 2);
        } elseif (str_starts_with($path, DIRECTORY_SEPARATOR . DIRECTORY_SEPARATOR)) {
            $prefix = DIRECTORY_SEPARATOR . DIRECTORY_SEPARATOR;
            $path = substr($path, 2);
        }

        $isAbsolute = str_starts_with($path, DIRECTORY_SEPARATOR);
        $parts = preg_split('/[\\\\\\/]+/', $path) ?: [];

        foreach ($parts as $part) {
            if ($part === "" || $part === ".") {
                continue;
            }

            if ($part === "..") {
                if ($segments !== [] && end($segments) !== "..") {
                    array_pop($segments);
                } elseif (!$isAbsolute) {
                    $segments[] = $part;
                }

                continue;
            }

            $segments[] = $part;
        }

        $normalized = implode(DIRECTORY_SEPARATOR, $segments);

        if ($prefix !== "") {
            return $prefix . DIRECTORY_SEPARATOR . $normalized;
        }

        return ($isAbsolute ? DIRECTORY_SEPARATOR : "") . $normalized;
    }

    private static function escapeArgument(string $value): string
    {
        return '"' . str_replace('"', '\"', $value) . '"';
    }

    private static function readVersionLine(string $path): ?string
    {
        if (!is_file($path)) {
            return null;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES);

        if (!is_array($lines)) {
            return null;
        }

        $version = trim((string) ($lines[0] ?? ""));

        return $version !== "" ? $version : null;
    }
}
