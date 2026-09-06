<?php
declare(strict_types=1);
namespace Fnlla\Php\Tests;

use Fnlla\Php\Database\DatabaseManager;
use PHPUnit\Framework\TestCase;

final class NamedDatabaseConnectionTest extends TestCase
{
    public function testNamedConnectionsAreStableIndependentAndShareDefaultIdentity(): void
    {
        $manager = new DatabaseManager();
        $primary = new NamedConnectionPdo();
        $audit = new NamedConnectionPdo();
        $manager->registerConnection((string) config("database.default", "mysql"), $primary);
        $manager->registerConnection("audit", $audit);
        self::assertSame($primary, $manager->connection());
        self::assertSame($audit, $manager->connection("audit"));
        self::assertSame($manager->forConnection("audit"), $manager->forConnection("audit"));
        $primary->active = true;
        try {
            $manager->purge();
            self::fail("Active transaction was purged.");
        } catch (\RuntimeException) {
            self::assertSame($primary, $manager->connection());
        }
        $replacement = new NamedConnectionPdo();
        $manager->registerConnection("audit", $replacement);
        self::assertSame($replacement, $manager->connection("audit"));
        self::assertSame($primary, $manager->connection());
    }

    public function testMissingAndInvalidNamesFailWithoutDefaultFallback(): void
    {
        foreach (["missing-connection", "../mysql"] as $name) {
            try {
                (new DatabaseManager())->connection($name);
                self::fail("Invalid connection was accepted.");
            } catch (\RuntimeException $error) {
                self::assertStringContainsString("connection", $error->getMessage());
            }
        }
    }
}

final class NamedConnectionPdo extends \PDO
{
    public bool $active = false;
    public function __construct() {}
    public function setAttribute(int $attribute, mixed $value): bool { return true; }
    public function inTransaction(): bool { return $this->active; }
}
