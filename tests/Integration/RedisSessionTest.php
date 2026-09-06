<?php

declare(strict_types=1);

namespace Fnlla\Php\Tests\Integration;

use Fnlla\Php\Session\RedisSessionHandler;
use PHPUnit\Framework\TestCase;

final class RedisSessionTest extends TestCase
{
    private \Redis $redis;
    private array $settings;
    private string $prefix;
    private array $handlers = [];

    protected function setUp(): void
    {
        if (!getenv("FNLLA_TEST_REDIS_HOST")) { self::markTestSkipped("Set FNLLA_TEST_REDIS_HOST for Redis session integration."); }
        if (!extension_loaded("redis")) { self::fail("Configured Redis integration requires ext-redis."); }
        $this->prefix = "fnlla:session-test:" . bin2hex(random_bytes(8)) . ":";
        $this->settings = ["host" => getenv("FNLLA_TEST_REDIS_HOST"), "port" => (int) (getenv("FNLLA_TEST_REDIS_PORT") ?: 6379),
            "prefix" => $this->prefix, "lock_wait_milliseconds" => 50, "lock_ttl_seconds" => 1];
        $this->redis = new \Redis();
        $this->redis->connect($this->settings["host"], $this->settings["port"], 2);
    }

    protected function tearDown(): void
    {
        if (!isset($this->redis)) { return; }
        foreach ($this->handlers as $handler) { $handler->close(); }
        foreach ($this->redis->keys($this->prefix . "*") as $key) { $this->redis->del($key); }
        $this->redis->close();
    }

    private function handler(int $ttl = 60): RedisSessionHandler
    {
        return $this->handlers[] = new RedisSessionHandler($this->settings, $ttl);
    }

    public function testStrictIdsRoundTripTouchAndDestruction(): void
    {
        $handler = $this->handler();
        foreach (["", "unknown", "with:colon", "with/slash", "with.dot", str_repeat("x", 257)] as $id) {
            self::assertFalse($handler->validateId($id));
        }
        $id = "session-123,ABC";
        self::assertSame("", $handler->read($id));
        self::assertTrue($handler->write($id, 'user|i:42;'));
        $handler->close();
        self::assertTrue($handler->validateId($id));
        self::assertSame('user|i:42;', $handler->read($id));
        $this->redis->expire($this->prefix . $id, 2);
        self::assertTrue($handler->updateTimestamp($id, 'user|i:42;'));
        self::assertGreaterThan(50, $this->redis->ttl($this->prefix . $id));
        self::assertTrue($handler->destroy($id));
        self::assertFalse($handler->validateId($id));
        self::assertFalse($handler->updateTimestamp($id, 'user|i:42;'));
        self::assertFalse($this->redis->exists($this->prefix . $id) > 0);
    }

    public function testCompetingReadersTimeOutAndResumeAfterClose(): void
    {
        $first = $this->handler();
        $second = $this->handler();
        $first->read("shared");
        $start = hrtime(true);
        try { $second->read("shared"); self::fail("Concurrent reader bypassed lock"); }
        catch (\RuntimeException $error) { self::assertStringContainsString("Timed out", $error->getMessage()); }
        self::assertLessThan(2000000000, hrtime(true) - $start);
        self::assertTrue($first->write("shared", "first"));
        $first->close();
        self::assertSame("first", $second->read("shared"));
        self::assertTrue($second->write("shared", "second"));
    }

    public function testExpiredOwnerCannotWriteDestroyTouchOrUnlockItsSuccessor(): void
    {
        $first = $this->handler();
        $second = $this->handler();
        $first->read("lease");
        $first->write("lease", "before");
        usleep(1100000);
        self::assertSame("before", $second->read("lease"));
        $second->write("lease", "after");
        foreach (["write", "destroy", "updateTimestamp", "read"] as $method) {
            try {
                in_array($method, ["write", "updateTimestamp"], true) ? $first->$method("lease", "stale") : $first->$method("lease");
                self::fail("Expired owner accepted: " . $method);
            } catch (\RuntimeException $error) { self::assertStringContainsString("lock lost", $error->getMessage()); }
        }
        $token = $this->redis->get($this->prefix . "lock:lease");
        $first->close();
        self::assertSame($token, $this->redis->get($this->prefix . "lock:lease"));
        self::assertSame("after", $this->redis->get($this->prefix . "lease"));
    }

