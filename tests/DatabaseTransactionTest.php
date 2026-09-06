<?php

declare(strict_types=1);

namespace Fnlla\Php\Tests;

use Fnlla\Php\Database\DatabaseManager;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class DatabaseTransactionTest extends TestCase
{
    public function testNestedRollbackUsesSavepointAndOuterTransactionCanCommit(): void
    {
        $pdo = new TransactionRecordingPdo();
        $db = DatabaseManager::using($pdo);
        $result = $db->transaction(function () use ($db): int {
            try {
                $db->transaction(static function (): void { throw new RuntimeException("inner"); });
            } catch (RuntimeException $error) {
                self::assertSame("inner", $error->getMessage());
            }
            return 42;
        });
        self::assertSame(42, $result);
        self::assertSame("BEGIN", $pdo->calls[0]);
        self::assertStringContainsString("SAVEPOINT fnlla_", $pdo->calls[1]);
        self::assertSame("ROLLBACK TO " . $pdo->calls[1], $pdo->calls[2]);
        self::assertSame("RELEASE " . $pdo->calls[1], $pdo->calls[3]);
        self::assertSame("COMMIT", $pdo->calls[4]);
    }

    public function testExternallyOwnedTransactionIsNotCommitted(): void
    {
        $pdo = new TransactionRecordingPdo();
        $pdo->beginTransaction();
        DatabaseManager::using($pdo)->transaction(static fn (): string => "ok");
        self::assertTrue($pdo->inTransaction());
        self::assertSame(3, count($pdo->calls));
        self::assertStringContainsString("RELEASE SAVEPOINT", $pdo->calls[2]);
    }

    public function testDatabaseAbortedTransactionPreservesOriginalException(): void
    {
        $pdo = new TransactionRecordingPdo();
        try {
            DatabaseManager::using($pdo)->transaction(static function () use ($pdo): void {
                $pdo->active = false;
                throw new RuntimeException("deadlock");
            });
            self::fail("Expected deadlock.");
        } catch (RuntimeException $error) {
            self::assertSame("deadlock", $error->getMessage());
            self::assertSame(["BEGIN"], $pdo->calls);
        }
    }

    public function testCallbackMayNotSilentlyCommitTheTransaction(): void
    {
        $pdo = new TransactionRecordingPdo();
        $this->expectException(RuntimeException::class);
        DatabaseManager::using($pdo)->transaction(static function () use ($pdo): void { $pdo->commit(); });
    }
}

final class TransactionRecordingPdo extends PDO
{
    public array $calls = [];
    public bool $active = false;
    public function __construct() {}
    public function setAttribute(int $attribute, mixed $value): bool { return true; }
    public function inTransaction(): bool { return $this->active; }
    public function beginTransaction(): bool { $this->calls[] = "BEGIN"; return $this->active = true; }
    public function commit(): bool { $this->calls[] = "COMMIT"; $this->active = false; return true; }
    public function rollBack(): bool { $this->calls[] = "ROLLBACK"; $this->active = false; return true; }
    public function exec(string $statement): int|false { $this->calls[] = $statement; return 0; }
}
