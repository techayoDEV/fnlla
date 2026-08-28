<?php

declare(strict_types=1);

namespace Fnlla\Php\Console\Commands;

use Fnlla\Php\Console\Command;

final class AiExplainLogCommand extends Command
{
    public function name(): string
    {
        return "ai:explain-log";
    }

    public function description(): string
    {
        return "Explain a local log file with a small redacted offline report.";
    }

    public function handle(array $arguments): int
    {
        $path = (string) ($arguments[0] ?? "");
        if ($path === "" || !is_file($path)) {
            $this->error("Usage: php fnlla ai:explain-log storage/logs/app.log [--json]");
            return 1;
        }

        $contents = substr((string) file_get_contents($path), -20000);
        $report = [
            "schema" => "fnlla.ai_log_explainer.v1",
            "file" => str_replace("\\", "/", $path),
            "probable_cause" => $this->probableCause($contents),
            "likely_area" => $this->likelyArea($contents),
            "recommended_test_filter" => $this->recommendedFilter($contents),
            "release_risk" => str_contains(strtolower($contents), "fatal") || str_contains(strtolower($contents), "exception") ? "medium" : "low",
        ];

        if (in_array("--json", $arguments, true)) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            return 0;
        }

        $this->line("Probable cause: " . $report["probable_cause"]);
        $this->line("Likely area: " . $report["likely_area"]);
        $this->line("Recommended test: " . $report["recommended_test_filter"]);

        return 0;
    }

    private function probableCause(string $contents): string
    {
        $lower = strtolower($contents);

        return match (true) {
            str_contains($lower, "csrf") => "CSRF/session verification failed.",
            str_contains($lower, "pdo") || str_contains($lower, "sqlstate") => "Database connection or query failure.",
            str_contains($lower, "redis") => "Redis-backed adapter is configured but unavailable or misconfigured.",
            str_contains($lower, "route") || str_contains($lower, "404") => "Route, controller or URL mismatch.",
            str_contains($lower, "fatal") || str_contains($lower, "exception") => "Unhandled runtime exception.",
            default => "No strong pattern found in the sampled log tail.",
        };
    }

    private function likelyArea(string $contents): string
    {
        $lower = strtolower($contents);

        return match (true) {
            str_contains($lower, "csrf") || str_contains($lower, "session") => "security/session",
            str_contains($lower, "pdo") || str_contains($lower, "migration") => "database",
            str_contains($lower, "queue") => "queue",
            str_contains($lower, "asset") || str_contains($lower, "runtime") => "runtime-ui",
            default => "application",
        };
    }

    private function recommendedFilter(string $contents): string
    {
        return match ($this->likelyArea($contents)) {
            "security/session" => "php scripts/test.php --filter HardeningTest",
            "database" => "php scripts/test.php --filter DatabaseSnapshotTest",
            "queue" => "php scripts/test.php --filter FrameworkExtensionsTest",
            "runtime-ui" => "php scripts/test.php --filter FnllaRuntimeGuardTest",
            default => "php scripts/test.php --filter ApplicationTest",
        };
    }
}
