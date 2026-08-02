<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA CONSOLE SOURCE
File: src\Console\Commands\AiReviewPackCommand.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

Purpose:
- Combines redacted context, app map and upgrade readiness into one local AI
  review artefact.
*/

namespace Fnlla\Php\Console\Commands;

use Fnlla\Php\Console\Command;
use Fnlla\Php\Support\AiContextBuilder;
use Fnlla\Php\Support\AppMapBuilder;
use Fnlla\Php\Support\UpgradeAnalyzer;

final class AiReviewPackCommand extends Command
{
    public function name(): string
    {
        return "ai:review-pack";
    }

    public function description(): string
    {
        return "Build a redacted local AI review pack.";
    }

    public function handle(array $arguments): int
    {
        $json = in_array("--json", $arguments, true);
        $output = $this->optionValue($arguments, "--output") ?? framework_ai_review_pack_path();
        $contextBuilder = $this->container->make(AiContextBuilder::class);
        $pack = $contextBuilder->redactedCopy([
            "schema" => "fnlla.ai_review_pack.v1",
            "generated_at_utc" => gmdate(DATE_ATOM),
            "privacy" => [
                "mode" => "local-only",
                "external_calls" => false,
                "raw_env_included" => false,
                "source_files_included" => false,
            ],
            "context" => $contextBuilder->build(),
            "app_map" => $this->container->make(AppMapBuilder::class)->build(),
            "upgrade" => $this->container->make(UpgradeAnalyzer::class)->report($this->optionValue($arguments, "--target") ?? "2.0.0"),
            "review_prompts" => [
                "Find release blockers and missing tests for this FNLLA change set.",
                "Review route, middleware and config posture for security regressions.",
                "Suggest migration notes for downstream projects upgrading to the target major release.",
            ],
        ]);

        $contextBuilder->write($pack, $output);

        if ($json) {
            $pack["output_path"] = $output;
            $this->line(json_encode($pack, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            return 0;
        }

        $this->line("AI review pack written: " . $output);
        $this->line("Routes mapped: " . (string) ($pack["app_map"]["routes"]["count"] ?? 0));

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
