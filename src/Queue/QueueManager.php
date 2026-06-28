<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA PHP QUEUE SOURCE
File: src\Queue\QueueManager.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA PHP is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA PHP framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Implements the maintained file-backed queue runtime for asynchronous tasks.
*/

namespace Fnlla\Php\Queue;

use Fnlla\Php\Container\Container;
use RuntimeException;

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
            $payload = json_decode((string) file_get_contents($file), true);
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
        }

        return $processed;
    }
}
