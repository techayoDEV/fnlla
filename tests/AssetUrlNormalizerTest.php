<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA TEST CASE
File: tests\AssetUrlNormalizerTest.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

Purpose:
- Verifies portable asset URL normalization for downstream domain moves.
*/

namespace Fnlla\Php\Tests;

use Fnlla\Php\Support\AssetUrlNormalizer;
use PHPUnit\Framework\TestCase;

final class AssetUrlNormalizerTest extends TestCase
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

    public function testKnownOriginsNormalizeToCurrentAssetHelper(): void
    {
        config_set("app.base_url", "https://old.example.test");
        config_set("app.asset_url", "");

        $url = (new AssetUrlNormalizer())->asset("https://old.example.test/assets/app.css");

        self::assertTrue(str_starts_with($url, "/assets/app.css?v="));
    }

    public function testConfiguredAssetOriginIsAppliedAfterNormalization(): void
    {
        config_set("app.base_url", "https://www.example.test");
        config_set("app.asset_url", "https://static.example.test");

        $url = (new AssetUrlNormalizer())->asset("/assets/app.css");

        self::assertTrue(str_starts_with($url, "https://static.example.test/assets/app.css?v="));
    }

    public function testSrcsetNormalizesEachCandidate(): void
    {
        config_set("app.base_url", "https://old.example.test");
        config_set("app.asset_url", "");

        $srcset = (new AssetUrlNormalizer())->srcset("https://old.example.test/assets/app.css 1x, /vendor/fnlla-runtime/assets/js/fnlla-runtime.js 2x");

        self::assertStringContainsString("/assets/app.css?v=", $srcset);
        self::assertStringContainsString(" 1x", $srcset);
        self::assertStringContainsString("/vendor/fnlla-runtime/assets/js/fnlla-runtime.js?v=", $srcset);
        self::assertStringContainsString(" 2x", $srcset);
    }
}
