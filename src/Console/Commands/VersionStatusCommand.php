<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA PHP CONSOLE SOURCE
File: src\Console\Commands\VersionStatusCommand.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). All rights reserved.
===============================================================================

FNLLA PHP is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the proprietary FNLLA PHP framework and its related delivery scripts, tests,
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
        return "Show the current FNLLA PHP and vendored FNLLA UI version contract.";
    }

    public function handle(array $arguments): int
    {
        $status = VersionManifest::status();

        $this->line("FNLLA PHP version: " . ($status["framework_version"] ?? "unknown"));
        $this->line("Vendored FNLLA UI version: " . ($status["vendored_ui_version"] ?? "unknown"));
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
