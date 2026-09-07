<?php

declare(strict_types=1);

namespace Fnlla\Php\Tests;

use Fnlla\Php\Console\Commands\ReleasePrepareCommand;
use Fnlla\Php\Support\ProcessRunner;
use Fnlla\Php\Support\ReleaseArtifactBuilder;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class ProjectToolingTest extends TestCase
{
    public function testExternalProjectTargetIsExplicitAndRejectsAmbiguousOptions(): void
    {
        $command = new \Fnlla\Php\Console\Commands\FrameworkUpdateCommand($GLOBALS["fnlla_container"]);
        $parse = new ReflectionMethod($command, "parseOptions");
        foreach ([["--project", "../example", "--dry-run"], ["--project=../example", "--dry-run"]] as $arguments) {
            $options = $parse->invoke($command, $arguments);
            self::assertSame("../example", $options["project"]);
            self::assertTrue($options["dry_run"]);
        }
        foreach ([["--project"], ["--project="], ["--project", "--apply"], ["--project=a", "--project=b"]] as $arguments) {
            try { $parse->invoke($command, $arguments); self::fail("Invalid project target accepted"); }
            catch (\RuntimeException $error) { self::assertStringContainsString("project", $error->getMessage()); }
        }
    }

    public function testReleaseGateOnlyInvokesAvailableScriptsAndCommands(): void
    {
        $command = new ReleasePrepareCommand($GLOBALS["fnlla_container"]);
        $method = new ReflectionMethod($command, "validationCommands");
        $result = ProcessRunner::run([PHP_BINARY, "-r", 'define("FNLLA_RUNTIME_SKIP_AUTO_GUARD", true); $_SERVER["argv"] = ["fnlla", "list"]; require "fnlla";'], base_path());
        self::assertSame(0, $result["exit_code"], $result["output"]);

        foreach ($method->invoke($command, true, "2.1.3") as $step) {
            self::assertFileExists($step[1]);
            if ($step[1] === base_path("fnlla")) {
                self::assertStringContainsString($step[2] . " ", $result["output"]);
            }
        }

        if (is_file(base_path(".fnlla/framework-lock.json"))) {
            foreach (["ai:ask", "ai:brief", "ai:triage", "ai:explain-log", "config:doctor", "developer:install-storage", "ops:backup-plan", "release:manifest", "tech-debt:update"] as $name) {
                self::assertStringContainsString($name . " ", $result["output"]);
            }
            self::assertStringNotContainsString("api:lock ", $result["output"]);
        }
    }

    public function testReleaseChecksumsExcludePrivateDataAndEnvironmentVariants(): void
    {
        $suffix = "release-fixture-" . bin2hex(random_bytes(6));
        $files = ["storage/app/" . $suffix, "storage/database/" . $suffix, "public/uploads/" . $suffix, ".env." . $suffix,
            ".fnlla/update-transaction/" . $suffix, $suffix . "/auth.json", $suffix . "/.env.production",
            $suffix . "/private.pem", $suffix . "/backup.sql", $suffix . "/snapshot.zip"];
        $output = framework_cache_path($suffix . ".txt");
        try {
            foreach ($files as $relativePath) {
                $directory = dirname(base_path($relativePath));
                if (!is_dir($directory)) {
                    mkdir($directory, 0777, true);
                }
                file_put_contents(base_path($relativePath), "synthetic fixture\n");
            }
            (new ReleaseArtifactBuilder())->buildChecksums($output);
            $checksums = (string) file_get_contents($output);
            foreach ($files as $relativePath) {
                self::assertStringNotContainsString($relativePath, $checksums);
            }
            self::assertStringContainsString(".env.example", $checksums);
            self::assertStringContainsString("public/index.php", $checksums);
            if (is_file(base_path("resources/source-distribution.json"))) {
                self::assertStringNotContainsString("branding/", $checksums);
            }
        } finally {
            foreach ($files as $relativePath) {
                if (is_file(base_path($relativePath))) {
                    unlink(base_path($relativePath));
                }
            }
            if (is_file($output)) {
                unlink($output);
            }
            if (is_dir(base_path($suffix))) { rmdir(base_path($suffix)); }
        }
    }

    public function testMaintainerDocumentationGateCoversEveryRelease(): void
    {
        if (!is_file(base_path("scripts/check-docs.php"))) {
            self::assertTrue(is_file(base_path(".fnlla/framework-lock.json")));
            return;
        }
        $command = new ReleasePrepareCommand($GLOBALS["fnlla_container"]);
        $checks = (new ReflectionMethod($command, "documentationCommands"))->invoke($command);
        self::assertSame([PHP_BINARY, base_path("scripts/check-docs.php")], $checks["docs hygiene"]);
        self::assertSame([PHP_BINARY, base_path("scripts/check-modernization.php")], $checks["modernization ledger"]);
        foreach ($checks as $check) {
            self::assertFileExists($check[1]);
        }
        $validation = (new ReflectionMethod($command, "validationCommands"))->invoke($command, false, "2.2.0");
        self::assertSame([PHP_BINARY, base_path("vendor/phpunit/phpunit/phpunit"), "--testsuite", "framework", "--fail-on-skipped"], $validation["tests"]);
        self::assertFalse(method_exists($command, "clearRuntimeResidue"));
    }

    public function testPrivateShellIsUpdatableWithoutOwningThePublicApplication(): void
    {
        foreach (["views/layouts/developer.php", "public/assets/app-base.css", "public/assets/developer-panel.css", "docs/framework/SUPPORT.md", "public/vendor/fnlla-runtime/assets/js/fnlla-runtime.js", "VERSION"] as $path) {
            self::assertTrue(\Fnlla\Php\Support\FrameworkLock::isFrameworkManagedPath($path));
        }
        foreach (["views/layouts/app.php", "public/assets/app.css", "routes/web.php", "config/app.php", ".env", "storage/app/business.json", "tests/ProjectTest.php", "tests/Feature/OrdersTest.php"] as $path) {
            self::assertFalse(\Fnlla\Php\Support\FrameworkLock::isFrameworkManagedPath($path));
        }
    }

    public function testUpgradeScannerDoesNotReadAnActiveTransactionLock(): void
    {
        $relative = ".fnlla/update-transaction/scan-fixture-" . bin2hex(random_bytes(6));
        $path = base_path($relative);
        if (!is_dir(dirname($path))) { mkdir(dirname($path), 0700, true); }
        $handle = fopen($path, "x+b");
        try {
            fwrite($handle, implode("", ["Co", "dex"]));
            fflush($handle);
            flock($handle, LOCK_EX);
            $method = new ReflectionMethod(\Fnlla\Php\Support\UpgradeAnalyzer::class, "checkAssistantVendorMarkers");
            $result = $method->invoke(new \Fnlla\Php\Support\UpgradeAnalyzer());
            self::assertFalse(in_array($relative, $result["data"]["files"], true));
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
            unlink($path);
        }
    }
}
