<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA TEST CASE
File: tests\PerformanceAndAiTest.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

Purpose:
- Validates performance warmup helpers, local profiling payloads and redacted AI
  context generation.
*/

namespace Fnlla\Php\Tests;

use Fnlla\Php\Console\Commands\AiContextCommand;
use Fnlla\Php\Console\Commands\AiRedactCommand;
use Fnlla\Php\Console\Commands\AiReviewPackCommand;
use Fnlla\Php\Console\Commands\AiUpgradeBriefCommand;
use Fnlla\Php\Console\Commands\AppMapCommand;
use Fnlla\Php\Console\Commands\PerfBaselineUpdateCommand;
use Fnlla\Php\Console\Commands\PerfBudgetCommand;
use Fnlla\Php\Console\Commands\PerfCompareCommand;
use Fnlla\Php\Console\Commands\PerfProfileCommand;
use Fnlla\Php\Console\Commands\UpgradeApplyCommand;
use Fnlla\Php\Console\Commands\UpgradeCheckCommand;
use Fnlla\Php\Console\Commands\UpgradePlanCommand;
use Fnlla\Php\Container\Container;
use Fnlla\Php\Support\AiContextBuilder;
use Fnlla\Php\Support\AppMapBuilder;
use Fnlla\Php\Support\AssetManifestBuilder;
use Fnlla\Php\Support\PerformanceProfiler;
use Fnlla\Php\Support\UpgradeAnalyzer;
use PHPUnit\Framework\TestCase;

final class PerformanceAndAiTest extends TestCase
{
    private ?string $previousAssetManifest = null;
    private bool $assetManifestExisted = false;
    private array $previousConfig = [];

    protected function setUp(): void
    {
        $this->previousConfig = config();
        $this->assetManifestExisted = is_file(framework_asset_manifest_path());
        $this->previousAssetManifest = $this->assetManifestExisted
            ? (string) file_get_contents(framework_asset_manifest_path())
            : null;
    }

