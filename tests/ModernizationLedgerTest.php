<?php

declare(strict_types=1);

namespace Fnlla\Php\Tests;

use PHPUnit\Framework\TestCase;

final class ModernizationLedgerTest extends TestCase
{
    public function testStrictGateCannotReportCompletionWithUnfinishedTasks(): void
    {
        foreach ([false, true] as $strict) {
            $command = [PHP_BINARY, base_path("scripts/check-modernization.php")];
            if ($strict) { $command[] = "--require-complete"; }
            $process = proc_open($command, [0 => ["pipe", "r"], 1 => ["pipe", "w"], 2 => ["pipe", "w"]], $pipes);
            fclose($pipes[0]);
            $output = stream_get_contents($pipes[1]); $errors = stream_get_contents($pipes[2]);
            fclose($pipes[1]); fclose($pipes[2]);
            $exit = proc_close($process);
            $report = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
            self::assertSame("fnlla.modernization_check.v1", $report["schema"]);
            self::assertSame($strict && $report["unfinished"] !== [] ? 1 : 0, $exit, $errors);
        }
    }
}
