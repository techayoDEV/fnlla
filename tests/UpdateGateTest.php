<?php

declare(strict_types=1);

namespace Fnlla\Php\Tests;

use PHPUnit\Framework\TestCase;

final class UpdateGateTest extends TestCase
{
    public function testGateBlocksInterruptedOrActiveUpdateBeforeFrameworkBoot(): void
    {
        $root = sys_get_temp_dir() . "/fnlla-gate-" . bin2hex(random_bytes(6));
        $directory = $root . "/.fnlla/update-transaction";
        mkdir($directory, 0700, true);
        $gate = require base_path("bootstrap/update-gate.php");
        $lock = null;
        try {
            self::assertTrue($gate($root)["ready"]);
            file_put_contents($directory . "/journal.json", "{}");
            self::assertFalse($gate($root)["ready"]);
            unlink($directory . "/journal.json");
            $lock = fopen($directory . "/lock", "c+b");
            flock($lock, LOCK_EX);
            self::assertFalse($gate($root)["ready"]);
            flock($lock, LOCK_UN);
            $lease = $gate($root);
            self::assertTrue($lease["ready"]);
            self::assertFalse(flock($lock, LOCK_EX | LOCK_NB));
            fclose($lease["lock"]);
            self::assertTrue(flock($lock, LOCK_EX | LOCK_NB));
            flock($lock, LOCK_UN);
            $lease = $gate($root);
            $GLOBALS["fnlla_update_read_lease"] = ["root" => $root, "stream" => $lease["lock"]];
            $transaction = new \Fnlla\Php\Support\FrameworkUpdateTransaction($root);
            self::assertFalse(is_resource($lease["lock"]));
            unset($transaction);
        } finally {
            unset($GLOBALS["fnlla_update_read_lease"]);
            if (is_resource($lock)) { fclose($lock); }
            foreach (["journal.json", "lock"] as $file) { if (is_file($directory . "/" . $file)) { unlink($directory . "/" . $file); } }
            rmdir($directory);
            rmdir($root . "/.fnlla");
            rmdir($root);
        }
    }
}
