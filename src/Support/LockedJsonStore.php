<?php

declare(strict_types=1);

namespace Fnlla\Php\Support;

use RuntimeException;

final class LockedJsonStore
{
    public function __construct(private string $path)
    {
    }

    public function read(): array
    {
        return $this->locked(false, null);
    }

    public function update(callable $change): array
    {
        return $this->locked(true, $change);
    }

    private function locked(bool $write, ?callable $change): array
    {
        $directory = dirname($this->path);
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException("Cannot create private state directory.");
        }
        if (is_link($this->path) || is_link($this->path . ".lock")) {
            throw new RuntimeException("Private state cannot be a symbolic link.");
        }
        $lock = fopen($this->path . ".lock", "c+b");
        if ($lock === false) {
            throw new RuntimeException("Cannot open private state lock.");
        }
        $temporary = null;
        try {
            if (!flock($lock, $write ? LOCK_EX : LOCK_SH)) {
                throw new RuntimeException("Cannot lock private state.");
            }
            clearstatcache(true, $this->path);
            $state = [];
            if (is_file($this->path)) {
                if (filesize($this->path) > 2097152) {
                    throw new RuntimeException("Private state exceeds the size limit.");
                }
                $state = json_decode((string) file_get_contents($this->path), true, 512, JSON_THROW_ON_ERROR);
                if (!is_array($state)) {
                    throw new RuntimeException("Invalid private state.");
                }
            }
            if ($change !== null) {
                $state = $change($state);
                $json = json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
                if (strlen($json) > 2097152) {
                    throw new RuntimeException("Private state exceeds the size limit.");
                }
                $temporary = tempnam($directory, ".state-");
                if ($temporary === false || realpath(dirname($temporary)) !== realpath($directory)
                    || file_put_contents($temporary, $json) !== strlen($json)) {
                    throw new RuntimeException("Cannot atomically save private state.");
                }
                $published = false;
                for ($attempt = 0; $attempt < 50; $attempt++) {
                    if (@rename($temporary, $this->path)) { $published = true; break; }
                    if (PHP_OS_FAMILY !== "Windows") { break; }
                    // Windows may briefly deny replacement. Keep our lock and the old file.
                    usleep(10000);
                }
                if (!$published) {
                    throw new RuntimeException("Cannot publish private state; previous state preserved.");
                }
            }
            return $state;
        } finally {
            if (is_string($temporary) && is_file($temporary)) {
                unlink($temporary);
            }
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
