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
            "attempts" => 0,
            "max_attempts" => (int) config("queue.max_attempts", 3),
            "available_at" => time(),
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
        $file = null;
        $payload = [];

        foreach ($files as $candidate) {
            $candidatePayload = $this->readPayload($candidate);
            if ((int) ($candidatePayload["available_at"] ?? 0) > time()) {
                continue;
            }

            $file = $candidate;
            $payload = $candidatePayload;
            break;
        }

        if ($file === null) {
            return null;
        }

        return [
            "id" => pathinfo($file, PATHINFO_FILENAME),
            "job" => (string) ($payload["job"] ?? ""),
            "payload" => is_array($payload["payload"] ?? null) ? $payload["payload"] : [],
            "attempts" => (int) ($payload["attempts"] ?? 0),
            "max_attempts" => (int) ($payload["max_attempts"] ?? config("queue.max_attempts", 3)),
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
        $payload = is_file($file) ? $this->readPayload($file) : [
            "job" => (string) ($job["job"] ?? ""),
            "payload" => (array) ($job["payload"] ?? []),
        ];
        $payload["attempts"] = ((int) ($payload["attempts"] ?? $job["attempts"] ?? 0)) + 1;
        $payload["max_attempts"] = (int) ($payload["max_attempts"] ?? $job["max_attempts"] ?? config("queue.max_attempts", 3));
        $payload["last_error"] = (string) ($job["last_error"] ?? "Job failed.");

        if ($payload["attempts"] < $payload["max_attempts"]) {
            $payload["available_at"] = time() + max(1, (int) config("queue.retry_backoff_seconds", 30));
            file_put_contents($file, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), LOCK_EX);

            return $file;
        }

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

        file_put_contents($destination, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), LOCK_EX);

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
