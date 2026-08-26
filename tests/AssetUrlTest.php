<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA TEST CASE
File: tests\AssetUrlTest.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Confirms browser asset URLs stay portable across local ports and production
  domains while still allowing an explicit static asset origin.
===============================================================================
*/

namespace Fnlla\Php\Tests;

use PHPUnit\Framework\TestCase;

final class AssetUrlTest extends TestCase
{
    private mixed $appConfigBackup;

    protected function setUp(): void
    {
        $this->appConfigBackup = config("app");
    }

    protected function tearDown(): void
    {
        config_set("app", $this->appConfigBackup);
    }

    public function testAssetUrlsDefaultToRootRelativePaths(): void
    {
        config_set("app.base_url", "https://fnlla.example.test");
        config_set("app.asset_url", "");

        $assetUrl = asset("assets/app.css");

        self::assertTrue(str_starts_with($assetUrl, "/assets/app.css?v="));
        self::assertStringNotContainsString("https://fnlla.example.test", $assetUrl);
    }

    public function testAssetUrlsCanUseConfiguredAssetOrigin(): void
    {
        config_set("app.base_url", "https://fnlla.example.test");
        config_set("app.asset_url", "https://static.example.test");

        self::assertTrue(str_starts_with(
            asset("assets/app.css"),
            "https://static.example.test/assets/app.css?v="
        ));
    }
}
