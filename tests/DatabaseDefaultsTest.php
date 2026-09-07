<?php

declare(strict_types=1);

namespace Fnlla\Php\Tests;

use Database\Factories\UserFactory;
use Database\Seeders\DatabaseSeeder;
use Fnlla\Php\Container\Container;
use Fnlla\Php\Console\Commands\SeedCommand;
use Fnlla\Php\Database\DatabaseManager;
use Fnlla\Php\Hashing\Hasher;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;

final class DatabaseDefaultsTest extends TestCase
{
    public function testSeedingRequiresExplicitConfirmationOutsideDevelopment(): void
    {
        $original = $GLOBALS["fnlla_config"]["app"]["environment"];
        $command = new SeedCommand(new Container());
        try {
            foreach (["production", "staging", "", "unknown"] as $environment) {
                $GLOBALS["fnlla_config"]["app"]["environment"] = $environment;
                try {
                    $command->handle([]);
                    self::fail("Non-local seeding must require --force.");
                } catch (\RuntimeException $error) {
                    self::assertStringContainsString("requires --force", $error->getMessage());
                }
            }
            self::assertSame(0, $command->handle(["--help"]));
            self::assertSame(0, $command->handle(["--force"]));
            foreach (["local", "development", "testing"] as $environment) {
                $GLOBALS["fnlla_config"]["app"]["environment"] = $environment;
                self::assertSame(0, $command->handle([]));
            }
        } finally {
            $GLOBALS["fnlla_config"]["app"]["environment"] = $original;
        }
    }

    public function testInvalidSeederInputIsRejectedBeforeExecution(): void
    {
        $command = new SeedCommand(new Container());
        foreach ([["--force=false"], ["--force", "--force"], ["--unknown"], ["One", "Two"]] as $arguments) {
            try {
                $command->handle($arguments);
                self::fail("Invalid seeder options must fail.");
            } catch (\InvalidArgumentException $error) {
                self::assertTrue($error->getMessage() !== "");
            }
        }
        $container = new Container();
        $container->bind(\stdClass::class, static function (): never {
            throw new \LogicException("Unrelated class must not be constructed.");
        });
        $this->expectException(\RuntimeException::class);
        (new SeedCommand($container))->handle([\stdClass::class, "--force"]);
    }

    public function testDefaultSeederDoesNotConnectOrCreateAnAccount(): void
    {
        $pdo = new NoSeedQueriesPdo();
        $seeder = new DatabaseSeeder(new Container(), DatabaseManager::using($pdo));
        $seeder->run();
        $seeder->run();
        self::assertSame(0, $pdo->queries);
    }

    public function testFactoryDefaultsHaveNoSharedPasswordOrElevatedRole(): void
    {
        $hasher = new Hasher();
        $factory = new UserFactory(new Container(), new DatabaseManager());
        $first = $factory->make();
        $second = $factory->make();
        self::assertSame("user", $first["role"]);
        self::assertNotSame($first["email"], $second["email"]);
        self::assertNotSame($first["password"], $second["password"]);
        self::assertFalse($hasher->check("password123", $first["password"]));
        self::assertFalse($hasher->needsRehash($first["password"]));
        self::assertSame($first["created_at"], $first["updated_at"]);
        $hash = $hasher->make("explicit-test-credential");
        self::assertSame($hash, $factory->make(["password" => $hash])["password"]);
    }
}

final class NoSeedQueriesPdo extends PDO
{
    public int $queries = 0;
    public function __construct() {}
    public function setAttribute(int $attribute, mixed $value): bool { return true; }
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        $this->queries++;
        throw new \LogicException("The default seeder must not access the database.");
    }
    public function exec(string $statement): int|false
    {
        $this->queries++;
        throw new \LogicException("The default seeder must not access the database.");
    }
}
