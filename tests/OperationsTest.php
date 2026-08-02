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
use Fnlla\Php\Console\Commands\DoctorCommand;
use Fnlla\Php\Console\Commands\SecurityAuditCommand;
use Fnlla\Php\Container\Container;
use Fnlla\Php\Exceptions\ExceptionHandler;
use Fnlla\Php\Http\Request;
use Fnlla\Php\Http\Response;
use Fnlla\Php\Routing\Router;
use Fnlla\Php\Support\DoctorReport;
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
        self::assertSame("security:audit", (new SecurityAuditCommand($container))->name());
    }

    public function testReleaseArtifactBuilderWritesCycloneDxSbomAndChecksums(): void
    {
        $builder = new ReleaseArtifactBuilder();
        $directory = storage_path("framework/cache/release-artifacts-" . bin2hex(random_bytes(4)));
        $sbomPath = $directory . DIRECTORY_SEPARATOR . "sbom.json";
        $checksumsPath = $directory . DIRECTORY_SEPARATOR . "SHA256SUMS";

        $sbom = $builder->buildSbom($sbomPath);
        $checksums = $builder->buildChecksums($checksumsPath);

        self::assertFileExists($sbomPath);
        self::assertFileExists($checksumsPath);
        self::assertTrue($sbom["components"] > 0);
        self::assertTrue($checksums["files"] > 0);
        self::assertStringContainsString("README.md", (string) file_get_contents($checksumsPath));

        @unlink($sbomPath);
        @unlink($checksumsPath);
        @rmdir($directory);
    }
}
