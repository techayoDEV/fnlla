<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA PHP CACHE SOURCE
File: src\Cache\FileCacheStore.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA PHP is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA PHP framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Implements maintained cache and rate-limiting primitives for the framework runtime.
*/

namespace Fnlla\Php\Cache;

final class FileCacheStore implements CacheStoreInterface
{
    public function __construct(private string $directory)
    {
        if (!is_dir($this->directory)) {
            mkdir($this->directory, 0777, true);
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $payload = $this->read($key);

        return $payload["value"] ?? $default;
    }

    public function put(string $key, mixed $value, int $ttlSeconds = 3600): bool
    {
        return $this->write($key, [
            "expires_at" => time() + max(1, $ttlSeconds),
            "value" => $value,
        ]);
    }

    public function remember(string $key, int $ttlSeconds, callable $callback): mixed
    {
        $value = $this->get($key, null);

        if ($value !== null) {
            return $value;
        }

        $value = $callback();
        $this->put($key, $value, $ttlSeconds);

        return $value;
    }

    public function forget(string $key): bool
    {
        $path = $this->path($key);

        return !is_file($path) || unlink($path);
    }

    public function clear(): bool
    {
        $files = glob($this->directory . DIRECTORY_SEPARATOR . "*.cache");

        if ($files === false) {
            return true;
        }

        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        return true;
    }

    public function increment(string $key, int $value = 1, int $ttlSeconds = 3600): int
    {
        $current = (int) $this->get($key, 0);
        $current += $value;
        $this->put($key, $current, $ttlSeconds);

        return $current;
    }

    public function decrement(string $key, int $value = 1, int $ttlSeconds = 3600): int
    {
        return $this->increment($key, $value * -1, $ttlSeconds);
    }

    private function read(string $key): ?array
    {
        $path = $this->path($key);

        if (!is_file($path)) {
            return null;
        }

        $contents = file_get_contents($path);

        if (!is_string($contents) || $contents === "") {
            return null;
        }

        $payload = @unserialize($contents);

        if (!is_array($payload)) {
            return null;
        }

        if (($payload["expires_at"] ?? 0) < time()) {
            unlink($path);

            return null;
        }

        return $payload;
    }

    private function write(string $key, array $payload): bool
    {
        return file_put_contents($this->path($key), serialize($payload), LOCK_EX) !== false;
    }

    private function path(string $key): string
    {
        return $this->directory . DIRECTORY_SEPARATOR . sha1($key) . ".cache";
    }
}
