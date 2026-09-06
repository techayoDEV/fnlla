<?php

declare(strict_types=1);

namespace Fnlla\Php\Tests;

use Fnlla\Php\Support\FrameworkUpdateTransaction;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class FrameworkUpdateTransactionTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . "/fnlla-transaction-" . bin2hex(random_bytes(8));
        mkdir($this->root);
        mkdir($this->root . "/src");
        mkdir($this->root . "/.fnlla");
        file_put_contents($this->root . "/src/old.php", "original");
        file_put_contents($this->root . "/.fnlla/framework-lock.json", "old lock");
        file_put_contents($this->root . "/.env", "private");
    }

    protected function tearDown(): void
    {
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $entry) {
            $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
        }
        rmdir($this->root);
    }

    public function testFailureRestoresChangedDeletedNewFilesAndLock(): void
    {
        $transaction = new FrameworkUpdateTransaction($this->root);
        try {
            $transaction->run(["src/old.php", "src/new.php", ".fnlla/framework-lock.json"], function (): void {
                unlink($this->root . "/src/old.php");
                file_put_contents($this->root . "/src/new.php", "new");
                file_put_contents($this->root . "/.fnlla/framework-lock.json", "new lock");
                throw new RuntimeException("synthetic post-install failure");
            });
            self::fail("Expected installation failure.");
        } catch (RuntimeException $error) {
            self::assertStringContainsString("previous framework files were restored", $error->getMessage());
        }
        self::assertSame("original", file_get_contents($this->root . "/src/old.php"));
        self::assertFalse(file_exists($this->root . "/src/new.php"));
        self::assertSame("old lock", file_get_contents($this->root . "/.fnlla/framework-lock.json"));
        self::assertSame("private", file_get_contents($this->root . "/.env"));
        $transaction->assertReady();
    }

    public function testCommitKeepsNewFilesAndReleasesJournal(): void
    {
        $transaction = new FrameworkUpdateTransaction($this->root);
        self::assertSame(42, $transaction->run(["src/old.php"], function (): int {
            FrameworkUpdateTransaction::replace($this->root . "/src/old.php", "updated");
            return 42;
        }));
        self::assertSame("updated", file_get_contents($this->root . "/src/old.php"));
        self::assertFalse(is_file($this->root . "/.fnlla/update-transaction/journal.json"));
    }

    public function testConcurrentUpdaterAndPrivatePathsAreRejected(): void
    {
        $transaction = new FrameworkUpdateTransaction($this->root);
        try {
            new FrameworkUpdateTransaction($this->root);
            self::fail("Expected concurrent updater rejection.");
        } catch (RuntimeException $error) {
            self::assertStringContainsString("Another framework update", $error->getMessage());
        }
        foreach (["../outside", ".env", "storage/app/data.json", "public/uploads/photo.jpg"] as $path) {
            try {
                $transaction->run([$path], static fn () => null);
                self::fail("Expected unsafe path rejection.");
            } catch (RuntimeException $error) {
                self::assertStringContainsString("Unsafe", $error->getMessage());
            }
        }
    }

    public function testRecoveryRejectsCorruptedBackupBeforeAnyRestore(): void
    {
        $transaction = new FrameworkUpdateTransaction($this->root);
        $backup = hash("sha256", "src/old.php") . ".backup";
        file_put_contents($this->root . "/.fnlla/update-transaction/" . $backup, "corrupted");
        file_put_contents($this->root . "/.fnlla/update-transaction/journal.json", json_encode([
            "schema" => "fnlla.update_transaction.v1", "entries" => ["src/old.php" => [
                "exists" => true, "backup" => $backup, "hash" => hash("sha256", "original"), "mode" => 0644,
            ]],
        ]));
        try {
            $transaction->recover();
            self::fail("Expected checksum rejection.");
        } catch (RuntimeException $error) {
            self::assertStringContainsString("checksum", $error->getMessage());
        }
        self::assertSame("original", file_get_contents($this->root . "/src/old.php"));
        self::assertTrue(is_file($this->root . "/.fnlla/update-transaction/journal.json"));
    }

    public function testInterruptedProcessCanBeRecoveredWithoutApplicationBootstrap(): void
    {
        $script = $this->root . "/interrupt.php";
        file_put_contents($script, '<?php require $argv[1]; $t = new \\Fnlla\\Php\\Support\\FrameworkUpdateTransaction($argv[2]);'
            . '$t->run(["src/old.php"], static function () use ($argv) { file_put_contents($argv[2]."/src/old.php", "interrupted"); exit(23); });');
        $process = proc_open([PHP_BINARY, $script, base_path("src/Support/FrameworkUpdateTransaction.php"), $this->root],
            [0 => ["pipe", "r"], 1 => ["pipe", "w"], 2 => ["pipe", "w"]], $pipes);
        foreach ($pipes as $pipe) {
            fclose($pipe);
        }
        self::assertSame(23, proc_close($process));
        self::assertSame("interrupted", file_get_contents($this->root . "/src/old.php"));
        $transaction = new FrameworkUpdateTransaction($this->root);
        $transaction->recover();
        self::assertSame("original", file_get_contents($this->root . "/src/old.php"));
        $transaction->assertReady();
    }
}
