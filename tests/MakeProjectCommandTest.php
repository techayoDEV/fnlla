<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA TEST CASE
File: tests\MakeProjectCommandTest.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Validates maintained framework behaviour inside the repository-local test harness.
*/

namespace Fnlla\Php\Tests;

use Fnlla\Php\Console\Commands\MakeProjectCommand;
use Fnlla\Php\Container\Container;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class MakeProjectCommandTest extends TestCase
{
    private string $targetPath;

    protected function setUp(): void
    {
        $this->targetPath = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . "fnlla-make-project-test-"
            . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        if (!is_dir($this->targetPath)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->targetPath, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($this->targetPath);
    }

    public function testPlainExportHasNoPanelAndPassesRuntimeAndHttpChecks(): void
    {
        $command = new MakeProjectCommand($GLOBALS["fnlla_container"]);
        self::assertSame(0, $command->handle([$this->targetPath, "Plain test", "--profile=plain"]));
        $this->assertExportBudget(400000, 160);
        foreach (["views/developer", "views/customer", "views/maintenance", "routes/maintenance.php", "src/Controllers/DeveloperAccessController.php", "public/assets/developer-panel.css"] as $path) {
            self::assertFalse(file_exists($this->targetPath . "/" . $path), $path);
        }
        self::assertSame("plain", trim((string) file_get_contents($this->targetPath . "/.fnlla/project-profile")));
        foreach (["public/vendor", "src", "config/ai.php", "config/developer_access.php", "packages/fnlla-core/src/Maintenance",
            "packages/fnlla-core/src/Ai", "packages/fnlla-core/src/Support/optional_helpers.php", "MANIFEST.json", ".env.full.example"] as $path) {
            self::assertFalse(file_exists($this->targetPath . "/" . $path), $path);
        }
        $composer = json_decode((string) file_get_contents($this->targetPath . "/composer.json"), true);
        self::assertArrayHasKey("techayodev/fnlla-core", $composer["require"]);
        self::assertSame("app/", $composer["autoload"]["psr-4"]["App\\"]);
        foreach (["scripts/test.php", "scripts/lint.php"] as $script) {
            [$exit, $output] = $this->runPhpScript($this->targetPath . "/" . $script);
            self::assertSame(0, $exit, $output);
        }
        [$exit, $output] = $this->runPhpScript($this->targetPath . "/fnlla", ["route:list"]);
        self::assertSame(0, $exit, $output);
        self::assertStringNotContainsString("developer", $output);
        foreach (["route:cache", "route:cache", "route:list"] as $action) {
            [$exit, $output] = $this->runPhpScript($this->targetPath . "/fnlla", [$action]);
            self::assertSame(0, $exit, $output);
        }
        $cached = require $this->targetPath . "/storage/framework/cache/routes.php";
        self::assertSame("plain", $cached["profile"]);
        [$exit, $output] = $this->runPhpScript($this->targetPath . "/fnlla", ["cache:clear"]);
        self::assertSame(0, $exit, $output);
    }

    public function testExportedProjectIncludesProjectSurfaceWithoutMaintainerResidue(): void
    {
        $container = $GLOBALS["fnlla_container"] ?? $GLOBALS["fnlla_php_container"] ?? null;
        self::assertInstanceOf(Container::class, $container);

        $command = new MakeProjectCommand($container);

        self::assertSame(0, $command->handle([$this->targetPath, "Project Test", "--no-interaction"]));
        $this->assertExportBudget(4000000, 430);
        self::assertFileExists($this->targetPath . DIRECTORY_SEPARATOR . "LICENSE.md");
        self::assertFileExists($this->targetPath . "/docs/framework/SUPPORT.md");
        self::assertFileExists($this->targetPath . "/docs/framework/TRADEMARKS.md");
        self::assertFalse(is_file($this->targetPath . DIRECTORY_SEPARATOR . "SUPPORT.md"));
        self::assertFalse(is_file($this->targetPath . DIRECTORY_SEPARATOR . "TRADEMARKS.md"));
        self::assertFileExists($this->targetPath . DIRECTORY_SEPARATOR . "VERSION");
        self::assertFileExists($this->targetPath . DIRECTORY_SEPARATOR . "MANIFEST.json");
        self::assertFileExists($this->targetPath . DIRECTORY_SEPARATOR . ".fnlla" . DIRECTORY_SEPARATOR . "framework-lock.json");
        $frameworkLockFiles = array_map(
            "basename",
            glob($this->targetPath . DIRECTORY_SEPARATOR . ".fnlla" . DIRECTORY_SEPARATOR . "*.json") ?: []
        );
        sort($frameworkLockFiles);
        self::assertSame(["framework-lock.json"], $frameworkLockFiles);
        self::assertFileExists($this->targetPath . DIRECTORY_SEPARATOR . "config" . DIRECTORY_SEPARATOR . "framework_update.php");
        self::assertFileExists($this->targetPath . DIRECTORY_SEPARATOR . "config" . DIRECTORY_SEPARATOR . "maintenance.php");
        self::assertFileExists($this->targetPath . DIRECTORY_SEPARATOR . "routes" . DIRECTORY_SEPARATOR . "maintenance.php");
        self::assertFileExists($this->targetPath . DIRECTORY_SEPARATOR . "views" . DIRECTORY_SEPARATOR . "maintenance" . DIRECTORY_SEPARATOR . "framework-update.php");
        self::assertFalse(is_file($this->targetPath . "/docs/index.html"));
        self::assertFalse(is_file($this->targetPath . DIRECTORY_SEPARATOR . "scripts" . DIRECTORY_SEPARATOR . "build-docs.php"));
        self::assertFalse(is_dir($this->targetPath . DIRECTORY_SEPARATOR . "resources" . DIRECTORY_SEPARATOR . "project-templates"));
        self::assertFalse(is_file($this->targetPath . DIRECTORY_SEPARATOR . "database" . DIRECTORY_SEPARATOR . "factories" . DIRECTORY_SEPARATOR . "UserFactory.php"));
        self::assertFalse(is_file($this->targetPath . DIRECTORY_SEPARATOR . "database" . DIRECTORY_SEPARATOR . "migrations" . DIRECTORY_SEPARATOR . "20260627180000_create_users_table.php"));
        self::assertFalse(is_file($this->targetPath . DIRECTORY_SEPARATOR . "database" . DIRECTORY_SEPARATOR . "migrations" . DIRECTORY_SEPARATOR . "20260627200000_add_role_to_users_table.php"));
        self::assertFalse(is_file($this->targetPath . DIRECTORY_SEPARATOR . "src" . DIRECTORY_SEPARATOR . "Controllers" . DIRECTORY_SEPARATOR . "AuthController.php"));
        self::assertFileExists($this->targetPath . "/src/Console/Commands/MakeCommandCommand.php");
        self::assertFalse(is_file($this->targetPath . DIRECTORY_SEPARATOR . "src" . DIRECTORY_SEPARATOR . "Console" . DIRECTORY_SEPARATOR . "Commands" . DIRECTORY_SEPARATOR . "MakeProjectCommand.php"));
        self::assertFalse(is_file($this->targetPath . DIRECTORY_SEPARATOR . "tests" . DIRECTORY_SEPARATOR . "MakeProjectCommandTest.php"));
        self::assertFalse(is_file($this->targetPath . DIRECTORY_SEPARATOR . "tests" . DIRECTORY_SEPARATOR . "FnllaRuntimeSyncCommandTest.php"));
        self::assertFalse(is_file($this->targetPath . DIRECTORY_SEPARATOR . "tests" . DIRECTORY_SEPARATOR . "FrameworkExtensionsTest.php"));
        self::assertFalse(is_file($this->targetPath . DIRECTORY_SEPARATOR . "tests" . DIRECTORY_SEPARATOR . "ValidationTest.php"));
        self::assertFalse(is_file($this->targetPath . DIRECTORY_SEPARATOR . "views" . DIRECTORY_SEPARATOR . "pages" . DIRECTORY_SEPARATOR . "platform.php"));
        self::assertFileExists($this->targetPath . DIRECTORY_SEPARATOR . "views" . DIRECTORY_SEPARATOR . "pages" . DIRECTORY_SEPARATOR . "about.php");
        self::assertFileExists($this->targetPath . DIRECTORY_SEPARATOR . "views" . DIRECTORY_SEPARATOR . "pages" . DIRECTORY_SEPARATOR . "services.php");
        self::assertFileExists($this->targetPath . DIRECTORY_SEPARATOR . "views" . DIRECTORY_SEPARATOR . "pages" . DIRECTORY_SEPARATOR . "contact.php");
        self::assertFileExists($this->targetPath . DIRECTORY_SEPARATOR . "views" . DIRECTORY_SEPARATOR . "pages" . DIRECTORY_SEPARATOR . "legal.php");
        self::assertFileExists($this->targetPath . DIRECTORY_SEPARATOR . "views" . DIRECTORY_SEPARATOR . "partials" . DIRECTORY_SEPARATOR . "page-hero.php");
        self::assertFalse(is_file($this->targetPath . DIRECTORY_SEPARATOR . "views" . DIRECTORY_SEPARATOR . "pages" . DIRECTORY_SEPARATOR . "login.php"));
        self::assertFalse(is_file($this->targetPath . DIRECTORY_SEPARATOR . "views" . DIRECTORY_SEPARATOR . "pages" . DIRECTORY_SEPARATOR . "project-launch.php"));
        self::assertFalse(is_file($this->targetPath . DIRECTORY_SEPARATOR . "storage" . DIRECTORY_SEPARATOR . "logs" . DIRECTORY_SEPARATOR . "app.log"));
        self::assertFalse(is_file($this->targetPath . DIRECTORY_SEPARATOR . "storage" . DIRECTORY_SEPARATOR . "framework" . DIRECTORY_SEPARATOR . "fnlla-runtime-guard.json"));
        self::assertSame(
            [],
            glob($this->targetPath . DIRECTORY_SEPARATOR . "storage" . DIRECTORY_SEPARATOR . "framework" . DIRECTORY_SEPARATOR . "cache" . DIRECTORY_SEPARATOR . "*.cache") ?: []
        );
        self::assertSame(
            [],
            glob($this->targetPath . DIRECTORY_SEPARATOR . "storage" . DIRECTORY_SEPARATOR . "framework" . DIRECTORY_SEPARATOR . "sessions" . DIRECTORY_SEPARATOR . "sess_*") ?: []
        );
        self::assertFileExists($this->targetPath . DIRECTORY_SEPARATOR . "storage" . DIRECTORY_SEPARATOR . "framework" . DIRECTORY_SEPARATOR . "sessions" . DIRECTORY_SEPARATOR . ".gitignore");
        foreach (["scripts/windows/test-project.cmd", "scripts/windows/lint-project.cmd", "scripts/windows/update-fnlla-runtime.cmd"] as $launcher) {
            self::assertFileExists($this->targetPath . DIRECTORY_SEPARATOR . str_replace("/", DIRECTORY_SEPARATOR, $launcher), $launcher);
        }
        self::assertStringContainsString(
            'validate-version-manifest.php',
            (string) file_get_contents($this->targetPath . DIRECTORY_SEPARATOR . "scripts" . DIRECTORY_SEPARATOR . "windows" . DIRECTORY_SEPARATOR . "lint-project.cmd")
        );
        foreach (["test-project.cmd", "lint-project.cmd", "update-fnlla-runtime.cmd"] as $launcher) {
            self::assertFalse(is_file($this->targetPath . DIRECTORY_SEPARATOR . $launcher), $launcher);
        }
        self::assertStringContainsString(
            "php scripts/validate-version-manifest.php",
            (string) file_get_contents($this->targetPath . DIRECTORY_SEPARATOR . "README.md")
        );
        self::assertStringContainsString(
            "SUPPORT.md",
            (string) file_get_contents($this->targetPath . DIRECTORY_SEPARATOR . "README.md")
        );
        self::assertStringContainsString(
            "TRADEMARKS.md",
            (string) file_get_contents($this->targetPath . DIRECTORY_SEPARATOR . "README.md")
        );
        self::assertStringContainsString(
            "does not copy the full maintainer workspace",
            (string) file_get_contents($this->targetPath . DIRECTORY_SEPARATOR . "README.md")
        );
        self::assertStringContainsString(
            "The application base keeps only the project-facing scripts, smoke tests and commands",
            (string) file_get_contents($this->targetPath . DIRECTORY_SEPARATOR . "README.md")
        );
        self::assertStringContainsString(
            ".fnlla/framework-lock.json",
            (string) file_get_contents($this->targetPath . DIRECTORY_SEPARATOR . "README.md")
        );
        self::assertStringContainsString(
            "/maintenance/framework-update",
            (string) file_get_contents($this->targetPath . DIRECTORY_SEPARATOR . "README.md")
        );
        self::assertStringContainsString(
            "MAINTENANCE_MODE_ENABLED",
            (string) file_get_contents($this->targetPath . DIRECTORY_SEPARATOR . "README.md")
        );
        self::assertStringContainsString(
            "DEVELOPER_ACCESS_USERS=",
            (string) file_get_contents($this->targetPath . DIRECTORY_SEPARATOR . ".env.example")
        );
        self::assertStringContainsString(
            'APP_NAME="Project Test"',
            (string) file_get_contents($this->targetPath . DIRECTORY_SEPARATOR . ".env.example")
        );
        self::assertStringContainsString(
            "APP_URL=",
            (string) file_get_contents($this->targetPath . DIRECTORY_SEPARATOR . ".env.example")
        );
        self::assertStringNotContainsString(
            "APP_URL=https://fnlla.com",
            (string) file_get_contents($this->targetPath . DIRECTORY_SEPARATOR . ".env.example")
        );
        self::assertStringContainsString(
            "FNLLA_OFFICIAL_URL=https://fnlla.com",
            (string) file_get_contents($this->targetPath . DIRECTORY_SEPARATOR . ".env.full.example")
        );
        self::assertStringContainsString(
            "MAIL_FROM_ADDRESS=no-reply@example.com",
            (string) file_get_contents($this->targetPath . DIRECTORY_SEPARATOR . ".env.example")
        );
        self::assertStringNotContainsString(
            "DEVELOPER_ACCESS_PASSWORD_HASH=",
            (string) file_get_contents($this->targetPath . DIRECTORY_SEPARATOR . ".env.example")
        );
        self::assertStringContainsString(
            "DEVELOPER_ACCESS_PATH=/developer",
            (string) file_get_contents($this->targetPath . DIRECTORY_SEPARATOR . ".env.example")
        );
        self::assertStringContainsString(
            "ASSET_URL=",
            (string) file_get_contents($this->targetPath . DIRECTORY_SEPARATOR . ".env.example")
        );
        self::assertStringContainsString(
            "DEVELOPER_OPERATIONS_NAV_MODE=hidden",
            (string) file_get_contents($this->targetPath . DIRECTORY_SEPARATOR . ".env.example")
        );
        self::assertStringContainsString(
            "Save and enable maintenance",
            (string) file_get_contents($this->targetPath . DIRECTORY_SEPARATOR . "views" . DIRECTORY_SEPARATOR . "maintenance" . DIRECTORY_SEPARATOR . "index.php")
        );
        self::assertStringNotContainsString(
            "maintenance_setup_username",
            (string) file_get_contents($this->targetPath . DIRECTORY_SEPARATOR . "views" . DIRECTORY_SEPARATOR . "maintenance" . DIRECTORY_SEPARATOR . "index.php")
        );
        self::assertStringContainsString(
            "Developer panel password",
            (string) file_get_contents($this->targetPath . DIRECTORY_SEPARATOR . "views" . DIRECTORY_SEPARATOR . "maintenance" . DIRECTORY_SEPARATOR . "index.php")
        );
        self::assertStringContainsString(
            "project-setup-visual",
            (string) file_get_contents($this->targetPath . DIRECTORY_SEPARATOR . "views" . DIRECTORY_SEPARATOR . "maintenance" . DIRECTORY_SEPARATOR . "index.php")
        );
        self::assertStringNotContainsString(
            "developer_operations_nav_mode",
            (string) file_get_contents($this->targetPath . DIRECTORY_SEPARATOR . "views" . DIRECTORY_SEPARATOR . "maintenance" . DIRECTORY_SEPARATOR . "index.php")
        );
        self::assertFileExists($this->targetPath . DIRECTORY_SEPARATOR . "views" . DIRECTORY_SEPARATOR . "pages" . DIRECTORY_SEPARATOR . "api-health.php");
        self::assertStringContainsString(
            "/about",
            (string) file_get_contents($this->targetPath . DIRECTORY_SEPARATOR . "README.md")
        );
        self::assertStringContainsString(
            "src/Controllers/PageController.php",
            (string) file_get_contents($this->targetPath . DIRECTORY_SEPARATOR . "README.md")
        );
        self::assertStringContainsString(
            "The export intentionally leaves `make:*`, `make:project`",
            (string) file_get_contents($this->targetPath . DIRECTORY_SEPARATOR . "README.md")
        );
        self::assertStringContainsString(
            "versioned project-export templates under `resources/project-templates/`",
            (string) file_get_contents($this->targetPath . DIRECTORY_SEPARATOR . "README.md")
        );
        self::assertStringContainsString(
            "php fnlla framework:update --check",
            (string) file_get_contents($this->targetPath . DIRECTORY_SEPARATOR . "README.md")
        );
        self::assertStringNotContainsString("--source <path-to-fnlla>", (string) file_get_contents($this->targetPath . DIRECTORY_SEPARATOR . "README.md"));
        self::assertStringContainsString(
            'assertFalse(class_exists("Database\\\\Factories\\\\UserFactory"))',
            (string) file_get_contents($this->targetPath . DIRECTORY_SEPARATOR . "tests" . DIRECTORY_SEPARATOR . "BootstrapAutoloadTest.php")
        );
        $frameworkLock = json_decode(
            (string) file_get_contents($this->targetPath . DIRECTORY_SEPARATOR . ".fnlla" . DIRECTORY_SEPARATOR . "framework-lock.json"),
            true
        );
        self::assertTrue(is_array($frameworkLock));
        self::assertSame("https://fnlla.com", $frameworkLock["framework_base"]["framework"]["website"] ?? null);
        self::assertSame("support@fnlla.com", $frameworkLock["framework_base"]["framework"]["support"] ?? null);
        self::assertArrayNotHasKey(
            "tests/BootstrapAutoloadTest.php",
            (array) ($frameworkLock["framework_base"]["managed_files"] ?? [])
        );
        self::assertArrayNotHasKey(
            "routes/web.php",
            (array) ($frameworkLock["framework_base"]["managed_files"] ?? [])
        );
        self::assertArrayHasKey(
            "src/Controllers/HomeController.php",
            (array) ($frameworkLock["framework_base"]["managed_files"] ?? [])
        );
        self::assertArrayNotHasKey(
            "src/Controllers/PageController.php",
            (array) ($frameworkLock["framework_base"]["managed_files"] ?? [])
        );
        self::assertArrayHasKey(
            "views/developer/panel.php",
            (array) ($frameworkLock["framework_base"]["managed_files"] ?? [])
        );
        self::assertArrayHasKey(
            "views/developer/panel-header.php",
            (array) ($frameworkLock["framework_base"]["managed_files"] ?? [])
        );
        self::assertArrayHasKey(
            "views/developer/profile.php",
            (array) ($frameworkLock["framework_base"]["managed_files"] ?? [])
        );
        self::assertArrayHasKey(
            "views/customer/panel.php",
            (array) ($frameworkLock["framework_base"]["managed_files"] ?? [])
        );
        self::assertArrayHasKey(
            "views/customer/kanban.php",
            (array) ($frameworkLock["framework_base"]["managed_files"] ?? [])
        );
        self::assertArrayHasKey(
            "views/developer/project-settings.php",
            (array) ($frameworkLock["framework_base"]["managed_files"] ?? [])
        );
        self::assertArrayNotHasKey(
            "views/pages/home.php",
            (array) ($frameworkLock["framework_base"]["managed_files"] ?? [])
        );
        self::assertArrayNotHasKey(
            "views/pages/api-health.php",
            (array) ($frameworkLock["framework_base"]["managed_files"] ?? [])
        );
        self::assertArrayNotHasKey(
            "public/assets/app.css",
            (array) ($frameworkLock["framework_base"]["managed_files"] ?? [])
        );
        self::assertFalse(is_file($this->targetPath . "/public/assets/fnlla-logo.png"));

        [$exitCode, $output] = $this->runPhpScript(
            $this->targetPath . DIRECTORY_SEPARATOR . "scripts" . DIRECTORY_SEPARATOR . "validate-version-manifest.php"
        );

        self::assertSame(0, $exitCode, $output);
        self::assertStringContainsString("FNLLA version manifest passed.", $output);

        [$listExitCode, $listOutput] = $this->runPhpScript(
            $this->targetPath . DIRECTORY_SEPARATOR . "fnlla",
            ["list"]
        );

        self::assertSame(0, $listExitCode, $listOutput);
        self::assertStringContainsString("framework:update", $listOutput);
        self::assertStringContainsString("fnlla-runtime:validate", $listOutput);
        self::assertStringContainsString("project:acceptance", $listOutput);
        self::assertStringContainsString("project:claim", $listOutput);
        self::assertFalse(str_contains($listOutput, "make:project"));
        self::assertStringContainsString("make:controller", $listOutput);
        self::assertStringContainsString("make:migration", $listOutput);

        [$claimExitCode, $claimOutput] = $this->runPhpScript(
            $this->targetPath . DIRECTORY_SEPARATOR . "fnlla",
            [
                "project:claim",
                "--product",
                "Claimed Project",
                "--id",
                "CLAIMED_PROJECT",
                "--owner",
                "Owner LTD",
                "--developer",
                "Developer LTD",
                "--maintainer",
                "Maintenance LTD",
                "--summary",
                "Claimed Project delivery workspace.",
            ]
        );

        self::assertSame(0, $claimExitCode, $claimOutput);
        self::assertStringContainsString("Project identity claimed.", $claimOutput);
        self::assertStringContainsString("Identifier: CLAIMED_PROJECT", $claimOutput);
        self::assertStringContainsString(
            "'name' => (string) env(\"APP_NAME\", 'Claimed Project')",
            (string) file_get_contents($this->targetPath . DIRECTORY_SEPARATOR . "config" . DIRECTORY_SEPARATOR . "app.php")
        );
        self::assertStringContainsString(
            'APP_NAME="Claimed Project"',
            (string) file_get_contents($this->targetPath . DIRECTORY_SEPARATOR . ".env.example")
        );
        self::assertStringContainsString(
            "PROJECT_ID=CLAIMED_PROJECT",
            (string) file_get_contents($this->targetPath . DIRECTORY_SEPARATOR . ".env.example")
        );
        self::assertStringContainsString(
            'PROJECT_NAME="Claimed Project"',
            (string) file_get_contents($this->targetPath . DIRECTORY_SEPARATOR . ".env.example")
        );
        self::assertStringContainsString(
            "PROJECT_MAINTAINER=\"Maintenance LTD\"",
            (string) file_get_contents($this->targetPath . DIRECTORY_SEPARATOR . ".env.example")
        );
        self::assertStringContainsString(
            "Product/project identifier: `CLAIMED_PROJECT`.",
            (string) file_get_contents($this->targetPath . DIRECTORY_SEPARATOR . "README.md")
        );

        $claimedManifest = json_decode(
            (string) file_get_contents($this->targetPath . DIRECTORY_SEPARATOR . "MANIFEST.json"),
            true
        );
        self::assertTrue(is_array($claimedManifest));
        self::assertSame(2, $claimedManifest["schema_version"] ?? null);
        self::assertSame("claimed_project", $claimedManifest["manifest_type"] ?? null);
        self::assertSame("CLAIMED_PROJECT", $claimedManifest["product"]["identifier"] ?? null);
        self::assertSame("Owner LTD", $claimedManifest["product"]["owner"]["name"] ?? null);
        self::assertSame("Developer LTD", $claimedManifest["product"]["developer"]["name"] ?? null);
        self::assertSame("Maintenance LTD", $claimedManifest["product"]["maintenance_provider"]["name"] ?? null);
        self::assertSame("TechAyo LTD (techayo.co.uk)", $claimedManifest["framework"]["creator"] ?? null);
        self::assertSame("https://fnlla.com", $claimedManifest["framework"]["website"] ?? null);
        self::assertSame("support@fnlla.com", $claimedManifest["framework"]["support"] ?? null);
        self::assertSame(".fnlla/framework-lock.json", $claimedManifest["framework"]["lock_file"] ?? null);
        $claimedFrameworkLock = json_decode(
            (string) file_get_contents($this->targetPath . DIRECTORY_SEPARATOR . ".fnlla" . DIRECTORY_SEPARATOR . "framework-lock.json"),
            true
        );
        self::assertTrue(is_array($claimedFrameworkLock));
        self::assertSame("Claimed Project", $claimedFrameworkLock["framework_base"]["application"]["name"] ?? null);
        self::assertSame("claimed-project", $claimedFrameworkLock["framework_base"]["application"]["package_slug"] ?? null);

        [$claimedManifestExitCode, $claimedManifestOutput] = $this->runPhpScript(
            $this->targetPath . DIRECTORY_SEPARATOR . "scripts" . DIRECTORY_SEPARATOR . "validate-version-manifest.php"
        );

        self::assertSame(0, $claimedManifestExitCode, $claimedManifestOutput);
        self::assertStringContainsString("FNLLA version manifest passed.", $claimedManifestOutput);

        [$projectTestExitCode, $projectTestOutput] = $this->runPhpScript(
            $this->targetPath . DIRECTORY_SEPARATOR . "scripts" . DIRECTORY_SEPARATOR . "test.php"
        );

        self::assertSame(0, $projectTestExitCode, $projectTestOutput);
        self::assertStringContainsString("OK (", $projectTestOutput);

        [$acceptanceExitCode, $acceptanceOutput] = $this->runPhpScript(
            $this->targetPath . DIRECTORY_SEPARATOR . "fnlla",
            ["project:acceptance", "--json"]
        );

        self::assertSame(0, $acceptanceExitCode, $acceptanceOutput);
        self::assertStringContainsString("fnlla.project_acceptance.v1", $acceptanceOutput);
        self::assertStringContainsString("http.api_health", $acceptanceOutput);

        [$updateCheckExitCode, $updateCheckOutput] = $this->runPhpScript(
            $this->targetPath . DIRECTORY_SEPARATOR . "fnlla",
            ["framework:update", "--check", "--source", base_path()]
        );

        self::assertSame(1, $updateCheckExitCode, $updateCheckOutput);
        self::assertStringContainsString("Local source updates are disabled", $updateCheckOutput);

        [$routeListExitCode, $routeListOutput] = $this->runPhpScript(
            $this->targetPath . DIRECTORY_SEPARATOR . "fnlla",
            ["route:list"]
        );

        self::assertSame(0, $routeListExitCode, $routeListOutput);
        self::assertStringContainsString("GET     /", $routeListOutput);
        self::assertStringContainsString("GET     /about", $routeListOutput);
        self::assertStringContainsString("GET     /services", $routeListOutput);
        self::assertStringContainsString("GET     /contact", $routeListOutput);
        self::assertStringContainsString("POST    /contact", $routeListOutput);
        self::assertStringContainsString("GET     /maintenance", $routeListOutput);
        self::assertStringContainsString("GET     /maintenance/health", $routeListOutput);
        self::assertStringContainsString("GET     /maintenance/framework-update", $routeListOutput);
        self::assertStringContainsString("POST    /maintenance/setup-access", $routeListOutput);
        self::assertStringContainsString("POST    /maintenance/unlock", $routeListOutput);
        self::assertStringContainsString("POST    /maintenance/lock", $routeListOutput);
        self::assertStringContainsString("GET     /health", $routeListOutput);
        self::assertStringContainsString("GET     /api/health", $routeListOutput);
        self::assertFalse(str_contains($routeListOutput, "/platform"));
        self::assertFalse(str_contains($routeListOutput, "/project/launch"));
        self::assertFalse(str_contains($routeListOutput, "/login"));
        self::assertFalse(str_contains($routeListOutput, "/dashboard"));
    }

    public function testProjectExportTemplatesAreVersionedFilesOutsideTheCommandBody(): void
    {
        foreach ([
            "README.md",
            "fnlla",
            "fnlla.cmd",
            "scripts/windows/test-project.cmd",
            "scripts/windows/lint-project.cmd",
            "database/seeders/DatabaseSeeder.php",
            "tests/BootstrapAutoloadTest.php",
        ] as $relativePath) {
            self::assertFileExists(base_path("resources/project-templates/v1/" . $relativePath));
        }

        self::assertStringNotContainsString("<<<", (string) file_get_contents(base_path("src/Console/Commands/MakeProjectCommand.php")));
    }

    public function testExplicitExportDoesNotCopyRuntimeOrUnlistedSourceFiles(): void
    {
        $suffix = "export-fixture-" . bin2hex(random_bytes(6));
        $paths = ["storage/framework/developer/" . $suffix, "storage/framework/updates/" . $suffix,
            "storage/app/" . $suffix, "storage/uploads/" . $suffix, "public/uploads/" . $suffix,
            "scripts/" . $suffix . ".php", "src/Support/" . $suffix . ".php", ".env." . $suffix];
        try {
            foreach ($paths as $path) {
                if (!is_dir(dirname(base_path($path)))) {
                    mkdir(dirname(base_path($path)), 0777, true);
                }
                file_put_contents(base_path($path), "synthetic export fixture\n");
            }
            $command = new MakeProjectCommand($GLOBALS["fnlla_container"]);
            self::assertSame(0, $command->handle([$this->targetPath, "Clean Export", "--no-interaction"]));
            foreach ($paths as $path) {
                self::assertFalse(is_file($this->targetPath . "/" . $path), $path);
            }
            foreach (["benchmark.php", "publish-fnlla-runtime.ps1", "audit-fnlla-ecosystem.ps1", "validate-release-metadata.php"] as $file) {
                self::assertFalse(is_file($this->targetPath . "/scripts/" . $file));
            }
            self::assertFileExists($this->targetPath . "/tests/ProjectTest.php");
            foreach (["ApplicationSurfaceTest.php", "RuntimeAiTest.php", "PerformanceAndAiTest.php", "OperationsTest.php"] as $file) {
                self::assertFalse(is_file($this->targetPath . "/tests/" . $file));
            }
            self::assertSame("sprite", trim((string) file_get_contents($this->targetPath . "/.fnlla/ui-distribution")));
            self::assertFalse(is_file($this->targetPath . "/.fnlla/modules-opt-in"));
            [$configExit, $configOutput] = $this->runPhpScript($this->targetPath . "/fnlla", ["config:doctor", "--json"]);
            self::assertSame(0, $configExit, $configOutput);
            foreach (["WORKSPACE", "ANALYTICS", "HEATMAP", "CUSTOMER_PORTAL"] as $module) {
                self::assertStringContainsString("FNLLA_MODULE_" . $module . "=true", (string) file_get_contents($this->targetPath . "/.env.example"));
            }
            self::assertSame(4, count(glob($this->targetPath . "/public/vendor/fnlla-runtime/assets/icons/*") ?: []));
            self::assertFileExists($this->targetPath . "/public/uploads/.gitignore");

            $queuePath = $this->targetPath . "/storage/framework/queue/" . $suffix;
            $sessionPath = $this->targetPath . "/storage/framework/sessions/" . $suffix;
            file_put_contents($queuePath, "queued synthetic work");
            file_put_contents($sessionPath, "synthetic session");
            [$exitCode, $output] = $this->runPhpScript($this->targetPath . "/fnlla", ["release:prepare", "--skip-tests", "--json"]);
            self::assertSame(0, $exitCode, $output);
            $report = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
            self::assertSame("unknown", $report["risk"]);
            self::assertSame("skipped", $report["validation"]);
            self::assertSame("queued synthetic work", file_get_contents($queuePath));
            self::assertSame("synthetic session", file_get_contents($sessionPath));
        } finally {
            foreach ($paths as $path) {
                if (is_file(base_path($path))) {
                    unlink(base_path($path));
                }
            }
        }
    }

    private function assertExportBudget(int $maxBytes, int $maxFiles): void
    {
        $bytes = 0;
        $count = 0;
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->targetPath, RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
            if ($file->isFile()) {
                $bytes += $file->getSize();
                $count++;
            }
        }
        self::assertTrue($bytes <= $maxBytes, "Export exceeds its byte budget: " . $bytes . " > " . $maxBytes);
        self::assertTrue($count <= $maxFiles, "Export exceeds its file budget: " . $count . " > " . $maxFiles);
    }

    public function testFailedCacheRebuildsPreservePreviouslyWorkingExports(): void
    {
        $command = new MakeProjectCommand($GLOBALS["fnlla_container"]);
        self::assertSame(0, $command->handle([$this->targetPath, "Cache test", "--profile=full", "--no-interaction"]));
        $configPath = $this->targetPath . "/storage/framework/cache/bootstrap-config.php";
        [$exit, $output] = $this->runPhpScript($this->targetPath . "/fnlla", ["config:cache"]);
        self::assertSame(0, $exit, $output);
        $oldConfig = hash_file("sha256", $configPath);
        file_put_contents($this->targetPath . "/config/failure-fixture.php", "<?php return new stdClass();");
        [$exit, $output] = $this->runPhpScript($this->targetPath . "/fnlla", ["config:cache"]);
        self::assertSame(1, $exit, $output);
        self::assertSame($oldConfig, hash_file("sha256", $configPath));
        unlink($this->targetPath . "/config/failure-fixture.php");
        [$exit, $output] = $this->runPhpScript($this->targetPath . "/fnlla", ["route:cache"]);
        self::assertSame(0, $exit, $output);
        $routePath = $this->targetPath . "/storage/framework/cache/routes.php";
        $oldRoutes = hash_file("sha256", $routePath);
        $original = (string) file_get_contents($this->targetPath . "/routes/web.php");
        file_put_contents($this->targetPath . "/routes/web.php", $original . "\n" . '$router->get("/uncacheable", static fn (): string => "fixture");');
        [$exit, $output] = $this->runPhpScript($this->targetPath . "/fnlla", ["route:cache"]);
        self::assertSame(1, $exit, $output);
        self::assertSame($oldRoutes, hash_file("sha256", $routePath));
        self::assertStringContainsString("closure", $output);
        [$exit, $output] = $this->runPhpScript($this->targetPath . "/fnlla", ["route:list"]);
        self::assertSame(0, $exit, $output);
        self::assertStringNotContainsString("uncacheable", $output);
        file_put_contents($this->targetPath . "/routes/web.php", $original);
        [$exit, $output] = $this->runPhpScript($this->targetPath . "/fnlla", ["route:cache"]);
        self::assertSame(0, $exit, $output);
        self::assertSame($oldRoutes, hash_file("sha256", $routePath));
    }

    public function testCompletePackagePreviewUsesTheSameCoreInventoryAndBootsOffline(): void
    {
        $command = new MakeProjectCommand($GLOBALS["fnlla_container"]);
        self::assertSame(0, $command->handle([$this->targetPath, "Package test", "--profile=full", "--packages", "--no-interaction"]));
        $metadata = json_decode((string) file_get_contents($this->targetPath . "/composer.json"), true, 512, JSON_THROW_ON_ERROR);
        self::assertArrayHasKey("techayodev/fnlla-core", $metadata["require"]);
        self::assertArrayHasKey("techayodev/fnlla-complete", $metadata["require"]);
        $core = json_decode((string) file_get_contents(base_path("resources/project-templates/v1/core-files.json")), true, 512, JSON_THROW_ON_ERROR);
        foreach ($core["files"] as $path) {
            self::assertSame(hash_file("sha256", base_path($path)), hash_file("sha256", $this->targetPath . "/packages/fnlla-core/" . $path), $path);
        }
        self::assertFalse(is_file($this->targetPath . "/src/Application.php"));
        self::assertFileExists($this->targetPath . "/src/Controllers/PageController.php");
        [$exit, $output] = $this->runPhpScript($this->targetPath . "/fnlla", ["route:list"]);
        self::assertSame(0, $exit, $output);
        self::assertStringContainsString("developer.panel", $output);
    }

    public function testGeneratorsWorkInBothPresetsAndPackagePreview(): void
    {
        foreach (["plain", "full", "packages"] as $profile) {
            $root = $this->targetPath . "/" . $profile;
            $options = $profile === "packages" ? ["--profile=full", "--packages"] : ["--profile=" . $profile];
            $command = new MakeProjectCommand($GLOBALS["fnlla_container"]);
            self::assertSame(0, $command->handle([$root, "Generator test", ...$options]));
            $generated = ["controller" => "app/Controllers/ExampleController.php",
                "middleware" => "app/Middleware/ExampleMiddleware.php", "command" => "app/Console/Commands/ExampleCommand.php",
                "factory" => "database/factories/ExampleFactory.php", "seeder" => "database/seeders/ExampleSeeder.php"];
            foreach ($generated as $kind => $path) {
                [$exit, $output] = $this->runPhpScript($root . "/fnlla", ["make:" . $kind, "Example"]);
                self::assertSame(0, $exit, $profile . ": " . $output);
                self::assertFileExists($root . "/" . $path);
                $hash = hash_file("sha256", $root . "/" . $path);
                [$exit, $output] = $this->runPhpScript($root . "/fnlla", ["make:" . $kind, "Example"]);
                self::assertSame(1, $exit, $output);
                self::assertSame($hash, hash_file("sha256", $root . "/" . $path));
            }
            [$exit, $output] = $this->runPhpScript($root . "/fnlla", ["make:migration", "create_examples"]);
            self::assertSame(0, $exit, $output);
            self::assertSame(1, count(glob($root . "/database/migrations/*_create_examples.php") ?: []));
            foreach (["../Escape", "Foo/Bar", "Foo\\Bar", "Controller", "--typo", ""] as $name) {
                [$exit, $output] = $this->runPhpScript($root . "/fnlla", ["make:controller", $name]);
                self::assertSame(1, $exit, $output);
            }
            $probe = $root . "/generator-probe.php";
            file_put_contents($probe, '<?php require __DIR__ . "/bootstrap/common.php"; foreach (["App\\\\Controllers\\\\ExampleController", "App\\\\Middleware\\\\ExampleMiddleware", "App\\\\Console\\\\Commands\\\\ExampleCommand", "Database\\\\Factories\\\\ExampleFactory", "Database\\\\Seeders\\\\ExampleSeeder"] as $class) { if (!class_exists($class)) { throw new RuntimeException($class); } }');
            [$exit, $output] = $this->runPhpScript($probe);
            self::assertSame(0, $exit, $profile . ": " . $output);
            unlink($probe);
            [$exit, $output] = $this->runPhpScript($root . "/scripts/lint.php");
            self::assertSame(0, $exit, $profile . ": " . $output);
            $composer = json_decode((string) file_get_contents($root . "/composer.json"), true);
            foreach (["../outside/", "vendor/custom/", "packages/fnlla-core/src/"] as $unsafe) {
                $composer["autoload"]["psr-4"]["App\\"] = $unsafe;
                file_put_contents($root . "/composer.json", json_encode($composer));
                [$exit, $output] = $this->runPhpScript($root . "/fnlla", ["make:controller", "Unsafe"]);
                self::assertSame(1, $exit, $output);
                self::assertStringContainsString("application-owned", $output);
            }
        }
    }

    private function runPhpScript(string $scriptPath, array $arguments = []): array
    {
        $escapedArguments = array_map(
            static fn (string $argument): string => '"' . str_replace('"', '\"', $argument) . '"',
            $arguments
        );
        $command = '"' . PHP_BINARY . '" "' . $scriptPath . '"'
            . ($escapedArguments !== [] ? ' ' . implode(' ', $escapedArguments) : '')
            . ' 2>&1';
        $lines = [];
        $exitCode = 1;

        exec($command, $lines, $exitCode);

        return [$exitCode, implode(PHP_EOL, $lines)];
    }
}
