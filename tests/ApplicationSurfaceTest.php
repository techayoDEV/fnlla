<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA TEST CASE
File: tests\ApplicationSurfaceTest.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Confirms the starter behaves like the public application base while maintenance and
  health remain linked framework capabilities.
===============================================================================
*/

namespace Fnlla\Php\Tests;

use Fnlla\Php\Application;
use Fnlla\Php\Container\Container;
use Fnlla\Php\Exceptions\ExceptionHandler;
use Fnlla\Php\Http\Request;
use PHPUnit\Framework\TestCase;

final class ApplicationSurfaceTest extends TestCase
{
    private mixed $containerBackup;
    private array $sessionBackup = [];

    protected function setUp(): void
    {
        $this->containerBackup = $GLOBALS["fnlla_container"] ?? $GLOBALS["fnlla_php_container"] ?? null;
        $this->sessionBackup = $_SESSION ?? [];
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $GLOBALS["fnlla_container"] = $this->containerBackup;
        $GLOBALS["fnlla_php_container"] = $this->containerBackup;
        $_SESSION = $this->sessionBackup;
    }

    public function testHomePageRendersStarterOwnedContent(): void
    {
        $application = $this->makeApplication();

        $response = $application->handle(Request::capture("", [
            "REQUEST_URI" => "/",
            "REQUEST_METHOD" => "GET",
        ]));

        self::assertSame(200, $response->status());
        self::assertStringContainsString("starter skeleton", $response->body());
        self::assertStringContainsString("Services", $response->body());
        self::assertStringContainsString("About", $response->body());
    }

    public function testStarterPagesAreAvailableThroughPublicRoutes(): void
    {
        $application = $this->makeApplication();

        $aboutResponse = $application->handle(Request::capture("", [
            "REQUEST_URI" => "/about",
            "REQUEST_METHOD" => "GET",
        ]));
        $servicesResponse = $application->handle(Request::capture("", [
            "REQUEST_URI" => "/services",
            "REQUEST_METHOD" => "GET",
        ]));

        self::assertSame(200, $aboutResponse->status());
        self::assertSame(200, $servicesResponse->status());
        self::assertStringContainsString("starter foundation", $aboutResponse->body());
        self::assertStringContainsString("real product or service structure", $servicesResponse->body());
    }

    public function testHealthRouteRedirectsToMaintenanceSurface(): void
    {
        $application = $this->makeApplication();

        $response = $application->handle(Request::capture("", [
            "REQUEST_URI" => "/health",
            "REQUEST_METHOD" => "GET",
        ]));

        self::assertSame(302, $response->status());
        self::assertSame("/maintenance/health", $response->headers()["Location"] ?? null);
    }

    public function testMaintenanceHealthPageIsAvailable(): void
    {
        $application = $this->makeApplication();

        $response = $application->handle(Request::capture("", [
            "REQUEST_URI" => "/maintenance/health",
            "REQUEST_METHOD" => "GET",
            "REMOTE_ADDR" => "127.0.0.1",
        ]));

        self::assertSame(200, $response->status());
        self::assertStringContainsString("Operational health should read like part of the same application shell", $response->body());
        self::assertStringContainsString("Version contract", $response->body());
    }

    public function testApiHealthReturnsStructuredJsonPayload(): void
    {
        $application = $this->makeApplication();

        $response = $application->handle(Request::capture("", [
            "REQUEST_URI" => "/api/health",
            "REQUEST_METHOD" => "GET",
            "REMOTE_ADDR" => "127.0.0.1",
            "HTTP_ACCEPT" => "application/json",
        ]));

        self::assertSame(200, $response->status());
        self::assertSame("application/json; charset=UTF-8", $response->headers()["Content-Type"] ?? null);
        self::assertStringContainsString('"name": "' . (string) config("app.name") . '"', $response->body());
        self::assertStringContainsString('"api_health": "/api/health"', $response->body());
    }

    private function makeApplication(): Application
    {
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

        return new Application($router, $container, $container->make(ExceptionHandler::class));
    }
}
