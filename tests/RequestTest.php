<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA PHP TEST CASE
File: tests\RequestTest.php
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

use Fnlla\Php\Http\Request;
use PHPUnit\Framework\TestCase;

final class RequestTest extends TestCase
{
    private mixed $trustedProxiesBackup;

    protected function setUp(): void
    {
        $this->trustedProxiesBackup = $_ENV["TRUSTED_PROXIES"] ?? null;
    }

    protected function tearDown(): void
    {
        if ($this->trustedProxiesBackup === null) {
            unset($_ENV["TRUSTED_PROXIES"], $_SERVER["TRUSTED_PROXIES"]);
            putenv("TRUSTED_PROXIES");
            return;
        }

        $_ENV["TRUSTED_PROXIES"] = $this->trustedProxiesBackup;
        $_SERVER["TRUSTED_PROXIES"] = (string) $this->trustedProxiesBackup;
        putenv("TRUSTED_PROXIES=" . (string) $this->trustedProxiesBackup);
    }

    public function testCaptureSupportsJsonHeadersAndMethodOverride(): void
    {
        $request = Request::capture(
            '{"name":"Ada","role":"admin"}',
            [
                "REQUEST_URI" => "/api/users/7?filter=active",
                "REQUEST_METHOD" => "POST",
                "CONTENT_TYPE" => "application/json",
                "HTTP_X_HTTP_METHOD_OVERRIDE" => "PATCH",
                "HTTP_ACCEPT" => "application/json",
                "HTTP_AUTHORIZATION" => "Bearer token-123",
                "REMOTE_ADDR" => "127.0.0.1",
            ],
            [
                "filter" => "active",
            ],
            [],
            [
                "theme" => "dark",
            ],
            []
        );

        self::assertSame("PATCH", $request->method());
        self::assertSame("POST", $request->originalMethod());
        self::assertSame("/api/users/7", $request->path());
        self::assertSame("Ada", $request->input("name"));
        self::assertSame("active", $request->query("filter"));
        self::assertSame("admin", $request->json("role"));
        self::assertTrue($request->isJson());
        self::assertTrue($request->expectsJson());
        self::assertSame("dark", $request->cookie("theme"));
        self::assertSame("token-123", $request->bearerToken());
        self::assertSame("127.0.0.1", $request->ip());
        self::assertNotSame("", $request->requestId());
    }

    public function testIpIgnoresForwardedHeadersUnlessRemoteProxyIsTrusted(): void
    {
        unset($_ENV["TRUSTED_PROXIES"], $_SERVER["TRUSTED_PROXIES"]);
        putenv("TRUSTED_PROXIES");

        $untrustedProxyRequest = Request::capture("", [
            "REQUEST_URI" => "/status",
            "REQUEST_METHOD" => "GET",
            "REMOTE_ADDR" => "198.51.100.10",
            "HTTP_X_FORWARDED_FOR" => "127.0.0.1, 198.51.100.10",
        ]);

        self::assertSame("198.51.100.10", $untrustedProxyRequest->ip());

        $_ENV["TRUSTED_PROXIES"] = "10.0.0.10";
        $_SERVER["TRUSTED_PROXIES"] = "10.0.0.10";
        putenv("TRUSTED_PROXIES=10.0.0.10");

        $trustedProxyRequest = Request::capture("", [
            "REQUEST_URI" => "/status",
            "REQUEST_METHOD" => "GET",
            "REMOTE_ADDR" => "10.0.0.10",
            "HTTP_X_FORWARDED_FOR" => "203.0.113.24, 10.0.0.10",
        ]);

        self::assertSame("203.0.113.24", $trustedProxyRequest->ip());
    }
}
