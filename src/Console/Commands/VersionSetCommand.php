<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA CONSOLE SOURCE
File: src\Console\Commands\VersionSetCommand.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Provides the maintainer release workflow for setting one unified FNLLA version
  across repository and integrated UI surface metadata.
*/

namespace Fnlla\Php\Console\Commands;

use Fnlla\Php\Console\Command;
use Fnlla\Php\Support\VersionManifest;

final class VersionSetCommand extends Command
{
    public function name(): string
    {
        return "version:set";
    }

    public function description(): string
    {
        return "Set the maintained FNLLA release version and synchronize repository/runtime metadata.";
    }

    public function handle(array $arguments): int
    {
        $version = trim((string) ($arguments[0] ?? ""));

        if ($version === "--help" || $version === "-h") {
            $this->printUsage();

            return 0;
        }

        if ($version === "") {
            $this->error("Missing FNLLA release version.");
            $this->printUsage();

            return 1;
        }

        if (count($arguments) > 1) {
            $this->error("version:set accepts exactly one semantic version.");
            $this->printUsage();

            return 1;
        }

        $manifest = VersionManifest::setRepositoryVersion($version);

        $this->line("FNLLA release version updated.");
        $this->line("FNLLA version: " . $manifest["product"]["version"]);
        $this->line("Integrated built-in UI surface version: " . $manifest["ui_runtime"]["version"]);
        $this->line("");
        $this->line("Recommended release checks:");
        $this->line("- php scripts/build-docs.php");
        $this->line("- php scripts/test.php");
        $this->line("- php scripts/lint.php");
        $this->line("- php scripts/validate-fnlla-runtime.php");
        $this->line("- php scripts/validate-version-manifest.php");
        $this->line("- php scripts/validate-release-metadata.php");
        $this->line("- powershell -ExecutionPolicy Bypass -File .\\scripts\\publish-fnlla-runtime.ps1");

        return 0;
    }

    private function printUsage(): void
    {
        $this->line("Usage: php fnlla version:set <semver>");
        $this->line("Example: php fnlla version:set 2.0.0");
    }
}
