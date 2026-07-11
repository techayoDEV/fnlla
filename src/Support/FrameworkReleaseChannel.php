<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA SUPPORT SOURCE
File: src\Support\FrameworkReleaseChannel.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Resolves the public GitHub release channel for downstream framework-update
  checks, download caching and release metadata reporting.
*/

namespace Fnlla\Php\Support;

use JsonException;
use RuntimeException;

final class FrameworkReleaseChannel
{
    public static function prepareReleaseSource(string $projectRoot, ?string $requestedTag = null): array
    {
        $projectRoot = rtrim($projectRoot, "\\/");
        $release = $requestedTag !== null && trim($requestedTag) !== ""
            ? self::fetchReleaseByTag($projectRoot, trim($requestedTag))
            : self::fetchLatestRelease($projectRoot);

        $cacheRoot = self::cacheRoot($projectRoot);
        $releaseDirectory = $cacheRoot . DIRECTORY_SEPARATOR . self::sanitizePathSegment((string) ($release["tag_name"] ?? "latest"));
        $sourceRoot = $releaseDirectory . DIRECTORY_SEPARATOR . "source";
        $downloadedNow = false;

        if (!self::isMaintainedSourceRoot($sourceRoot)) {
            self::removeDirectory($sourceRoot);

            if (!is_dir($releaseDirectory) && !mkdir($releaseDirectory, 0777, true) && !is_dir($releaseDirectory)) {
                throw new RuntimeException("Unable to create the framework update release cache: " . $releaseDirectory);
            }

            self::cloneTaggedRelease($projectRoot, $release, $sourceRoot);
            $downloadedNow = true;
        }

        $summary = self::releaseSummary($projectRoot, $release, $sourceRoot, $releaseDirectory, $downloadedNow);
        self::writeReleaseMetadata($cacheRoot, $releaseDirectory, $summary);

        return [
            "source_root" => $sourceRoot,
            "source_origin" => "downloaded GitHub release cache",
            "cache_root" => $cacheRoot,
            "cache_path" => $releaseDirectory,
            "downloaded_now" => $downloadedNow,
            "github_release" => $summary,
        ];
    }

