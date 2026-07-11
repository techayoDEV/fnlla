<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA TEST CASE
File: tests\VersionManifestTest.php
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

use Fnlla\Php\Support\VersionManifest;
use PHPUnit\Framework\TestCase;

final class VersionManifestTest extends TestCase
{
    public function testBuildRepositoryManifestReflectsCurrentRepositoryVersions(): void
    {
        $manifest = VersionManifest::buildRepositoryManifest();

        self::assertSame("FNLLA", $manifest["product"]["name"] ?? null);
        self::assertSame("fnlla", $manifest["product"]["slug"] ?? null);
        self::assertTrue((bool) preg_match('/^\d+\.\d+\.\d+$/', (string) ($manifest["product"]["version"] ?? "")));
        self::assertSame("FNLLA Runtime", $manifest["ui_runtime"]["name"] ?? null);
        self::assertTrue((bool) preg_match('/^\d+\.\d+\.\d+$/', (string) ($manifest["ui_runtime"]["vendored_version"] ?? "")));
        self::assertSame(
            $manifest["ui_runtime"]["vendored_version"] ?? null,
            $manifest["ui_runtime"]["validated_version"] ?? null
        );
    }

    public function testVersionManifestValidationPassesForMaintainedRepositoryState(): void
    {
        self::assertSame([], VersionManifest::validateRepositoryManifest());
    }
}
