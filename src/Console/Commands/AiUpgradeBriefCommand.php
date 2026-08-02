<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA CONSOLE SOURCE
File: src\Console\Commands\AiUpgradeBriefCommand.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

Purpose:
- Writes a concise Markdown brief for AI-assisted major-release migration work.
*/

namespace Fnlla\Php\Console\Commands;

use Fnlla\Php\Console\Command;
use Fnlla\Php\Support\UpgradeAnalyzer;

final class AiUpgradeBriefCommand extends Command
{
    public function name(): string
    {
        return "ai:upgrade-brief";
    }

    public function description(): string
    {
        return "Write a local AI brief for major-release migration review.";
    }

    public function handle(array $arguments): int
    {
        $json = in_array("--json", $arguments, true);
        $target = $this->optionValue($arguments, "--target") ?? "2.0.0";
        $output = $this->optionValue($arguments, "--output") ?? framework_ai_upgrade_brief_path();
        $report = $this->container->make(UpgradeAnalyzer::class)->report($target);
        $markdown = $this->markdown($report);

        $directory = dirname($output);

        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        file_put_contents($output, $markdown . PHP_EOL, LOCK_EX);

        if ($json) {
            $this->line(json_encode([
                "schema" => "fnlla.ai_upgrade_brief.v1",
                "ok" => true,
                "target_version" => $target,
                "output_path" => $output,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            return 0;
        }

        $this->line("AI upgrade brief written: " . $output);

        return 0;
    }

    private function markdown(array $report): string
    {
        $lines = [
            "# FNLLA AI Upgrade Brief",
            "",
            "- Current version: " . (string) ($report["current_version"] ?? "unknown"),
            "- Target version: " . (string) ($report["target_version"] ?? "unknown"),
            "- Repository kind: " . (string) ($report["repository_kind"] ?? "unknown"),
            "",
            "## Readiness Checks",
            "",
        ];

        foreach ((array) ($report["checks"] ?? []) as $check) {
            $lines[] = "- [" . strtoupper((string) ($check["status"] ?? "info")) . "] " . (string) ($check["id"] ?? "check") . ": " . (string) ($check["detail"] ?? "");
        }

        $lines[] = "";
        $lines[] = "## Requested AI Review";
        $lines[] = "";
        $lines[] = "Review this upgrade for release blockers, migration risks, missing tests, docs gaps and backward-compatibility concerns. Do not assume secrets or raw source are present.";

        return implode(PHP_EOL, $lines);
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
