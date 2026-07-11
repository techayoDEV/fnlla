<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA TEST CASE
File: tests\PublicRouterTest.php
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

use PHPUnit\Framework\TestCase;

final class PublicRouterTest extends TestCase
{
    private array $serverBackup = [];

    protected function setUp(): void
    {
        $this->serverBackup = $_SERVER;
        http_response_code(200);
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->serverBackup;
        http_response_code(200);
    }

    public function testRouterDoesNotServeDotfilesLocally(): void
    {
        $_SERVER["REQUEST_URI"] = "/.htaccess";

        ob_start();
        $result = require base_path("public/router.php");
        $output = (string) ob_get_clean();

        self::assertTrue($result);
        self::assertSame(404, http_response_code());
        self::assertSame("Not Found", $output);
    }

    public function testRouterStillPassesThroughPublicStaticFiles(): void
    {
        $_SERVER["REQUEST_URI"] = "/assets/app.css";

        $result = require base_path("public/router.php");

        self::assertFalse($result);
    }
}
