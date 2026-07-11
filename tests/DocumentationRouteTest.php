<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA TEST CASE
File: tests\DocumentationRouteTest.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Validates that the maintained documentation workspace is reachable through local app routes.
*/

namespace Fnlla\Php\Tests;

use Fnlla\Php\Application;
use Fnlla\Php\Container\Container;
use Fnlla\Php\Exceptions\ExceptionHandler;
use Fnlla\Php\Http\Request;
use PHPUnit\Framework\TestCase;

final class DocumentationRouteTest extends TestCase
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

    public function testDocsIndexRendersDocumentationHub(): void
    {
        if ($this->skipWhenDocsWorkspaceMissing()) {
            return;
        }

        $application = $this->makeApplication();

        $response = $application->handle(Request::capture("", [
            "REQUEST_URI" => "/docs",
            "REQUEST_METHOD" => "GET",
        ]));

        self::assertSame(200, $response->status());
        self::assertStringContainsString("Documentation hub", $response->body());
        self::assertStringContainsString("/docs/index.html", $response->body());
        self::assertStringContainsString("/docs/starting-a-new-project.html", $response->body());
    }

    public function testDocsOverviewPageIsServedThroughApplicationRoute(): void
    {
        if ($this->skipWhenDocsWorkspaceMissing()) {
            return;
        }

        $application = $this->makeApplication();

        $response = $application->handle(Request::capture("", [
            "REQUEST_URI" => "/docs/index.html",
            "REQUEST_METHOD" => "GET",
        ]));

        self::assertSame(200, $response->status());
        self::assertStringContainsString("FNLLA Documentation", $response->body());
        self::assertStringContainsString("/docs/assets/docs.css", $response->body());
        self::assertStringContainsString("/docs/distribution.html", $response->body());
        self::assertStringContainsString("/vendor/fnlla-runtime/assets/js/fnlla-runtime.js", $response->body());
    }

    public function testDocsStylesheetIsServedThroughApplicationRoute(): void
    {
        if ($this->skipWhenDocsWorkspaceMissing()) {
            return;
        }

        $application = $this->makeApplication();

        $response = $application->handle(Request::capture("", [
            "REQUEST_URI" => "/docs/assets/docs.css",
            "REQUEST_METHOD" => "GET",
        ]));

        self::assertSame(200, $response->status());
        self::assertSame("text/css; charset=UTF-8", $response->headers()["Content-Type"] ?? null);
        self::assertStringContainsString(".doc-wrapper", $response->body());
    }

    public function testBuildingGuideReflectsCurrentStarterControllerAndLayoutRules(): void
    {
        if ($this->skipWhenDocsWorkspaceMissing()) {
            return;
        }

        $application = $this->makeApplication();

        $response = $application->handle(Request::capture("", [
            "REQUEST_URI" => "/docs/building-with-fnlla.html",
            "REQUEST_METHOD" => "GET",
        ]));

        self::assertSame(200, $response->status());
        self::assertStringContainsString("PageController::class", $response->body());
        self::assertStringContainsString("&quot;services&quot;])-&gt;name(&quot;services&quot;);", $response->body());
        self::assertStringContainsString("views/layouts/app.php", $response->body());
        self::assertFalse(str_contains($response->body(), "HomeController::class"));
    }

    public function testFnllaRuntimeGuideShowsFullStarterShell(): void
    {
        if ($this->skipWhenDocsWorkspaceMissing()) {
            return;
        }

        $application = $this->makeApplication();

        $response = $application->handle(Request::capture("", [
            "REQUEST_URI" => "/docs/fnlla-runtime.html",
            "REQUEST_METHOD" => "GET",
        ]));

        self::assertSame(200, $response->status());
        self::assertStringContainsString("Shared starter layout", $response->body());
        self::assertStringContainsString("Starter route, controller and page body", $response->body());
        self::assertStringContainsString("&lt;header&gt;", $response->body());
        self::assertStringContainsString("&lt;main&gt;", $response->body());
        self::assertStringContainsString("&lt;footer&gt;", $response->body());
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

    private function skipWhenDocsWorkspaceMissing(): bool
    {
        if (has_local_docs_workspace()) {
            return false;
        }

        self::assertTrue(true);

        return true;
    }
}
