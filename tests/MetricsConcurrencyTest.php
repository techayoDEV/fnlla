<?php

declare(strict_types=1);

namespace Fnlla\Php\Tests;

use Fnlla\Php\Http\Request;
use Fnlla\Php\Http\Response;
use Fnlla\Php\Observability\MetricsRecorder;
use PHPUnit\Framework\TestCase;

final class MetricsConcurrencyTest extends TestCase
{
    private array $config;
    private string $relative;
    private string $directory;
    private string $path;
    private array $workers = [];

    protected function setUp(): void
    {
        $this->config = config();
        $this->relative = "framework/metrics-concurrency-" . bin2hex(random_bytes(8));
        $this->directory = storage_path($this->relative);
        mkdir($this->directory, 0700, true);
        $this->path = $this->directory . "/metrics.json";
        config_set("observability.metrics.path", $this->relative . "/metrics.json");
        config_set("observability.metrics.enabled", true);
        file_put_contents($this->directory . "/worker.php", <<<'PHP'
<?php
define("FNLLA_RUNTIME_SKIP_AUTO_GUARD", true);
require $argv[1];
$directory = storage_path($argv[2]);
config_set("observability.metrics.path", $argv[2] . "/metrics.json");
config_set("observability.metrics.enabled", true);
config_set("observability.analytics.sample_rate", 100);
config_set("observability.heatmap.enabled", true);
config_set("observability.heatmap.sample_rate", 100);
config_set("modules.analytics", true);
config_set("modules.heatmap", true);
config_set("app.log_path", $directory . "/app.log");
config_set("debug.history.path", $directory . "/history.json");
config_set("debug.runtime_issues.path", $directory . "/issues.json");
if ($argv[3] === "debug") {
    $account = ["email" => "metrics@example.test", "name" => "Metrics", "role" => "admin", "password_hash" => password_hash("fixture", PASSWORD_DEFAULT)];
    config_set("developer_access.enabled", true);
    config_set("developer_access.users", developer_access()->serializeAccounts([$account]));
    developer_access()->grantAccess($account);
}
$recorder = new \Fnlla\Php\Observability\MetricsRecorder();
file_put_contents($argv[4] . ".ready", "ready");
switch ($argv[3]) {
    case "snapshot":
        $result = $recorder->snapshot()["total_requests"] ?? 0;
        break;
    case "analytics":
        $result = (new \Fnlla\Php\Support\DeveloperAnalyticsReport())->build()["summary"]["total_requests"];
        break;
    case "heatmap":
        $result = (new \Fnlla\Php\Support\DeveloperHeatmapReport())->build()["summary"]["behavior_events"];
        break;
    case "operations":
        $result = (new \Fnlla\Php\Support\DeveloperOperationsReport())->build()["analytics"]["total_requests"];
        break;
    case "debug":
        $response = (new \Fnlla\Php\Controllers\DeveloperDebugController())->live(new \Fnlla\Php\Http\Request("GET", "/developer/panel/debug/live"), developer_access());
        if ($response->status() !== 200) { throw new \RuntimeException("Debug reader was not authorized."); }
        $result = json_decode($response->body(), true, 512, JSON_THROW_ON_ERROR)["report"]["metrics"]["total_requests"];
        break;
    case "clear":
        $recorder->clear();
        $result = $recorder->snapshot();
        break;
    case "write":
        for ($i = 0; $i < 25; $i++) {
            $recorder->record(new \Fnlla\Php\Http\Request("GET", "/"), \Fnlla\Php\Http\Response::html("ok"), 1);
            $recorder->recordConsent(["analytics" => true]);
            $recorder->recordBehaviorEvent(["type" => "view", "path" => "/"]);
        }
        $result = "done";
        break;
    case "observe":
        $previous = 0;
        for ($i = 0; $i < 100; $i++) {
            $snapshot = $recorder->snapshot();
            $current = $snapshot["total_requests"] ?? 0;
            if ($current < $previous || $current > 75) { throw new \RuntimeException("Inconsistent concurrent metrics snapshot."); }
            $previous = $current;
            usleep(1000);
        }
        $result = "done";
        break;
    default:
        throw new \RuntimeException("Unknown metrics test worker.");
}
file_put_contents($argv[4], json_encode($result, JSON_THROW_ON_ERROR));
PHP);
    }

    protected function tearDown(): void
    {
        foreach ($this->workers as $worker) {
            if (is_resource($worker["process"])) {
                if (proc_get_status($worker["process"])["running"]) { proc_terminate($worker["process"]); }
                proc_close($worker["process"]);
            }
        }
        $GLOBALS["fnlla_config"] = $this->config;
        $GLOBALS["fnlla_php_config"] = $this->config;
        foreach (glob($this->directory . "/*") ?: [] as $file) { unlink($file); }
        foreach (glob($this->directory . "/.state-*") ?: [] as $file) { unlink($file); }
        rmdir($this->directory);
    }

