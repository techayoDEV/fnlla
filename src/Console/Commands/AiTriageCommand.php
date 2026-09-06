<?php

declare(strict_types=1);

namespace Fnlla\Php\Console\Commands;

use Fnlla\Php\Console\Command;
use Fnlla\Php\Support\AiContextBuilder;
use Fnlla\Php\Support\AppMapBuilder;
use Fnlla\Php\Support\UpgradeAnalyzer;

final class AiTriageCommand extends Command
{
    public function name(): string
    {
        return "ai:triage";
    }

    public function description(): string
    {
        return "Build a local AI triage report for a bug, change or upgrade question.";
    }

    public function handle(array $arguments): int
    {
        $json = in_array("--json", $arguments, true);
        $input = $this->optionValue($arguments, "--input") ?? trim(implode(" ", array_values(array_filter(
            $arguments,
            static fn (string $argument): bool => !str_starts_with($argument, "--")
        ))));

        if ($input === "") {
            $this->error("Usage: php fnlla ai:triage --input=\"problem or change request\" [--json]");
            return 1;
        }

        $report = $this->buildReport($input);

        if ($json) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            return 0;
        }

        $this->line("AI triage");
        $this->line("Answer: " . (string) ($report["answer"]["answer"] ?? ""));
        $this->line("Likely areas: " . implode(", ", (array) ($report["likely_areas"] ?? [])));

        return 0;
    }

    public function buildReport(string $input): array
    {
        $contextBuilder = $this->container->make(AiContextBuilder::class);

        return $contextBuilder->redactedCopy([
            "schema" => "fnlla.ai_triage.v1",
            "generated_at_utc" => gmdate(DATE_ATOM),
            "privacy" => [
                "mode" => "local-only",
                "external_calls" => false,
                "raw_env_included" => false,
                "source_files_included" => false,
            ],
            "input" => $input,
            "answer" => \framework_runtime_ai_provider($this->container)->answer($input, [
                "command" => "ai:triage",
                "environment" => app_environment(),
            ]),
            "likely_areas" => $this->likelyAreas($input),
            "app_map_summary" => [
                "routes" => (int) ($this->container->make(AppMapBuilder::class)->build()["routes"]["count"] ?? 0),
            ],
            "upgrade_summary" => $this->container->make(UpgradeAnalyzer::class)->report(UpgradeAnalyzer::DEFAULT_TARGET_VERSION)["summary"] ?? [],
            "next_steps" => [
                "Run composer test -- --filter when the affected area is known.",
                "Run php fnlla app:map --json before changing routes or controllers.",
                "Run php fnlla ai:review-pack --json before release review.",
            ],
        ]);
    }

    private function likelyAreas(string $input): array
    {
        $input = strtolower($input);
        $areas = [];

        foreach ([
            "routing" => ["route", "url", "404", "controller"],
            "database" => ["db", "database", "migration", "mysql", "query"],
            "security" => ["csrf", "cors", "auth", "session", "header", "host"],
            "runtime-ui" => ["asset", "css", "runtime", "layout", "view"],
            "release" => ["release", "version", "manifest", "checksum", "tag"],
            "ai" => ["ai", "assistant", "triage", "knowledge"],
        ] as $area => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($input, $needle)) {
                    $areas[] = $area;
                    break;
                }
            }
        }

        return $areas !== [] ? array_values(array_unique($areas)) : ["application"];
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
