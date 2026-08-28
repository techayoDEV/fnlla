<?php

declare(strict_types=1);

namespace Fnlla\Php\Console\Commands;

use Fnlla\Php\Ai\RuntimeAiProviderRegistry;
use Fnlla\Php\Console\Command;

final class AiProvidersCommand extends Command
{
    public function name(): string
    {
        return "ai:providers";
    }

    public function description(): string
    {
        return "Show runtime AI provider readiness.";
    }

    public function handle(array $arguments): int
    {
        $report = $this->container->make(RuntimeAiProviderRegistry::class)->report();

        if (in_array("--json", $arguments, true)) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

            return 0;
        }

        $this->line("Runtime AI providers");
        $this->line("Selected driver: " . (string) ($report["selected_driver"] ?? "local"));

        foreach ((array) ($report["providers"] ?? []) as $driver => $provider) {
            $ready = ($provider["provider_ready"] ?? false) === true ? "ready" : "not ready";
            $state = (string) ($provider["integration_state"] ?? "configured");
            $externalCalls = ($provider["external_calls"] ?? false) === true ? "yes" : "no";
            $selected = ($provider["selected"] ?? false) === true ? " selected" : "";

            $this->line("- " . (string) $driver . ": " . $state . ", " . $ready . ", external calls: " . $externalCalls . $selected);
        }

        return 0;
    }
}
