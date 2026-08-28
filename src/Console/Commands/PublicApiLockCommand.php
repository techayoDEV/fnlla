<?php

declare(strict_types=1);

namespace Fnlla\Php\Console\Commands;

use Fnlla\Php\Console\Command;

final class PublicApiLockCommand extends Command
{
    public function name(): string
    {
        return "api:lock";
    }

    public function description(): string
    {
        return "Write the small public API lock file used by release checks.";
    }

    public function handle(array $arguments): int
    {
        $path = base_path("docs/PUBLIC-API.lock.json");
        $payload = [
            "schema" => "fnlla.public_api_lock.v1",
            "helpers" => ["config", "env", "base_path", "public_path", "storage_path", "url", "asset", "route", "csrf_token", "csrf_field", "csp_nonce", "auth", "db", "cache", "queue", "runtime_ai", "stream_request_body_to_file"],
            "commands" => ["doctor", "config:doctor", "security:audit", "ops:backup-plan", "project:acceptance", "app:map", "upgrade:check", "perf:budget", "release:prepare", "release:manifest", "ai:ask", "ai:triage", "ai:explain-log", "ai:brief", "ai:providers"],
            "data" => [
                "database.transaction",
                "query_builder.offset",
                "query_builder.paginate",
                "runtime_ai.answer",
                "runtime_ai.providers",
                "runtime_ai.provider_status",
                "runtime_ai.provider.fionn",
            ],
        ];

        file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL, LOCK_EX);
        $this->line("Public API lock written: " . $path);

        return 0;
    }
}
