<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA CONSOLE SOURCE
File: src\Console\Commands\AiRedactCommand.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

Purpose:
- Redacts sensitive-looking keys from a JSON file before a developer shares it
  with an AI assistant or review system.
*/

namespace Fnlla\Php\Console\Commands;

use Fnlla\Php\Console\Command;
use Fnlla\Php\Support\AiContextBuilder;

final class AiRedactCommand extends Command
{
    public function name(): string
    {
        return "ai:redact";
    }

    public function description(): string
    {
        return "Redact sensitive-looking keys from a local JSON file.";
    }

    public function handle(array $arguments): int
    {
        $input = $this->optionValue($arguments, "--input") ?? framework_ai_review_pack_path();
        $output = $this->optionValue($arguments, "--output") ?? $this->defaultOutput($input);
        $json = in_array("--json", $arguments, true);

        if (!is_file($input)) {
            $this->error("Input JSON file does not exist: " . $input);
            return 1;
        }

        $decoded = json_decode((string) file_get_contents($input), true);

        if (!is_array($decoded)) {
            $this->error("Input must be a JSON object or array: " . $input);
            return 1;
        }

        $redacted = $this->container->make(AiContextBuilder::class)->redactedCopy($decoded);
        $directory = dirname($output);

        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        file_put_contents($output, json_encode($redacted, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL, LOCK_EX);

        if ($json) {
            $this->line(json_encode([
                "schema" => "fnlla.ai_redact.v1",
                "ok" => true,
                "input_path" => $input,
                "output_path" => $output,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            return 0;
        }

        $this->line("Redacted JSON written: " . $output);

        return 0;
    }

    private function defaultOutput(string $input): string
    {
        $extension = pathinfo($input, PATHINFO_EXTENSION);
        $suffix = $extension !== "" ? "." . $extension : "";

        return dirname($input) . DIRECTORY_SEPARATOR . pathinfo($input, PATHINFO_FILENAME) . ".redacted" . $suffix;
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
