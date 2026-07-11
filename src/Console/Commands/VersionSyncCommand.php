<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA CONSOLE SOURCE
File: src\Console\Commands\VersionSyncCommand.php
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

final class VersionSyncCommand extends Command
{
    public function name(): string
    {
        return "version:sync";
    }

    public function description(): string
    {
        return "Regenerate the FNLLA repository MANIFEST.json from current version state.";
    }

    public function handle(array $arguments): int
    {
        $manifest = VersionManifest::syncRepositoryManifest();

        $this->line("FNLLA MANIFEST.json synchronized.");
        $this->line("Framework version: " . $manifest["product"]["version"]);
        $this->line("Vendored FNLLA Runtime version: " . $manifest["ui_runtime"]["vendored_version"]);

        return 0;
    }
}