    protected function tearDown(): void
    {
        if ($this->assetManifestExisted && $this->previousAssetManifest !== null) {
            file_put_contents(framework_asset_manifest_path(), $this->previousAssetManifest, LOCK_EX);
        } elseif (is_file(framework_asset_manifest_path())) {
            unlink(framework_asset_manifest_path());
        }

        if (is_file(framework_ai_context_path())) {
            unlink(framework_ai_context_path());
        }

        foreach ([framework_ai_review_pack_path(), framework_ai_upgrade_brief_path(), framework_app_map_path(), framework_upgrade_plan_path()] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        $GLOBALS["fnlla_config"] = $this->previousConfig;
        $GLOBALS["fnlla_php_config"] = $this->previousConfig;
    }

    public function testAssetManifestCanDriveAssetVersionWithoutFileMtimeLookup(): void
    {
        $directory = dirname(framework_asset_manifest_path());

        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        file_put_contents(framework_asset_manifest_path(), "<?php\n\nreturn ['assets/app.css' => ['version' => 'manifest-test']];\n", LOCK_EX);

        self::assertStringContainsString("?v=manifest-test", asset("assets/app.css"));
    }

    public function testAssetManifestBuilderWritesPublicAssetMetadata(): void
    {
        $result = (new AssetManifestBuilder())->build(public_path(), framework_asset_manifest_path());
        $manifest = require framework_asset_manifest_path();

        self::assertTrue(($result["assets"] ?? 0) > 0);
        self::assertArrayHasKey("assets/app.css", $manifest);
        self::assertArrayHasKey("sha256", $manifest["assets/app.css"]);
    }

    public function testPerformanceProfilerProducesMachineReadableProfile(): void
    {
        $profile = (new PerformanceProfiler())->profile(1);

        self::assertSame("fnlla.performance_profile.v1", $profile["schema"] ?? null);
        self::assertArrayHasKey("cli", $profile);
        self::assertArrayHasKey("footprint", $profile);
    }

    public function testPerformanceCommandsAreNamedForCli(): void
    {
        $container = new Container();

        self::assertSame("perf:profile", (new PerfProfileCommand($container))->name());
        self::assertSame("perf:budget", (new PerfBudgetCommand($container))->name());
        self::assertSame("perf:baseline:update", (new PerfBaselineUpdateCommand($container))->name());
        self::assertSame("perf:compare", (new PerfCompareCommand($container))->name());
    }

    public function testAiContextIsLocalOnlyAndRedacted(): void
    {
        config_set("app.secret_key_for_test", "do-not-leak");

        $context = (new AiContextBuilder())->build();
        $encoded = json_encode($context, JSON_THROW_ON_ERROR);

        self::assertSame("fnlla.ai_context.v1", $context["schema"] ?? null);
        self::assertFalse((bool) ($context["privacy"]["external_calls"] ?? true));
        self::assertFalse((bool) ($context["privacy"]["raw_env_included"] ?? true));
        self::assertFalse((bool) ($context["configuration_posture"]["cors"]["supports_credentials"] ?? true));
        self::assertStringNotContainsString("do-not-leak", $encoded);
        self::assertArrayHasKey("routes", $context);
        self::assertTrue((int) ($context["routes"]["count"] ?? 0) > 0);
    }

    public function testAiContextCommandIsNamedForCli(): void
    {
        self::assertSame("ai:context", (new AiContextCommand(new Container()))->name());
    }

    public function testAppMapBuilderMapsRoutesHandlersAndViews(): void
    {
        $map = (new AppMapBuilder())->build();

        self::assertSame("fnlla.app_map.v1", $map["schema"] ?? null);
        self::assertTrue((int) ($map["routes"]["count"] ?? 0) > 0);

        $homeRoute = null;

        foreach ((array) ($map["routes"]["items"] ?? []) as $route) {
            if (($route["name"] ?? null) === "home") {
                $homeRoute = $route;
                break;
            }
        }

        self::assertTrue(is_array($homeRoute));
        self::assertSame("Fnlla\\Php\\Controllers\\PageController@home", $homeRoute["handler"]["label"] ?? null);
        self::assertStringContainsString("pages/home", implode(",", (array) ($homeRoute["view_references"] ?? [])));
    }

    public function testUpgradeAnalyzerProducesMajorReleasePlan(): void
    {
        $report = (new UpgradeAnalyzer())->report("2.0.0");

        self::assertSame("fnlla.upgrade_report.v1", $report["schema"] ?? null);
        self::assertSame("2.0.0", $report["target_version"] ?? null);
        self::assertArrayHasKey("checks", $report);
        self::assertArrayHasKey("plan", $report);
    }

    public function testMajorReleaseCommandsAreNamedForCli(): void
    {
        $container = new Container();

        self::assertSame("app:map", (new AppMapCommand($container))->name());
        self::assertSame("upgrade:check", (new UpgradeCheckCommand($container))->name());
        self::assertSame("upgrade:plan", (new UpgradePlanCommand($container))->name());
        self::assertSame("upgrade:apply", (new UpgradeApplyCommand($container))->name());
        self::assertSame("ai:review-pack", (new AiReviewPackCommand($container))->name());
        self::assertSame("ai:upgrade-brief", (new AiUpgradeBriefCommand($container))->name());
        self::assertSame("ai:redact", (new AiRedactCommand($container))->name());
    }

    public function testAiRedactionKeepsNeutralKeyLabelsButMasksSecrets(): void
    {
        $redacted = (new AiContextBuilder())->redactedCopy([
            "key" => "config",
            "app_secret" => "do-not-leak",
            "nested" => [
                "api_token" => "also-hidden",
            ],
        ]);

        $encoded = json_encode($redacted, JSON_THROW_ON_ERROR);

        self::assertSame("config", $redacted["key"] ?? null);
        self::assertStringNotContainsString("do-not-leak", $encoded);
        self::assertStringNotContainsString("also-hidden", $encoded);
    }

    public function testAiReviewPackCombinesContextMapAndUpgradeReadiness(): void
    {
        $contextBuilder = new AiContextBuilder();
        $pack = $contextBuilder->redactedCopy([
            "context" => $contextBuilder->build(),
            "app_map" => (new AppMapBuilder())->build(),
            "upgrade" => (new UpgradeAnalyzer())->report("2.0.0"),
            "app_secret" => "do-not-leak",
        ]);
        $encoded = json_encode($pack, JSON_THROW_ON_ERROR);

        self::assertArrayHasKey("context", $pack);
        self::assertArrayHasKey("app_map", $pack);
        self::assertArrayHasKey("upgrade", $pack);
        self::assertStringNotContainsString("do-not-leak", $encoded);
    }
}
