<?php

declare(strict_types=1);

namespace Fnlla\Php\Tests;

use Fnlla\Php\Application;
use Fnlla\Php\Container\Container;
use Fnlla\Php\Exceptions\ExceptionHandler;
use Fnlla\Php\Http\Request;
use Fnlla\Php\Observability\MetricsRecorder;
use Fnlla\Php\Support\DeveloperAnalyticsReport;
use Fnlla\Php\Support\DeveloperHeatmapReport;
use Fnlla\Php\Support\DeveloperOperationsReport;
use PHPUnit\Framework\TestCase;

final class MetricsReportAvailabilityTest extends TestCase
{
    private array $config;
    private array $session;
    private mixed $container;
    private string $directory;
    private string $path;
    private Application $application;

    protected function setUp(): void
    {
        $this->config = config();
        $this->session = $_SESSION;
        $this->container = $GLOBALS["fnlla_container"];
        $relative = "framework/metrics-report-" . bin2hex(random_bytes(8));
        $this->directory = storage_path($relative);
        mkdir($this->directory, 0700, true);
        $this->path = $this->directory . "/metrics.json";
        config_set("observability.metrics.path", $relative . "/metrics.json");
        config_set("observability.metrics.enabled", true);
        config_set("app.environment", "testing");
        config_set("app.log_path", $this->directory . "/app.log");
        config_set("debug.history.path", $this->directory . "/history.json");
        config_set("debug.runtime_issues.path", $this->directory . "/issues.json");
        config_set("developer_tools.debt_path", $this->directory . "/debt.json");
        config_set("developer_workspace.driver", "file");
        config_set("developer_workspace.path", $this->directory . "/workspace.json");
        config_set("modules.analytics", true);
        config_set("modules.heatmap", true);
        $_SESSION = [];
        $container = new Container();
        foreach ((array) config("app.providers") as $class) {
            $provider = new $class($container);
            $provider->register();
            $provider->boot();
        }
        $GLOBALS["fnlla_container"] = $container;
        $GLOBALS["fnlla_php_container"] = $container;
        $account = ["email" => "metrics@example.test", "name" => "Metrics QA", "role" => "admin",
            "password_hash" => password_hash("fixture", PASSWORD_DEFAULT)];
        config_set("developer_access.enabled", true);
        config_set("developer_access.path", "/developer");
        config_set("developer_access.users", developer_access()->serializeAccounts([$account]));
        developer_access()->grantAccess($account);
        $router = require base_path("bootstrap/router.php");
        $this->application = new Application($router, $container, $container->make(ExceptionHandler::class));
    }

    protected function tearDown(): void
    {
        $GLOBALS["fnlla_config"] = $this->config;
        $GLOBALS["fnlla_php_config"] = $this->config;
        $GLOBALS["fnlla_container"] = $this->container;
        $GLOBALS["fnlla_php_container"] = $this->container;
        $_SESSION = $this->session;
        unset($_SERVER["FNLLA_ROUTE_NAME"]);
        foreach (glob($this->directory . "/*") ?: [] as $file) { unlink($file); }
        rmdir($this->directory);
    }

    public function testCorruptionIsDistinctFromEmptyMetricsAndReportsRecover(): void
    {
        $recorder = new MetricsRecorder();
        $analytics = new DeveloperAnalyticsReport();
        $heatmap = new DeveloperHeatmapReport();
        self::assertSame([], $recorder->snapshotForReport());
        self::assertTrue($analytics->build()["metrics_available"]);
        self::assertSame(0, $analytics->build()["summary"]["total_requests"]);

        foreach (["{invalid json", "null", "42", ""] as $corrupt) {
            file_put_contents($this->path, $corrupt);
            self::assertNull($recorder->snapshotForReport());
            $report = $analytics->build();
            self::assertFalse($report["metrics_available"]);
            self::assertNull($report["summary"]["total_requests"]);
            self::assertSame([], $report["charts"]);
            self::assertSame([], $report["insights"]);
            self::assertSame([], $report["goals"]);
            self::assertNotSame([], $report["settings"]);
            $report = $heatmap->build();
            self::assertFalse($report["metrics_available"]);
            self::assertNull($report["summary"]["behavior_events"]);
            self::assertSame([], $report["charts"]);
            self::assertSame($corrupt, file_get_contents($this->path));
        }

        $operations = (new DeveloperOperationsReport())->build();
        self::assertFalse($operations["metrics_available"]);
        self::assertNull($operations["analytics"]["page_views"]);
        self::assertNull($operations["analytics"]["consent"]["backend_rate"]);
        self::assertNotSame([], $operations["performance"]["probes"]);
        self::assertNotSame([], $operations["release_readiness"]);
        self::assertArrayHasKey("recent_count", $operations["forms"]);

        file_put_contents($this->path, '{"analytics_total_requests":7,"heatmap_page_counts":{"/":4}}');
        self::assertTrue($analytics->build()["metrics_available"]);
        self::assertSame(7, $analytics->build()["summary"]["total_requests"]);
        self::assertTrue($heatmap->build()["metrics_available"]);
        self::assertSame(4, $heatmap->build()["summary"]["behavior_events"]);
    }

