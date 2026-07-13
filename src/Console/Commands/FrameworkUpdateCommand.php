<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA CONSOLE SOURCE
File: src\Console\Commands\FrameworkUpdateCommand.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Checks or applies framework-base updates inside downstream applications
  without blindly overwriting application-owned files.
*/

namespace Fnlla\Php\Console\Commands;

use Fnlla\Php\Console\Command;
use Fnlla\Php\Support\FrameworkLock;
use Fnlla\Php\Support\FrameworkUpdater;
use RuntimeException;

final class FrameworkUpdateCommand extends Command
{
    public function name(): string
    {
        return "framework:update";
    }

    public function description(): string
    {
        return "Check or apply FNLLA framework-base updates from a maintained source repository or the public GitHub release channel.";
    }

    public function handle(array $arguments): int
    {
        $options = $this->parseOptions($arguments);

        if ($options["help"] === true) {
            $this->printUsage();

            return 0;
        }

        $projectRoot = rtrim((string) base_path(), "\\/");
        $currentLock = FrameworkLock::load($projectRoot);
        $appName = (string) ($currentLock["framework_base"]["application"]["name"] ?? config("app.name", "FNLLA Project"));

        $report = ($options["github"] ?? false) === true
            ? (
                $options["apply"] === true
                    ? FrameworkUpdater::applyLatestRelease($projectRoot, $appName, (string) ($options["release_tag"] ?? ""))
                    : FrameworkUpdater::checkLatestRelease($projectRoot, $appName, (string) ($options["release_tag"] ?? ""))
            )
            : (
                $options["apply"] === true
                    ? FrameworkUpdater::apply($projectRoot, (string) ($options["source"] ?? ""), $appName)
                    : FrameworkUpdater::check($projectRoot, (string) ($options["source"] ?? ""), $appName)
            );

        $this->renderReport($report);

        if ($options["apply"] === true) {
            $this->line("");
            $this->line("Applied framework update changes: " . (int) ($report["applied_changes"] ?? 0));
            $this->renderPostInstallChecks((array) ($report["post_install_checks"] ?? []));

            return ($report["post_install_ok"] ?? true) === true ? 0 : 1;
        }

        return $report["conflicts"] === [] ? 0 : 1;
    }

    private function parseOptions(array $arguments): array
    {
        $options = [
            "apply" => false,
            "github" => false,
            "help" => false,
            "release_tag" => null,
            "source" => null,
        ];

        for ($index = 0, $count = count($arguments); $index < $count; $index++) {
            $argument = trim((string) $arguments[$index]);

            if ($argument === "") {
                continue;
            }

            if ($argument === "--apply") {
                $options["apply"] = true;
                continue;
            }

            if ($argument === "--check") {
                $options["apply"] = false;
                continue;
            }

            if ($argument === "--github") {
                $options["github"] = true;
                continue;
            }

            if ($argument === "--help" || $argument === "-h") {
                $options["help"] = true;
                continue;
            }

            if (str_starts_with($argument, "--release-tag=")) {
                $options["release_tag"] = substr($argument, strlen("--release-tag="));
                continue;
            }

            if ($argument === "--release-tag") {
                $options["release_tag"] = trim((string) ($arguments[$index + 1] ?? ""));
                $index++;
                continue;
            }

            if (str_starts_with($argument, "--source=")) {
                $options["source"] = substr($argument, strlen("--source="));
                continue;
            }

            if ($argument === "--source") {
                $options["source"] = trim((string) ($arguments[$index + 1] ?? ""));
                $index++;
                continue;
            }

            throw new RuntimeException("Unknown option for framework:update: " . $argument);
        }

        return $options;
    }

    private function printUsage(): void
    {
        $this->line("Usage: php fnlla framework:update --check [--source <path-to-fnlla>]");
        $this->line("   or: php fnlla framework:update --apply [--source <path-to-fnlla>]");
        $this->line("   or: php fnlla framework:update --check --github [--release-tag v1.0.x]");
        $this->line("   or: php fnlla framework:update --apply --github [--release-tag v1.0.x]");
        $this->line("If --source is omitted, FNLLA will try to auto-detect a sibling maintained repository.");
        $this->line("Use --github to compare against the latest published FNLLA release downloaded into the local update cache.");
    }

