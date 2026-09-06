<?php

declare(strict_types=1);

namespace Fnlla\Php\Tests;

use Fnlla\Php\Support\PhpArrayCache;
use PHPUnit\Framework\TestCase;

final class BootstrapCacheTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . "/fnlla-bootstrap-" . bin2hex(random_bytes(8));
        mkdir($this->directory, 0700);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . "/*") ?: [] as $path) { unlink($path); }
        foreach (glob($this->directory . "/.bootstrap-*") ?: [] as $path) { unlink($path); }
        rmdir($this->directory);
    }

    public function testInvalidConfigurationPreservesThePreviouslyPublishedCache(): void
    {
        $path = $this->directory . "/config.php";
        PhpArrayCache::write($path, ["generation" => "old"]);
        foreach ([["object" => new \stdClass()], ["value" => INF], ["value" => NAN], ["closure" => static fn (): bool => true]] as $payload) {
            try {
                PhpArrayCache::write($path, $payload);
                self::fail("Invalid cache was published.");
            } catch (\RuntimeException) {
                self::assertSame(["generation" => "old"], require $path);
            }
        }
        PhpArrayCache::write($path, ["generation" => "new", "nested" => [false, null, 1.25]]);
        self::assertSame("new", (require $path)["generation"]);
        self::assertSame([], glob($this->directory . "/.bootstrap-*") ?: []);
    }

    public function testRouteProfileIsPublishedWithItsDataAndLegacyCachesRemainReadable(): void
    {
        $path = $this->directory . "/routes.php";
        PhpArrayCache::write($path, ["schema" => "fnlla.routes.v1", "profile" => "plain", "routes" => [["path" => "/fixture"]]]);
        file_put_contents($path . ".profile", "full");
        self::assertSame([["path" => "/fixture"]], PhpArrayCache::routes($path, "plain"));
        self::assertSame(null, PhpArrayCache::routes($path, "full"));
        PhpArrayCache::write($path, [["path" => "/legacy"]]);
        self::assertSame([["path" => "/legacy"]], PhpArrayCache::routes($path, "full"));
        self::assertSame(null, PhpArrayCache::routes($path, "plain"));
    }

    public function testConcurrentReadersNeverObservePartiallyWrittenPhp(): void
    {
        $path = $this->directory . "/concurrent.php";
        PhpArrayCache::write($path, ["generation" => 0, "padding" => str_repeat("x", 50000)]);
        $script = '<?php require ' . var_export(base_path("src/Support/PhpArrayCache.php"), true)
            . '; $published = 0; $last = 0; for ($i=1; $i<=80; $i++) { try { \\Fnlla\\Php\\Support\\PhpArrayCache::write('
            . var_export($path, true) . ', ["generation"=>$i,"padding"=>str_repeat("x",50000)]); $published++; $last=$i; }
            catch (RuntimeException $error) { if (PHP_OS_FAMILY !== "Windows" || $error->getMessage() !== "Cannot publish bootstrap cache; previous cache preserved.") { throw $error; } } }
            echo json_encode(["published"=>$published,"last"=>$last]);';
        file_put_contents($this->directory . "/writer.php", $script);
        $process = proc_open([PHP_BINARY, $this->directory . "/writer.php"],
            [0 => ["pipe", "r"], 1 => ["file", $this->directory . "/writer.log", "a"], 2 => ["file", $this->directory . "/writer.log", "a"]], $pipes);
        self::assertTrue(is_resource($process));
        fclose($pipes[0]);
        $reads = 0;
        $deadline = microtime(true) + 60;
        try {
            do {
                $payload = require $path;
                if (!is_int($payload["generation"] ?? null) || strlen($payload["padding"] ?? "") !== 50000) {
                    self::fail("Reader observed an incomplete bootstrap cache.");
                }
                $reads++;
                usleep(1000);
                $status = proc_get_status($process);
            } while ($status["running"] && microtime(true) < $deadline);
            self::assertFalse($status["running"], "Bootstrap cache publisher timed out.");
            $closed = proc_close($process);
            $process = null;
            self::assertSame(0, $status["exitcode"] >= 0 ? $status["exitcode"] : $closed, (string) file_get_contents($this->directory . "/writer.log"));
            self::assertTrue($reads > 0);
            $publication = json_decode((string) file_get_contents($this->directory . "/writer.log"), true, 512, JSON_THROW_ON_ERROR);
            self::assertTrue($publication["published"] > 0, "No concurrent publication succeeded.");
            self::assertSame($publication["last"], (require $path)["generation"]);
            // Bounded Windows lock failures are allowed; the last successful file must survive.
            // Once competing readers stop, a fresh publication must still work.
            PhpArrayCache::write($path, ["generation" => 80, "padding" => str_repeat("x", 50000)]);
            self::assertSame(80, (require $path)["generation"]);
            self::assertSame([], glob($this->directory . "/.bootstrap-*") ?: []);
        } finally {
            if (is_resource($process)) { proc_terminate($process); proc_close($process); }
        }
    }
}
