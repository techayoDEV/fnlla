<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA CONSOLE SOURCE
File: src\Console\Commands\FnllaRuntimeSyncCommand.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Implements the maintained CLI surface and scheduler-oriented console behaviour.
*/

namespace Fnlla\Php\Console\Commands;

use Fnlla\Php\Console\Command;
use Fnlla\Php\Support\FnllaRuntimeGuard;
use RuntimeException;

final class FnllaRuntimeSyncCommand extends Command
{
    public function name(): string
    {
        return "fnlla-runtime:sync";
    }

    public function description(): string
    {
        return "Sync FNLLA's integrated UI surface from the official techayoDEV/fnlla GitHub repository.";
    }

    public function handle(array $arguments): int
    {
        $options = $this->parseOptions($arguments);

        if ($options["help"] === true) {
            $this->printUsage();

            return 0;
        }

        FnllaRuntimeGuard::syncNowWithOptions($options);
        $this->line("FNLLA integrated UI surface sync completed.");

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
                throw new RuntimeException("Local runtime source sync is disabled. FNLLA runtime updates can only use the official techayoDEV/fnlla GitHub repository.");
            }

            if ($argument === "--source") {
                throw new RuntimeException("Local runtime source sync is disabled. FNLLA runtime updates can only use the official techayoDEV/fnlla GitHub repository.");
            }

            if (str_starts_with($argument, "--repo-url=")) {
                throw new RuntimeException("Runtime repository overrides are disabled. FNLLA runtime updates can only use the official techayoDEV/fnlla GitHub repository.");
            }

            if ($argument === "--repo-url") {
                throw new RuntimeException("Runtime repository overrides are disabled. FNLLA runtime updates can only use the official techayoDEV/fnlla GitHub repository.");
            }

            if (str_starts_with($argument, "--repository=")) {
                throw new RuntimeException("Runtime repository overrides are disabled. FNLLA runtime updates can only use the official techayoDEV/fnlla GitHub repository.");
            }

            if ($argument === "--repository") {
                throw new RuntimeException("Runtime repository overrides are disabled. FNLLA runtime updates can only use the official techayoDEV/fnlla GitHub repository.");
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

            throw new RuntimeException("Unknown option for fnlla-runtime:sync: " . $argument);
        }

        return $options;
    }

    private function printUsage(): void
    {
        $this->line("Usage: php fnlla fnlla-runtime:sync [--working-clone-path <path>] [--ref <git-ref>]");
        $this->line("FNLLA clones only the official techayoDEV/fnlla GitHub repository, publishes the integrated UI surface export and syncs from that published output.");
    }
}
