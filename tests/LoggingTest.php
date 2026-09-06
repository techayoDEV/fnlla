<?php

declare(strict_types=1);

namespace Fnlla\Php\Tests;

use Fnlla\Php\Support\Logger;
use PHPUnit\Framework\TestCase;

final class LoggingTest extends TestCase
{
    public function testUntrustedContextIsBoundedRedactedAndNeverInvokesSerializers(): void
    {
        $previous = config("app.log_path");
        $path = storage_path("framework/testing/logging-" . bin2hex(random_bytes(6)) . ".log");
        config_set("app.log_path", $path);
        $recursive = [];
        $recursive["self"] = &$recursive;
        $object = new class implements \JsonSerializable {
            public function jsonSerialize(): mixed { throw new \RuntimeException("Do not invoke arbitrary code"); }
        };
        try {
            Logger::write("info", "Invalid UTF-8: \xff", ["password" => "must-not-leak", "cyclic" => $recursive, "object" => $object]);
            $contents = (string) file_get_contents($path);
            $data = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
            self::assertSame("INFO", $data["level"]);
            self::assertStringNotContainsString("must-not-leak", $contents);
            self::assertStringContainsString("[depth limit]", $contents);
            self::assertTrue(strlen($contents) < 4000);
        } finally {
            config_set("app.log_path", $previous);
            if (is_file($path)) { unlink($path); }
        }
    }
}
