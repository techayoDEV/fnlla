<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA CONSOLE SOURCE
File: src\Console\Commands\ProjectClaimCommand.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Exposes the downstream project claim workflow for exported FNLLA projects.
*/

namespace Fnlla\Php\Console\Commands;

use Fnlla\Php\Console\Command;
use Fnlla\Php\Support\ProjectClaimManager;
use RuntimeException;

final class ProjectClaimCommand extends Command
{
    public function name(): string
    {
        return "project:claim";
    }

    public function description(): string
    {
        return "Claim an exported project by writing owner, developer, maintainer and runtime metadata.";
    }

    public function handle(array $arguments): int
    {
        $options = $this->parseOptions($arguments);

        if ($options["help"] === true) {
            $this->printUsage();

            return 0;
        }

        unset($options["help"]);

        try {
            $identity = (new ProjectClaimManager())->claim($options);
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());
            $this->printUsage();

            return 1;
        }

        $this->line("Project identity claimed.");
        $this->line("Product: " . $identity["product"]);
        $this->line("Identifier: " . $identity["id"]);
        $this->line("Owner: " . $identity["owner"]);
        $this->line("Developer: " . $identity["developer"]);
        $this->line("Maintainer: " . $identity["maintainer"]);
        $this->line("Runtime: " . $identity["runtime"] . " by " . $identity["runtime_creator"]);

        return 0;
    }

    private function parseOptions(array $arguments): array
    {
        $options = [
            "help" => false,
            "product" => null,
            "id" => null,
            "slug" => null,
            "summary" => null,
            "owner" => null,
            "funder" => null,
            "client" => null,
            "system_owner" => null,
            "developer" => null,
            "maintainer" => null,
            "runtime" => null,
            "runtime_creator" => null,
        ];

        $aliases = [
            "identifier" => "id",
            "name" => "product",
            "maintenance-provider" => "maintainer",
            "maintenance_provider" => "maintainer",
            "runtime-creator" => "runtime_creator",
            "runtime_creator" => "runtime_creator",
            "system-owner" => "system_owner",
            "system_owner" => "system_owner",
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

            if (!str_starts_with($argument, "--")) {
                throw new RuntimeException("Unknown project:claim argument: " . $argument);
            }

            $option = substr($argument, 2);
            $value = null;

            if (str_contains($option, "=")) {
                [$option, $value] = explode("=", $option, 2);
            }

            $option = $aliases[$option] ?? $option;

            if (!array_key_exists($option, $options) || $option === "help") {
                throw new RuntimeException("Unknown option for project:claim: --" . $option);
            }

            if ($value === null) {
                $value = trim((string) ($arguments[$index + 1] ?? ""));
                $index++;
            }

            $options[$option] = trim((string) $value);
        }

        return $options;
    }

    private function printUsage(): void
    {
        $this->line("Usage: php fnlla project:claim --product <name> --owner <owner> --developer <developer> [options]");
        $this->line("Required:");
        $this->line("  --product <name>       Product, website or application name.");
        $this->line("  --owner <name>         Owner, funder, client or system owner when more specific values are omitted.");
        $this->line("  --developer <name>     Implementation provider or developer.");
        $this->line("Options:");
        $this->line("  --id <IDENTIFIER>      Stable product identifier. Defaults to an uppercase identifier derived from product.");
        $this->line("  --slug <slug>          Package/product slug. Defaults to a slug derived from product.");
        $this->line("  --funder <name>        Funding party. Defaults to owner.");
        $this->line("  --client <name>        Client. Defaults to owner.");
        $this->line("  --system-owner <name>  Website/system owner. Defaults to owner.");
        $this->line("  --maintainer <name>    Maintenance/update provider. Defaults to developer.");
        $this->line("  --runtime <name>       Runtime/framework name. Defaults to FNLLA.");
        $this->line("  --runtime-creator <n>  Runtime/framework creator. Defaults to TechAyo LTD (techayo.co.uk).");
        $this->line("  --summary <text>       Short machine-readable project summary.");
    }
}
