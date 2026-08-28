<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA TEST CASE
File: tests\ReleaseWorkflowTest.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Validates release workflow guardrails that keep FNLLA publication checks
  aligned with repository history and version metadata tooling.
*/

namespace Fnlla\Php\Tests;

use PHPUnit\Framework\TestCase;

final class ReleaseWorkflowTest extends TestCase
{
    public function testReleaseGateFetchesFullGitHistoryForHistoryDependentTests(): void
    {
        $workflow = (string) file_get_contents(base_path(".github/workflows/fnlla-release-gate.yml"));

        self::assertStringContainsString("uses: actions/checkout@v5", $workflow);
        self::assertStringContainsString("fetch-depth: 0", $workflow);
        self::assertStringContainsString("composer run test -- --suite fast", $workflow);
        self::assertStringContainsString("composer run lint", $workflow);
        self::assertStringContainsString("php ./scripts/build-docs.php --check", $workflow);
        self::assertStringContainsString("php ./fnlla release:prepare --skip-tests", $workflow);
    }

    public function testTagReleaseGateAttachesSupplyChainAssetsToGitHubRelease(): void
    {
        $workflow = (string) file_get_contents(base_path(".github/workflows/fnlla-release-gate.yml"));

        self::assertStringContainsString("release-assets:", $workflow);
        self::assertStringContainsString("gh release upload", $workflow);
        self::assertStringContainsString("dist/release/fnlla-sbom.cdx.json", $workflow);
        self::assertStringContainsString("dist/release/SHA256SUMS", $workflow);
        self::assertStringContainsString("dist/release/fnlla-release-manifest.json", $workflow);
        self::assertStringContainsString("FNLLA_RELEASE_SIGNING_KEY", $workflow);
    }

    public function testMaintainerLauncherExposesVersionSetWorkflow(): void
    {
        $launcher = (string) file_get_contents(base_path("fnlla"));

        self::assertStringContainsString("VersionSetCommand", $launcher);
        self::assertStringContainsString("version:set", $launcher);
        self::assertStringContainsString("\$console->register(VersionSetCommand::class);", $launcher);
    }

    public function testOptionalStrictStaticAnalysisConfigurationIsPresent(): void
    {
        $fallbackAnalysis = (string) file_get_contents(base_path("scripts/static-analysis.php"));

        self::assertFileExists(base_path("phpstan.neon.dist"));
        self::assertStringContainsString("Install PHPStan or Psalm locally", $fallbackAnalysis);
        self::assertStringContainsString("declares return type", $fallbackAnalysis);
        self::assertStringContainsString("T_RETURN", $fallbackAnalysis);
    }
}
