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
    }

    public function testMaintainerLauncherExposesVersionSetWorkflow(): void
    {
        $launcher = (string) file_get_contents(base_path("fnlla"));

        self::assertStringContainsString("VersionSetCommand", $launcher);
        self::assertStringContainsString("version:set", $launcher);
        self::assertStringContainsString("\$console->register(VersionSetCommand::class);", $launcher);
    }
}
