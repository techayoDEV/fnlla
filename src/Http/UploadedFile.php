<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA PHP HTTP SOURCE
File: src\Http\UploadedFile.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA PHP is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA PHP framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Implements request, response and HTTP-facing runtime primitives.
*/

namespace Fnlla\Php\Http;

final class UploadedFile
{
    public function __construct(
        private string $tmpName,
        private string $originalName,
        private string $mimeType,
        private int $size,
        private int $error
    ) {
    }

    public static function fromArray(array $file): self
    {
        return new self(
            (string) ($file["tmp_name"] ?? ""),
            (string) ($file["name"] ?? ""),
            (string) ($file["type"] ?? "application/octet-stream"),
            (int) ($file["size"] ?? 0),
            (int) ($file["error"] ?? UPLOAD_ERR_NO_FILE)
        );
    }

    public function isValid(): bool
    {
        return $this->error === UPLOAD_ERR_OK && is_file($this->tmpName);
    }

    public function originalName(): string
    {
        return $this->originalName;
    }

    public function mimeType(): string
    {
        return $this->mimeType;
    }

    public function size(): int
    {
        return $this->size;
    }

    public function extension(): string
    {
        return strtolower(pathinfo($this->originalName, PATHINFO_EXTENSION));
    }

    public function hashName(): string
    {
        $extension = $this->extension();
        $suffix = $extension !== "" ? "." . $extension : "";

        return sha1($this->originalName . "|" . $this->tmpName . "|" . microtime(true)) . $suffix;
    }

    public function move(string $targetPath): bool
    {
        if (!$this->isValid()) {
            return false;
        }

        if (function_exists("move_uploaded_file") && @move_uploaded_file($this->tmpName, $targetPath)) {
            return true;
        }

        return rename($this->tmpName, $targetPath) || copy($this->tmpName, $targetPath);
    }

    public function store(string $directory, string $disk = "public"): string
    {
        return app(\Fnlla\Php\Filesystem\StorageManager::class)->disk($disk)->putFile($directory, $this);
    }
}
