<?php

declare(strict_types=1);

namespace Fnlla\Php\Console\Commands;

use Fnlla\Php\Console\Command;

final class ConfigDoctorCommand extends Command
{
    public function name(): string
    {
        return "config:doctor";
    }

    public function description(): string
    {
        return "Check the highest-risk environment and runtime configuration.";
    }

    public function handle(array $arguments): int
    {
        $checks = [
            $this->check("app_url", app_environment() !== "production" || str_starts_with((string) config("app.base_url", ""), "https://"), "Production APP_URL should be HTTPS."),
            $this->check("asset_url", (string) config("app.asset_url", "") === "" || filter_var((string) config("app.asset_url"), FILTER_VALIDATE_URL) !== false, "ASSET_URL should be empty or a valid URL."),
            $this->check("redis_extension", !$this->usesRedis() || class_exists(\Redis::class), "Redis is configured but ext-redis is not loaded."),
            $this->check("production_debug", app_environment() !== "production" || !app_debug(), "APP_DEBUG must be false in production."),
            $this->check("trusted_hosts", app_environment() !== "production" || (array) config("security.trusted_hosts", []) !== [], "Production should set TRUSTED_HOSTS."),
        ];
        $ok = count(array_filter($checks, static fn (array $check): bool => $check["status"] === "fail")) === 0;
        $payload = [
            "schema" => "fnlla.config_doctor.v1",
            "ok" => $ok,
            "checks" => $checks,
        ];

        if (in_array("--json", $arguments, true)) {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            return $ok ? 0 : 1;
        }

        foreach ($checks as $check) {
            $this->line(sprintf("[%s] %s - %s", strtoupper((string) $check["status"]), (string) $check["id"], (string) $check["detail"]));
        }

        return $ok ? 0 : 1;
    }

    private function check(string $id, bool $ok, string $detail): array
    {
        return ["id" => $id, "status" => $ok ? "pass" : "fail", "detail" => $detail];
    }

    private function usesRedis(): bool
    {
        return (string) config("cache.default", "file") === "redis"
            || (string) config("queue.default", "file") === "redis"
            || (string) config("session.driver", "file") === "redis";
    }
}
