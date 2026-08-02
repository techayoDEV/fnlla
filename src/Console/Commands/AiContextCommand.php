<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA CONSOLE SOURCE
File: src\Console\Commands\AiContextCommand.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

Purpose:
- Exports a local, redacted framework context pack for AI-assisted development
  and release review workflows.
*/

namespace Fnlla\Php\Console\Commands;

use Fnlla\Php\Console\Command;
use Fnlla\Php\Support\AiContextBuilder;

final class AiContextCommand extends Command
{
    public function name(): string
    {
        return "ai:context";
    }

    public function description(): string
    {
        return "Build a redacted local context pack for AI-assisted development.";
    }

    public function handle(array $arguments): int
    {
        $json = in_array("--json", $arguments, true);
        $output = $this->optionValue($arguments, "--output") ?? framework_ai_context_path();
        $builder = $this->container->make(AiContextBuilder::class);
        $context = $builder->build();

        $builder->write($context, $output);

        if ($json) {
            $context["output_path"] = $output;
            $this->line(json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            return 0;
        }

        $this->line("AI context pack written: " . $output);
        $this->line("Routes indexed: " . (string) ($context["routes"]["count"] ?? 0));
        $this->line("Docs indexed: " . (string) ($context["documentation"]["count"] ?? 0));
        $this->line("Privacy mode: local-only, no external calls, no raw .env.");

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
