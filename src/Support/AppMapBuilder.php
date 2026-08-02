<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA SUPPORT SOURCE
File: src\Support\AppMapBuilder.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

Purpose:
- Builds a machine-readable application map for onboarding, audits, AI review
  and major-release migration planning.
*/

namespace Fnlla\Php\Support;

use Fnlla\Php\Container\Container;
use Fnlla\Php\Routing\RouteDefinition;
use Fnlla\Php\Routing\Router;
use ReflectionClass;

final class AppMapBuilder
{
    public function build(): array
    {
        $routes = $this->routes();

        return [
            "schema" => "fnlla.app_map.v1",
            "generated_at_utc" => gmdate(DATE_ATOM),
            "application" => [
                "name" => (string) config("app.name", "FNLLA"),
                "environment" => app_environment(),
                "version" => $this->readFirstLine(base_path("VERSION")),
                "debug" => app_debug(),
            ],
            "routes" => [
                "count" => count($routes),
                "items" => $routes,
            ],
            "middleware" => $this->middlewareSummary($routes),
            "configuration" => $this->configurationFiles(),
            "database" => [
                "migrations" => $this->fileIndex("database/migrations", "*.php"),
                "seeders" => $this->fileIndex("database/seeders", "*.php"),
                "factories" => $this->fileIndex("database/factories", "*.php"),
            ],
            "views" => $this->fileIndex("views", "*.php"),
            "public_assets" => $this->publicAssets(),
            "docs" => $this->fileIndex("docs", "*.{md,html}"),
        ];
    }

