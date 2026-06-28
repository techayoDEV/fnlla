<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA PHP TEST CASE
File: tests\AuthTest.php
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

use Fnlla\Php\Auth\AuthManager;
use Fnlla\Php\Auth\UserProviderInterface;
use Fnlla\Php\Hashing\Hasher;
use Fnlla\Php\Session\SessionStore;
use PHPUnit\Framework\TestCase;

final class AuthTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    public function testAttemptLogsUserIntoSession(): void
    {
        $provider = new class implements UserProviderInterface {
            public function findById(string|int $id): ?array
            {
                return $id === 1 ? ["id" => 1, "email" => "dev@example.com"] : null;
            }

            public function findByCredentials(array $credentials): ?array
            {
                if (($credentials["email"] ?? null) !== "dev@example.com") {
                    return null;
                }

                return [
                    "id" => 1,
                    "email" => "dev@example.com",
                    "password" => password_hash("secret-pass", PASSWORD_DEFAULT),
                ];
            }
        };

        $auth = new AuthManager(new SessionStore(), $provider, new Hasher());

        self::assertTrue($auth->attempt([
            "email" => "dev@example.com",
            "password" => "secret-pass",
        ]));
        self::assertTrue($auth->check());
        self::assertSame(1, $auth->id());
    }
}
