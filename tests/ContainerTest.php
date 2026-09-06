<?php

declare(strict_types=1);

namespace Fnlla\Php\Tests;

use Fnlla\Php\Container\Container;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ContainerTest extends TestCase
{
    public function testCycleReportsDependencyChainAndDoesNotPoisonLaterResolution(): void
    {
        $container = new Container();
        $container->bind("first", static fn (Container $c): mixed => $c->make("second"));
        $container->bind("second", static fn (Container $c): mixed => $c->make("first"));
        try {
            $container->make("first");
            self::fail("Cycle was not detected.");
        } catch (RuntimeException $error) {
            self::assertStringContainsString("first -> second -> first", $error->getMessage());
        }
        $container->bind("second", static fn (): string => "repaired");
        self::assertSame("repaired", $container->make("first"));
    }

    public function testRebindingInvalidatesSingletonAndPreservesExplicitDefaults(): void
    {
        $container = new Container();
        $container->singleton("version", static fn (): int => 1);
        self::assertSame(1, $container->make("version"));
        $container->bind("version", static fn (): int => 2);
        self::assertSame(2, $container->make("version"));
        self::assertSame(null, $container->call(static fn (?\DateTimeImmutable $clock = null): ?\DateTimeImmutable => $clock));
        $clock = new \DateTimeImmutable("2026-01-01");
        $container->instance(\DateTimeImmutable::class, $clock);
        self::assertSame($clock, $container->call(static fn (?\DateTimeImmutable $clock = null): ?\DateTimeImmutable => $clock));
    }

    public function testVariadicArgumentsUseRemainingPositionalsOrNamedArray(): void
    {
        $container = new Container();
        $join = static fn (string $prefix, string ...$names): string => $prefix . implode(",", $names);
        self::assertSame("team:a,b", $container->call($join, ["team:", "a", "b"]));
        self::assertSame("team:a,b", $container->call($join, ["prefix" => "team:", "names" => ["a", "b"]]));
        self::assertSame("team:", $container->call($join, ["team:"]));
    }
}
