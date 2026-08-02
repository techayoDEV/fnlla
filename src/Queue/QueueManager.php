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
    public function __construct(
        private Container $container,
        private ?QueueStoreInterface $store = null
    )
    {
        $this->store ??= new FileQueueStore(storage_path((string) config("queue.connections.file.path", "framework/queue")));
    }

    public function push(string $jobClass, array $payload = []): string
    {
        return $this->store->push($jobClass, $payload);
    }

    public function work(int $maxJobs = 50): int
    {
        $processed = 0;

        for ($index = 0; $index < max(1, $maxJobs); $index++) {
            $queuedJob = $this->store->pop();

            if ($queuedJob === null) {
                break;
            }

            try {
                $jobClass = $queuedJob["job"] ?? null;
                $parameters = is_array($queuedJob["payload"] ?? null) ? $queuedJob["payload"] : [];

                if (!is_string($jobClass) || !class_exists($jobClass)) {
                    throw new RuntimeException("Queued job class is invalid: " . (string) $jobClass);
                }

                $job = $this->container->make($jobClass, $parameters);

                if (!method_exists($job, "handle")) {
                    throw new RuntimeException("Queued job must define a handle method: " . $jobClass);
                }

                $job->handle();
                $this->store->complete($queuedJob);
                $processed++;
            } catch (Throwable $exception) {
                $failedPath = $this->store->fail($queuedJob);

                Logger::exception($exception, [
                    "queue_job_id" => $queuedJob["id"] ?? null,
                    "queue_failed_job_file" => $failedPath,
                ]);
            }
        }

        return $processed;
    }
}
