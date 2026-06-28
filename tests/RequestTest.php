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
}
