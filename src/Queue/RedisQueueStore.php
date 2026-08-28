<?php

declare(strict_types=1);

namespace Fnlla\Php\Queue;

use Redis;
use RuntimeException;

final class RedisQueueStore implements QueueStoreInterface
{
    private Redis $redis;
    private string $pendingKey;
    private string $failedKey;

    public function __construct(array $config)
    {
        if (!class_exists(Redis::class)) {
            throw new RuntimeException("Redis queue store requires the ext-redis PHP extension.");
        }

        $prefix = (string) ($config["prefix"] ?? "fnlla:queue:");
        $this->pendingKey = $prefix . "pending";
        $this->failedKey = $prefix . "failed";
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

    public function push(string $jobClass, array $payload = []): string
    {
        $id = gmdate("YmdHis") . "_" . bin2hex(random_bytes(8));
        $this->redis->rPush($this->pendingKey, json_encode([
            "id" => $id,
            "job" => $jobClass,
            "payload" => $payload,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

        return $id;
    }

    public function pop(): ?array
    {
        $payload = $this->redis->lPop($this->pendingKey);

        if ($payload === false || $payload === null) {
            return null;
        }

        $decoded = json_decode((string) $payload, true);
        if (!is_array($decoded)) {
            throw new RuntimeException("Redis queued job payload is invalid JSON.");
        }

        return [
            "id" => (string) ($decoded["id"] ?? ""),
            "job" => (string) ($decoded["job"] ?? ""),
            "payload" => is_array($decoded["payload"] ?? null) ? $decoded["payload"] : [],
            "source" => $payload,
        ];
    }

    public function complete(array $job): void
    {
    }

    public function fail(array $job): string
    {
        $id = (string) ($job["id"] ?? bin2hex(random_bytes(4)));
        $this->redis->rPush($this->failedKey, json_encode($job, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

        return "redis:" . $id;
    }

    public function pendingCount(): int
    {
        return (int) $this->redis->lLen($this->pendingKey);
    }

    public function failedCount(): int
    {
        return (int) $this->redis->lLen($this->failedKey);
    }
}
