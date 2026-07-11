<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA CONSOLE SOURCE
File: src\Console\Scheduling\ScheduledTask.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Implements the maintained CLI surface and scheduler-oriented console behavior.
*/

namespace Fnlla\Php\Console\Scheduling;

use DateTimeImmutable;

final class ScheduledTask
{
    private string $frequency = "everyMinute";
    private ?string $time = null;

    public function __construct(
        private mixed $task,
        private string $description = ""
    ) {
    }

    public function everyMinute(): self
    {
        $this->frequency = "everyMinute";

        return $this;
    }

    public function hourly(): self
    {
        $this->frequency = "hourly";

        return $this;
    }

    public function dailyAt(string $time): self
    {
        $this->frequency = "dailyAt";
        $this->time = $time;

        return $this;
    }

    public function isDue(DateTimeImmutable $now): bool
    {
        return match ($this->frequency) {
            "hourly" => $now->format("i") === "00",
            "dailyAt" => $this->time !== null && $now->format("H:i") === $this->time,
            default => true,
        };
    }

    public function task(): mixed
    {
        return $this->task;
    }

    public function description(): string
    {
        return $this->description;
    }
}
