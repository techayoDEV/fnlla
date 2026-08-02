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
- Validates maintained framework behaviour inside the repository-local test harness.
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
        self::assertSame("Integrated FNLLA UI surface", $manifest["ui_runtime"]["name"] ?? null);
        self::assertTrue((bool) preg_match('/^\d+\.\d+\.\d+$/', (string) ($manifest["ui_runtime"]["version"] ?? "")));
        self::assertSame(
            $manifest["product"]["version"] ?? null,
            $manifest["ui_runtime"]["version"] ?? null
        );
    }

    public function testVersionManifestValidationPassesForMaintainedRepositoryState(): void
    {
        self::assertSame([], VersionManifest::validateRepositoryManifest());
    }

    public function testBuildClaimedProjectManifestRecordsOwnershipAndRuntimeCreator(): void
    {
        $manifest = VersionManifest::buildClaimedProjectManifest([
            "id" => "ACME_PORTAL",
            "product" => "Acme Portal",
            "slug" => "acme-portal",
            "summary" => "Acme Portal built on FNLLA.",
            "owner" => "Acme LTD",
            "funder" => "Acme LTD",
            "client" => "Acme LTD",
            "system_owner" => "Acme LTD",
            "developer" => "Delivery LTD",
            "maintainer" => "Care LTD",
            "runtime" => "FNLLA",
            "runtime_creator" => "TechAyo LTD (techayo.co.uk)",
        ]);

        self::assertSame(2, $manifest["schema_version"] ?? null);
        self::assertSame("claimed_project", $manifest["manifest_type"] ?? null);
        self::assertSame("ACME_PORTAL", $manifest["product"]["identifier"] ?? null);
        self::assertSame("Acme LTD", $manifest["product"]["owner"]["name"] ?? null);
        self::assertSame("Delivery LTD", $manifest["product"]["developer"]["name"] ?? null);
        self::assertSame("Care LTD", $manifest["product"]["maintenance_provider"]["name"] ?? null);
        self::assertSame("FNLLA", $manifest["framework"]["name"] ?? null);
        self::assertSame("TechAyo LTD (techayo.co.uk)", $manifest["framework"]["creator"] ?? null);
    }
}