    private function renderReport(array $report): void
    {
        $this->line("Framework update check");
        if (is_string($report["source_root"] ?? null) && $report["source_root"] !== "") {
            $this->line("Source repository: " . $report["source_root"] . " (" . ($report["source_origin"] ?? "resolved") . ")");
        }
        if (is_array($report["github_release"] ?? null) && $report["github_release"] !== []) {
            $githubRelease = (array) $report["github_release"];
            $this->line(
                "GitHub release: "
                . (string) ($githubRelease["tag"] ?? "unknown")
                . " (current: "
                . (string) ($githubRelease["current_version"] ?? "unknown")
                . ")"
            );
            $this->line("GitHub cache: " . (string) ($report["download_cache_path"] ?? "unknown"));
        }
        $this->line("Current FNLLA base: " . $this->baseVersionSummary($report, "current"));
        $this->line("Source FNLLA base: " . $this->baseVersionSummary($report, "source"));
        $this->line("Managed files tracked: " . $report["tracked_managed_files"] . " (source export: " . $report["source_managed_files"] . ")");
        $this->line("Safe framework changes available: " . count($report["updates"]));
        $this->line("Conflicts: " . count($report["conflicts"]));
        $this->line("Local-only managed changes preserved: " . count($report["local_only_changes"]));

        if ($report["updates"] === [] && $report["conflicts"] === []) {
            $this->line("");
            if (is_string($report["release_skip_reason"] ?? null) && $report["release_skip_reason"] !== "") {
                $this->line((string) $report["release_skip_reason"]);
            } else {
                $this->line(
                    $report["local_only_changes"] === []
                        ? "Framework base is already aligned with the provided source export."
                        : "No upstream framework drift was detected. Local managed-file edits stay untouched."
                );
            }
        }

        foreach ($report["updates"] as $path => $update) {
            $this->line("[" . $this->updateActionLabel((array) $update) . "] " . $path);
        }

        foreach ($report["conflicts"] as $path => $conflict) {
            $this->error("[CONFLICT] " . $path . " - " . $conflict["reason"]);

            if (is_string($conflict["next_step"] ?? null) && $conflict["next_step"] !== "") {
                $this->line("           Next step: " . $conflict["next_step"]);
            }
        }
    }

    private function renderPostInstallChecks(array $checks): void
    {
        if ($checks === []) {
            $this->line("No post-install checks were recorded for this update run.");

            return;
        }

        $this->line("Post-install checks:");

        foreach ($checks as $check) {
            if (!is_array($check)) {
                continue;
            }

            $status = strtoupper((string) ($check["status"] ?? "unknown"));
            $label = (string) ($check["label"] ?? "Check");
            $exitCode = $check["exit_code"];

            $this->line(
                "- {$label}: {$status}" . ($exitCode !== null ? " (exit {$exitCode})" : "")
            );
        }
    }

    private function updateActionLabel(array $update): string
    {
        $label = trim((string) ($update["label"] ?? ""));

        if ($label !== "") {
            return $label;
        }

        return match ((string) ($update["action"] ?? "update")) {
            "add" => "Automatic add ready",
            "remove" => "Automatic removal ready",
            "sync" => "Formatting-only sync ready",
            default => "Automatic update ready",
        };
    }

    private function baseVersionSummary(array $report, string $prefix): string
    {
        $frameworkVersion = trim((string) ($report[$prefix . "_framework_version"] ?? "unknown"));
        $uiVersion = trim((string) ($report[$prefix . "_ui_version"] ?? "unknown"));

        if ($frameworkVersion !== "" && $frameworkVersion === $uiVersion) {
            return $frameworkVersion;
        }

        return $frameworkVersion . " / integrated UI surface " . $uiVersion;
    }
}
