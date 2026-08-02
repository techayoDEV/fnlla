<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA CONSOLE SOURCE
File: src\Console\Commands\AppMapCommand.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

Purpose:
- Exposes the generated application map used for audits, AI review and major
  release migration planning.
*/

namespace Fnlla\Php\Console\Commands;

use Fnlla\Php\Console\Command;
use Fnlla\Php\Support\AppMapBuilder;

final class AppMapCommand extends Command
{
    public function name(): string
    {
        return "app:map";
    }

    public function description(): string
    {
        return "Generate a route/controller/view application map.";
    }

    public function handle(array $arguments): int
    {
        $json = in_array("--json", $arguments, true);
        $html = in_array("--html", $arguments, true);
        $output = $this->optionValue($arguments, "--output") ?? framework_app_map_path();
        $builder = $this->container->make(AppMapBuilder::class);
        $map = $builder->build();

        if ($html) {
            $output = $this->optionValue($arguments, "--output") ?? preg_replace('/\.json$/', ".html", framework_app_map_path());
            $builder->writeHtml($map, (string) $output);
        } else {
            $builder->writeJson($map, $output);
        }

        if ($json) {
            $map["output_path"] = $output;
            $this->line(json_encode($map, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            return 0;
        }

        $this->line("Application map written: " . $output);
        $this->line("Routes mapped: " . (string) ($map["routes"]["count"] ?? 0));
        $this->line("Middleware aliases used: " . (string) count((array) ($map["middleware"] ?? [])));

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
