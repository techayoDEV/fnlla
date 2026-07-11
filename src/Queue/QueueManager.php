<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA QUEUE SOURCE
File: src\Queue\QueueManager.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Implements the maintained file-backed queue runtime for asynchronous tasks.
*/

namespace Fnlla\Php\Queue;

use Fnlla\Php\Container\Container;
use Fnlla\Php\Support\Logger;
use RuntimeException;
use Throwable;

final class QueueManager
{
    public function __construct(private Container $container)
    {
    }

    public function push(string $jobClass, array $payload = []): string
    {
        $directory = storage_path((string) config("queue.connections.file.path", "framework/queue"));

        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $id = gmdate("YmdHis") . "_" . bin2hex(random_bytes(8));
        $path = $directory . DIRECTORY_SEPARATOR . $id . ".job";
        $contents = json_encode([
            "job" => $jobClass,
            "payload" => $payload,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        file_put_contents($path, $contents, LOCK_EX);

        return $id;
    }

    public function work(int $maxJobs = 50): int
    {
        $directory = storage_path((string) config("queue.connections.file.path", "framework/queue"));

        if (!is_dir($directory)) {
            return 0;
        }

        $files = glob($directory . DIRECTORY_SEPARATOR . "*.job");

        if ($files === false) {
            return 0;
        }

        sort($files);
        $processed = 0;

        foreach (array_slice($files, 0, max(1, $maxJobs)) as $file) {
            try {
                $payload = $this->readPayload($file);
                $jobClass = $payload["job"] ?? null;
                $parameters = is_array($payload["payload"] ?? null) ? $payload["payload"] : [];

                if (!is_string($jobClass) || !class_exists($jobClass)) {
                    throw new RuntimeException("Queued job class is invalid: " . (string) $jobClass);
                }

                $job = $this->container->make($jobClass, $parameters);

                if (!method_exists($job, "handle")) {
                    throw new RuntimeException("Queued job must define a handle method: " . $jobClass);
                }

                $job->handle();
                unlink($file);
                $processed++;
            } catch (Throwable $exception) {
                $failedPath = $this->quarantineFailedJob($file);

                Logger::exception($exception, [
                    "queue_job_file" => $file,
                    "queue_failed_job_file" => $failedPath,
                ]);
            }
        }

        return $processed;
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

    private function quarantineFailedJob(string $file): string
    {
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
}
