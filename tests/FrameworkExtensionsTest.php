<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA PHP TEST CASE
File: tests\FrameworkExtensionsTest.php
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
use Fnlla\Php\Auth\AuthManager;
use Fnlla\Php\Auth\Authorization\Gate;
use Fnlla\Php\Auth\Middleware\Authorize;
use Fnlla\Php\Auth\UserProviderInterface;
use Fnlla\Php\Cache\CacheStoreInterface;
use Fnlla\Php\Cache\FileCacheStore;
use Fnlla\Php\Cache\RateLimiter;
use Fnlla\Php\Container\Container;
use Fnlla\Php\Exceptions\ExceptionHandler;
use Fnlla\Php\Hashing\Hasher;
use Fnlla\Php\Http\Request;
use Fnlla\Php\Http\Response;
use Fnlla\Php\Http\Resources\JsonResource;
use Fnlla\Php\Http\UploadedFile;
use Fnlla\Php\Middleware\ThrottleRequests;
use Fnlla\Php\Routing\Router;
use Fnlla\Php\Routing\UrlGenerator;
use Fnlla\Php\Session\SessionStore;
use PHPUnit\Framework\TestCase;

final class FrameworkExtensionsTest extends TestCase
{
    private string $cacheDirectory;

    protected function setUp(): void
    {
        $_SESSION = [];
        $this->cacheDirectory = storage_path("framework/cache/tests");

        if (is_dir($this->cacheDirectory)) {
            foreach (glob($this->cacheDirectory . DIRECTORY_SEPARATOR . "*.cache") ?: [] as $file) {
                unlink($file);
            }
        }
    }

    public function testGroupedNamedRoutesGenerateUrls(): void
    {
        $container = new Container();
        $router = new Router($container);
        $generator = new UrlGenerator($router);

        $router->group([
            "prefix" => "api",
            "as" => "api.",
        ], static function (Router $router): void {
            $router->get("/health", static fn (): string => "ok")->name("health");
        });

        self::assertSame("/api/health", $generator->route("api.health"));
    }

    public function testAuthorizeMiddlewareRedirectsGuests(): void
    {
        $container = new Container();
        $router = new Router($container);
        $auth = $this->makeAuthManager();
        $gate = new Gate($container, $auth);
        $gate->define("manage-admin-area", static fn (?array $user): bool => $user !== null && (($user["role"] ?? "") === "admin"));

        $container->instance(AuthManager::class, $auth);
        $container->instance(Gate::class, $gate);
        $container->instance(UrlGenerator::class, new UrlGenerator($router));
        $GLOBALS["fnlla_php_container"] = $container;

        $router->middleware("authorize", Authorize::class);
        $router->get("/", static fn (): Response => Response::html("home"))->name("home");
        $router->get("/admin", static fn (): Response => Response::html("secret"))->authorize("manage-admin-area")->name("admin");

        $response = $router->dispatch(Request::capture("", [
            "REQUEST_URI" => "/admin",
            "REQUEST_METHOD" => "GET",
        ]));

        self::assertInstanceOf(Response::class, $response);
        self::assertSame(302, $response->status());
        self::assertSame("/", $response->headers()["Location"] ?? null);
    }

    public function testThrottleMiddlewareReturnsTooManyRequests(): void
    {
        $container = new Container();
        $router = new Router($container);
        $cache = new FileCacheStore($this->cacheDirectory);

        $container->instance(CacheStoreInterface::class, $cache);
        $container->instance(RateLimiter::class, new RateLimiter($cache));
        $router->middleware("throttle", ThrottleRequests::class);
        $router->get("/limited", static fn (): Response => Response::html("ok"))->throttle(1, 1);

        $first = $router->dispatch(Request::capture("", [
            "REQUEST_URI" => "/limited",
            "REQUEST_METHOD" => "GET",
            "REMOTE_ADDR" => "127.0.0.1",
        ]));
        $second = $router->dispatch(Request::capture("", [
            "REQUEST_URI" => "/limited",
            "REQUEST_METHOD" => "GET",
            "REMOTE_ADDR" => "127.0.0.1",
        ]));

        self::assertInstanceOf(Response::class, $first);
        self::assertSame(200, $first->status());
        self::assertInstanceOf(Response::class, $second);
        self::assertSame(429, $second->status());
    }

    public function testRequestCaptureNormalizesUploadedFiles(): void
    {
        $temporaryFile = tempnam(sys_get_temp_dir(), "fnl");
        self::assertIsString($temporaryFile);
        file_put_contents($temporaryFile, "content");

        $request = Request::capture("", [
            "REQUEST_URI" => "/upload",
            "REQUEST_METHOD" => "POST",
        ], [], [], [], [
            "avatar" => [
                "name" => "avatar.png",
                "type" => "image/png",
                "tmp_name" => $temporaryFile,
                "error" => UPLOAD_ERR_OK,
                "size" => 7,
            ],
        ]);

        $file = $request->file("avatar");

        self::assertInstanceOf(UploadedFile::class, $file);
        self::assertSame("avatar.png", $file->originalName());

        unlink($temporaryFile);
    }

    public function testApplicationNormalizesJsonResources(): void
    {
        $container = new Container();
        $router = new Router($container);
        $router->get("/resource", static fn (): JsonResource => new class(["status" => "ok"]) extends JsonResource {
            public function toArray(): array
            {
                return [
                    "meta" => $this->resource,
                ];
            }
        });

        $application = new Application($router, $container, new ExceptionHandler());
        $response = $application->handle(Request::capture("", [
            "REQUEST_URI" => "/resource",
            "REQUEST_METHOD" => "GET",
        ]));

        self::assertSame(200, $response->status());
        self::assertStringContainsString('"status": "ok"', $response->body());
    }

    private function makeAuthManager(): AuthManager
    {
        $provider = new class implements UserProviderInterface {
            public function findById(string|int $id): ?array
            {
                if ($id !== 1) {
                    return null;
                }

                return [
                    "id" => 1,
                    "email" => "admin@example.com",
                    "role" => "admin",
                ];
            }

            public function findByCredentials(array $credentials): ?array
            {
                return null;
            }
        };

        return new AuthManager(new SessionStore(), $provider, new Hasher());
    }
}
