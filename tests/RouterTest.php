<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA TEST CASE
File: tests\RouterTest.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Validates maintained framework behaviour inside the repository-local test harness.
*/

namespace Fnlla\Php\Tests;

use Fnlla\Php\Container\Container;
use Fnlla\Php\Http\Request;
use Fnlla\Php\Http\Response;
use Fnlla\Php\Middleware\MiddlewareInterface;
use Fnlla\Php\Routing\Router;
use PHPUnit\Framework\TestCase;

final class RouterTest extends TestCase
{
    public function testDispatchesDynamicRouteParametersIntoRequestAndHandler(): void
    {
        $router = new Router(new Container());
        $router->get("/projects/{project}/versions/{version}", static function (Request $request, string $project, string $version): array {
            return [
                "from_request" => $request->routeParams(),
                "project" => $project,
                "version" => $version,
            ];
        });

        $result = $router->dispatch(Request::capture("", [
            "REQUEST_URI" => "/projects/core/versions/2",
            "REQUEST_METHOD" => "GET",
        ]));

        self::assertSame([
            "from_request" => [
                "project" => "core",
                "version" => "2",
            ],
            "project" => "core",
            "version" => "2",
        ], $result);
    }

    public function testOptionsReturnsAllowHeaderForMatchingRoute(): void
    {
        $router = new Router(new Container());
        $router->get("/api/health", static fn (): string => "ok");

        $response = $router->dispatch(Request::capture("", [
            "REQUEST_URI" => "/api/health",
            "REQUEST_METHOD" => "OPTIONS",
        ]));

        self::assertInstanceOf(Response::class, $response);
        self::assertSame(204, $response->status());
        self::assertSame("GET, HEAD, OPTIONS", $response->headers()["Allow"] ?? null);
    }

    public function testStaticRouteIndexPreservesFirstRegisteredMatch(): void
    {
        $router = new Router(new Container());
        $router->get("/indexed", static fn (): string => "first");
        $router->get("/indexed", static fn (): string => "second");

        $result = $router->dispatch(Request::capture("", [
            "REQUEST_URI" => "/indexed",
            "REQUEST_METHOD" => "GET",
        ]));

        self::assertSame("first", $result);
    }

    public function testRouteMiddlewareAliasWrapsHandler(): void
    {
        $container = new Container();
        $container->singleton("test.middleware", static fn (): MiddlewareInterface => new class implements MiddlewareInterface {
            public function handle(Request $request, callable $next): mixed
            {
                $response = $next($request);

                return $response instanceof Response
                    ? $response->withHeader("X-Middleware", "applied")
                    : $response;
            }
        });

        $router = new Router($container);
        $router->middleware("test", "test.middleware");
        $router->get("/wrapped", static fn (): Response => Response::html("ok"))->middleware("test");

        $response = $router->dispatch(Request::capture("", [
            "REQUEST_URI" => "/wrapped",
            "REQUEST_METHOD" => "GET",
        ]));

        self::assertInstanceOf(Response::class, $response);
        self::assertSame("applied", $response->headers()["X-Middleware"] ?? null);
    }

    public function testRouteCacheExportsAndReloadsControllerRoutes(): void
    {
        $container = new Container();
        $router = new Router($container);
        $router->get("/cached/{name}", [RouterCacheFixtureController::class, "show"])->name("cached.show");

        $cachedRoutes = $router->exportCache();
        $loadedRouter = new Router($container);
        $loadedRouter->loadCachedRoutes($cachedRoutes);

        $result = $loadedRouter->dispatch(Request::capture("", [
            "REQUEST_URI" => "/cached/fnlla",
            "REQUEST_METHOD" => "GET",
        ]));

        self::assertSame("cached.show", $loadedRouter->routeByName("cached.show")?->routeName());
        self::assertSame(["name" => "fnlla"], $result);
    }

    public function testRouteCacheRejectsClosureHandlers(): void
    {
        $router = new Router(new Container());
        $router->get("/closure", static fn (): string => "not-cacheable");

        $this->expectException(\RuntimeException::class);
        $router->exportCache();
    }
}

final class RouterCacheFixtureController
{
    public function show(Request $request, string $name): array
    {
        return ["name" => $name];
    }
}
