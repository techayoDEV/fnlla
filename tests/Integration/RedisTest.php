<?php

declare(strict_types=1);

namespace Fnlla\Php\Tests\Integration;

use Fnlla\Php\Cache\RedisCacheStore;
use Fnlla\Php\Queue\RedisQueueStore;
use PHPUnit\Framework\TestCase;

final class RedisTest extends TestCase
{
    private \Redis $redis;
    private array $settings;
    private string $prefix;

    protected function setUp(): void
    {
        if (!getenv("FNLLA_TEST_REDIS_HOST")) {
            self::markTestSkipped("Set FNLLA_TEST_REDIS_HOST for Redis integration tests.");
        }
        if (!extension_loaded("redis")) {
            self::fail("Configured Redis integration requires ext-redis.");
        }
        $this->prefix = "fnlla:test:" . bin2hex(random_bytes(8)) . ":";
        $this->settings = ["host" => getenv("FNLLA_TEST_REDIS_HOST"), "port" => (int) (getenv("FNLLA_TEST_REDIS_PORT") ?: 6379), "prefix" => $this->prefix];
        $this->redis = new \Redis();
        $this->redis->connect($this->settings["host"], $this->settings["port"], 2);
    }

    protected function tearDown(): void
    {
        if (!isset($this->redis)) { return; }
        // Never flush a shared database; only delete this test's random namespace.
        foreach ($this->redis->keys($this->prefix . "*") as $key) { $this->redis->del($key); }
        $this->redis->close();
    }

    public function testCacheRoundTripsValuesAndClearIsPrefixScoped(): void
    {
        $cache = new RedisCacheStore($this->settings);
        $outside = $this->prefix . "outside:key";
        $this->redis->set($outside, "preserve");
        $scoped = new RedisCacheStore(array_merge($this->settings, ["prefix" => $this->prefix . "cache:"]));
        self::assertTrue($scoped->put("value", ["answer" => 42], 60));
        self::assertSame(["answer" => 42], $scoped->get("value"));
        self::assertSame(1, $cache->increment("counter"));
        self::assertSame(2, $cache->increment("counter"));
        self::assertTrue($scoped->clear());
        self::assertSame("preserve", $this->redis->get($outside));
        self::assertSame(null, $scoped->get("value"));
    }

    public function testQueueSuccessAndFailureAreRecorded(): void
    {
        $queue = new RedisQueueStore($this->settings);
        $id = $queue->push("SyntheticJob", ["value" => 1]);
        self::assertSame(1, $queue->pendingCount());
        $job = $queue->pop();
        self::assertSame($id, $job["id"]);
        self::assertSame(["value" => 1], $job["payload"]);
        $queue->complete($job);
        self::assertSame(null, $queue->pop());
        $queue->push("FailedSyntheticJob");
        $queue->fail($queue->pop());
        self::assertSame(1, $queue->failedCount());
    }

    public function testQueueLeaseRecoveryRejectsStaleAckAndHonorsRetryDelay(): void
    {
        $previous = (array) config("queue", []);
        config_set("queue.max_attempts", 3);
        config_set("queue.visibility_timeout_seconds", 1);
        config_set("queue.retry_backoff_seconds", 1);
        try {
            $first = new RedisQueueStore($this->settings);
            $second = new RedisQueueStore($this->settings);
            $id = $first->push("Synthetic", ["key" => 42]);
            $old = $first->pop();
            self::assertNull($second->pop());
            sleep(2);
            $new = $second->pop();
            self::assertSame($id, $new["id"]);
            self::assertSame(2, $new["attempts"]);
            try { $first->complete($old); self::fail("Stale acknowledgement accepted"); }
            catch (\RuntimeException $error) { self::assertStringContainsString("reservation", $error->getMessage()); }
            $second->fail($new);
            self::assertNull($second->pop());
            sleep(2);
            $last = $second->pop();
            self::assertSame(3, $last["attempts"]);
            sleep(2);
            self::assertNull($first->pop());
            self::assertSame(1, $first->failedCount());
            self::assertSame(0, $first->pendingCount());
        } finally { config_set("queue", $previous); }
    }
}