    public static function readCachedReleaseSummary(string $projectRoot): ?array
    {
        $path = self::cacheRoot($projectRoot) . DIRECTORY_SEPARATOR . "latest-release.json";

        if (!is_file($path)) {
            return null;
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            return null;
        }

        try {
            $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    public static function compareVersions(string $left, string $right): int
    {
        return version_compare(self::normalizeVersion($left), self::normalizeVersion($right));
    }

    private static function fetchLatestRelease(string $projectRoot): array
    {
        $repository = self::repositoryConfig($projectRoot);

        try {
            return self::requestJson($repository["api_base_url"] . "/repos/" . $repository["slug"] . "/releases/latest");
        } catch (RuntimeException $exception) {
            $tag = self::latestTagFromGit($repository["clone_url"]);

            return [
                "tag_name" => $tag,
                "name" => "Latest published tag " . $tag,
                "html_url" => $repository["html_url"] . "/releases/tag/" . $tag,
                "published_at" => null,
                "body" => "GitHub release metadata could not be loaded from the API. FNLLA fell back to the latest published Git tag instead." . PHP_EOL . $exception->getMessage(),
            ];
        }
    }

    private static function fetchReleaseByTag(string $projectRoot, string $tag): array
    {
        $repository = self::repositoryConfig($projectRoot);
        $normalizedTag = self::normalizeReleaseTag($tag);

        try {
            return self::requestJson($repository["api_base_url"] . "/repos/" . $repository["slug"] . "/releases/tags/" . rawurlencode($normalizedTag));
        } catch (RuntimeException $exception) {
            return [
                "tag_name" => $normalizedTag,
                "name" => "Requested tag " . $normalizedTag,
                "html_url" => $repository["html_url"] . "/releases/tag/" . $normalizedTag,
                "published_at" => null,
                "body" => "GitHub release metadata for the requested tag could not be loaded from the API. FNLLA will still try to clone that tag directly." . PHP_EOL . $exception->getMessage(),
            ];
        }
    }

    private static function repositoryConfig(string $projectRoot): array
    {
        $configuredSlug = trim((string) config("framework_update.github_repository", ""));
        $configuredApiBase = rtrim((string) config("framework_update.github_api_base_url", "https://api.github.com"), "/");
        $configuredCloneUrl = trim((string) config("framework_update.github_clone_url", ""));

        if ($configuredSlug !== "") {
            return [
                "slug" => $configuredSlug,
                "clone_url" => $configuredCloneUrl !== "" ? $configuredCloneUrl : "https://github.com/" . $configuredSlug . ".git",
                "html_url" => "https://github.com/" . $configuredSlug,
                "api_base_url" => $configuredApiBase,
            ];
        }

        $lock = FrameworkLock::load($projectRoot);
        $repositoryUrl = trim((string) ($lock["framework_base"]["framework"]["repository"] ?? ""));
        $slug = self::parseRepositorySlug($repositoryUrl);

        return [
            "slug" => $slug,
            "clone_url" => $repositoryUrl !== "" ? $repositoryUrl : "https://github.com/" . $slug . ".git",
            "html_url" => "https://github.com/" . $slug,
            "api_base_url" => $configuredApiBase,
        ];
    }

    private static function releaseSummary(string $projectRoot, array $release, string $sourceRoot, string $cachePath, bool $downloadedNow): array
    {
        $currentVersion = self::currentFrameworkVersion($projectRoot);
        $tag = self::normalizeReleaseTag((string) ($release["tag_name"] ?? "latest"));
        $version = self::normalizeVersion($tag);
        $currentNormalizedVersion = $currentVersion !== null ? self::normalizeVersion($currentVersion) : null;
        $comparison = $currentNormalizedVersion !== null ? self::compareVersions($version, $currentNormalizedVersion) : null;

        return [
            "tag" => $tag,
            "version" => $version,
            "name" => trim((string) ($release["name"] ?? "")),
            "html_url" => trim((string) ($release["html_url"] ?? "")),
            "published_at_utc" => trim((string) ($release["published_at"] ?? "")),
            "current_version" => $currentVersion,
            "comparison" => $comparison === null ? "unknown" : ($comparison > 0 ? "newer" : ($comparison < 0 ? "older" : "same")),
            "has_newer_release" => $comparison !== null ? $comparison > 0 : null,
            "downloaded_now" => $downloadedNow,
            "cache_path" => $cachePath,
            "source_root" => $sourceRoot,
            "notes" => self::trimReleaseBody((string) ($release["body"] ?? "")),
            "checked_at_utc" => gmdate(DATE_ATOM),
        ];
    }

    private static function writeReleaseMetadata(string $cacheRoot, string $releaseDirectory, array $summary): void
    {
        if (!is_dir($cacheRoot) && !mkdir($cacheRoot, 0777, true) && !is_dir($cacheRoot)) {
            throw new RuntimeException("Unable to create the framework update cache root: " . $cacheRoot);
        }

        $encoded = self::encodeJson($summary, "framework update release summary");

        file_put_contents($releaseDirectory . DIRECTORY_SEPARATOR . "release.json", $encoded);
        file_put_contents($cacheRoot . DIRECTORY_SEPARATOR . "latest-release.json", $encoded);
    }

    private static function cloneTaggedRelease(string $projectRoot, array $release, string $sourceRoot): void
    {
        $repository = self::repositoryConfig($projectRoot);
        $tag = self::normalizeReleaseTag((string) ($release["tag_name"] ?? ""));
        $gitBinary = trim((string) env("GIT_BINARY", "git"));

        if ($tag === "") {
            throw new RuntimeException("GitHub did not provide a usable FNLLA release tag for framework updates.");
        }

        $directory = dirname($sourceRoot);

        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new RuntimeException("Unable to prepare the framework update download directory: " . $directory);
        }

        $command = self::escapeArgument($gitBinary)
            . " clone --depth 1 --branch "
            . self::escapeArgument($tag)
            . " "
            . self::escapeArgument($repository["clone_url"])
            . " "
            . self::escapeArgument($sourceRoot)
            . " 2>&1";

        $lines = [];
        $exitCode = 1;

        exec($command, $lines, $exitCode);

        if ($exitCode !== 0 || !self::isMaintainedSourceRoot($sourceRoot)) {
            self::removeDirectory($sourceRoot);

            throw new RuntimeException(
                "Unable to download the requested FNLLA release from GitHub into the local update cache."
                . PHP_EOL
                . implode(PHP_EOL, $lines)
            );
        }
    }

    private static function latestTagFromGit(string $repositoryUrl): string
    {
        $gitBinary = trim((string) env("GIT_BINARY", "git"));
        $command = self::escapeArgument($gitBinary)
            . " ls-remote --tags --refs "
            . self::escapeArgument($repositoryUrl)
            . " 2>&1";
        $lines = [];
        $exitCode = 1;

        exec($command, $lines, $exitCode);

        if ($exitCode !== 0) {
            throw new RuntimeException(
                "Unable to resolve the latest FNLLA release tag from GitHub." . PHP_EOL . implode(PHP_EOL, $lines)
            );
        }

        $tags = [];

        foreach ($lines as $line) {
            if (!is_string($line) || !preg_match('/refs\/tags\/(v?\d+\.\d+\.\d+)$/', $line, $matches)) {
                continue;
            }

            $tags[] = $matches[1];
        }

        if ($tags === []) {
            throw new RuntimeException("GitHub did not return any usable FNLLA release tags.");
        }

        usort($tags, static fn (string $left, string $right): int => self::compareVersions($right, $left));

        return $tags[0];
    }

    private static function requestJson(string $url): array
    {
        $decoded = function (string $payload, string $context): array {
            try {
                $data = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                throw new RuntimeException("Unable to decode GitHub release metadata for {$context}: " . $exception->getMessage(), 0, $exception);
            }

            if (!is_array($data)) {
                throw new RuntimeException("GitHub release metadata for {$context} must decode to a JSON object.");
            }

            return $data;
        };

        if (function_exists("curl_init")) {
            $curl = curl_init($url);

            if ($curl === false) {
                throw new RuntimeException("Unable to initialize the cURL client for GitHub release metadata.");
            }

            curl_setopt_array($curl, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => max(5, (int) config("framework_update.github_timeout_seconds", 20)),
                CURLOPT_HTTPHEADER => [
                    "Accept: application/vnd.github+json",
                    "User-Agent: fnlla-FrameworkUpdate",
                    "X-GitHub-Api-Version: 2022-11-28",
                ],
            ]);

            $response = curl_exec($curl);
            $statusCode = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            $error = curl_error($curl);
            curl_close($curl);

            if (!is_string($response) || $response === "") {
                throw new RuntimeException(
                    "GitHub release metadata request returned an empty response for {$url}."
                    . ($error !== "" ? " cURL error: {$error}" : "")
                );
            }

            if ($statusCode < 200 || $statusCode >= 300) {
                throw new RuntimeException("GitHub release metadata request failed with HTTP {$statusCode} for {$url}.");
            }

            return $decoded($response, $url);
        }

        if (!ini_get("allow_url_fopen")) {
            throw new RuntimeException("GitHub release metadata requires either cURL or allow_url_fopen to be enabled.");
        }

        $context = stream_context_create([
            "http" => [
                "method" => "GET",
                "timeout" => max(5, (int) config("framework_update.github_timeout_seconds", 20)),
                "ignore_errors" => true,
                "header" => implode("\r\n", [
                    "Accept: application/vnd.github+json",
                    "User-Agent: fnlla-FrameworkUpdate",
                    "X-GitHub-Api-Version: 2022-11-28",
                ]),
            ],
        ]);
        $response = @file_get_contents($url, false, $context);
        $statusLine = $http_response_header[0] ?? "";

        if (!is_string($response) || $response === "") {
            throw new RuntimeException("GitHub release metadata request returned an empty response for {$url}.");
        }

        if (!preg_match('/\s(\d{3})\s/', (string) $statusLine, $matches) || (int) $matches[1] < 200 || (int) $matches[1] >= 300) {
            throw new RuntimeException("GitHub release metadata request failed for {$url} with response: {$statusLine}");
        }

        return $decoded($response, $url);
    }

