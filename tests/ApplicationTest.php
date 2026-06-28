<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA PHP TEST CASE
File: tests\ApplicationTest.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA PHP is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA PHP framework released under the MIT License and its related delivery scripts, tests,
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
}
