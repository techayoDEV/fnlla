<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA PHP MAINTAINER SCRIPT
File: scripts\test.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA PHP is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA PHP framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Supports framework maintenance, validation, release hygiene or repository hardening.
*/

define("FNLLA_UI_SKIP_AUTO_GUARD", true);

require dirname(__DIR__) . DIRECTORY_SEPARATOR . "tests" . DIRECTORY_SEPARATOR . "bootstrap.php";
require dirname(__DIR__) . DIRECTORY_SEPARATOR . "tests" . DIRECTORY_SEPARATOR . "PHPUnit" . DIRECTORY_SEPARATOR . "Framework" . DIRECTORY_SEPARATOR . "TestCase.php";

use PHPUnit\Framework\TestCase;

/*
Local harness note:
- FNLLA PHP keeps a repository-local test runner so routine framework work does
  not depend on Packagist or an external PHPUnit install
- the namespace-compatible TestCase shim under `tests/PHPUnit/Framework/`
  preserves a familiar test authoring surface for maintainers
*/
$testFiles = glob(dirname(__DIR__) . DIRECTORY_SEPARATOR . "tests" . DIRECTORY_SEPARATOR . "*Test.php");

if ($testFiles === false) {
    fwrite(STDERR, "Unable to discover tests." . PHP_EOL);
    exit(1);
}

sort($testFiles);
TestCase::resetAssertionCount();

$results = [];
$totalTests = 0;

foreach ($testFiles as $file) {
    /* Load each test file once, then discover only the classes introduced by that file. */
    $beforeClasses = get_declared_classes();
    require_once $file;
    $afterClasses = get_declared_classes();
    $newClasses = array_values(array_diff($afterClasses, $beforeClasses));

    foreach ($newClasses as $className) {
        if (!is_subclass_of($className, TestCase::class)) {
            continue;
        }

        $reflection = new ReflectionClass($className);

        if ($reflection->isAbstract()) {
            continue;
        }

        $methods = array_filter(
            $reflection->getMethods(ReflectionMethod::IS_PUBLIC),
            static fn (ReflectionMethod $method): bool => str_starts_with($method->getName(), "test") && $method->getNumberOfRequiredParameters() === 0
        );

        usort($methods, static fn (ReflectionMethod $left, ReflectionMethod $right): int => strcmp($left->getName(), $right->getName()));

        foreach ($methods as $method) {
            $totalTests++;
            $instance = $reflection->newInstance();
            $testName = $reflection->getShortName() . "::" . $method->getName();

            try {
                $instance->runTestMethod($method->getName());
                $results[] = [
                    "name" => $testName,
                    "status" => "passed",
                ];
            } catch (Throwable $exception) {
                $results[] = [
                    "name" => $testName,
                    "status" => "failed",
                    "message" => $exception->getMessage(),
                    "file" => $exception->getFile(),
                    "line" => $exception->getLine(),
                ];
            }
        }
    }
}

$failed = array_values(array_filter($results, static fn (array $result): bool => $result["status"] === "failed"));

foreach ($results as $result) {
    $symbol = $result["status"] === "passed" ? "PASS" : "FAIL";
    fwrite(STDOUT, sprintf("[%s] %s", $symbol, $result["name"]) . PHP_EOL);

    if ($result["status"] === "failed") {
        fwrite(STDOUT, "       " . $result["message"] . PHP_EOL);
        fwrite(STDOUT, "       " . $result["file"] . ":" . $result["line"] . PHP_EOL);
    }
}

fwrite(STDOUT, PHP_EOL);

if ($failed !== []) {
    fwrite(STDOUT, sprintf("FAILED (%d tests, %d assertions, %d failures)", $totalTests, TestCase::assertionCount(), count($failed)) . PHP_EOL);
    exit(1);
}

fwrite(STDOUT, sprintf("OK (%d tests, %d assertions)", $totalTests, TestCase::assertionCount()) . PHP_EOL);