    private static function currentFrameworkVersion(string $projectRoot): ?string
    {
        try {
            $lock = FrameworkLock::load($projectRoot);
            $version = trim((string) ($lock["framework_base"]["framework"]["version"] ?? ""));

            if ($version !== "") {
                return $version;
            }
        } catch (RuntimeException) {
        }

        return self::readVersionLine($projectRoot . DIRECTORY_SEPARATOR . "VERSION");
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

    private static function parseRepositorySlug(string $repositoryUrl): string
    {
        $normalized = trim($repositoryUrl);

        if ($normalized === "") {
            throw new RuntimeException("FNLLA framework repository URL is missing from the framework lock.");
        }

        if (preg_match('#github\.com[:/]+([^/]+)/([^/.]+)(?:\.git)?$#i', $normalized, $matches) !== 1) {
            throw new RuntimeException("Unable to derive the GitHub repository slug from: " . $repositoryUrl);
        }

        return $matches[1] . "/" . $matches[2];
    }

    private static function trimReleaseBody(string $body): string
    {
        $body = trim($body);

        if ($body === "") {
            return "";
        }

        $body = preg_replace("/\r\n?/", "\n", $body);
        $body = is_string($body) ? trim($body) : "";

        if (strlen($body) <= 4000) {
            return $body;
        }

        return rtrim(substr($body, 0, 4000)) . PHP_EOL . PHP_EOL . "[release notes truncated]";
    }

    private static function normalizeReleaseTag(string $tag): string
    {
        $trimmed = trim($tag);

        if ($trimmed === "") {
            return "";
        }

        return str_starts_with(strtolower($trimmed), "v") ? $trimmed : "v" . $trimmed;
    }

    private static function normalizeVersion(string $value): string
    {
        $normalized = trim($value);
        $normalized = preg_replace('/^v/i', '', $normalized);

        return is_string($normalized) ? $normalized : $value;
    }

    private static function cacheRoot(string $projectRoot): string
    {
        $configured = trim((string) config("framework_update.download_cache_path", "framework/updates/fnlla"));

        if ($configured === "") {
            $configured = "framework/updates/fnlla";
        }

        if (preg_match('/^[A-Za-z]:[\\\\\\/]/', $configured) === 1 || str_starts_with($configured, "\\\\") || str_starts_with($configured, "/")) {
            return rtrim(str_replace(["/", "\\"], DIRECTORY_SEPARATOR, $configured), "\\/");
        }

        return storage_path($configured);
    }

    private static function sanitizePathSegment(string $value): string
    {
        $sanitized = preg_replace('/[^A-Za-z0-9._-]+/', '-', trim($value));
        $sanitized = is_string($sanitized) ? trim($sanitized, '-') : '';

        return $sanitized !== '' ? $sanitized : 'latest';
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

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
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

    private static function encodeJson(array $payload, string $label): string
    {
        try {
            return json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
        } catch (JsonException $exception) {
            throw new RuntimeException("Unable to encode {$label}: " . $exception->getMessage(), 0, $exception);
        }
    }

    private static function escapeArgument(string $value): string
    {
        return '"' . str_replace('"', '\"', $value) . '"';
    }
}
