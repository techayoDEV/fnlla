<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA CONSOLE SOURCE
File: src\Console\Commands\VersionStatusCommand.php
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

use Fnlla\Php\Console\Command;
use Fnlla\Php\Support\VersionManifest;

final class VersionStatusCommand extends Command
{
    public function name(): string
    {
        return "version:status";
    }

    public function description(): string
    {
        return "Show the unified FNLLA version contract.";
    }

    public function handle(array $arguments): int
    {
        $status = VersionManifest::status();

        $this->line("FNLLA version: " . ($status["fnlla_version"] ?? "unknown"));
        $this->line(
            "Integrated built-in UI surface: "
            . (($status["integrated_runtime_synced"] ?? false)
                ? "synced"
                : ("out of sync (" . ($status["integrated_runtime_version"] ?? "unknown") . ")"))
        );
        $this->line("Repository MANIFEST.json: " . ($status["repository_manifest_exists"] ? "present" : "missing"));
        $this->line("Version contract: " . ($status["version_contract_ok"] ? "ok" : "out of sync"));

        if ($status["version_contract_ok"]) {
            return 0;
        }

        foreach ((array) ($status["errors"] ?? []) as $error) {
            $this->error("- " . $error);
        }

        return 1;
    }
}
