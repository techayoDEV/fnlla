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

use Fnlla\Php\Console\Commands\AiAskCommand;
use Fnlla\Php\Console\Commands\AiBriefCommand;
use Fnlla\Php\Console\Commands\AiContextCommand;
use Fnlla\Php\Console\Commands\AiExplainLogCommand;
use Fnlla\Php\Console\Commands\AiProvidersCommand;
use Fnlla\Php\Console\Commands\AiRedactCommand;
use Fnlla\Php\Console\Commands\AiReviewPackCommand;
use Fnlla\Php\Console\Commands\AiTriageCommand;
use Fnlla\Php\Console\Commands\AiUpgradeBriefCommand;
use Fnlla\Php\Console\Commands\AppMapCommand;
use Fnlla\Php\Console\Commands\ConfigDoctorCommand;
use Fnlla\Php\Console\Commands\PerfBaselineUpdateCommand;
use Fnlla\Php\Console\Commands\PerfBudgetCommand;
use Fnlla\Php\Console\Commands\PerfCompareCommand;
use Fnlla\Php\Console\Commands\PerfProfileCommand;
use Fnlla\Php\Console\Commands\TechDebtUpdateCommand;
use Fnlla\Php\Console\Commands\UpgradeApplyCommand;
use Fnlla\Php\Console\Commands\UpgradeCheckCommand;
use Fnlla\Php\Console\Commands\UpgradePlanCommand;
use Fnlla\Php\Container\Container;
use Fnlla\Php\Http\Request;
use Fnlla\Php\Http\Response;
use Fnlla\Php\Observability\MetricsRecorder;
use Fnlla\Php\Support\AiContextBuilder;
use Fnlla\Php\Support\AppMapBuilder;
use Fnlla\Php\Support\AssetManifestBuilder;
use Fnlla\Php\Support\DeveloperAnalyticsReport;
use Fnlla\Php\Support\DeveloperHeatmapReport;
use Fnlla\Php\Support\DeveloperOperationsReport;
use Fnlla\Php\Support\PerformanceProfiler;
use Fnlla\Php\Support\TechnicalDebtReportBuilder;
use Fnlla\Php\Support\UpgradeAnalyzer;
use PHPUnit\Framework\TestCase;

final class PerformanceAndAiTest extends TestCase
{
    private ?string $previousAssetManifest = null;
    private bool $assetManifestExisted = false;
    private array $previousConfig = [];
    private array $temporaryFiles = [];

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

        if (is_file(framework_technical_debt_report_path())) {
            unlink(framework_technical_debt_report_path());
        }

