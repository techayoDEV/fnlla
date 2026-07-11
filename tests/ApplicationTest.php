<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA TEST CASE
File: tests\ApplicationTest.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Validates maintained framework behavior inside the repository-local test harness.
*/

namespace Fnlla\Php\Tests;

use Fnlla\Php\Application;
use Fnlla\Php\Container\Container;
use Fnlla\Php\Exceptions\ExceptionHandler;
use Fnlla\Php\Http\Request;
use Fnlla\Php\Http\Response;
use Fnlla\Php\Middleware\HandleCors;
use Fnlla\Php\Middleware\MiddlewareInterface;
use Fnlla\Php\Routing\Router;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ApplicationTest extends TestCase
{
    private string $logPath;

    protected function setUp(): void
    {
        $this->logPath = storage_path("logs/test.log");
        if (is_file($this->logPath)) {
            unlink($this->logPath);
        }

        config_set("cors", [
            "allowed_origins" => [],
            "allowed_methods" => ["GET", "POST", "PUT", "PATCH", "DELETE", "OPTIONS"],
            "allowed_headers" => ["Content-Type", "Authorization", "X-Requested-With", "X-Request-Id", "X-CSRF-TOKEN"],
            "supports_credentials" => false,
            "max_age" => 3600,
        ]);
    }

    public function testHeadRequestsKeepStatusAndHeadersButDropBody(): void
    {
        $container = new Container();
        $router = new Router($container);
        $router->get("/status", static fn (): Response => Response::html("ok"));

        $application = new Application($router, $container, new ExceptionHandler());
        $response = $application->handle(Request::capture("", [
            "REQUEST_URI" => "/status",
            "REQUEST_METHOD" => "HEAD",
        ]));

        self::assertSame(200, $response->status());
        self::assertSame("", $response->body());
        self::assertArrayHasKey("X-Request-Id", $response->headers());
        self::assertArrayHasKey("X-Content-Type-Options", $response->headers());
    }

    public function testApiExceptionsReturnJsonAndAreLogged(): void
    {
        $container = new Container();
        $router = new Router($container);
        $router->get("/api/fail", static function (): never {
            throw new RuntimeException("Boom");
        });

        $application = new Application($router, $container, new ExceptionHandler());
        $response = $application->handle(Request::capture("", [
            "REQUEST_URI" => "/api/fail",
            "REQUEST_METHOD" => "GET",
            "HTTP_ACCEPT" => "application/json",
        ]));

        self::assertSame(500, $response->status());
        self::assertStringContainsString('"error": "Server Error"', $response->body());
        self::assertStringContainsString('"request_id":', $response->body());
        self::assertFileExists($this->logPath);
        self::assertStringContainsString("Boom", (string) file_get_contents($this->logPath));
    }

    public function testGlobalMiddlewareAliasWrapsApplicationRequests(): void
    {
        $container = new Container();
        $container->singleton("test.middleware", static fn (): MiddlewareInterface => new class implements MiddlewareInterface {
            public function handle(Request $request, callable $next): mixed
            {
                $response = $next($request);

                return $response instanceof Response
                    ? $response->withHeader("X-Global-Middleware", "applied")
                    : $response;
            }
        });

        $router = new Router($container);
        $router->middleware("test", "test.middleware");
        $router->get("/wrapped", static fn (): Response => Response::html("ok"));

        $application = new Application($router, $container, new ExceptionHandler());
        $application->middleware("test");

        $response = $application->handle(Request::capture("", [
            "REQUEST_URI" => "/wrapped",
            "REQUEST_METHOD" => "GET",
        ]));

        self::assertSame(200, $response->status());
        self::assertSame("applied", $response->headers()["X-Global-Middleware"] ?? null);
    }

    public function testCorsMiddlewareFallsThroughToRouterForNonPreflightOptions(): void
    {
        $container = new Container();
        $router = new Router($container);
        $router->middleware("cors", HandleCors::class);
        $router->get("/api/health", static fn (): Response => Response::html("ok"));

        $application = new Application($router, $container, new ExceptionHandler());
        $application->middleware("cors");

        $response = $application->handle(Request::capture("", [
            "REQUEST_URI" => "/api/health",
            "REQUEST_METHOD" => "OPTIONS",
        ]));

        self::assertSame(204, $response->status());
        self::assertSame("GET, HEAD, OPTIONS", $response->headers()["Allow"] ?? null);
        self::assertArrayNotHasKey("Access-Control-Allow-Origin", $response->headers());
    }

    public function testCorsMiddlewareAddsHeadersOnlyForConfiguredOrigins(): void
    {
        config_set("cors", [
            "allowed_origins" => ["https://portal.example.test"],
            "allowed_methods" => ["GET", "POST", "OPTIONS"],
            "allowed_headers" => ["Content-Type", "X-Requested-With"],
            "supports_credentials" => true,
            "max_age" => 600,
        ]);

        $container = new Container();
        $router = new Router($container);
        $router->middleware("cors", HandleCors::class);
        $router->get("/status", static fn (): Response => Response::html("ok"));

        $application = new Application($router, $container, new ExceptionHandler());
        $application->middleware("cors");

        $allowed = $application->handle(Request::capture("", [
            "REQUEST_URI" => "/status",
            "REQUEST_METHOD" => "GET",
            "HTTP_ORIGIN" => "https://portal.example.test",
        ]));

        $blocked = $application->handle(Request::capture("", [
            "REQUEST_URI" => "/status",
            "REQUEST_METHOD" => "GET",
            "HTTP_ORIGIN" => "https://evil.example.test",
        ]));

        self::assertSame("https://portal.example.test", $allowed->headers()["Access-Control-Allow-Origin"] ?? null);
        self::assertSame("true", $allowed->headers()["Access-Control-Allow-Credentials"] ?? null);
        self::assertSame("Origin", $allowed->headers()["Vary"] ?? null);
        self::assertArrayNotHasKey("Access-Control-Allow-Origin", $blocked->headers());
    }
}
