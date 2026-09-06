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
    private ?array $sourceExclusions = null;
    private array $excludedNames = ["auth.json", ".npmrc", ".pypirc", "id_rsa", "id_ed25519"];
    private array $excludedExtensions = ["key", "pem", "p12", "pfx", "sql", "sqlite", "sqlite3", "bak", "log", "tmp", "zip"];
    public function buildSbom(string $outputPath): array
    {
        $components = [];

        foreach ($this->releaseFiles() as $relativePath => $absolutePath) {
            $hash = $this->fileHash($absolutePath);

            if ($hash === null) {
                continue;
            }

            $components[] = [
                "type" => "file",
                "name" => $relativePath,
                "hashes" => [
                    [
                        "alg" => "SHA-256",
                        "content" => $hash,
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
            if (!is_file($absolutePath) || !is_readable($absolutePath)) {
                continue;
            }

            $hash = $this->fileHash($absolutePath);

            if ($hash === null) {
                continue;
            }

            $lines[] = $hash . "  " . str_replace("\\", "/", $relativePath);
        }

        sort($lines);
        $this->writeText($outputPath, implode(PHP_EOL, $lines) . PHP_EOL);

        return [
            "path" => $outputPath,
            "files" => count($files),
            "version" => $this->version(),
        ];
    }

    public function buildManifest(string $outputPath, array $artifactPaths): array
    {
        $artifacts = [];

        foreach ($artifactPaths as $name => $path) {
            if (!is_string($path) || !is_file($path) || !is_readable($path)) {
                continue;
            }

            $hash = $this->fileHash($path);

            if ($hash === null) {
                continue;
            }

            $artifacts[(string) $name] = [
                "path" => str_replace("\\", "/", ltrim(str_replace(base_path(), "", $path), "\\/")),
                "sha256" => $hash,
                "bytes" => filesize($path),
            ];
        }

        $payload = [
            "schema" => "fnlla.release_manifest.v1",
            "generated_at_utc" => gmdate(DATE_ATOM),
            "product" => (string) config("app.name", "FNLLA"),
            "version" => $this->version(),
            "artifacts" => $artifacts,
            "signature" => null,
        ];
        $signingKey = (string) env("RELEASE_SIGNING_KEY", "");

        if ($signingKey !== "") {
            $signaturePayload = [
                "schema" => $payload["schema"],
                "product" => $payload["product"],
                "version" => $payload["version"],
                "artifacts" => $artifacts,
            ];

            $payload["signature"] = [
                "algorithm" => "hmac-sha256",
                "key_id" => trim((string) env("RELEASE_SIGNING_KEY_ID", "")) !== ""
                    ? trim((string) env("RELEASE_SIGNING_KEY_ID", ""))
                    : "local",
                "signed_at_utc" => gmdate(DATE_ATOM),
                "payload_sha256" => hash("sha256", json_encode($signaturePayload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)),
                "value" => hash_hmac("sha256", json_encode($signaturePayload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES), $signingKey),
            ];
        }

        $this->writeJson($outputPath, $payload);

        return [
            "path" => $outputPath,
            "artifacts" => count($artifacts),
            "signed" => $payload["signature"] !== null,
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
            if (!$item->isFile() || $item->isLink()) {
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
        if ($this->sourceExclusions === null) {
            $policyPath = base_path("resources/source-distribution.json");
            $policy = is_file($policyPath)
                ? json_decode((string) file_get_contents($policyPath), true, 512, JSON_THROW_ON_ERROR) : [];
            $this->sourceExclusions = (array) ($policy["excluded_prefixes"] ?? []);
            $this->excludedNames = (array) ($policy["excluded_names"] ?? $this->excludedNames);
            $this->excludedExtensions = (array) ($policy["excluded_extensions"] ?? $this->excludedExtensions);
        }
        foreach ($this->sourceExclusions as $prefix) {
            if (is_string($prefix) && $prefix !== "" && str_starts_with($relativePath, $prefix)) {
                return true;
            }
        }
        foreach ([
            ".git/",
            ".fnlla/update-transaction/",
            "dist/",
            "vendor/",
            "storage/",
            "public/uploads/",
            "storage/framework/",
            "storage/framework/cache/",
            "storage/framework/developer/",
            "storage/framework/queue/",
            "storage/framework/sessions/",
            "storage/framework/updates/",
            "storage/logs/",
        ] as $prefix) {
            if (str_starts_with($relativePath, $prefix)) {
                return !str_ends_with($relativePath, ".gitignore");
            }
        }

        $name = strtolower(basename($relativePath));
        if (in_array($name, array_map("strtolower", $this->excludedNames), true)
            || in_array(strtolower(pathinfo($name, PATHINFO_EXTENSION)), $this->excludedExtensions, true)) {
            return true;
        }
        if (str_starts_with($name, ".env") && !in_array($name, [".env.example", ".env.full.example"], true)) {
            return true;
        }

        return in_array($relativePath, [
            ".env",
            "storage/framework/fnlla-runtime-guard.json",
        ], true);
    }

    private function fileHash(string $path): ?string
    {
        if (!is_file($path) || !is_readable($path)) {
            return null;
        }

        $hash = @hash_file("sha256", $path);

        return is_string($hash) ? $hash : null;
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
