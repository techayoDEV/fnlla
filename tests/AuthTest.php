<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA TEST CASE
File: tests\AuthTest.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Validates maintained framework behaviour inside the repository-local test harness.
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

    public function testDeletedMismatchedAndCorruptIdentitiesFailClosed(): void
    {
        $provider = new class implements UserProviderInterface {
            public ?array $user = ["id" => 7, "role" => "admin"];
            public function findById(string|int $id): ?array { return $this->user; }
            public function findByCredentials(array $credentials): ?array { return null; }
        };
        $session = new SessionStore();
        $auth = new AuthManager($session, $provider, new Hasher());
        $key = (string) config("auth.session_key", "auth.user_id");
        $auth->login(["id" => 7]);
        self::assertTrue($auth->check());
        $provider->user = null;
        self::assertTrue($auth->guest());
        self::assertSame(null, $auth->id());
        $session->put($key, 7);
        $provider->user = ["id" => 8];
        self::assertSame(null, $auth->user());
        self::assertSame(null, $auth->id());
        foreach ([[], new \stdClass(), true, 1.5, "", "  "] as $id) {
            $session->put($key, $id);
            self::assertSame(null, $auth->id());
            self::assertFalse($session->has($key));
        }
        $provider->user = ["id" => "7", "role" => "reader"];
        $auth->login(["id" => 7]);
        self::assertSame("reader", $auth->user()["role"]);
        $provider->user["role"] = "editor";
        self::assertSame("editor", $auth->user()["role"]);
        $session->put("cart", [1]);
        $auth->logout();
        self::assertSame(null, $auth->id());
        self::assertSame([1], $session->get("cart"));
        $session->invalidate();
        self::assertFalse($session->has("cart"));
    }

    public function testLoginRejectsInvalidProviderIdentityWithoutReplacingCurrentUser(): void
    {
        $provider = new class implements UserProviderInterface {
            public function findById(string|int $id): ?array { return ["id" => $id]; }
            public function findByCredentials(array $credentials): ?array { return null; }
        };
        $auth = new AuthManager(new SessionStore(), $provider, new Hasher());
        $auth->login(["id" => 7]);
        foreach ([[], ["id" => null], ["id" => []], ["id" => ""], ["id" => false]] as $user) {
            try { $auth->login($user); self::fail("Invalid identity accepted."); }
            catch (\InvalidArgumentException) { self::assertSame(7, $auth->id()); }
        }
    }
}
