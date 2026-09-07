<?php

declare(strict_types=1);

namespace Fnlla\Php\Tests;

use Fnlla\Php\Queue\FileQueueStore;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class QueueReservationTest extends TestCase
{
    private string $directory;
    private array $configuration;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . "/fnlla-queue-lease-" . bin2hex(random_bytes(8));
        $this->configuration = (array) config("queue", []);
        config_set("queue.max_attempts", 2);
        config_set("queue.visibility_timeout_seconds", 1);
        config_set("queue.retry_backoff_seconds", 1);
    }

    protected function tearDown(): void
    {
        config_set("queue", $this->configuration);
        if (!is_dir($this->directory)) { return; }
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->directory,
            \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) { $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname()); }
        rmdir($this->directory);
    }

    public function testExpiredReservationIsRecoveredAndOldWorkerCannotAcknowledge(): void
    {
        $first = new FileQueueStore($this->directory);
        $second = new FileQueueStore($this->directory);
        $id = $first->push("Synthetic", ["invoice_id" => 42]);
        $old = $first->pop();
        self::assertSame(null, $second->pop());
        self::assertSame(1, $first->pendingCount());
        sleep(2);
        // Only the abandoned lease should expire; acknowledgement is not a one-second performance test.
        config_set("queue.visibility_timeout_seconds", 30);
        $new = $second->pop();
        self::assertSame($id, $new["id"]);
        self::assertSame(2, $new["attempts"]);
        self::assertNotSame($old["reservation"], $new["reservation"]);
        try { $first->complete($old); self::fail("Stale acknowledgement accepted"); }
        catch (RuntimeException $exception) { self::assertStringContainsString("reservation", $exception->getMessage()); }
        $second->complete($new);
        self::assertSame(0, $first->pendingCount());
    }

    public function testRetryDelayAndExhaustedCrashAreQuarantined(): void
    {
        $queue = new FileQueueStore($this->directory);
        $queue->push("Synthetic");
        $queue->fail($queue->pop());
        self::assertSame(null, $queue->pop());
        sleep(2);
        self::assertSame(2, $queue->pop()["attempts"]);
        sleep(2);
        self::assertSame(null, $queue->pop());
        self::assertSame(1, $queue->failedCount());
        self::assertSame(0, $queue->pendingCount());
    }

    public function testProcessExitLeavesRecoverableReservation(): void
    {
        $queue = new FileQueueStore($this->directory);
        $id = $queue->push("Synthetic");
        $code = 'define("FNLLA_RUNTIME_SKIP_AUTO_GUARD", true); require $argv[1];'
            . 'config_set("queue.visibility_timeout_seconds", 1);'
            . '$q = new \\Fnlla\\Php\\Queue\\FileQueueStore($argv[2]); echo $q->pop()["id"]; exit(23);';
        $process = proc_open([PHP_BINARY, "-r", $code, base_path("tests/bootstrap.php"), $this->directory],
            [0 => ["pipe", "r"], 1 => ["pipe", "w"], 2 => ["pipe", "w"]], $pipes);
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        self::assertSame(23, proc_close($process), $errors);
        self::assertSame($id, $output);
        sleep(2);
        config_set("queue.visibility_timeout_seconds", 30);
        $recovered = $queue->pop();
        self::assertSame($id, $recovered["id"]);
        $queue->complete($recovered);
    }

    public function testParallelWorkersDoNotShareLiveReservations(): void
    {
        $queue = new FileQueueStore($this->directory);
        $expected = [];
        for ($i = 0; $i < 60; $i++) { $expected[] = $queue->push("Synthetic"); }
        $workers = [];
        for ($i = 0; $i < 4; $i++) {
            $process = proc_open([PHP_BINARY, base_path("tests/Integration/queue-reservation-worker.php"), $this->directory],
                [0 => ["pipe", "r"], 1 => ["pipe", "w"], 2 => ["pipe", "w"]], $pipes);
            fclose($pipes[0]);
            $workers[] = [$process, $pipes];
        }
        $actual = [];
        foreach ($workers as [$process, $pipes]) {
            $output = trim((string) stream_get_contents($pipes[1]));
            $errors = stream_get_contents($pipes[2]);
            fclose($pipes[1]); fclose($pipes[2]);
            self::assertSame(0, proc_close($process), $errors);
            if ($output !== "") { array_push($actual, ...explode("\n", $output)); }
        }
        sort($expected); sort($actual);
        self::assertSame($expected, $actual);
        self::assertSame(0, $queue->pendingCount());
    }

    public function testConfiguredBackendFailureDoesNotSilentlyCreateAFileQueue(): void
    {
        $previous = $GLOBALS["fnlla_container"];
        $container = new \Fnlla\Php\Container\Container();
        $container->bind(\Fnlla\Php\Queue\QueueStoreInterface::class,
            static function (): never { throw new RuntimeException("Synthetic Redis outage"); });
        $GLOBALS["fnlla_container"] = $container;
        try {
            $error = null;
            try { new \Fnlla\Php\Queue\QueueManager($container); }
            catch (RuntimeException $exception) { $error = $exception; }
            self::assertInstanceOf(RuntimeException::class, $error);
            self::assertSame("Synthetic Redis outage", $error->getMessage());
        } finally { $GLOBALS["fnlla_container"] = $previous; }
    }
}
