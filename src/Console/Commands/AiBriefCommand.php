<?php

declare(strict_types=1);

namespace Fnlla\Php\Console\Commands;

use Fnlla\Php\Console\Command;
use Fnlla\Php\Support\AppMapBuilder;
use Fnlla\Php\Support\UpgradeAnalyzer;

final class AiBriefCommand extends Command
{
    public function name(): string
    {
        return "ai:brief";
    }

    public function description(): string
    {
        return "Generate a short local project brief for review.";
    }

    public function handle(array $arguments): int
    {
        $map = $this->container->make(AppMapBuilder::class)->build();
        $upgrade = $this->container->make(UpgradeAnalyzer::class)->report((string) ($arguments[0] ?? "2.1.1"));
        $brief = [
            "schema" => "fnlla.ai_brief.v1",
            "project" => (string) config("app.name", "FNLLA"),
            "environment" => app_environment(),
            "routes" => (int) ($map["routes"]["count"] ?? 0),
            "upgrade_summary" => $upgrade["summary"] ?? [],
            "next_steps" => [
                "Run composer test before release.",
                "Run composer lint before release.",
                "Run php fnlla security:audit --strict in production-like env.",
            ],
        ];

        $this->line(json_encode($brief, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        return 0;
    }
}
