<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA FILESYSTEM SOURCE
File: src\Filesystem\FilesystemAdapter.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Implements maintained file storage behavior for uploads and local runtime assets.
*/

namespace Fnlla\Php\Filesystem;

use Fnlla\Php\Http\UploadedFile;
use RuntimeException;

final class FilesystemAdapter
{
    public function __construct(
        private string $root,
        private string $baseUrl = ""
    ) {
        if (!is_dir($this->root)) {
            mkdir($this->root, 0777, true);
        }
    }

    public function put(string $path, string $contents): bool
    {
        $resolved = $this->path($path);
        $directory = dirname($resolved);

        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        return file_put_contents($resolved, $contents, LOCK_EX) !== false;
    }

    public function putFile(string $directory, UploadedFile $file, ?string $name = null): string
    {
        $filename = $name ?? $file->hashName();
        $relativePath = trim($directory, "\\/") . "/" . $filename;
        $resolved = $this->path($relativePath);
        $targetDirectory = dirname($resolved);

        if (!is_dir($targetDirectory)) {
            mkdir($targetDirectory, 0777, true);
        }

        if (!$file->move($resolved)) {
            throw new RuntimeException("Unable to move uploaded file.");
        }

        return str_replace("\\", "/", trim($relativePath, "\\/"));
    }

    public function delete(string $path): bool
    {
        $resolved = $this->path($path);

        return !is_file($resolved) || unlink($resolved);
    }

    public function exists(string $path): bool
    {
        return is_file($this->path($path));
    }

    public function url(string $path): string
    {
        $normalized = str_replace("\\", "/", ltrim($path, "\\/"));

        if ($this->baseUrl !== "") {
            return rtrim($this->baseUrl, "/") . "/" . $normalized;
        }

        return url($normalized);
    }

    public function path(string $path): string
    {
        return rtrim($this->root, "\\/") . DIRECTORY_SEPARATOR . ltrim(str_replace("/", DIRECTORY_SEPARATOR, $path), "\\/");
    }
}
