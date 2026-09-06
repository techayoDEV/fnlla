<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA SUPPORT SOURCE
File: src\Support\EnvironmentFileManager.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Writes project-local environment values so downstream maintenance setup can
  enable preview protection directly from the project setup surface.
*/

namespace Fnlla\Php\Support;

use RuntimeException;

final class EnvironmentFileManager
{
    public function envPath(): string
    {
        return (string) config("maintenance.env_path", env_file_path());
    }

    public function envExamplePath(): string
    {
        return (string) config("maintenance.env_example_path", base_path(".env.example"));
    }

    public function envExists(): bool
    {
        return is_file($this->envPath());
    }

    public function isWritable(): bool
    {
        $envPath = $this->envPath();

        if (is_file($envPath)) {
            return is_writable($envPath);
        }

        $directory = dirname($envPath);

        return is_dir($directory) && is_writable($directory);
    }

    public function write(array $values): void
    {
        $this->writeCompared($values, []);
    }

    /** Reject stale credential updates while sharing the lock with ordinary writes. */
    public function writeCompared(array $values, array $expected): void
    {
        $this->withLock(function () use ($values, $expected): void {
            $contents = is_file($this->envPath()) ? (string) file_get_contents($this->envPath()) : "";
            $current = [];
            foreach (preg_split('/\R/', $contents) ?: [] as $line) {
                $line = trim($line);
                if ($line === "" || str_starts_with($line, "#")) {
                    continue;
                }
                if (str_starts_with($line, "export ")) {
                    $line = trim(substr($line, 7));
                }
                [$key, $value] = array_pad(explode("=", $line, 2), 2, "");
                $key = trim($key);
                if (array_key_exists($key, $current) && array_key_exists($key, $expected)) {
                    throw new RuntimeException("Duplicate credential configuration. Resolve it before recovery.");
                }
                $current[$key] = Env::parseValue(trim($value));
            }
            foreach ($expected as $key => $value) {
                if (!isset($current[$key]) || !hash_equals((string) $value, $current[$key])) {
                    throw new RuntimeException("Configuration changed. Request a fresh recovery link.");
                }
            }
            $this->writeUnlocked($values);
        });
    }

    private function writeUnlocked(array $values): void
    {
        $envPath = $this->envPath();

        if (!$this->isWritable()) {
            throw new RuntimeException("The project .env file is not writable from this environment.");
        }

        $this->ensureEnvFileExists();
        $contents = (string) file_get_contents($envPath);

        foreach ($values as $key => $value) {
            if (!is_string($key) || $key === "") {
                continue;
            }

            $serialized = $this->serializeValue($value);
            $pattern = '/^[\t ]*(?:export[\t ]+)?' . preg_quote($key, '/') . '[\t ]*=.*$/m';

            if (preg_match($pattern, $contents) === 1) {
                $contents = (string) preg_replace_callback(
                    $pattern,
                    static fn (): string => $key . "=" . $serialized,
                    $contents,
                    1
                );
                continue;
            }

            if ($contents !== "" && !str_ends_with($contents, PHP_EOL)) {
                $contents .= PHP_EOL;
            }

            $contents .= $key . "=" . $serialized . PHP_EOL;
        }

        $this->replaceContents($contents);
    }

    public function apply(array $values): void
    {
        foreach ($values as $key => $value) {
            if (!is_string($key) || $key === "") {
                continue;
            }

            $resolved = is_bool($value)
                ? ($value ? "true" : "false")
                : (string) $value;

            putenv($key . "=" . $resolved);
            $_ENV[$key] = $resolved;
            $_SERVER[$key] = $resolved;
        }
    }

    /**
     * @param array<array-key, mixed> $keys Values are validated before constructing the removal pattern.
     */
    public function remove(array $keys): void
    {
        $this->withLock(fn () => $this->removeUnlocked($keys));
    }

    private function removeUnlocked(array $keys): void
    {
        $envPath = $this->envPath();

        if (!is_file($envPath)) {
            return;
        }

        if (!$this->isWritable()) {
            throw new RuntimeException("The project .env file is not writable from this environment.");
        }

        $contents = (string) file_get_contents($envPath);

        foreach ($keys as $key) {
            if (!is_string($key) || $key === "") {
                continue;
            }

            $contents = (string) preg_replace('/^' . preg_quote($key, '/') . '=.*(?:\R|$)/m', "", $contents);
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);
        }

        if ($contents !== "" && !str_ends_with($contents, PHP_EOL)) {
            $contents .= PHP_EOL;
        }

        $this->replaceContents($contents);
    }

    private function withLock(callable $action): void
    {
        $lock = fopen($this->envPath() . ".lock", "c+b");
        if ($lock === false) {
            throw new RuntimeException("Cannot open environment lock.");
        }
        try {
            if (!flock($lock, LOCK_EX)) {
                throw new RuntimeException("Cannot lock environment configuration.");
            }
            $action();
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function replaceContents(string $contents): void
    {
        $path = $this->envPath();
        $temporary = tempnam(dirname($path), ".env-");
        if ($temporary === false) {
            throw new RuntimeException("Cannot stage environment configuration.");
        }
        try {
            $permissions = fileperms($path);
            if (!chmod($temporary, $permissions === false ? 0600 : ($permissions & 0777))
                || file_put_contents($temporary, $contents) !== strlen($contents)
                || !rename($temporary, $path)) {
                throw new RuntimeException("Unable to atomically write the project .env file.");
            }
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
    }

    private function ensureEnvFileExists(): void
    {
        $envPath = $this->envPath();

        if (is_file($envPath)) {
            return;
        }

        $examplePath = $this->envExamplePath();

        if (is_file($examplePath)) {
            if (!copy($examplePath, $envPath)) {
                throw new RuntimeException("Unable to create the project .env file from .env.example.");
            }

            return;
        }

        if (file_put_contents($envPath, "") === false) {
            throw new RuntimeException("Unable to create the project .env file.");
        }
    }

    private function serializeValue(mixed $value): string
    {
        $stringValue = is_bool($value)
            ? ($value ? "true" : "false")
            : trim((string) $value);

        if (str_contains($stringValue, "\r") || str_contains($stringValue, "\n") || str_contains($stringValue, "\0")) {
            throw new RuntimeException("Environment values cannot contain line breaks or null bytes.");
        }

        if ($stringValue === "" || preg_match('~^[A-Za-z0-9_.:\\\\/@$|;,+-]+$~', $stringValue) === 1) {
            return $stringValue;
        }

        return '"' . str_replace('"', '\"', $stringValue) . '"';
    }
}
