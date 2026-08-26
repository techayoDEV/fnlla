<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA TEST CASE
File: tests\WorkspaceChromeTest.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

Purpose:
- Verifies neutral workspace chrome data builders for downstream dashboards.
*/

namespace Fnlla\Php\Tests;

use Fnlla\Php\Support\WorkspaceChrome;
use PHPUnit\Framework\TestCase;

final class WorkspaceChromeTest extends TestCase
{
    public function testBuildsActiveNavigationItems(): void
    {
        $item = WorkspaceChrome::navItem("settings", "Settings", "/settings", "settings");

        self::assertSame("settings", $item["key"]);
        self::assertSame("Settings", $item["label"]);
        self::assertSame("/settings", $item["href"]);
        self::assertTrue($item["active"]);
    }

    public function testBuildsStatusCardsWithSafeTone(): void
    {
        $card = WorkspaceChrome::statusCard("jobs", "Jobs", 12, "unknown");

        self::assertSame("jobs", $card["key"]);
        self::assertSame("12", $card["value"]);
        self::assertSame("neutral", $card["tone"]);
    }

    public function testBuildsIdentityInitials(): void
    {
        $identity = WorkspaceChrome::identity("Acme Owner", "owner@example.test");

        self::assertSame("Acme Owner", $identity["name"]);
        self::assertSame("owner@example.test", $identity["email"]);
        self::assertSame("AO", $identity["avatar"]);
    }
}
