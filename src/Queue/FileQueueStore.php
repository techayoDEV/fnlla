<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA QUEUE SOURCE
File: src\Queue\FileQueueStore.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Implements the default file-backed queue storage adapter.
*/

namespace Fnlla\Php\Queue;

use RuntimeException;

final class FileQueueStore implements QueueStoreInterface
{
    public function __construct(private string $directory)
    {
        if (!is_dir($this->directory)) {
            mkdir($this->directory, 0777, true);
        }
    }

    public function push(string $jobClass, array $payload = []): string
    {
        $id = gmdate("YmdHis") . "_" . bin2hex(random_bytes(8));
        $path = $this->directory . DIRECTORY_SEPARATOR . $id . ".job";
        $contents = json_encode([
            "job" => $jobClass,
            "payload" => $payload,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        file_put_contents($path, $contents, LOCK_EX);

        return $id;
    }

    public function pop(): ?array
    {
        $files = glob($this->directory . DIRECTORY_SEPARATOR . "*.job");

        if ($files === false || $files === []) {
            return null;
        }

        sort($files);
        $file = $files[0];
        $payload = $this->readPayload($file);

        return [
            "id" => pathinfo($file, PATHINFO_FILENAME),
            "job" => (string) ($payload["job"] ?? ""),
            "payload" => is_array($payload["payload"] ?? null) ? $payload["payload"] : [],
            "source" => $file,
        ];
    }

    public function complete(array $job): void
    {
        $file = $this->sourcePath($job);

        if (is_file($file)) {
            unlink($file);
        }
    }

    public function fail(array $job): string
    {
        $file = $this->sourcePath($job);
        $failedDirectory = dirname($file) . DIRECTORY_SEPARATOR . "failed";

        if (!is_dir($failedDirectory)) {
            mkdir($failedDirectory, 0777, true);
        }

        $destination = $failedDirectory . DIRECTORY_SEPARATOR . pathinfo($file, PATHINFO_FILENAME) . ".failed.job";

        if (is_file($destination)) {
            $destination = $failedDirectory
                . DIRECTORY_SEPARATOR
                . pathinfo($file, PATHINFO_FILENAME)
                . "-"
                . bin2hex(random_bytes(4))
                . ".failed.job";
        }

        if (!@rename($file, $destination)) {
            if (!copy($file, $destination) || !unlink($file)) {
                throw new RuntimeException("Unable to quarantine failed queued job: " . $file);
            }
        }

        return $destination;
    }

    public function pendingCount(): int
    {
        return $this->count("*.job", $this->directory);
    }

    public function failedCount(): int
    {
        return $this->count("*.failed.job", $this->directory . DIRECTORY_SEPARATOR . "failed");
    }

    private function readPayload(string $file): array
    {
        $contents = file_get_contents($file);

        if (!is_string($contents) || trim($contents) === "") {
            throw new RuntimeException("Queued job payload is empty: " . $file);
        }

        $payload = json_decode($contents, true);

        if (!is_array($payload)) {
            throw new RuntimeException("Queued job payload is invalid JSON: " . $file);
        }

        return $payload;
    }

    private function sourcePath(array $job): string
    {
        $file = $job["source"] ?? null;

        if (!is_string($file) || $file === "") {
            throw new RuntimeException("Queued job source is missing.");
        }

        return $file;
    }

    private function count(string $pattern, string $directory): int
    {
        if (!is_dir($directory)) {
            return 0;
        }

        $files = glob($directory . DIRECTORY_SEPARATOR . $pattern);

        return is_array($files) ? count(array_filter($files, "is_file")) : 0;
    }
}
