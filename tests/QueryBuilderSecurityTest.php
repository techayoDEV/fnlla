<?php

declare(strict_types=1);

namespace Fnlla\Php\Tests;

use Fnlla\Php\Database\QueryBuilder;
use PDO;
use ReflectionClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class QueryBuilderSecurityTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = (new ReflectionClass(PDO::class))->newInstanceWithoutConstructor();
    }

    public function testRejectsInvalidTableNames(): void
    {
        $this->expectException(RuntimeException::class);

        new QueryBuilder($this->pdo, "users; DROP TABLE users");
    }

    public function testRejectsInvalidWhereIdentifiers(): void
    {
        $builder = new QueryBuilder($this->pdo, "users");

        $this->expectException(RuntimeException::class);
        $builder->where("email; DELETE FROM users", "x");
    }

    public function testRejectsUnsupportedOperators(): void
    {
        $builder = new QueryBuilder($this->pdo, "users");

        $this->expectException(RuntimeException::class);
        $builder->where("email", "or 1=1 --", "x");
    }
}
