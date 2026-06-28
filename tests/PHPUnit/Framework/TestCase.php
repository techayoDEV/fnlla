<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA PHP TEST HARNESS SOURCE
File: tests\PHPUnit\Framework\TestCase.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA PHP is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA PHP framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Implements the repository-local test harness used by FNLLA PHP without Packagist dependencies.
*/

namespace PHPUnit\Framework;

use ReflectionMethod;
use RuntimeException;
use Throwable;

abstract class TestCase
{
    private static int $assertionCount = 0;
    private ?string $expectedException = null;

    public static function assertionCount(): int
    {
        return self::$assertionCount;
    }

    public static function resetAssertionCount(): void
    {
        self::$assertionCount = 0;
    }

    public function expectException(string $exceptionClass): void
    {
        $this->expectedException = $exceptionClass;
    }

    public function runTestMethod(string $method): void
    {
        $this->expectedException = null;

        try {
            $this->setUp();
            $reflectionMethod = new ReflectionMethod($this, $method);
            $reflectionMethod->invoke($this);

            if ($this->expectedException !== null) {
                self::fail("Expected exception {$this->expectedException} was not thrown.");
            }
        } catch (Throwable $exception) {
            if ($this->expectedException !== null && is_a($exception, $this->expectedException)) {
                self::incrementAssertions();
                return;
            }

            throw $exception;
        } finally {
            $this->tearDown();
        }
    }

    protected function setUp(): void
    {
    }

    protected function tearDown(): void
    {
    }

    public static function assertSame(mixed $expected, mixed $actual, string $message = ""): void
    {
        self::incrementAssertions();

        if ($expected !== $actual) {
            self::fail($message !== "" ? $message : "Failed asserting that two values are the same. Expected " . self::export($expected) . " got " . self::export($actual) . ".");
        }
    }

    public static function assertNotSame(mixed $expected, mixed $actual, string $message = ""): void
    {
        self::incrementAssertions();

        if ($expected === $actual) {
            self::fail($message !== "" ? $message : "Failed asserting that two values are not the same.");
        }
    }

    public static function assertTrue(bool $condition, string $message = ""): void
    {
        self::incrementAssertions();

        if ($condition !== true) {
            self::fail($message !== "" ? $message : "Failed asserting that condition is true.");
        }
    }

    public static function assertFalse(bool $condition, string $message = ""): void
    {
        self::incrementAssertions();

        if ($condition !== false) {
            self::fail($message !== "" ? $message : "Failed asserting that condition is false.");
        }
    }

    public static function assertArrayHasKey(string|int $key, array $array, string $message = ""): void
    {
        self::incrementAssertions();

        if (!array_key_exists($key, $array)) {
            self::fail($message !== "" ? $message : "Failed asserting that array has key " . self::export($key) . ".");
        }
    }

    public static function assertStringContainsString(string $needle, string $haystack, string $message = ""): void
    {
        self::incrementAssertions();

        if (!str_contains($haystack, $needle)) {
            self::fail($message !== "" ? $message : "Failed asserting that string contains " . self::export($needle) . ".");
        }
    }

    public static function assertFileExists(string $path, string $message = ""): void
    {
        self::incrementAssertions();

        if (!is_file($path)) {
            self::fail($message !== "" ? $message : "Failed asserting that file exists: {$path}");
        }
    }

    public static function assertInstanceOf(string $expectedClass, mixed $actual, string $message = ""): void
    {
        self::incrementAssertions();

        if (!$actual instanceof $expectedClass) {
            self::fail($message !== "" ? $message : "Failed asserting that value is instance of {$expectedClass}.");
        }
    }

    public static function assertIsString(mixed $value, string $message = ""): void
    {
        self::incrementAssertions();

        if (!is_string($value)) {
            self::fail($message !== "" ? $message : "Failed asserting that value is a string.");
        }
    }

    public static function fail(string $message): never
    {
        throw new RuntimeException($message);
    }

    private static function incrementAssertions(): void
    {
        self::$assertionCount++;
    }

    private static function export(mixed $value): string
    {
        return var_export($value, true);
    }
}
