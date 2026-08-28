<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA SUPPORT SOURCE
File: src\Support\ApplicationProbe.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

Purpose:
- Boots the local FNLLA HTTP application in-process for acceptance and
  performance probes without requiring a web server.
*/

namespace Fnlla\Php\Support;

use Fnlla\Php\Application;
use Fnlla\Php\Container\Container;
use Fnlla\Php\Exceptions\ExceptionHandler;
use Fnlla\Php\Http\Request;
use Fnlla\Php\Http\Response;

final class ApplicationProbe
{
    private ?Application $application = null;

    public function get(string $uri, array $server = []): Response
    {
        return $this->application()->handle(Request::capture("", array_merge([
            "REQUEST_URI" => $uri,
            "REQUEST_METHOD" => "GET",
            "HTTP_ACCEPT" => "text/html",
            "REMOTE_ADDR" => "127.0.0.1",
            "HTTP_HOST" => "localhost",
            "HTTPS" => "off",
        ], $server)));
    }

    public function measure(array $requests, int $iterations): array
    {
        $iterations = max(1, $iterations);
        $containerBackup = $GLOBALS["fnlla_container"] ?? $GLOBALS["fnlla_php_container"] ?? null;
        $sessionBackup = $_SESSION ?? [];
        $rows = [];

        try {
            $_SESSION = [];

            foreach ($requests as $name => $definition) {
                $uri = (string) ($definition["uri"] ?? "/");
                $server = (array) ($definition["server"] ?? []);
                $expectedStatuses = array_map("intval", (array) ($definition["expected_statuses"] ?? [200]));
                $times = [];
                $statuses = [];

                try {
                    for ($index = 0; $index < $iterations; $index++) {
                        $started = microtime(true);
                        $response = $this->get($uri, $server);
                        $times[] = (microtime(true) - $started) * 1000;
                        $statuses[] = $response->status();
                    }
                } catch (\Throwable $exception) {
                    $rows[$name] = [
                        "ok" => false,
                        "uri" => $uri,
                        "error" => $exception->getMessage(),
                    ];
                    continue;
                }

                sort($times);
                $unexpectedStatuses = array_values(array_diff(array_unique($statuses), $expectedStatuses));
                $rows[$name] = [
                    "ok" => $unexpectedStatuses === [],
                    "uri" => $uri,
                    "statuses" => array_values(array_unique($statuses)),
                    "expected_statuses" => $expectedStatuses,
                    "avg_ms" => round(array_sum($times) / count($times), 3),
                    "p50_ms" => round($this->percentile($times, 50), 3),
                    "p95_ms" => round($this->percentile($times, 95), 3),
                    "min_ms" => round(min($times), 3),
                    "max_ms" => round(max($times), 3),
                ];
            }
        } finally {
            $GLOBALS["fnlla_container"] = $containerBackup;
            $GLOBALS["fnlla_php_container"] = $containerBackup;
            $_SESSION = $sessionBackup;
        }

        return $rows;
    }

    private function application(): Application
    {
        if ($this->application instanceof Application) {
            return $this->application;
        }

        $container = new Container();
        $providers = [];

        foreach ((array) config("app.providers", []) as $providerClass) {
            $provider = new $providerClass($container);
            $provider->register();
            $providers[] = $provider;
        }

        foreach ($providers as $provider) {
            $provider->boot();
        }

        $GLOBALS["fnlla_container"] = $container;
        $GLOBALS["fnlla_php_container"] = $container;
        $router = (static function (Container $container) {
            return require base_path("bootstrap/router.php");
        })($container);

        $this->application = new Application($router, $container, $container->make(ExceptionHandler::class));
        $this->application->middleware(["cors", "maintenance"]);

        return $this->application;
    }

    private function percentile(array $sortedTimes, int $percentile): float
    {
        if ($sortedTimes === []) {
            return 0.0;
        }

        $index = (int) ceil(($percentile / 100) * count($sortedTimes)) - 1;
        $index = max(0, min(count($sortedTimes) - 1, $index));

        return (float) $sortedTimes[$index];
    }
}
