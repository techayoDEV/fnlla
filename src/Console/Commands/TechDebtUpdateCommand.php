<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA CONSOLE SOURCE
File: src\Console\Commands\TechDebtUpdateCommand.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

Purpose:
- Updates the self-checking technical-debt report and documentation snapshot.
*/

namespace Fnlla\Php\Console\Commands;

use Fnlla\Php\Console\Command;
use Fnlla\Php\Support\TechnicalDebtReportBuilder;

final class TechDebtUpdateCommand extends Command
{
    public function name(): string
    {
        return "tech-debt:update";
    }

    public function description(): string
    {
        return "Update or check the self-checking technical-debt report.";
    }

    public function handle(array $arguments): int
    {
        $json = in_array("--json", $arguments, true);
        $check = in_array("--check", $arguments, true);
        $docsPath = $this->optionValue($arguments, "--docs") ?? base_path("docs/DEVELOPER-PANEL.md");
        $reportPath = $this->optionValue($arguments, "--output") ?? framework_technical_debt_report_path();
        $builder = $this->container->make(TechnicalDebtReportBuilder::class);
        $report = $builder->build();
        $projectReport = is_file(base_path(".fnlla/framework-lock.json")) && $this->optionValue($arguments, "--docs") === null;
        $sync = $projectReport
            ? ["ok" => true, "mode" => "project-report", "path" => null]
            : $builder->syncMarkdown($docsPath, $report, $check);

        if (!$check) {
            $builder->writeJson($report, $reportPath);
        }

        $payload = [
            "schema" => "fnlla.technical_debt_update.v1",
            "ok" => (bool) ($sync["ok"] ?? false),
            "check" => $check,
            "docs" => $sync,
            "report_path" => $check ? null : $reportPath,
            "report" => $report,
        ];

        if ($json) {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            return $payload["ok"] ? 0 : 1;
        }

        if (!$payload["ok"]) {
            $this->error("Technical-debt snapshot is stale: " . $docsPath);
            return 1;
        }

        $summary = (array) ($report["summary"] ?? []);
        $this->line($check ? "Technical-debt snapshot is current." : "Technical-debt report updated.");
        $this->line(sprintf(
            "Checks: %s pass, %s warn, %s fail.",
            (string) ($summary["passed"] ?? 0),
            (string) ($summary["warnings"] ?? 0),
            (string) ($summary["failures"] ?? 0)
        ));

        return 0;
    }

    private function optionValue(array $arguments, string $name): ?string
    {
        foreach ($arguments as $index => $argument) {
            if ($argument === $name && isset($arguments[$index + 1])) {
                return (string) $arguments[$index + 1];
            }

            if (str_starts_with((string) $argument, $name . "=")) {
                return substr((string) $argument, strlen($name) + 1);
            }
        }

        return null;
    }
}
