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
  enable preview protection directly from the starter surface.
*/

namespace Fnlla\Php\Support;

use RuntimeException;

final class EnvironmentFileManager
{
    public function envPath(): string
    {
        return (string) config("maintenance.env_path", base_path(".env"));
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
            $pattern = '/^' . preg_quote($key, '/') . '=.*$/m';

            if (preg_match($pattern, $contents) === 1) {
                $contents = (string) preg_replace($pattern, $key . "=" . $serialized, $contents, 1);
                continue;
            }

            if ($contents !== "" && !str_ends_with($contents, PHP_EOL)) {
                $contents .= PHP_EOL;
            }

            $contents .= $key . "=" . $serialized . PHP_EOL;
        }

        if (file_put_contents($envPath, $contents) === false) {
            throw new RuntimeException("Unable to write the project .env file.");
        }
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

        return $stringValue;
    }
}
