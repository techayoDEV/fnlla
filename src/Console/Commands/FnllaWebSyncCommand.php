<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA PHP CONSOLE SOURCE
File: src\Console\Commands\FnllaWebSyncCommand.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA PHP is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA PHP framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Implements the maintained CLI surface and scheduler-oriented console behavior.
*/

namespace Fnlla\Php\Console\Commands;

use Fnlla\Php\Console\Command;
use Fnlla\Php\Support\FnllaWebGuard;
use RuntimeException;

final class FnllaWebSyncCommand extends Command
{
    public function name(): string
    {
        return "fnlla-web:sync";
    }

    public function description(): string
    {
        return "Sync the vendored FNLLA Web runtime from GitHub or a local runtime export.";
    }

    public function handle(array $arguments): int
    {
        $options = $this->parseOptions($arguments);

        if ($options["help"] === true) {
            $this->printUsage();

            return 0;
        }

        FnllaWebGuard::syncNowWithOptions($options);
        $this->line("FNLLA Web sync completed.");

        return 0;
    }

    private function parseOptions(array $arguments): array
    {
        $options = [
            "help" => false,
            "ref" => null,
            "repo_url" => null,
            "repository" => null,
            "source" => null,
            "working_clone_path" => null,
        ];

        for ($index = 0, $count = count($arguments); $index < $count; $index++) {
            $argument = trim((string) $arguments[$index]);

            if ($argument === "") {
                continue;
            }

            if ($argument === "--help" || $argument === "-h") {
                $options["help"] = true;
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

            if (str_starts_with($argument, "--repo-url=")) {
                $options["repo_url"] = substr($argument, strlen("--repo-url="));
                continue;
            }

            if ($argument === "--repo-url") {
                $options["repo_url"] = trim((string) ($arguments[$index + 1] ?? ""));
                $index++;
                continue;
            }

            if (str_starts_with($argument, "--repository=")) {
                $options["repository"] = substr($argument, strlen("--repository="));
                continue;
            }

            if ($argument === "--repository") {
                $options["repository"] = trim((string) ($arguments[$index + 1] ?? ""));
                $index++;
                continue;
            }

            if (str_starts_with($argument, "--working-clone-path=")) {
                $options["working_clone_path"] = substr($argument, strlen("--working-clone-path="));
                continue;
            }

            if ($argument === "--working-clone-path") {
                $options["working_clone_path"] = trim((string) ($arguments[$index + 1] ?? ""));
                $index++;
                continue;
            }

            if (str_starts_with($argument, "--ref=")) {
                $options["ref"] = substr($argument, strlen("--ref="));
                continue;
            }

            if ($argument === "--ref") {
                $options["ref"] = trim((string) ($arguments[$index + 1] ?? ""));
                $index++;
                continue;
            }

            throw new RuntimeException("Unknown option for fnlla-web:sync: " . $argument);
        }

        return $options;
    }

    private function printUsage(): void
    {
        $this->line("Usage: php fnlla fnlla-web:sync [--source <path-to-fnlla-web-or-runtime-export>]");
        $this->line("   or: php fnlla fnlla-web:sync [--repo-url <git-url>] [--repository techayoDEV/fnlla] [--working-clone-path <path>] [--ref <git-ref>]");
        $this->line("If --source is provided, FNLLA PHP syncs from the local runtime export or from dist\\fnlla-web in a local source checkout.");
        $this->line("If --source is omitted, FNLLA PHP clones the maintained repository and syncs from the published runtime export.");
    }
}
