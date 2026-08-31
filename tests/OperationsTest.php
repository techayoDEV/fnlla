<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA TEST CASE
File: tests\OperationsTest.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

Purpose:
- Validates observability and release-operations helpers.
*/

namespace Fnlla\Php\Tests;

use Fnlla\Php\Application;
use Fnlla\Php\Console\Commands\BackupPlanCommand;
use Fnlla\Php\Console\Commands\DeveloperInstallStorageCommand;
use Fnlla\Php\Console\Commands\DoctorCommand;
use Fnlla\Php\Console\Commands\ProjectAcceptanceCommand;
use Fnlla\Php\Console\Commands\PublicApiLockCommand;
use Fnlla\Php\Console\Commands\SecurityAuditCommand;
use Fnlla\Php\Container\Container;
use Fnlla\Php\Exceptions\ExceptionHandler;
use Fnlla\Php\Http\Request;
use Fnlla\Php\Http\Response;
use Fnlla\Php\Routing\Router;
use Fnlla\Php\Support\DoctorReport;
use Fnlla\Php\Support\BackupPlanBuilder;
use Fnlla\Php\Support\DeveloperPanelStorageInstaller;
use Fnlla\Php\Support\DeveloperWorkspaceBoard;
use Fnlla\Php\Support\ProjectAcceptanceReportBuilder;
use Fnlla\Php\Support\RecentFileLines;
use Fnlla\Php\Support\ReleaseArtifactBuilder;
use Fnlla\Php\Support\SecurityAuditReport;
use Fnlla\Php\Support\TechAyoRemoteControlPlugin;
use PHPUnit\Framework\TestCase;

final class OperationsTest extends TestCase
{
    private array $previousConfig = [];

    protected function setUp(): void
    {
        $this->previousConfig = config();
    }

    protected function tearDown(): void
    {
        $GLOBALS["fnlla_config"] = $this->previousConfig;
        $GLOBALS["fnlla_php_config"] = $this->previousConfig;
    }

    public function testApplicationAddsResponseTimeAndRecordsMetrics(): void
    {
        $metricsPath = "framework/operations-test-" . bin2hex(random_bytes(4)) . ".json";
        config_set("observability.metrics.path", $metricsPath);
        config_set("observability.metrics.enabled", true);
        config_set("observability.access_log.enabled", false);
        config_set("observability.response_time_header.enabled", true);

        $container = new Container();
        $router = new Router($container);
        $router->get("/observed", static fn (): Response => Response::text("ok"))->name("observed.route");
        $application = new Application($router, $container, new ExceptionHandler());

        $response = $application->handle(Request::capture("", [
            "REQUEST_URI" => "/observed",
            "REQUEST_METHOD" => "GET",
            "REMOTE_ADDR" => "127.0.0.1",
        ]));

        $absoluteMetricsPath = storage_path($metricsPath);
        self::assertSame(200, $response->status());
        self::assertArrayHasKey("X-Response-Time", $response->headers());
        self::assertFileExists($absoluteMetricsPath);

        $metrics = json_decode((string) file_get_contents($absoluteMetricsPath), true);
        self::assertSame("fnlla.metrics.v1", $metrics["schema"] ?? null);
        self::assertSame(1, $metrics["total_requests"] ?? null);
        self::assertSame(1, $metrics["route_counts"]["observed.route"] ?? null);
        self::assertSame(1, $metrics["page_views"] ?? null);
        self::assertSame(1, $metrics["page_route_counts"]["observed.route"] ?? null);
        self::assertSame(1, $metrics["source_counts"]["direct"] ?? null);
        self::assertArrayHasKey("daily_page_views", $metrics);
        self::assertArrayHasKey("route_duration_totals", $metrics);
        self::assertStringNotContainsString("127.0.0.1", json_encode($metrics, JSON_THROW_ON_ERROR));

        @unlink($absoluteMetricsPath);
        @unlink($absoluteMetricsPath . ".lock");
    }

    public function testRecentFileLinesReadsNewestNonEmptyLinesWithoutWholeFileContract(): void
    {
        $path = storage_path("framework/cache/recent-lines-" . bin2hex(random_bytes(4)) . ".log");

        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }

