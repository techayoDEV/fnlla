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

    public function testDocumentationTotalsMatchLedger(): void
    {
        $ledger = $this->ledger();
        $counts = ["done" => 0, "partial" => 0, "open" => 0, "blocked" => 0];

        foreach ($ledger["tasks"] as $task) {
            $counts[$task["status"]]++;
        }

        $unfinished = $counts["partial"] + $counts["open"] + $counts["blocked"];
        $status = (string) file_get_contents(base_path("docs/MODERNIZATION-STATUS.md"));
        $panel = (string) file_get_contents(base_path("docs/DEVELOPER-PANEL.md"));

        self::assertStringContainsString(
            sprintf("Current totals: **%d done, %d partial, %d open, %d blocked**", $counts["done"], $counts["partial"], $counts["open"], $counts["blocked"]),
            $status
        );
        self::assertStringContainsString($unfinished . " modernization criteria remain unfinished", $panel);
    }

    public function testClosedUpgradeAndHttpEdgeCriteriaKeepOperationalEvidence(): void
    {
        $tasks = [];
        foreach ($this->ledger()["tasks"] as $task) {
            $tasks[$task["id"]] = $task;
        }

        self::assertSame("done", $tasks["upgrade-matrix"]["status"]);
        self::assertContains("tests/Integration/ReleasedUpgradeTest.php", $tasks["upgrade-matrix"]["evidence"]);
        self::assertContains(".github/workflows/quality.yml", $tasks["upgrade-matrix"]["evidence"]);

        self::assertSame("done", $tasks["http-edge-matrix"]["status"]);
        self::assertContains("scripts/acceptance/fpm.sh", $tasks["http-edge-matrix"]["evidence"]);
        self::assertContains("scripts/acceptance/http-smoke.php", $tasks["http-edge-matrix"]["evidence"]);
        self::assertContains("tests/ReleaseWorkflowTest.php", $tasks["http-edge-matrix"]["evidence"]);
    }

    private function ledger(): array
    {
        return json_decode((string) file_get_contents(base_path("resources/modernization-tasks.json")), true, 512, JSON_THROW_ON_ERROR);
    }
}
