<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA CONSOLE SOURCE
File: src\Console\Commands\ProjectAcceptanceCommand.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

Purpose:
- Runs the project-base acceptance smoke checks expected before commercial
  application work starts on an exported FNLLA project.
*/

namespace Fnlla\Php\Console\Commands;

use Fnlla\Php\Console\Command;
use Fnlla\Php\Support\ProjectAcceptanceReportBuilder;

final class ProjectAcceptanceCommand extends Command
{
    public function name(): string
    {
        return "project:acceptance";
    }

    public function description(): string
    {
        return "Run acceptance smoke checks for a generated FNLLA project base.";
    }

    public function handle(array $arguments): int
    {
        $json = in_array("--json", $arguments, true);
        $report = $this->container->make(ProjectAcceptanceReportBuilder::class)->build();

        if ($json) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

            return ($report["ok"] ?? false) ? 0 : 1;
        }

        $this->line("FNLLA project acceptance");
        $this->line("Checks: " . (string) ($report["summary"]["checks"] ?? 0));
        $this->line("Failures: " . (string) ($report["summary"]["failures"] ?? 0));
        $this->line("Warnings: " . (string) ($report["summary"]["warnings"] ?? 0));

        foreach ((array) ($report["checks"] ?? []) as $check) {
            $this->line(sprintf(
                "[%s] %s - %s",
                strtoupper((string) ($check["status"] ?? "unknown")),
                (string) ($check["id"] ?? "check"),
                (string) ($check["detail"] ?? "")
            ));
        }

        return ($report["ok"] ?? false) ? 0 : 1;
    }
}
