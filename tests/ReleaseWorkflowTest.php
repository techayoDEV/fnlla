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
    public function testSourceArchiveRunsTheFullReleasePreparationWithoutPrivateStorage(): void
    {
        $workflow = (string) file_get_contents(base_path(".github/workflows/quality.yml"));
        self::assertStringContainsString("git archive --format=zip", $workflow);
        self::assertStringContainsString('test ! -e "${{ runner.temp }}/fnlla-source/storage"', $workflow);
        self::assertStringContainsString("run: php fnlla release:prepare --json", $workflow);
        self::assertStringContainsString("name: accepted-source-archive", $workflow);
    }

    public function testReleaseGateFetchesFullGitHistoryForHistoryDependentTests(): void
    {
        $workflow = (string) file_get_contents(base_path(".github/workflows/fnlla-release-gate.yml"));

        self::assertStringContainsString("uses: actions/checkout@v5", $workflow);
        self::assertStringContainsString("fetch-depth: 0", $workflow);
        self::assertStringContainsString("composer run test -- --suite fast", $workflow);
        self::assertStringContainsString("composer install --no-interaction --prefer-dist", $workflow);
        self::assertStringContainsString("composer audit --locked", $workflow);
        self::assertStringContainsString("--testsuite framework --fail-on-skipped", $workflow);
        self::assertStringContainsString("composer run lint", $workflow);
        self::assertStringContainsString("php ./scripts/check-docs.php", $workflow);
        self::assertStringContainsString("php ./fnlla release:prepare --skip-tests", $workflow);
        self::assertStringContainsString("php ./fnlla security:audit --strict", $workflow);
        self::assertStringContainsString("php ./fnlla ops:backup-plan --verify --output=framework/backup-plan.json", $workflow);
        self::assertStringContainsString("php ./fnlla project:acceptance --json", $workflow);
        self::assertStringContainsString("php ./fnlla perf:budget --iterations=1 --max-regression=20 --max-regression-ms=1000", $workflow);
    }

    public function testProductionHttpGateConsumesTheAcceptedArchive(): void
    {
        $workflow = (string) file_get_contents(base_path(".github/workflows/quality.yml"));
        $runner = (string) file_get_contents(base_path("scripts/acceptance/fpm.sh"));
        self::assertStringContainsString("production-http:", $workflow);
        self::assertStringContainsString("needs: source-archive", $workflow);
        self::assertStringContainsString("actions/download-artifact@v4", $workflow);
        self::assertStringContainsString("scripts/acceptance/fpm.sh", $workflow);
        self::assertStringContainsString("opcache.validate_timestamps] = 0", $runner);
        self::assertStringContainsString("after-reload", $runner);
        self::assertStringContainsString("trap cleanup EXIT", $runner);
        self::assertStringNotContainsString("continue-on-error", $workflow);
    }

    public function testPublishingRequiresAnExplicitDraftAndNeverReplacesAssets(): void
    {
        $workflow = (string) file_get_contents(base_path(".github/workflows/fnlla-release-gate.yml"));

        self::assertStringNotContainsString("contents: write", $workflow);
        self::assertStringNotContainsString("gh release create", $workflow);
        $draft = (string) file_get_contents(base_path(".github/workflows/release-draft.yml"));
        $script = (string) file_get_contents(base_path("scripts/release/prepare-draft.mjs"));
        self::assertStringContainsString("workflow_dispatch:", $draft);
        self::assertStringNotContainsString("  push:", $draft);
        self::assertStringContainsString("'--draft'", $script);
        self::assertStringContainsString("'--verify-tag'", $script);
        self::assertStringNotContainsString("--clobber", $script);
        self::assertStringContainsString("accepted-source-archive", $script);
        self::assertStringContainsString("fnlla-downloads.sha256", $script);
        foreach (["quality.yml", "fnlla-hardening.yml", "fnlla-release-gate.yml"] as $required) {
            self::assertStringContainsString($required, $script);
        }
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

    public function testBusinessReferenceBlueprintCoversProfessionalApplicationSurface(): void
    {
        $manifest = json_decode((string) file_get_contents(base_path("resources/business-reference/MANIFEST.json")), true);
        $blueprint = json_decode((string) file_get_contents(base_path("resources/business-reference/blueprint.json")), true);
        $readme = (string) file_get_contents(base_path("resources/business-reference/README.md"));

        self::assertSame("fnlla.business_reference.v1", $manifest["schema"] ?? null);
        self::assertSame(trim((string) file(base_path("VERSION"))[0]), $manifest["version"] ?? null);
        self::assertSame($manifest["version"], $blueprint["version"] ?? null);

        foreach (["login", "roles", "crud", "dashboard", "business_form", "log_mailer", "migrations", "seeders", "queue", "health", "maintenance_preview", "backup_restore", "production_security"] as $capability) {
            self::assertTrue(in_array($capability, (array) ($manifest["capabilities"] ?? []), true), $capability);
        }

        foreach (["admin", "operator", "client"] as $role) {
            self::assertTrue(in_array($role, (array) ($manifest["roles"] ?? []), true), $role);
        }

        self::assertSame("fnlla.business_app_blueprint.v1", $blueprint["schema"] ?? null);
        $gates = array_column($blueprint["gates"], null, "name");
        foreach ($blueprint["routes"] as $route) {
            foreach ($route["middleware"] as $middleware) {
                if (str_starts_with($middleware, "authorize:")) {
                    $gate = $gates[substr($middleware, strlen("authorize:"))] ?? null;
                    self::assertTrue(is_array($gate), "Route references an undeclared gate: " . $middleware);
                    self::assertTrue(in_array($route["role"], $gate["roles"], true));
                }
            }
            if ($route["method"] === "POST") {
                self::assertTrue(in_array("csrf", $route["middleware"], true));
            }
        }
        self::assertStringContainsString("make:project", $readme);
        self::assertStringContainsString("security:audit --strict", $readme);
    }

    public function testUpdatePathAndPerformanceBaselinePolicyAreReleaseVisible(): void
    {
        $workflow = (string) file_get_contents(base_path(".github/workflows/fnlla-release-gate.yml"));
        $policy = json_decode((string) file_get_contents(base_path("resources/performance-baselines/policy.json")), true);

        self::assertStringContainsString("v2.0.3", $workflow);
        self::assertStringContainsString("upgrade:check --target=2.2.0", $workflow);
        self::assertStringContainsString("project:acceptance --json", $workflow);
        self::assertSame("fnlla.performance_baseline_policy.v1", $policy["schema"] ?? null);

        foreach (["cli.list", "cli.route_list", "http.home", "http.health", "project.export"] as $target) {
            self::assertTrue(in_array($target, array_column((array) ($policy["targets"] ?? []), "id"), true), $target);
        }
    }
}
