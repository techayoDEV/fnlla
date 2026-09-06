<?php

declare(strict_types=1);

namespace Fnlla\Php\Tests\Interop;

use Fnlla\Deploy\ReleaseStore;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class AtomicDeploymentTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . "/fnlla-atomic-" . bin2hex(random_bytes(8));
        mkdir($this->root . "/artifact/public", 0700, true);
        file_put_contents($this->root . "/artifact/public/index.php", "<?php echo 'release-one';");
        file_put_contents($this->root . "/artifact/public/app.css", "body {color: red;}");
        file_put_contents($this->root . "/artifact/.env", "SECRET=must-not-copy");
    }

    protected function tearDown(): void
    {
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root,
            \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) { $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname()); }
        rmdir($this->root);
    }

    public function testActivationFailureAndRollbackPreserveImmutableReleasesAndSharedData(): void
    {
        $store = new ReleaseStore($this->root . "/deployment");
        $one = $store->stage($this->root . "/artifact", "one");
        self::assertFileDoesNotExist($one . "/.env");
        file_put_contents($this->root . "/deployment/shared/.env", "SECRET=keep");
        self::assertSame("one", $store->activate("one", null, static fn (): bool => true)["current"]);
        file_put_contents($this->root . "/artifact/public/index.php", "<?php echo 'release-two';");
        $two = $store->stage($this->root . "/artifact", "two");
        try { $store->activate("two", "one", static fn (): bool => false); self::fail("Invalid release activated"); }
        catch (RuntimeException $error) { self::assertStringContainsString("validation", $error->getMessage()); }
        self::assertSame("one", $store->state()["current"]);
        $store->activate("two", "one", static fn (): bool => true);
        self::assertSame("<?php echo 'release-one';", file_get_contents($one . "/public/index.php"));
        self::assertSame("one", $store->rollback("two", static fn (): bool => true)["current"]);
        self::assertSame("SECRET=keep", file_get_contents($this->root . "/deployment/shared/.env"));
        file_put_contents($two . "/public/index.php", "tampered");
        $this->expectException(RuntimeException::class);
        $store->activate("two", "one", static fn (): bool => true);
    }

    public function testInterruptedValidationDoesNotChangeActivePointerAndLockIsReleased(): void
    {
        $store = new ReleaseStore($this->root . "/deployment");
        $store->stage($this->root . "/artifact", "one");
        $store->stage($this->root . "/artifact", "two");
        $store->activate("one", null, static fn (): bool => true);
        $code = 'require $argv[1]; $s=new \\Fnlla\\Deploy\\ReleaseStore($argv[2]);'
            . '$s->activate("two","one",static function():bool { exit(23); });';
        $process = proc_open([PHP_BINARY, "-r", $code, base_path("packages/fnlla-deploy/src/ReleaseStore.php"),
            $this->root . "/deployment"], [0 => ["pipe", "r"], 1 => ["pipe", "w"], 2 => ["pipe", "w"]], $pipes);
        fclose($pipes[0]);
        stream_get_contents($pipes[1]); $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        self::assertSame(23, proc_close($process), $errors);
        self::assertSame("one", $store->state()["current"]);
        self::assertSame("two", $store->activate("two", "one", static fn (): bool => true)["current"]);
        $this->expectException(RuntimeException::class);
        $store->activate("one", "one", static fn (): bool => true);
    }
}