    public function testUnavailableStorageDoesNotMasqueradeAsAnEmptyStore(): void
    {
        // A directory in place of the lock reliably denies opening it on Windows and Unix.
        mkdir($this->path . ".lock");
        set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });
        try {
            self::assertNull((new MetricsRecorder())->snapshotForReport());
            self::assertFalse((new DeveloperAnalyticsReport())->build()["metrics_available"]);
            self::assertFileDoesNotExist($this->path);
        } finally {
            restore_error_handler();
            rmdir($this->path . ".lock");
        }
        self::assertSame([], (new MetricsRecorder())->snapshotForReport());

        mkdir($this->path);
        try {
            self::assertNull((new MetricsRecorder())->snapshotForReport());
            self::assertFalse((new DeveloperHeatmapReport())->build()["metrics_available"]);
        } finally {
            rmdir($this->path);
        }
    }

    public function testPanelPagesAndLiveDiagnosticsSurviveCorruptMetrics(): void
    {
        $corrupt = "{invalid metrics";
        file_put_contents($this->path, $corrupt);
        foreach (["analytics", "heatmap", "operations", "debug", "release-readiness", "integrations"] as $page) {
            $response = $this->application->handle(new Request("GET", "/developer/panel/" . $page));
            self::assertSame(200, $response->status(), $page);
            if (in_array($page, ["analytics", "heatmap", "operations", "debug"], true)) {
                self::assertStringContainsString("Metrics are unavailable.", $response->body(), $page);
            }
            if ($page === "analytics" || $page === "heatmap") {
                self::assertStringNotContainsString('aria-label="' . ucfirst($page) . ' summary"', $response->body());
                self::assertStringContainsString('aria-label="' . ucfirst($page) . ' settings"', $response->body());
            }
            if ($page === "debug") {
                self::assertStringContainsString("Recent error log", $response->body());
                self::assertStringContainsString('data-debug-total-requests>n/a</span>', $response->body());
            }
        }
        $response = $this->application->handle(new Request("GET", "/developer/panel/debug/live"));
        self::assertSame(200, $response->status());
        $payload = json_decode($response->body(), true, 512, JSON_THROW_ON_ERROR);
        self::assertFalse($payload["report"]["metrics_available"]);
        self::assertNull($payload["report"]["metrics"]["total_requests"]);
        self::assertArrayHasKey("history", $payload);
        self::assertArrayHasKey("runtime_issues", $payload);
        self::assertStringNotContainsString($this->path, $response->body());
        self::assertSame($corrupt, file_get_contents($this->path));

        $recorder = new MetricsRecorder();
        $recorder->clear();
        $response = $this->application->handle(new Request("GET", "/developer/panel/debug/live"));
        $payload = json_decode($response->body(), true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($payload["report"]["metrics_available"]);
        self::assertNotSame(null, $payload["report"]["metrics"]["total_requests"]);
    }

    public function testCustomerReportsDoNotDisplayZeroCountersWhenUnavailable(): void
    {
        file_put_contents($this->path, "{invalid metrics");
        $client = ["email" => "client@example.test", "name" => "Metrics QA", "company" => "Example",
            "permissions" => array_keys(customer_access()->permissionOptions()),
            "password_hash" => password_hash("fixture", PASSWORD_DEFAULT)];
        config_set("customer_access.enabled", true);
        config_set("customer_access.users", customer_access()->serializeAccounts([$client]));
        customer_access()->grantAccess($client);
        foreach (["analytics", "heatmap"] as $page) {
            $response = $this->application->handle(new Request("GET", "/client/panel/" . $page));
            self::assertSame(200, $response->status(), $page);
            self::assertStringContainsString("Metrics are unavailable.", $response->body());
            self::assertStringNotContainsString('aria-label="' . ucfirst($page) . ' metrics"', $response->body());
        }
    }
}
