<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA CONSOLE SOURCE
File: src\Console\Commands\SecurityAuditCommand.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

Purpose:
- Audits deploy-time security configuration before production release.
*/

namespace Fnlla\Php\Console\Commands;

use Fnlla\Php\Console\Command;
use Fnlla\Php\Support\SecurityAuditReport;

final class SecurityAuditCommand extends Command
{
    public function name(): string
    {
        return "security:audit";
    }

    public function description(): string
    {
        return "Audit FNLLA security configuration posture.";
    }

    public function handle(array $arguments): int
    {
        $report = $this->container->make(SecurityAuditReport::class)->build();
        $strict = in_array("--strict", $arguments, true);

        if (in_array("--json", $arguments, true)) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

            return $this->exitCode($report, $strict);
        }

        $this->line("FNLLA security audit");
        $this->line("Environment: " . (string) $report["environment"]);

        foreach ((array) $report["checks"] as $check) {
            $this->line(sprintf(
                "[%s] %s - %s",
                strtoupper((string) $check["status"]),
                (string) $check["id"],
                (string) $check["detail"]
            ));
        }

        $summary = (array) $report["summary"];
        $this->line(sprintf(
            "Summary: %d passed, %d warnings, %d failures",
            (int) ($summary["passed"] ?? 0),
            (int) ($summary["warnings"] ?? 0),
            (int) ($summary["failures"] ?? 0)
        ));

        return $this->exitCode($report, $strict);
    }

    private function exitCode(array $report, bool $strict): int
    {
        $summary = (array) ($report["summary"] ?? []);
        $failures = (int) ($summary["failures"] ?? 0);
        $warnings = (int) ($summary["warnings"] ?? 0);

        return $failures > 0 || ($strict && $warnings > 0) ? 1 : 0;
    }
}
