<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA PHP CONSOLE SOURCE
File: src\Console\Scheduling\Schedule.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA PHP is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA PHP framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Implements the maintained CLI surface and scheduler-oriented console behavior.
*/

namespace Fnlla\Php\Console\Scheduling;

final class Schedule
{
    private array $tasks = [];

    public function call(callable $callback, string $description = "callback"): ScheduledTask
    {
        $task = new ScheduledTask($callback, $description);
        $this->tasks[] = $task;

        return $task;
    }

    public function command(string $command, array $arguments = []): ScheduledTask
    {
        $task = new ScheduledTask([
            "command" => $command,
            "arguments" => $arguments,
        ], $command);
        $this->tasks[] = $task;

        return $task;
    }

    public function tasks(): array
    {
        return $this->tasks;
    }
}