        foreach ($this->temporaryFiles as $path) {
            if (is_file($path)) {
                unlink($path);
            }

            if (is_file($path . ".lock")) {
                unlink($path . ".lock");
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
        self::assertArrayHasKey("assets/app.css", $manifest);
        self::assertArrayHasKey("sha256", $manifest["assets/app.css"]);
    }

    public function testPerformanceProfilerProducesMachineReadableProfile(): void
    {
        $profile = (new PerformanceProfiler())->profile(1);

        self::assertSame("fnlla.performance_profile.v1", $profile["schema"] ?? null);
        self::assertArrayHasKey("cli", $profile);
        self::assertArrayHasKey("http", $profile);
        self::assertArrayHasKey("footprint", $profile);
        self::assertTrue((bool) ($profile["http"]["GET /api/health"]["ok"] ?? false), json_encode($profile["http"]["GET /api/health"] ?? [], JSON_PRETTY_PRINT));
    }

    public function testDeveloperOperationsReportUsesPrivacyLightAnalytics(): void
    {
        $relativeMetricsPath = "framework/cache/operations-test-" . bin2hex(random_bytes(4)) . ".json";
        $this->temporaryFiles[] = storage_path($relativeMetricsPath);
        config_set("observability.metrics.path", $relativeMetricsPath);
        config_set("observability.metrics.enabled", true);
        $_SERVER["FNLLA_ROUTE_NAME"] = "home";

        (new MetricsRecorder())->record(Request::capture("", [
            "REQUEST_URI" => "/",
            "REQUEST_METHOD" => "GET",
            "REMOTE_ADDR" => "203.0.113.10",
            "HTTP_ACCEPT" => "text/html",
            "HTTP_REFERER" => "https://example.test/campaign?utm_secret=hidden",
        ]), Response::html("OK"), 14.5);

        unset($_SERVER["FNLLA_ROUTE_NAME"]);

        $report = (new DeveloperOperationsReport())->build();
        $encoded = json_encode($report, JSON_THROW_ON_ERROR);

        self::assertSame("fnlla.developer_operations.v1", $report["schema"] ?? null);
        self::assertFalse((bool) ($report["privacy"]["raw_ip_addresses"] ?? true));
        self::assertFalse((bool) ($report["privacy"]["raw_user_agents"] ?? true));
        self::assertSame(1, (int) ($report["analytics"]["page_views"] ?? 0));
        self::assertSame("example.test", $report["analytics"]["referrers"][0]["label"] ?? null);
        self::assertSame(0.0, (float) ($report["analytics"]["consent"]["backend_rate"] ?? 0.0));
        self::assertSame(0, (int) ($report["analytics"]["consent"]["events"] ?? 0));
        self::assertArrayHasKey("performance", $report);
        self::assertArrayHasKey("forms", $report);
        self::assertArrayHasKey("release_readiness", $report);
        self::assertArrayHasKey("integrations", $report);
        self::assertArrayHasKey("heatmaps", $report);
        self::assertStringNotContainsString("203.0.113.10", $encoded);
        self::assertStringNotContainsString("utm_secret", $encoded);
    }

    public function testDeveloperOperationsReportReadsRecentLogTailForErrors(): void
    {
        $logPath = storage_path("framework/cache/operations-log-tail-" . bin2hex(random_bytes(4)) . ".log");
        $this->temporaryFiles[] = $logPath;
        config_set("app.log_path", $logPath);

        if (!is_dir(dirname($logPath))) {
            mkdir(dirname($logPath), 0777, true);
        }

        $lines = [
            json_encode([
                "timestamp" => "2026-08-01T00:00:00+00:00",
                "level" => "ERROR",
                "message" => "Old ignored failure",
            ], JSON_THROW_ON_ERROR),
        ];

        for ($index = 0; $index < 300; $index++) {
            $lines[] = json_encode([
                "timestamp" => "2026-08-01T00:00:00+00:00",
                "level" => "INFO",
                "message" => "Routine line " . $index,
            ], JSON_THROW_ON_ERROR);
        }

        $lines[] = json_encode([
            "timestamp" => "2026-08-01T00:05:00+00:00",
            "level" => "ERROR",
            "message" => "Recent edge failure",
        ], JSON_THROW_ON_ERROR);
        file_put_contents($logPath, implode(PHP_EOL, $lines) . PHP_EOL, LOCK_EX);

        $report = (new DeveloperOperationsReport())->build();
        $encodedErrors = json_encode($report["analytics"]["errors"] ?? [], JSON_THROW_ON_ERROR);

        self::assertStringContainsString("Recent edge failure", $encodedErrors);
        self::assertStringNotContainsString("Old ignored failure", $encodedErrors);
    }

    public function testDeveloperAnalyticsReportBuildsInternalCockpitWithoutRawVisitorData(): void
    {
        $relativeMetricsPath = "framework/cache/analytics-test-" . bin2hex(random_bytes(4)) . ".json";
        $this->temporaryFiles[] = storage_path($relativeMetricsPath);
        config_set("observability.metrics.path", $relativeMetricsPath);
        config_set("observability.metrics.enabled", true);
        config_set("observability.analytics.enabled", true);
        config_set("observability.analytics.sample_rate", 100);
        $_SERVER["FNLLA_ROUTE_NAME"] = "contact";

        $recorder = new MetricsRecorder();
        $recorder->record(Request::capture("", [
            "REQUEST_URI" => "/contact?token=hidden",
            "REQUEST_METHOD" => "GET",
            "REMOTE_ADDR" => "203.0.113.55",
            "HTTP_ACCEPT" => "text/html",
            "HTTP_REFERER" => "https://google.com/search?q=private",
            "HTTP_USER_AGENT" => "Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X)",
        ]), Response::html("OK"), 45.25);
        $recorder->record(Request::capture("", [
            "REQUEST_URI" => "/contact",
            "REQUEST_METHOD" => "POST",
            "REMOTE_ADDR" => "203.0.113.55",
            "HTTP_ACCEPT" => "text/html",
            "HTTP_USER_AGENT" => "Mozilla/5.0",
        ]), Response::html("Saved"), 60.0);
        $recorder->recordConsent([
            "analytics" => true,
            "marketing" => false,
            "source" => "cookie-banner",
        ]);

        unset($_SERVER["FNLLA_ROUTE_NAME"]);

        $report = (new DeveloperAnalyticsReport())->build();
        $encoded = json_encode($report, JSON_THROW_ON_ERROR);

        self::assertSame("fnlla.developer_analytics.v1", $report["schema"] ?? null);
        self::assertSame(1, (int) ($report["summary"]["page_views"] ?? 0));
        self::assertSame(1, (int) ($report["summary"]["conversion_events"] ?? 0));
        self::assertSame(1, (int) ($report["summary"]["consent_events"] ?? 0));
        self::assertSame(100.0, (float) ($report["summary"]["analytics_consent_rate"] ?? 0.0));
        self::assertArrayHasKey("daily_page_views", $report["charts"] ?? []);
        self::assertArrayHasKey("hourly_page_views", $report["charts"] ?? []);
        self::assertArrayHasKey("device_counts", $report["charts"] ?? []);
        self::assertArrayHasKey("route_response_times", $report["charts"] ?? []);
        self::assertArrayHasKey("consent_counts", $report["charts"] ?? []);
        self::assertArrayHasKey("daily_consent_events", $report["charts"] ?? []);
        self::assertSame("analytics_only", $report["charts"]["consent_counts"][0]["label"] ?? null);
        self::assertSame("analytics_only", $report["last_consent_event"]["state"] ?? null);
        self::assertSame("FNLLA Internal Analytics", $report["integrations"]["fnlla_internal"]["name"] ?? null);
        self::assertTrue((bool) ($report["settings"]["editable_from_panel"] ?? false));
        self::assertStringNotContainsString("203.0.113.55", $encoded);
        self::assertStringNotContainsString("q=private", $encoded);
        self::assertStringNotContainsString("iPhone OS", $encoded);
    }

    public function testDeveloperHeatmapReportBuildsAggregateBehaviorCockpitWithoutRawVisitorData(): void
    {
        $relativeMetricsPath = "framework/cache/heatmap-test-" . bin2hex(random_bytes(4)) . ".json";
        $this->temporaryFiles[] = storage_path($relativeMetricsPath);
        config_set("observability.metrics.path", $relativeMetricsPath);
        config_set("observability.metrics.enabled", true);
        config_set("observability.analytics.enabled", true);
        config_set("observability.heatmap.enabled", true);
        config_set("observability.heatmap.sample_rate", 100);
        config_set("observability.heatmap.click_grid_columns", 5);
        config_set("observability.heatmap.click_grid_rows", 5);

        $recorder = new MetricsRecorder();
        $recorder->recordBehaviorEvent([
            "type" => "view",
            "path" => "/services?token=hidden",
            "device" => "desktop",
            "viewport" => ["width" => 1440, "height" => 900],
        ]);
        $recorder->recordBehaviorEvent([
            "type" => "click",
            "path" => "/services",
            "device" => "desktop",
            "element" => "button",
            "element_label" => "Request quote",
            "element_context" => "main",
            "position" => ["x_percent" => 66, "y_percent" => 42],
        ]);
        $recorder->recordBehaviorEvent([
            "type" => "scroll",
            "path" => "/services",
            "device" => "desktop",
            "depth" => 75,
        ]);

        $report = (new DeveloperHeatmapReport())->build();
        $encoded = json_encode($report, JSON_THROW_ON_ERROR);

        self::assertSame("fnlla.developer_heatmap.v1", $report["schema"] ?? null);
        self::assertSame("first-party aggregate heatmap", $report["privacy"]["mode"] ?? null);
        self::assertFalse((bool) ($report["privacy"]["raw_session_recording"] ?? true));
        self::assertFalse((bool) ($report["privacy"]["raw_cursor_trails"] ?? true));
        self::assertSame(3, (int) ($report["summary"]["behavior_events"] ?? 0));
        self::assertSame(1, (int) ($report["summary"]["click_events"] ?? 0));
        self::assertSame(1, (int) ($report["summary"]["scroll_events"] ?? 0));
        self::assertSame("/services", $report["summary"]["top_page"] ?? null);
        self::assertSame(5, (int) ($report["charts"]["top_page_click_grid"]["columns"] ?? 0));
        self::assertSame(5, count((array) ($report["charts"]["top_page_click_grid"]["rows"] ?? [])));
        self::assertSame("button", $report["charts"]["click_elements"][0]["label"] ?? null);
        self::assertSame("Center-right public page area", $report["charts"]["top_page_click_grid"]["rows"][2][3]["zone"] ?? null);
        self::assertSame("button: Request quote in main", $report["charts"]["top_page_click_grid"]["rows"][2][3]["targets"][0]["label"] ?? null);
        self::assertStringContainsString("Public page /services.", (string) ($report["charts"]["top_page_click_grid"]["rows"][2][3]["tooltip"] ?? ""));
        self::assertStringContainsString("Most clicked: button: Request quote in main (1).", (string) ($report["charts"]["top_page_click_grid"]["rows"][2][3]["tooltip"] ?? ""));
        self::assertStringNotContainsString("token=hidden", $encoded);
        self::assertStringNotContainsString("Mozilla", $encoded);
        self::assertStringNotContainsString("203.0.113", $encoded);
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
        config_set("cors.supports_credentials", false);

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
        $aboutRoute = null;

        foreach ((array) ($map["routes"]["items"] ?? []) as $route) {
            if (($route["name"] ?? null) === "home") {
                $homeRoute = $route;
            }

            if (($route["name"] ?? null) === "about") {
                $aboutRoute = $route;
            }
        }

        self::assertTrue(is_array($homeRoute));
        self::assertSame("Fnlla\\Php\\Controllers\\HomeController@projectHome", $homeRoute["handler"]["label"] ?? null);
        self::assertTrue(is_array($aboutRoute));
        self::assertStringContainsString("pages/about", implode(",", (array) ($aboutRoute["view_references"] ?? [])));
    }

    public function testUpgradeAnalyzerProducesMajorReleasePlan(): void
    {
        $report = (new UpgradeAnalyzer())->report();

        self::assertSame("fnlla.upgrade_report.v1", $report["schema"] ?? null);
        self::assertSame(UpgradeAnalyzer::DEFAULT_TARGET_VERSION, $report["target_version"] ?? null);
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
        self::assertSame("ai:ask", (new AiAskCommand($container))->name());
        self::assertSame("ai:triage", (new AiTriageCommand($container))->name());
        self::assertSame("ai:upgrade-brief", (new AiUpgradeBriefCommand($container))->name());
        self::assertSame("ai:redact", (new AiRedactCommand($container))->name());
        self::assertSame("ai:brief", (new AiBriefCommand($container))->name());
        self::assertSame("ai:explain-log", (new AiExplainLogCommand($container))->name());
        self::assertSame("ai:providers", (new AiProvidersCommand($container))->name());
        self::assertSame("tech-debt:update", (new TechDebtUpdateCommand($container))->name());
    }

    public function testConfigDoctorCommandIsNamedForCli(): void
    {
        self::assertSame("config:doctor", (new ConfigDoctorCommand(new Container()))->name());
    }

    public function testUpgradeAnalyzerReportsPublicApiAndDistributedAdapterPosture(): void
    {
        $report = (new UpgradeAnalyzer())->report();
        $ids = array_map(static fn (array $check): string => (string) ($check["id"] ?? ""), (array) ($report["checks"] ?? []));

        self::assertTrue(in_array("public-api-contract", $ids, true));
        self::assertTrue(in_array("distributed-adapters", $ids, true));
    }

    public function testAiTriageBuildsLocalOnlyReport(): void
    {
        $container = new Container();
        $command = new AiTriageCommand($container);

        $payload = $command->buildReport("route 404 on controller");

        self::assertSame("fnlla.ai_triage.v1", $payload["schema"] ?? null);
        self::assertFalse((bool) ($payload["privacy"]["external_calls"] ?? true));
        self::assertTrue(in_array("routing", (array) ($payload["likely_areas"] ?? []), true));
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
            "upgrade" => (new UpgradeAnalyzer())->report(),
            "app_secret" => "do-not-leak",
        ]);
        $encoded = json_encode($pack, JSON_THROW_ON_ERROR);

        self::assertArrayHasKey("context", $pack);
        self::assertArrayHasKey("app_map", $pack);
        self::assertArrayHasKey("upgrade", $pack);
        self::assertStringNotContainsString("do-not-leak", $encoded);
    }

    public function testTechnicalDebtReportBuildsMachineReadableReleaseSnapshot(): void
    {
        $builder = new TechnicalDebtReportBuilder();
        $report = $builder->build();
        $ids = array_map(static fn (array $check): string => (string) ($check["id"] ?? ""), (array) ($report["checks"] ?? []));
        $snapshot = $builder->markdownSnapshot($report);

        self::assertSame("fnlla.technical_debt_report.v1", $report["schema"] ?? null);
        self::assertTrue(in_array("explicit-debt-markers", $ids, true));
        self::assertTrue(in_array("runtime-residue", $ids, true));
        self::assertSame(!is_file(base_path(".fnlla/framework-lock.json")), in_array("documentation-hygiene", $ids, true));
        self::assertTrue(in_array("ai-product-runtime", $ids, true));
        self::assertStringContainsString("FNLLA_TECH_DEBT_REPORT:BEGIN", $snapshot);
        self::assertStringContainsString("php fnlla tech-debt:update --check", $snapshot);
    }
}