    public function testEveryPanelMetricsReaderWaitsForTheWriter(): void
    {
        $lock = fopen($this->path . ".lock", "c+b");
        flock($lock, LOCK_EX);
        $writer = fopen($this->path, "c+b");
        flock($writer, LOCK_EX);
        // Pause a write between truncation and completion. No reader may see this state.
        fwrite($writer, "{");
        $readers = [];
        try {
            foreach (["snapshot", "analytics", "heatmap", "operations", "debug"] as $reader) {
                $readers[] = $this->startWorker($reader);
            }
            foreach ($readers as $id) { $this->waitForFile($this->workers[$id]["result"] . ".ready"); }
            usleep(150000);
            foreach ($readers as $id) { self::assertFileDoesNotExist($this->workers[$id]["result"]); }
            rewind($writer);
            ftruncate($writer, 0);
            fwrite($writer, json_encode([
                "total_requests" => 42, "analytics_total_requests" => 42,
                "heatmap_page_counts" => ["/" => 42],
            ], JSON_THROW_ON_ERROR));
            fflush($writer);
        } finally {
            flock($writer, LOCK_UN);
            fclose($writer);
            flock($lock, LOCK_UN);
            fclose($lock);
        }
        foreach ($readers as $id) { self::assertSame(42, $this->finishWorker($id)); }
    }

    public function testConcurrentRequestsConsentAndBehaviorEventsAreNotLost(): void
    {
        $writers = [];
        for ($i = 0; $i < 3; $i++) { $writers[] = $this->startWorker("write"); }
        $writers[] = $this->startWorker("observe");
        foreach ($writers as $id) { self::assertSame("done", $this->finishWorker($id)); }
        $snapshot = (new MetricsRecorder())->snapshot();
        self::assertSame(75, $snapshot["total_requests"]);
        self::assertSame(75, $snapshot["consent_events_total"]);
        self::assertSame(75, $snapshot["behavior_events_total"]);
        self::assertSame(75, $snapshot["heatmap_page_counts"]["/"]);
        self::assertSame([], glob($this->directory . "/.state-*") ?: []);
    }

    public function testClearWaitsForTheWriterAndKeepsTheExistingLock(): void
    {
        file_put_contents($this->path, '{"total_requests":42}');
        $lock = fopen($this->path . ".lock", "c+b");
        flock($lock, LOCK_EX);
        fwrite($lock, "existing lock");
        fflush($lock);
        try {
            $id = $this->startWorker("clear");
            $this->waitForFile($this->workers[$id]["result"] . ".ready");
            usleep(150000);
            self::assertFileExists($this->path);
            self::assertFileDoesNotExist($this->workers[$id]["result"]);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
        self::assertSame([], $this->finishWorker($id));
        self::assertFileDoesNotExist($this->path);
        self::assertSame("existing lock", file_get_contents($this->path . ".lock"));
        (new MetricsRecorder())->recordConsent(["analytics" => true]);
        self::assertSame(1, (new MetricsRecorder())->snapshot()["consent_events_total"]);
    }

    public function testFailedPublicationPreservesExistingLargeMetrics(): void
    {
        $recorder = new MetricsRecorder();
        $padding = str_repeat("x", 2097153);
        file_put_contents($this->path, json_encode(["existing_aggregate" => $padding]));
        $recorder->recordConsent(["analytics" => true]);
        self::assertSame($padding, $recorder->snapshot()["existing_aggregate"]);
        $original = file_get_contents($this->path);
        try {
            $recorder->record(new Request("GET", "/"), Response::html("ok"), NAN);
            self::fail("Non-finite metrics were published.");
        } catch (\JsonException) {
            self::assertSame($original, file_get_contents($this->path));
        }
        self::assertSame([], glob($this->directory . "/.state-*") ?: []);
    }

    public function testCorruptMetricsArePreservedUntilExplicitlyCleared(): void
    {
        file_put_contents($this->path, "invalid json");
        $recorder = new MetricsRecorder();
        try {
            $recorder->recordConsent(["analytics" => true]);
            self::fail("Corrupt metrics were silently overwritten.");
        } catch (\JsonException) {
            self::assertSame("invalid json", file_get_contents($this->path));
        }
        $recorder->clear();
        self::assertSame([], $recorder->snapshot());
    }

    private function startWorker(string $mode): int
    {
        $id = count($this->workers);
        $result = $this->directory . "/result-" . $id . ".json";
        $errors = $this->directory . "/errors-" . $id . ".log";
        $process = proc_open([PHP_BINARY, $this->directory . "/worker.php", base_path("tests/bootstrap.php"), $this->relative, $mode, $result],
            [0 => ["pipe", "r"], 1 => ["file", $errors, "a"], 2 => ["file", $errors, "a"]], $pipes);
        if (!is_resource($process)) { throw new \RuntimeException("Cannot start metrics test worker."); }
        fclose($pipes[0]);
        $this->workers[] = ["process" => $process, "result" => $result, "errors" => $errors];
        return $id;
    }

    private function waitForFile(string $path): void
    {
        $deadline = microtime(true) + 15;
        do {
            clearstatcache(true, $path);
            if (is_file($path)) { return; }
            usleep(10000);
        } while (microtime(true) < $deadline);
        self::fail("Timed out waiting for metrics worker: " . basename($path));
    }

    private function finishWorker(int $id): mixed
    {
        $worker = $this->workers[$id];
        $deadline = microtime(true) + 30;
        do {
            $status = proc_get_status($worker["process"]);
            if (!$status["running"]) { break; }
            usleep(10000);
        } while (microtime(true) < $deadline);
        self::assertFalse($status["running"], "Metrics worker timed out.");
        $exitCode = proc_close($worker["process"]);
        self::assertSame(0, $status["exitcode"] >= 0 ? $status["exitcode"] : $exitCode, (string) file_get_contents($worker["errors"]));
        self::assertFileExists($worker["result"]);
        return json_decode((string) file_get_contents($worker["result"]), true, 512, JSON_THROW_ON_ERROR);
    }
}
