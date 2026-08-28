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
use Fnlla\Php\Console\Commands\DoctorCommand;
use Fnlla\Php\Console\Commands\PublicApiLockCommand;
use Fnlla\Php\Console\Commands\SecurityAuditCommand;
use Fnlla\Php\Container\Container;
use Fnlla\Php\Exceptions\ExceptionHandler;
use Fnlla\Php\Http\Request;
use Fnlla\Php\Http\Response;
use Fnlla\Php\Routing\Router;
use Fnlla\Php\Support\DoctorReport;
use Fnlla\Php\Support\BackupPlanBuilder;
use Fnlla\Php\Support\ReleaseArtifactBuilder;
use Fnlla\Php\Support\SecurityAuditReport;
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

        @unlink($absoluteMetricsPath);
        @unlink($absoluteMetricsPath . ".lock");
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
        self::assertSame("api:lock", (new PublicApiLockCommand($container))->name());
        self::assertSame("security:audit", (new SecurityAuditCommand($container))->name());
        self::assertSame("ops:backup-plan", (new BackupPlanCommand($container))->name());
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
        self::assertTrue(in_array("query_builder.paginate", (array) ($payload["data"] ?? []), true));
    }

    public function testBackupPlanIsRedactedAndProductionActionable(): void
    {
        config_set("database.connections.mysql.password", "do-not-leak");

        $plan = (new BackupPlanBuilder())->build();
        $encoded = json_encode($plan, JSON_THROW_ON_ERROR);

        self::assertSame("fnlla.backup_plan.v1", $plan["schema"] ?? null);
        self::assertStringContainsString("mysqldump --single-transaction", (string) ($plan["database"]["recommended_dump"] ?? ""));
        self::assertStringContainsString("security:audit --strict", implode(" ", (array) ($plan["restore_order"] ?? [])));
        self::assertStringNotContainsString("do-not-leak", $encoded);
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
