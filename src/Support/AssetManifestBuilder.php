<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA SUPPORT SOURCE
File: src\Support\AssetManifestBuilder.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

Purpose:
- Builds a compact asset manifest so production requests avoid repeated filemtime
  calls in the `asset()` helper.
*/

namespace Fnlla\Php\Support;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class AssetManifestBuilder
{
    public function build(string $publicRoot, string $outputPath): array
    {
        $assets = [];

        if (!is_dir($publicRoot)) {
            return [];
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($publicRoot, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $item) {
            if (!$item->isFile()) {
                continue;
            }

            $relative = ltrim(str_replace($publicRoot, "", $item->getPathname()), "\\/");
            $relative = str_replace("\\", "/", $relative);

            if ($this->shouldSkip($relative)) {
                continue;
            }

            $assets[$relative] = [
                "version" => (string) $item->getMTime(),
                "bytes" => $item->getSize(),
                "sha256" => hash_file("sha256", $item->getPathname()),
            ];
        }

        ksort($assets);
        $this->writePhpArray($outputPath, $assets);

        return [
            "path" => $outputPath,
            "assets" => count($assets),
            "bytes" => array_sum(array_map(static fn (array $asset): int => (int) ($asset["bytes"] ?? 0), $assets)),
        ];
    }

    private function shouldSkip(string $relativePath): bool
    {
        return str_starts_with($relativePath, ".")
            || str_contains($relativePath, "/.")
            || str_ends_with($relativePath, ".php");
    }

    private function writePhpArray(string $path, array $payload): void
    {
        $directory = dirname($path);

        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        file_put_contents($path, "<?php\n\nreturn " . var_export($payload, true) . ";\n", LOCK_EX);
    }
}
