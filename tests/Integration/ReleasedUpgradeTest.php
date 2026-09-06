<?php

declare(strict_types=1);

namespace Fnlla\Php\Tests\Integration;

use Fnlla\Php\Support\FrameworkLock;
use Fnlla\Php\Support\FrameworkUpdater;
use Fnlla\Php\Support\FrameworkUpdateTransaction;
use Fnlla\Php\Support\ProcessRunner;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class ReleasedUpgradeTest extends TestCase
{
    public function testPublished213ProjectPreservesApplicationWhenUpdatingToCandidate(): void
    {
        $previous = getenv("FNLLA_TEST_PREVIOUS_SOURCE");
        if (!$previous) { self::markTestSkipped("Set FNLLA_TEST_PREVIOUS_SOURCE to the official v2.1.3 checkout."); }
        $revision = ProcessRunner::run(["git", "rev-parse", "HEAD"], $previous);
        self::assertSame(0, $revision["exit_code"]);
        self::assertSame("06b2b03e3bc7d6e92e6e6d0221e8e720cd58a085", trim($revision["stdout"]));
        $root = sys_get_temp_dir() . "/fnlla-released-upgrade-" . bin2hex(random_bytes(8));
        mkdir($root, 0700);
        $project = $root . "/project";
        $candidate = $root . "/candidate";
        try {
            foreach ([[$previous, $project], [base_path(), $candidate]] as [$source, $target]) {
                $export = ProcessRunner::run([PHP_BINARY, $source . "/fnlla", "make:project", $target, "Upgrade Fixture"], $source);
                self::assertSame(0, $export["exit_code"], $export["output"]);
            }
            self::assertSame("2.1.3", trim((string) strtok((string) file_get_contents($project . "/VERSION"), "\r\n")));
            $claim = ProcessRunner::run([PHP_BINARY, $project . "/fnlla", "project:claim", "--product", "Application Fixture",
                "--owner", "Example Owner", "--developer", "Example Developer"], $project);
            self::assertSame(0, $claim["exit_code"], $claim["output"]);
            $identity = json_decode((string) file_get_contents($project . "/MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR);
            $fixtures = [".env" => "APP_NAME=\"Upgrade Fixture\"\nFNLLA_MODULE_WORKSPACE=false\n",
                "storage/app/order.json" => '{"id":42}', "public/uploads/example.txt" => "application upload",
                "app/Services/Order.php" => "<?php namespace App\\Services; final class Order {}\n",
                "tests/OrdersTest.php" => "<?php namespace App\\Tests; final class OrdersTest extends \\PHPUnit\\Framework\\TestCase { public function testOrder(): void { self::assertTrue(true); } }\n",
                "tests/OperationsTest.php" => "<?php namespace App\\Tests; final class OperationsTest extends \\PHPUnit\\Framework\\TestCase { public function testCustomOperation(): void { self::assertTrue(true); } }\n"];
            foreach ($fixtures as $path => $contents) {
                if (!is_dir(dirname($project . "/" . $path))) { mkdir(dirname($project . "/" . $path), 0700, true); }
                file_put_contents($project . "/" . $path, $contents);
            }
            foreach (["routes/web.php", "views/layouts/app.php", "public/assets/app.css", "config/app.php", ".env.example", "README.md"] as $path) {
                $fixtures[$path] = (string) file_get_contents($project . "/" . $path);
            }
            // Exercise merge/transaction/post-checks without pretending the candidate is published.
            $report = (new ReflectionMethod(FrameworkUpdater::class, "buildReport"))->invoke(null,
                FrameworkLock::load($project), FrameworkLock::load($candidate), $project, $candidate);
            self::assertSame([], $report["conflicts"], json_encode(array_keys($report["conflicts"])));
            self::assertGreaterThan(0, count($report["updates"]));
            (new FrameworkUpdateTransaction($project))->run([...array_keys($report["updates"]), FrameworkLock::lockFile(), "MANIFEST.json"], static function () use ($report, $project, $candidate): void {
                (new ReflectionMethod(FrameworkUpdater::class, "applyReport"))->invoke(null, $report, $project, $candidate);
                FrameworkLock::syncFromExport($candidate, $project);
                (new ReflectionMethod(FrameworkUpdater::class, "refreshProjectManifest"))->invoke(null, $project);
                $checks = (new ReflectionMethod(FrameworkUpdater::class, "runPostInstallChecks"))->invoke(null, $project);
                foreach ($checks as $check) { self::assertTrue($check["ok"], $check["output"] ?? $check["label"]); }
            });
            foreach ($fixtures as $path => $contents) { self::assertSame($contents, file_get_contents($project . "/" . $path), $path); }
            self::assertFalse(is_file($project . "/tests/ApplicationSurfaceTest.php"));
            self::assertFileExists($project . "/docs/framework/SUPPORT.md");
            self::assertFileExists($project . "/docs/framework/TRADEMARKS.md");
            self::assertSame(file_get_contents($candidate . "/VERSION"), file_get_contents($project . "/VERSION"));
            self::assertSame(file_get_contents($candidate . "/public/vendor/fnlla-runtime/VERSION"), file_get_contents($project . "/public/vendor/fnlla-runtime/VERSION"));
            $updatedIdentity = json_decode((string) file_get_contents($project . "/MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR);
            foreach (["name", "owner", "developer"] as $key) { self::assertSame($identity["product"][$key], $updatedIdentity["product"][$key]); }
            $routes = ProcessRunner::run([PHP_BINARY, $project . "/fnlla", "route:list"], $project);
            self::assertSame(0, $routes["exit_code"], $routes["output"]);
        } finally {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($files as $file) { $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname()); }
            rmdir($root);
        }
    }
}
