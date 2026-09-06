<?php

declare(strict_types=1);

namespace Fnlla\Php\Tests\Interop;

use Fnlla\Php\Container\Container;
use Fnlla\Psr\ContainerAdapter;
use Fnlla\Psr\LoggerAdapter;
use PHPUnit\Framework\TestCase;

final class PsrAdaptersTest extends TestCase
{
    public function testMissingEntryImplementsPsrNotFoundContract(): void
    {
        $adapter = new ContainerAdapter(new Container());
        self::assertFalse($adapter->has("missing"));
        $this->expectException(\Psr\Container\NotFoundExceptionInterface::class);
        $adapter->get("missing");
    }

    public function testResolutionFailuresAreNotReportedAsMissingEntries(): void
    {
        $container = new Container();
        $container->instance("null", null);
        $container->bind("broken", static function (): void { throw new \RuntimeException("factory failed"); });
        $adapter = new ContainerAdapter($container);
        self::assertTrue($adapter->has("null"));
        self::assertNull($adapter->get("null"));
        try { $adapter->get("broken"); self::fail("Expected factory failure"); }
        catch (\Psr\Container\ContainerExceptionInterface $error) {
            self::assertFalse($error instanceof \Psr\Container\NotFoundExceptionInterface);
            self::assertSame("factory failed", $error->getPrevious()->getMessage());
        }
    }

    public function testLoggerUsesPsrLevelsAndRejectsUnknownLevel(): void
    {
        $path = storage_path("framework/testing/psr-" . bin2hex(random_bytes(6)) . ".log");
        $previous = config("app.log_path");
        config_set("app.log_path", $path);
        try {
            $logger = new LoggerAdapter();
            foreach (["debug", "info", "notice", "warning", "error", "critical", "alert", "emergency"] as $level) {
                $logger->$level("Message {password}", ["password" => "private-value"]);
            }
            $lines = file($path, FILE_IGNORE_NEW_LINES);
            self::assertCount(8, $lines);
            self::assertStringNotContainsString("private-value", implode("", $lines));
            $this->expectException(\Psr\Log\InvalidArgumentException::class);
            $logger->log("undefined", "message");
        } finally {
            config_set("app.log_path", $previous);
            if (is_file($path)) { unlink($path); }
        }
    }
}
