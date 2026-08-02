<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA CONSOLE SOURCE
File: src\Console\Commands\DoctorCommand.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

Purpose:
- Reports local runtime readiness for developers, CI and release operators.
*/

namespace Fnlla\Php\Console\Commands;

use Fnlla\Php\Console\Command;
use Fnlla\Php\Support\DoctorReport;

final class DoctorCommand extends Command
{
    public function name(): string
    {
        return "doctor";
    }

    public function description(): string
    {
        return "Inspect local FNLLA runtime readiness.";
    }

    public function handle(array $arguments): int
    {
        $report = $this->container->make(DoctorReport::class)->build();

        if (in_array("--json", $arguments, true)) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

            return (int) $report["summary"]["failures"] > 0 ? 1 : 0;
        }

        $this->line("FNLLA doctor");
        $this->line("Environment: " . (string) $report["environment"]);

        foreach ((array) $report["checks"] as $check) {
            $this->line(sprintf(
                "[%s] %s - %s",
                strtoupper((string) $check["status"]),
                (string) $check["label"],
                (string) $check["detail"]
            ));
        }

        $summary = (array) $report["summary"];
        $this->line(sprintf(
            "Summary: %d ok, %d warnings, %d failures",
            (int) ($summary["ok"] ?? 0),
            (int) ($summary["warnings"] ?? 0),
            (int) ($summary["failures"] ?? 0)
        ));

        return (int) ($summary["failures"] ?? 0) > 0 ? 1 : 0;
    }
}
