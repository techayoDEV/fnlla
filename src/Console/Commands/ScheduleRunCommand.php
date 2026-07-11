<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA CONSOLE SOURCE
File: src\Console\Commands\ScheduleRunCommand.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Implements the maintained CLI surface and scheduler-oriented console behavior.
*/

namespace Fnlla\Php\Console\Commands;

use DateTimeImmutable;
use Fnlla\Php\Console\Application;
use Fnlla\Php\Console\Command;
use Fnlla\Php\Console\Scheduling\Schedule;

final class ScheduleRunCommand extends Command
{
    public function name(): string
    {
        return "schedule:run";
    }

    public function description(): string
    {
        return "Run due scheduled tasks.";
    }

    public function handle(array $arguments): int
    {
        $schedule = new Schedule();
        $scheduleFile = base_path("routes/console.php");

        if (is_file($scheduleFile)) {
            require $scheduleFile;
        }

        $ran = 0;
        $now = new DateTimeImmutable("now");

        foreach ($schedule->tasks() as $task) {
            if (!$task->isDue($now)) {
                continue;
            }

            $payload = $task->task();

            if (is_callable($payload)) {
                $this->container->call($payload);
            } elseif (is_array($payload) && isset($payload["command"])) {
                $console = $this->container->make(Application::class);
                $console->runCommand((string) $payload["command"], (array) ($payload["arguments"] ?? []));
            }

            $this->line("Ran scheduled task: " . $task->description());
            $ran++;
        }

        if ($ran === 0) {
            $this->line("No scheduled tasks were due.");
        }

        return 0;
    }
}