    public function testInvalidIdsCannotAliasKeysOrLockKeys(): void
    {
        $handler = $this->handler();
        foreach (["a:b", "a/b", "a.b", "lock:a", "a\0b"] as $id) {
            try { $handler->read($id); self::fail("Invalid ID accepted"); }
            catch (\RuntimeException $error) { self::assertSame("Invalid session identifier.", $error->getMessage()); }
        }
        self::assertSame([], $this->redis->keys($this->prefix . "*"));
    }

    public function testRealHttpIdentityAndStrictCookieContract(): void
    {
        require_once dirname(__DIR__) . "/SessionHttpTest.php";
        $http = new \Fnlla\Php\Tests\SessionHttpTest("testPersistentIdentityRotationExpiryAndDomainIsolation");
        $http->verifyPersistentIdentity("redis", array_replace($this->settings, ["lock_ttl_seconds" => 60]));
    }

    public function testParallelProcessesSerializeWritesAndRecoverAfterTermination(): void
    {
        $root = sys_get_temp_dir() . "/fnlla-redis-process-" . bin2hex(random_bytes(8));
        mkdir($root, 0700);
        $processes = [];
        $environment = getenv();
        $environment["FNLLA_SESSION_WORKER_SETTINGS"] = json_encode(array_replace($this->settings, ["lock_wait_milliseconds" => 2000]), JSON_THROW_ON_ERROR);
        $extension = getenv("FNLLA_TEST_REDIS_EXTENSION") ? ["-d", "extension=" . getenv("FNLLA_TEST_REDIS_EXTENSION")] : [];
        $spawn = static function (string $mode, int $index) use ($root, $environment, $extension, &$processes): mixed {
            $process = proc_open([PHP_BINARY, ...$extension, __DIR__ . "/redis-session-worker.php", "parallel", $mode, $root . "/ready-" . $index],
                [0 => ["pipe", "r"], 1 => ["file", $root . "/out-" . $index, "w"], 2 => ["file", $root . "/err-" . $index, "w"]], $pipes, base_path(), $environment);
            self::assertTrue(is_resource($process));
            fclose($pipes[0]);
            $processes[$index] = $process;
            return $process;
        };
        try {
            $seed = $this->handler();
            $seed->read("parallel");
            $seed->write("parallel", "counter|i:0;");
            $seed->close();
            $first = $spawn("increment", 0);
            $second = $spawn("increment", 1);
            foreach ([$first, $second] as $index => $process) {
                self::assertSame(0, proc_close($process), (string) file_get_contents($root . "/err-" . $index));
                unset($processes[$index]);
            }
            $results = [json_decode((string) file_get_contents($root . "/out-0"), true), json_decode((string) file_get_contents($root . "/out-1"), true)];
            $counts = array_column($results, "counter");
            sort($counts);
            self::assertSame([1, 2], $counts);
            self::assertSame(["parallel", "parallel"], array_column($results, "id"));
            $crashed = $spawn("crash", 2);
            for ($i = 0; $i < 150 && !is_file($root . "/ready-2"); $i++) { usleep(20000); }
            self::assertFileExists($root . "/ready-2");
            proc_terminate($crashed, 9);
            proc_close($crashed);
            unset($processes[2]);
            usleep(1100000);
            $next = $this->handler();
            self::assertTrue($next->validateId("parallel"));
            self::assertSame("counter|i:2;", $next->read("parallel"));
        } finally {
            foreach ($processes as $process) { if (is_resource($process)) { proc_terminate($process, 9); proc_close($process); } }
            foreach (glob($root . "/*") ?: [] as $path) { unlink($path); }
            rmdir($root);
        }
    }
}
