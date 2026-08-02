<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA SUPPORT SOURCE
File: src\Support\AiContextBuilder.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

Purpose:
- Builds a privacy-first, local context pack that developers can hand to an AI
  assistant without exposing raw environment values, credentials or source files.
*/

namespace Fnlla\Php\Support;

use Fnlla\Php\Container\Container;
use Fnlla\Php\Routing\RouteDefinition;
use Fnlla\Php\Routing\Router;
use Fnlla\Php\Support\ServiceProvider;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class AiContextBuilder
{
    private const SECRET_KEY_PATTERN = '/(password|passwd|secret|token|private|credential|auth|cookie|key)/i';

    public function build(): array
    {
        $context = [
            "schema" => "fnlla.ai_context.v1",
            "generated_at_utc" => gmdate(DATE_ATOM),
            "privacy" => [
                "mode" => "local-only",
                "external_calls" => false,
                "raw_env_included" => false,
                "source_files_included" => false,
                "redaction_policy" => "allowlisted configuration with defensive sensitive-key redaction",
            ],
            "application" => $this->application(),
            "routes" => $this->routes(),
            "configuration_posture" => $this->configurationPosture(),
            "runtime_artifacts" => $this->runtimeArtifacts(),
            "documentation" => $this->documentation(),
            "repository_footprint" => $this->repositoryFootprint(),
            "recommended_ai_workflows" => [
                "Ask for a route/controller impact analysis before changing public endpoints.",
                "Ask for release-risk review using docs, config posture and generated performance numbers.",
                "Ask for test suggestions based on routes, middleware and security posture.",
            ],
        ];

        return $this->redact($context);
    }

    public function write(array $context, string $path): void
    {
        $directory = dirname($path);

        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        file_put_contents($path, json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL, LOCK_EX);
    }

    public function redactedCopy(mixed $value): mixed
    {
        return $this->redact($value);
    }

    private function application(): array
    {
        return [
            "name" => (string) config("app.name", "FNLLA"),
            "environment" => app_environment(),
            "debug" => app_debug(),
            "php_version" => PHP_VERSION,
            "fnlla_version" => $this->readFirstLine(base_path("VERSION")),
            "base_url_configured" => trim((string) config("app.base_url", "")) !== "",
            "timezone" => (string) config("app.timezone", "UTC"),
        ];
    }

    private function routes(): array
    {
        $router = $this->bootRouter();
        $rows = [];

        foreach ($router->getRoutes() as $method => $routesByMethod) {
            foreach ($routesByMethod as $route) {
                /** @var RouteDefinition $definition */
                $definition = $route["definition"];
                $rows[] = [
                    "method" => (string) $method,
                    "path" => $definition->path(),
                    "name" => $definition->routeName(),
                    "middleware" => $definition->middlewareStack(),
                    "dynamic" => ((array) ($route["parameters"] ?? [])) !== [],
                ];
            }
        }

        usort($rows, static function (array $left, array $right): int {
            return [$left["path"], $left["method"]] <=> [$right["path"], $right["method"]];
        });

        return [
            "count" => count($rows),
            "items" => $rows,
        ];
    }

    private function configurationPosture(): array
    {
        return [
            "cache" => [
                "default" => config("cache.default"),
                "serializer" => config("cache.serializer"),
            ],
            "session" => [
                "secure" => config("session.secure"),
                "http_only" => config("session.http_only"),
                "same_site" => config("session.same_site"),
                "strict_mode" => config("session.strict_mode"),
                "rotation_minutes" => config("session.rotate_after_minutes"),
            ],
            "cors" => [
                "supports_credentials" => config("cors.supports_credentials"),
                "allowed_origin_count" => count((array) config("cors.allowed_origins", [])),
                "uses_wildcard_origin" => in_array("*", (array) config("cors.allowed_origins", []), true),
            ],
            "security" => [
                "csrf_rotate_after_minutes" => config("security.csrf.rotate_after_minutes"),
                "max_body_bytes" => config("security.request.max_body_bytes"),
                "max_upload_bytes" => config("security.uploads.max_file_bytes"),
            ],
            "observability" => [
                "metrics_enabled" => config("observability.metrics.enabled"),
                "access_log_enabled" => config("observability.access_log.enabled"),
                "response_time_header_enabled" => config("observability.response_time_header.enabled"),
            ],
            "queue" => [
                "default" => config("queue.default"),
            ],
        ];
    }

    private function runtimeArtifacts(): array
    {
        return [
            "config_cache" => $this->artifact(framework_config_cache_path()),
            "route_cache" => $this->artifact(framework_route_cache_path()),
            "asset_manifest" => $this->artifact(framework_asset_manifest_path()),
            "opcache_preload" => $this->artifact(framework_preload_path()),
            "performance_baseline" => $this->artifact(framework_performance_baseline_path()),
        ];
    }

    private function documentation(): array
    {
        $documents = [];

        foreach (glob(base_path("docs") . DIRECTORY_SEPARATOR . "*.{md,html}", GLOB_BRACE) ?: [] as $path) {
            if (!is_file($path)) {
                continue;
            }

            $documents[] = [
                "file" => str_replace("\\", "/", substr($path, strlen(base_path()) + 1)),
                "bytes" => filesize($path),
                "updated_at_utc" => gmdate(DATE_ATOM, (int) filemtime($path)),
            ];
        }

        usort($documents, static fn (array $left, array $right): int => strcmp((string) $left["file"], (string) $right["file"]));

        return [
            "count" => count($documents),
            "items" => $documents,
        ];
    }

    private function repositoryFootprint(): array
    {
        $rows = [];

        foreach (["src", "bootstrap", "config", "routes", "views", "public", "tests", "docs", "scripts"] as $directory) {
            $absolute = base_path($directory);

            if (!is_dir($absolute)) {
                continue;
            }

            [$files, $bytes] = $this->countFiles($absolute);
            $rows[$directory] = [
                "files" => $files,
                "bytes" => $bytes,
            ];
        }

        return $rows;
    }

    private function bootRouter(): Router
    {
        $previousContainer = $GLOBALS["fnlla_container"] ?? null;
        $previousPhpContainer = $GLOBALS["fnlla_php_container"] ?? null;
        $container = $this->newContextContainer();
        $GLOBALS["fnlla_container"] = $container;
        $GLOBALS["fnlla_php_container"] = $container;

        try {
            $router = require base_path("bootstrap/router.php");
        } finally {
            $GLOBALS["fnlla_container"] = $previousContainer;
            $GLOBALS["fnlla_php_container"] = $previousPhpContainer;
        }

        if (!$router instanceof Router) {
            throw new \RuntimeException("Router bootstrap did not return a Router instance.");
        }

        return $router;
    }

    private function newContextContainer(): Container
    {
        $container = new Container();
        $providers = [];

        foreach ((array) config("app.providers", []) as $providerClass) {
            if (!is_string($providerClass) || !class_exists($providerClass)) {
                continue;
            }

            $provider = new $providerClass($container);

            if (!$provider instanceof ServiceProvider) {
                continue;
            }

            $provider->register();
            $providers[] = $provider;
        }

        foreach ($providers as $provider) {
            $provider->boot();
        }

        return $container;
    }

    private function artifact(string $path): array
    {
        return [
            "exists" => is_file($path),
            "path" => str_replace("\\", "/", substr($path, strlen(base_path()) + 1)),
            "bytes" => is_file($path) ? filesize($path) : 0,
            "updated_at_utc" => is_file($path) ? gmdate(DATE_ATOM, (int) filemtime($path)) : null,
        ];
    }

    private function countFiles(string $directory): array
    {
        $files = 0;
        $bytes = 0;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $item) {
            if (!$item->isFile()) {
                continue;
            }

            $files++;
            $bytes += $item->getSize();
        }

        return [$files, $bytes];
    }

    private function readFirstLine(string $path): ?string
    {
        if (!is_file($path)) {
            return null;
        }

        $contents = trim(strtok((string) file_get_contents($path), "\r\n") ?: "");

        return $contents !== "" ? $contents : null;
    }

    private function redact(mixed $value, string $key = ""): mixed
    {
        if ($this->isSensitiveKey($key)) {
            return "[redacted]";
        }

        if (is_array($value)) {
            $redacted = [];

            foreach ($value as $childKey => $childValue) {
                $redacted[$childKey] = $this->redact($childValue, is_string($childKey) ? $childKey : "");
            }

            return $redacted;
        }

        return $value;
    }

    private function isSensitiveKey(string $key): bool
    {
        if ($key === "") {
            return false;
        }

        if (in_array($key, ["supports_credentials", "key"], true)) {
            return false;
        }

        return preg_match(self::SECRET_KEY_PATTERN, $key) === 1;
    }
}
