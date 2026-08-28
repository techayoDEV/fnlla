<?php

declare(strict_types=1);

namespace Fnlla\Php\Session;

use Redis;
use RuntimeException;
use SessionHandlerInterface;

final class RedisSessionHandler implements SessionHandlerInterface
{
    private Redis $redis;
    private string $prefix;
    private int $ttlSeconds;

    public function __construct(array $config, int $ttlSeconds)
    {
        if (!class_exists(Redis::class)) {
            throw new RuntimeException("Redis session handler requires the ext-redis PHP extension.");
        }

        $this->prefix = (string) ($config["prefix"] ?? "fnlla:session:");
        $this->ttlSeconds = max(1, $ttlSeconds);
        $this->redis = new Redis();
        $this->redis->connect((string) ($config["host"] ?? "127.0.0.1"), (int) ($config["port"] ?? 6379), (float) ($config["timeout"] ?? 1.5));

        $password = (string) ($config["password"] ?? "");
        if ($password !== "") {
            $this->redis->auth($password);
        }

        $database = (int) ($config["database"] ?? 0);
        if ($database > 0) {
            $this->redis->select($database);
        }
    }

    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string|false
    {
        $value = $this->redis->get($this->key($id));

        return $value === false ? "" : (string) $value;
    }

    public function write(string $id, string $data): bool
    {
        return (bool) $this->redis->setex($this->key($id), $this->ttlSeconds, $data);
    }

    public function destroy(string $id): bool
    {
        $this->redis->del($this->key($id));

        return true;
    }

    public function gc(int $max_lifetime): int|false
    {
        return 0;
    }

    private function key(string $id): string
    {
        return $this->prefix . preg_replace('/[^A-Za-z0-9_.:-]+/', ":", $id);
    }
}
