<?php

declare(strict_types=1);

namespace Fnlla\Php\Tests;

use Fnlla\Php\Support\ProcessRunner;
use PHPUnit\Framework\TestCase;

final class RecoveryDrillTest extends TestCase
{
    public function testOfflineRestoreVerifiesFilesWithoutRunningApplicationOrLeakingSecrets(): void
    {
        $directory = sys_get_temp_dir() . "/fnlla-drill-" . bin2hex(random_bytes(8));
        mkdir($directory . "/project/storage/app", 0700, true);
        file_put_contents($directory . "/project/fnlla", '<?php throw new RuntimeException("Do not execute application");');
        file_put_contents($directory . "/project/.env", "APP_KEY=private-drill-fixture\n");
        file_put_contents($directory . "/project/storage/app/data.json", '{"fixture":true}');
        $arguments = [PHP_BINARY, base_path("scripts/recovery-drill.php"), "--source=" . $directory . "/project", "--output=" . $directory . "/drill", "--quiesced"];
        try {
            $result = ProcessRunner::run($arguments);
            self::assertSame(0, $result["exit_code"], $result["output"]);
            $report = json_decode($result["stdout"], true, 512, JSON_THROW_ON_ERROR);
            self::assertTrue($report["passed"]);
            self::assertTrue($report["source_unchanged"]);
            self::assertSame(3, $report["files_verified"]);
            self::assertStringNotContainsString("private-drill-fixture", $result["output"]);
            self::assertSame(file_get_contents($directory . "/project/.env"), file_get_contents($directory . "/drill/restored/.env"));
            self::assertSame(1, ProcessRunner::run($arguments)["exit_code"]);
            $arguments[3] = "--output=" . $directory . "/project/nested";
            self::assertSame(1, ProcessRunner::run($arguments)["exit_code"]);
            self::assertFalse(is_dir($directory . "/project/nested"));
        } finally {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($iterator as $file) {
                $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            }
            rmdir($directory);
        }
    }
}
