<?php

declare(strict_types=1);

namespace Fnlla\Php\Tests;

use Fnlla\Php\Auth\AuthManager;
use Fnlla\Php\Auth\Authorization\Gate;
use Fnlla\Php\Auth\UserProviderInterface;
use Fnlla\Php\Container\Container;
use Fnlla\Php\Hashing\Hasher;
use Fnlla\Php\Http\Request;
use Fnlla\Php\Session\SessionStore;
use PHPUnit\Framework\TestCase;

final class AuthorizationGateTest extends TestCase
{
    public function testGatePassesTypedArgumentsIntoAbilityCallbacks(): void
    {
        $container = new Container();
        $gate = new Gate($container, $this->makeAuthManager());
        $request = Request::capture("", [
            "REQUEST_URI" => "/reports",
            "REQUEST_METHOD" => "GET",
        ]);

        $gate->define("view-reports", static function (?array $user, Request $request): bool {
            return $user !== null && $request->path() === "/reports";
        });

        self::assertTrue($gate->allows("view-reports", $request));
    }

    private function makeAuthManager(): AuthManager
    {
        $_SESSION = [
            (string) config("auth.session_key", "auth.user_id") => 7,
        ];

        $provider = new class implements UserProviderInterface {
            public function findById(string|int $id): ?array
            {
                return [
                    "id" => $id,
                    "email" => "user@example.com",
                ];
            }

            public function findByCredentials(array $credentials): ?array
            {
                return null;
            }
        };

        return new AuthManager(new SessionStore(), $provider, new Hasher());
    }
}
