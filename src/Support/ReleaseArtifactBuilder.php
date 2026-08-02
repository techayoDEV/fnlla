<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA SUPPORT SOURCE
File: src\Support\ReleaseArtifactBuilder.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

Purpose:
- Builds SBOM and checksum artefacts for source release preparation.
*/

namespace Fnlla\Php\Support;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

final class ReleaseArtifactBuilder
{
    public function buildSbom(string $outputPath): array
    {
        $components = [];

        foreach ($this->releaseFiles() as $relativePath => $absolutePath) {
            $components[] = [
                "type" => "file",
                "name" => $relativePath,
                "hashes" => [
                    [
                        "alg" => "SHA-256",
                        "content" => hash_file("sha256", $absolutePath),
                    ],
                ],
            ];
        }

        $payload = [
            "bomFormat" => "CycloneDX",
            "specVersion" => "1.6",
            "serialNumber" => "urn:uuid:" . $this->uuidV4(),
            "version" => 1,
            "metadata" => [
                "timestamp" => gmdate(DATE_ATOM),
                "component" => [
                    "type" => "application",
                    "name" => (string) config("app.name", "FNLLA"),
                    "version" => $this->version(),
                    "licenses" => [
                        ["license" => ["id" => "MIT"]],
                    ],
                ],
            ],
            "components" => $components,
        ];

        $this->writeJson($outputPath, $payload);

        return [
            "path" => $outputPath,
            "components" => count($components),
            "version" => $this->version(),
        ];
    }

    public function buildChecksums(string $outputPath): array
    {
        $lines = [];
        $files = $this->releaseFiles();

        foreach ($files as $relativePath => $absolutePath) {
            $lines[] = hash_file("sha256", $absolutePath) . "  " . str_replace("\\", "/", $relativePath);
        }

        sort($lines);
        $this->writeText($outputPath, implode(PHP_EOL, $lines) . PHP_EOL);

        return [
            "path" => $outputPath,
            "files" => count($files),
            "version" => $this->version(),
        ];
    }

    public function defaultOutputPath(string $filename): string
    {
        return base_path("dist/release/" . $filename);
    }

    /**
     * @return array<string,string>
     */
    private function releaseFiles(): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(base_path(), FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $item) {
            if (!$item->isFile()) {
                continue;
            }

            $absolutePath = $item->getPathname();
            $relativePath = ltrim(str_replace(base_path(), "", $absolutePath), "\\/");
            $relativePath = str_replace("\\", "/", $relativePath);

            if ($this->shouldSkip($relativePath)) {
                continue;
            }

            $files[$relativePath] = $absolutePath;
        }

        ksort($files);

        return $files;
    }

    private function shouldSkip(string $relativePath): bool
    {
        foreach ([
            ".git/",
            "dist/",
            "vendor/",
            "storage/framework/cache/",
            "storage/framework/queue/",
            "storage/framework/sessions/",
            "storage/framework/updates/",
            "storage/logs/",
        ] as $prefix) {
            if (str_starts_with($relativePath, $prefix)) {
                return !str_ends_with($relativePath, ".gitignore");
            }
        }

        return in_array($relativePath, [
            ".env",
            "storage/framework/fnlla-runtime-guard.json",
        ], true);
    }

    private function writeJson(string $path, array $payload): void
    {
        $this->ensureDirectory(dirname($path));
        file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL, LOCK_EX);
    }

    private function writeText(string $path, string $payload): void
    {
        $this->ensureDirectory(dirname($path));
        file_put_contents($path, $payload, LOCK_EX);
    }

    private function ensureDirectory(string $directory): void
    {
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new RuntimeException("Unable to create release artefact directory: " . $directory);
        }
    }

    private function version(): string
    {
        $versionFile = base_path("VERSION");

        if (!is_file($versionFile)) {
            return "unknown";
        }

        $lines = file($versionFile, FILE_IGNORE_NEW_LINES);
        $version = is_array($lines) ? trim((string) ($lines[0] ?? "")) : "";

        return $version !== "" ? $version : "unknown";
    }

    private function uuidV4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);

        return sprintf(
            "%s-%s-%s-%s-%s",
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20)
        );
    }
}