    public function writeJson(array $map, string $path): void
    {
        $this->ensureDirectory($path);
        file_put_contents($path, json_encode($map, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL, LOCK_EX);
    }

    public function writeHtml(array $map, string $path): void
    {
        $rows = "";

        foreach ((array) ($map["routes"]["items"] ?? []) as $route) {
            $rows .= "<tr><td>" . h((string) ($route["method"] ?? "")) . "</td><td>" . h((string) ($route["path"] ?? "")) . "</td><td>" . h((string) ($route["name"] ?? "-")) . "</td><td>" . h((string) ($route["handler"]["label"] ?? "-")) . "</td><td>" . h(implode(", ", (array) ($route["middleware"] ?? []))) . "</td></tr>";
        }

        $html = "<!doctype html><html lang=\"en\"><head><meta charset=\"utf-8\"><title>FNLLA App Map</title><style>body{font-family:Arial,sans-serif;margin:32px;color:#17202a}table{border-collapse:collapse;width:100%}td,th{border:1px solid #d8dee4;padding:8px;text-align:left}th{background:#f6f8fa}.muted{color:#57606a}</style></head><body>"
            . "<h1>FNLLA App Map</h1><p class=\"muted\">Generated " . h((string) ($map["generated_at_utc"] ?? "")) . "</p>"
            . "<h2>Routes</h2><table><thead><tr><th>Method</th><th>Path</th><th>Name</th><th>Handler</th><th>Middleware</th></tr></thead><tbody>{$rows}</tbody></table>"
            . "</body></html>";

        $this->ensureDirectory($path);
        file_put_contents($path, $html . PHP_EOL, LOCK_EX);
    }

    private function routes(): array
    {
        $router = $this->bootRouter();
        $rows = [];

        foreach ($router->getRoutes() as $method => $routesByMethod) {
            foreach ($routesByMethod as $route) {
                /** @var RouteDefinition $definition */
                $definition = $route["definition"];
                $handler = $this->handler($definition->handler());

                $rows[] = [
                    "method" => (string) $method,
                    "path" => $definition->path(),
                    "name" => $definition->routeName(),
                    "middleware" => $definition->middlewareStack(),
                    "dynamic_parameters" => array_values((array) ($route["parameters"] ?? [])),
                    "handler" => $handler,
                    "view_references" => $this->viewReferences(
                        (string) ($handler["file"] ?? ""),
                        (int) ($handler["line_start"] ?? 0),
                        (int) ($handler["line_end"] ?? 0)
                    ),
                ];
            }
        }

        usort($rows, static fn (array $left, array $right): int => [$left["path"], $left["method"]] <=> [$right["path"], $right["method"]]);

        return $rows;
    }

    private function handler(mixed $handler): array
    {
        if (is_array($handler) && is_string($handler[0] ?? null) && is_string($handler[1] ?? null)) {
            $file = null;
            $lineStart = null;
            $lineEnd = null;

            if (class_exists($handler[0])) {
                $reflectionClass = new ReflectionClass($handler[0]);
                $file = $reflectionClass->getFileName() ?: null;

                if ($reflectionClass->hasMethod($handler[1])) {
                    $reflectionMethod = $reflectionClass->getMethod($handler[1]);
                    $lineStart = $reflectionMethod->getStartLine();
                    $lineEnd = $reflectionMethod->getEndLine();
                }
            }

            return [
                "type" => "controller",
                "class" => $handler[0],
                "method" => $handler[1],
                "label" => $handler[0] . "@" . $handler[1],
                "file" => is_string($file) ? $this->relativePath($file) : null,
                "line_start" => $lineStart,
                "line_end" => $lineEnd,
            ];
        }

        return [
            "type" => "callable",
            "label" => "non-cacheable callable",
            "file" => null,
        ];
    }

    private function viewReferences(string $relativeFile, int $lineStart = 0, int $lineEnd = 0): array
    {
        if ($relativeFile === "") {
            return [];
        }

        $path = base_path($relativeFile);

        if (!is_file($path)) {
            return [];
        }

        $contents = (string) file_get_contents($path);

        if ($lineStart > 0 && $lineEnd >= $lineStart) {
            $lines = file($path);

            if (is_array($lines)) {
                $contents = implode("", array_slice($lines, $lineStart - 1, ($lineEnd - $lineStart) + 1));
            }
        }

        preg_match_all('/(?:view|View::render)\(\s*[\"\']([^\"\']+)[\"\']/', $contents, $matches);
        $views = array_values(array_unique($matches[1] ?? []));
        sort($views);

        return $views;
    }

    private function middlewareSummary(array $routes): array
    {
        $summary = [];

        foreach ($routes as $route) {
            foreach ((array) ($route["middleware"] ?? []) as $middleware) {
                $summary[$middleware] = ($summary[$middleware] ?? 0) + 1;
            }
        }

        ksort($summary);

        return $summary;
    }

    private function configurationFiles(): array
    {
        $files = $this->fileIndex("config", "*.php");

        return array_map(static function (array $file): array {
            $file["key"] = basename((string) $file["file"], ".php");
            return $file;
        }, $files);
    }

    private function publicAssets(): array
    {
        return array_values(array_filter(
            $this->fileIndex("public", "*"),
            static fn (array $file): bool => !str_ends_with((string) $file["file"], ".php")
        ));
    }

    private function fileIndex(string $directory, string $pattern): array
    {
        $root = base_path($directory);

        if (!is_dir($root)) {
            return [];
        }

        $files = glob($root . DIRECTORY_SEPARATOR . "**" . DIRECTORY_SEPARATOR . $pattern, GLOB_BRACE) ?: [];
        $topLevel = glob($root . DIRECTORY_SEPARATOR . $pattern, GLOB_BRACE) ?: [];
        $files = array_values(array_unique(array_merge($files, $topLevel)));
        $rows = [];

        foreach ($files as $file) {
            if (!is_file($file)) {
                continue;
            }

            $rows[] = [
                "file" => $this->relativePath($file),
                "bytes" => filesize($file),
                "updated_at_utc" => gmdate(DATE_ATOM, (int) filemtime($file)),
            ];
        }

        usort($rows, static fn (array $left, array $right): int => strcmp((string) $left["file"], (string) $right["file"]));

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

    private function readFirstLine(string $path): ?string
    {
        if (!is_file($path)) {
            return null;
        }

        $contents = trim(strtok((string) file_get_contents($path), "\r\n") ?: "");

        return $contents !== "" ? $contents : null;
    }

    private function relativePath(string $path): string
    {
        return str_replace("\\", "/", substr($path, strlen(base_path()) + 1));
    }

    private function ensureDirectory(string $path): void
    {
        $directory = dirname($path);

        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }
    }
}
