<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA CONSOLE SOURCE
File: src\Console\Commands\ReleaseSbomCommand.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

Purpose:
- Generates a CycloneDX SBOM for the source release tree.
*/

namespace Fnlla\Php\Console\Commands;

use Fnlla\Php\Console\Command;
use Fnlla\Php\Support\ReleaseArtifactBuilder;

final class ReleaseSbomCommand extends Command
{
    public function name(): string
    {
        return "release:sbom";
    }

    public function description(): string
    {
        return "Generate a CycloneDX source SBOM.";
    }

    public function handle(array $arguments): int
    {
        $builder = $this->container->make(ReleaseArtifactBuilder::class);
        $path = $this->optionValue($arguments, "--output") ?? $builder->defaultOutputPath("fnlla-sbom.cdx.json");
        $result = $builder->buildSbom($path);

        if (in_array("--json", $arguments, true)) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        } else {
            $this->line("SBOM written: " . $result["path"]);
            $this->line("Components: " . (string) $result["components"]);
        }

        return 0;
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