        try {
            $lines = [];

            for ($index = 0; $index < 320; $index++) {
                $lines[] = "line-" . $index;
            }

            file_put_contents($path, implode(PHP_EOL, $lines) . PHP_EOL . PHP_EOL, LOCK_EX);

            self::assertSame(["line-319", "line-318", "line-317"], RecentFileLines::read($path, 3));
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function testDoctorReportHasMachineReadableSummary(): void
    {
        $report = (new DoctorReport())->build();

        self::assertSame("fnlla.doctor.v1", $report["schema"] ?? null);
        self::assertArrayHasKey("checks", $report);
        self::assertArrayHasKey("summary", $report);
    }

    public function testSecurityAuditFlagsCredentialedWildcardCors(): void
    {
        config_set("cors.allowed_origins", ["*"]);
        config_set("cors.supports_credentials", true);

        $report = (new SecurityAuditReport())->build();
        $checks = [];

        foreach ((array) $report["checks"] as $check) {
            $checks[$check["id"]] = $check;
        }

        self::assertSame("fail", $checks["credentialed_cors_explicit"]["status"] ?? null);
    }

    public function testOperationalCommandsAreNamedForCli(): void
    {
        $container = new Container();

        self::assertSame("doctor", (new DoctorCommand($container))->name());
        self::assertSame("developer:install-storage", (new DeveloperInstallStorageCommand($container))->name());
        self::assertSame("api:lock", (new PublicApiLockCommand($container))->name());
        self::assertSame("security:audit", (new SecurityAuditCommand($container))->name());
        self::assertSame("ops:backup-plan", (new BackupPlanCommand($container))->name());
        self::assertSame("project:acceptance", (new ProjectAcceptanceCommand($container))->name());
    }

    public function testPublicApiLockFileExists(): void
    {
        if (!is_file(base_path("docs/PUBLIC-API.md"))) {
            self::assertTrue(true);
            return;
        }

        self::assertFileExists(base_path("docs/PUBLIC-API.lock.json"));
        $payload = json_decode((string) file_get_contents(base_path("docs/PUBLIC-API.lock.json")), true);

        self::assertSame("fnlla.public_api_lock.v1", $payload["schema"] ?? null);
        self::assertTrue(in_array("csp_nonce", (array) ($payload["helpers"] ?? []), true));
        self::assertTrue(in_array("db", (array) ($payload["helpers"] ?? []), true));
        self::assertTrue(in_array("ops:backup-plan", (array) ($payload["commands"] ?? []), true));
        self::assertTrue(in_array("project:acceptance", (array) ($payload["commands"] ?? []), true));
        self::assertTrue(in_array("developer:install-storage", (array) ($payload["commands"] ?? []), true));
        self::assertTrue(in_array("query_builder.paginate", (array) ($payload["data"] ?? []), true));
        self::assertTrue(in_array("developer.analytics", (array) ($payload["data"] ?? []), true));
        self::assertTrue(in_array("developer.analytics_settings", (array) ($payload["data"] ?? []), true));
    }

    public function testDeveloperPanelStorageInstallerProvidesDatabaseContract(): void
    {
        $installer = new DeveloperPanelStorageInstaller();
        $sql = implode("\n", $installer->statements());
        $tables = $installer->tables();

        self::assertSame("fnlla_developer_activity_log", $tables["activity_log"] ?? null);
        self::assertSame("fnlla_developer_workspace_state", $tables["workspace_state"] ?? null);
        self::assertStringContainsString("fnlla_developer_notifications", $sql);
        self::assertStringContainsString("acknowledged_at", $sql);
        self::assertStringContainsString("archived_at", $sql);
        self::assertStringContainsString("fnlla_developer_analytics_events", $sql);
        self::assertStringContainsString("event_hash", $sql);
    }

    public function testTechAyoRemoteControlPluginDescribesPublicContract(): void
    {
        config_set("developer_control.remote.enabled", true);
        config_set("developer_control.remote.endpoint", "https://techayo.co.uk/admin/fnlla/projects/qwerty/control.json?secret=hidden");
        config_set("developer_control.remote.project_id", "qwerty");
        config_set("developer_control.remote.tenant", "techayo");
        config_set("developer_control.remote.signature_secret", "do-not-leak");

        $manifest = (new TechAyoRemoteControlPlugin())->manifest();
        $encoded = json_encode($manifest, JSON_THROW_ON_ERROR);

        self::assertSame("fnlla.remote_control_plugin.v1", $manifest["schema"] ?? null);
        self::assertSame("TechAyo Limited", $manifest["provider"] ?? null);
        self::assertSame("ready", $manifest["status"] ?? null);
        self::assertSame("https://techayo.co.uk/admin", $manifest["admin_surface"] ?? null);
        self::assertSame("https://techayo.co.uk/admin/fnlla/projects/qwerty/control.json", $manifest["fnlla_runtime_contract"]["endpoint"] ?? null);
        self::assertStringContainsString("X-FNLLA-Control-Signature", $encoded);
        self::assertStringNotContainsString("secret=hidden", $encoded);
        self::assertStringNotContainsString("do-not-leak", $encoded);
    }

    public function testDeveloperWorkspaceBoardTracksKanbanDeliveryMetadata(): void
    {
        $path = "framework/developer/workspace-test-" . bin2hex(random_bytes(4)) . ".json";
        config_set("developer_workspace.driver", "file");
        config_set("developer_workspace.path", $path);

        $board = new DeveloperWorkspaceBoard();
        $board->create([
            "title" => "Prepare client handover",
            "status" => "in_progress",
            "priority" => "high",
            "type" => "release",
            "assignee" => "dev@example.com",
            "due_date" => gmdate("Y-m-d", strtotime("+2 days")),
            "estimate" => "45m",
            "blocked" => true,
            "checklist" => "[x] Confirm routes\n[ ] Run release checks",
        ], ["email" => "dev@example.com"]);

        $state = $board->state(["email" => "dev@example.com"]);
        $task = $state["columns_with_tasks"]["in_progress"][0] ?? [];
        $taskId = (string) ($task["id"] ?? "");

        $board->update($taskId, [
            "title" => "Prepare client handover",
            "status" => "review",
            "position" => 250,
            "priority" => "urgent",
            "type" => "release",
            "color" => "sky",
            "assignee" => "dev@example.com",
            "due_date" => gmdate("Y-m-d", strtotime("+2 days")),
            "estimate" => "45m",
            "blocked" => true,
            "checklist" => "[x] Confirm routes\n[ ] Run release checks",
            "subtask" => "Capture QA screenshot",
            "subtask_color" => "sky",
            "comment" => "Client handover needs a final browser pass.",
            "attachment_label" => "Release checklist",
            "attachment_url" => "https://example.test/release-checklist",
            "attachment_added_by" => "lead@example.com",
        ], ["email" => "lead@example.com"]);
        $updatedState = $board->state(["email" => "dev@example.com"]);
        $updatedTask = [];
        foreach ((array) ($updatedState["columns_with_tasks"]["review"] ?? []) as $candidate) {
            if (($candidate["id"] ?? "") === $taskId) {
                $updatedTask = $candidate;
                break;
            }
        }

        self::assertSame("fnlla.developer_workspace.v1", $state["schema"] ?? null);
        self::assertSame(1, $state["my_tasks_count"] ?? null);
        self::assertSame(1, $state["blocked_tasks_count"] ?? null);
        self::assertSame(1, $state["due_soon_count"] ?? null);
        self::assertSame("release", $task["type"] ?? null);
        self::assertSame("45m", $task["estimate"] ?? null);
        self::assertSame(2, count((array) ($task["checklist"] ?? [])));
        self::assertSame("review", $updatedTask["status"] ?? null);
        self::assertSame(250.0, (float) ($updatedTask["position"] ?? 0.0));
        self::assertSame("urgent", $updatedTask["priority"] ?? null);
        self::assertSame("lead@example.com", $updatedTask["updated_by"] ?? null);
        self::assertSame(3, count((array) ($updatedTask["checklist"] ?? [])));
        self::assertSame(1, $updatedState["comments_count"] ?? null);
        self::assertSame(1, $updatedState["attachments_count"] ?? null);

        @unlink(storage_path($path));
    }

    public function testBackupPlanIsRedactedAndProductionActionable(): void
    {
        config_set("database.connections.mysql.password", "do-not-leak");

        $plan = (new BackupPlanBuilder())->build();
        $encoded = json_encode($plan, JSON_THROW_ON_ERROR);

        self::assertSame("fnlla.backup_plan.v1", $plan["schema"] ?? null);
        self::assertStringContainsString("mysqldump --single-transaction", (string) ($plan["database"]["recommended_dump"] ?? ""));
        self::assertStringContainsString("mysql --default-character-set", (string) ($plan["database"]["recommended_restore"] ?? ""));
        self::assertStringContainsString("security:audit --strict", implode(" ", (array) ($plan["restore_order"] ?? [])));
        self::assertStringContainsString("project:acceptance --json", implode(" ", (array) ($plan["verification"] ?? [])));
        self::assertStringNotContainsString("do-not-leak", $encoded);

        $verification = (new BackupPlanBuilder())->verify($plan);
        self::assertSame("fnlla.backup_plan_verification.v1", $verification["schema"] ?? null);
        self::assertTrue((bool) ($verification["ok"] ?? false), json_encode($verification, JSON_PRETTY_PRINT));
    }

    public function testProjectAcceptanceReportCoversProjectRuntimeHttpAndStorage(): void
    {
        $report = (new ProjectAcceptanceReportBuilder())->build();
        $checks = [];

        foreach ((array) ($report["checks"] ?? []) as $check) {
            $checks[$check["id"]] = $check;
        }

        self::assertSame("fnlla.project_acceptance.v1", $report["schema"] ?? null);
        self::assertTrue((bool) ($report["ok"] ?? false), json_encode($report, JSON_PRETTY_PRINT));
        self::assertSame("pass", $checks["http.home"]["status"] ?? null);
        self::assertSame("pass", $checks["http.api_health"]["status"] ?? null);
        self::assertSame("pass", $checks["storage.cache"]["status"] ?? null);
    }

    public function testReleaseArtifactBuilderWritesCycloneDxSbomAndChecksums(): void
    {
        $builder = new ReleaseArtifactBuilder();
        $directory = storage_path("framework/cache/release-artifacts-" . bin2hex(random_bytes(4)));
        $sbomPath = $directory . DIRECTORY_SEPARATOR . "sbom.json";
        $checksumsPath = $directory . DIRECTORY_SEPARATOR . "SHA256SUMS";
        $manifestPath = $directory . DIRECTORY_SEPARATOR . "release-manifest.json";

        $sbom = $builder->buildSbom($sbomPath);
        $checksums = $builder->buildChecksums($checksumsPath);
        $manifest = $builder->buildManifest($manifestPath, [
            "sbom" => $sbomPath,
            "checksums" => $checksumsPath,
        ]);

        self::assertFileExists($sbomPath);
        self::assertFileExists($checksumsPath);
        self::assertFileExists($manifestPath);
        self::assertTrue($sbom["components"] > 0);
        self::assertTrue($checksums["files"] > 0);
        self::assertSame(2, $manifest["artifacts"]);
        self::assertStringContainsString("README.md", (string) file_get_contents($checksumsPath));

        @unlink($sbomPath);
        @unlink($checksumsPath);
        @unlink($manifestPath);
        @rmdir($directory);
    }

    public function testReleaseManifestIncludesKeyedSignatureWhenSigningKeyIsConfigured(): void
    {
        $previousEnv = [$_ENV["RELEASE_SIGNING_KEY"] ?? null, $_ENV["RELEASE_SIGNING_KEY_ID"] ?? null];
        $_ENV["RELEASE_SIGNING_KEY"] = "test-signing-key";
        $_ENV["RELEASE_SIGNING_KEY_ID"] = "fnlla-test-key";
        putenv("RELEASE_SIGNING_KEY=test-signing-key");
        putenv("RELEASE_SIGNING_KEY_ID=fnlla-test-key");

        $builder = new ReleaseArtifactBuilder();
        $directory = storage_path("framework/cache/release-signature-" . bin2hex(random_bytes(4)));
        $sbomPath = $directory . DIRECTORY_SEPARATOR . "sbom.json";
        $checksumsPath = $directory . DIRECTORY_SEPARATOR . "SHA256SUMS";
        $manifestPath = $directory . DIRECTORY_SEPARATOR . "release-manifest.json";

        try {
            $builder->buildSbom($sbomPath);
            $builder->buildChecksums($checksumsPath);
            $result = $builder->buildManifest($manifestPath, [
                "sbom" => $sbomPath,
                "checksums" => $checksumsPath,
            ]);
            $payload = json_decode((string) file_get_contents($manifestPath), true);

            self::assertTrue($result["signed"]);
            self::assertSame("hmac-sha256", $payload["signature"]["algorithm"] ?? null);
            self::assertSame("fnlla-test-key", $payload["signature"]["key_id"] ?? null);
            self::assertArrayHasKey("payload_sha256", (array) ($payload["signature"] ?? []));
        } finally {
            if ($previousEnv[0] === null) {
                unset($_ENV["RELEASE_SIGNING_KEY"], $_SERVER["RELEASE_SIGNING_KEY"]);
                putenv("RELEASE_SIGNING_KEY");
            } else {
                $_ENV["RELEASE_SIGNING_KEY"] = $previousEnv[0];
                putenv("RELEASE_SIGNING_KEY=" . $previousEnv[0]);
            }

            if ($previousEnv[1] === null) {
                unset($_ENV["RELEASE_SIGNING_KEY_ID"], $_SERVER["RELEASE_SIGNING_KEY_ID"]);
                putenv("RELEASE_SIGNING_KEY_ID");
            } else {
                $_ENV["RELEASE_SIGNING_KEY_ID"] = $previousEnv[1];
                putenv("RELEASE_SIGNING_KEY_ID=" . $previousEnv[1]);
            }

            @unlink($sbomPath);
            @unlink($checksumsPath);
            @unlink($manifestPath);
            @rmdir($directory);
        }
    }
}
