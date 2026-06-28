<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA PHP TEST CASE
File: tests\ValidationTest.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA PHP is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA PHP framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Validates maintained framework behavior inside the repository-local test harness.
*/

namespace Fnlla\Php\Tests;

use Fnlla\Php\Validation\ValidationException;
use Fnlla\Php\Validation\Validator;
use PHPUnit\Framework\TestCase;

final class ValidationTest extends TestCase
{
    public function testValidatorReturnsValidatedPayload(): void
    {
        $validated = Validator::make([
            "email" => "dev@example.com",
            "password" => "secret-pass",
        ], [
            "email" => ["required", "email"],
            "password" => ["required", "string", "min:8"],
        ])->validate();

        self::assertSame("dev@example.com", $validated["email"]);
        self::assertSame("secret-pass", $validated["password"]);
    }

    public function testValidatorThrowsWithFieldErrors(): void
    {
        $this->expectException(ValidationException::class);

        try {
            Validator::make([
                "email" => "bad",
            ], [
                "email" => ["required", "email"],
                "password" => ["required", "string", "min:8"],
            ])->validate();
        } catch (ValidationException $exception) {
            self::assertArrayHasKey("email", $exception->errors());
            self::assertArrayHasKey("password", $exception->errors());
            throw $exception;
        }
    }
}
