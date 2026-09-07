<?php

declare(strict_types=1);

namespace Fnlla\Php\Console\Commands;

use Fnlla\Php\Console\Command;

final class AiAskCommand extends Command
{
    public function name(): string
    {
        return "ai:ask";
    }

    public function description(): string
    {
        return "Ask the selected runtime AI provider a question (cloud providers are opt-in).";
    }

    public function handle(array $arguments): int
    {
        $json = in_array("--json", $arguments, true);
        $question = trim(implode(" ", array_values(array_filter($arguments, static fn (string $argument): bool => !str_starts_with($argument, "--")))));

        if ($question === "") {
            $this->error("Usage: php fnlla ai:ask \"question\" [--json]");
            return 1;
        }

        $answer = \framework_runtime_ai_provider($this->container)->answer($question, [
            "command" => "ai:ask",
            "environment" => app_environment(),
        ]);

        if ($json) {
            $this->line(json_encode($answer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            return 0;
        }

        $this->line((string) ($answer["answer"] ?? ""));
        $this->line(($answer["provider"]["confidence_measured"] ?? true) === false
            ? "Confidence: not measured; review generated answers."
            : "Confidence: " . (string) ($answer["confidence"] ?? 0) . "%");

        if (($answer["actions"] ?? []) !== []) {
            $this->line("Actions:");
            foreach ((array) $answer["actions"] as $action) {
                $this->line("- " . (string) $action);
            }
        }

        return 0;
    }
}
