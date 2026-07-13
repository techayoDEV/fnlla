<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA TEST CASE
File: tests\ApplicationSurfaceTest.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Confirms the starter behaves like the public application base while maintenance and
  health remain linked framework capabilities.
===============================================================================
*/

namespace Fnlla\Php\Tests;

use Fnlla\Php\Application;
use Fnlla\Php\Container\Container;
use Fnlla\Php\Exceptions\ExceptionHandler;
use Fnlla\Php\Http\Request;
use PHPUnit\Framework\TestCase;

final class ApplicationSurfaceTest extends TestCase
{
    private mixed $containerBackup;
    private array $sessionBackup = [];
    private mixed $maintenanceConfigBackup;
    private mixed $developerAccessConfigBackup;
    private mixed $frameworkUpdateConfigBackup;
    private ?string $temporaryEnvironmentDirectory = null;

    protected function setUp(): void
    {
        $this->containerBackup = $GLOBALS["fnlla_container"] ?? $GLOBALS["fnlla_php_container"] ?? null;
        $this->sessionBackup = $_SESSION ?? [];
        $this->maintenanceConfigBackup = config("maintenance");
        $this->developerAccessConfigBackup = config("developer_access");
        $this->frameworkUpdateConfigBackup = config("framework_update");
        $_SESSION = [];
        config_set("maintenance", array_merge((array) config("maintenance", []), [
            "enabled" => false,
            "username" => "",
            "password" => "",
        ]));
        config_set("developer_access", array_merge((array) config("developer_access", []), [
            "enabled" => true,
            "path" => "",
            "password" => "",
            "operations_nav_mode" => "visible",
        ]));
    }

    protected function tearDown(): void
    {
        $GLOBALS["fnlla_container"] = $this->containerBackup;
        $GLOBALS["fnlla_php_container"] = $this->containerBackup;
        $_SESSION = $this->sessionBackup;
        config_set("maintenance", $this->maintenanceConfigBackup);
        config_set("developer_access", $this->developerAccessConfigBackup);
        config_set("framework_update", $this->frameworkUpdateConfigBackup);

        if ($this->temporaryEnvironmentDirectory !== null && is_dir($this->temporaryEnvironmentDirectory)) {
            $entries = scandir($this->temporaryEnvironmentDirectory) ?: [];

            foreach ($entries as $entry) {
                if ($entry === "." || $entry === "..") {
                    continue;
                }

                $path = $this->temporaryEnvironmentDirectory . DIRECTORY_SEPARATOR . $entry;

                if (is_file($path)) {
                    unlink($path);
                }
            }

            rmdir($this->temporaryEnvironmentDirectory);
        }
    }

    public function testHomePageRendersStarterOwnedContent(): void
    {
        $application = $this->makeApplication();

        $response = $application->handle(Request::capture("", [
            "REQUEST_URI" => "/",
            "REQUEST_METHOD" => "GET",
        ]));

        self::assertSame(200, $response->status());
        self::assertStringContainsString("Starter-first development", $response->body());
        self::assertStringContainsString("Services", $response->body());
        self::assertStringContainsString("About", $response->body());
        self::assertStringNotContainsString("DEV OPERATIONS", $response->body());
        self::assertStringNotContainsString(">Operations<", $response->body());
    }

    public function testStarterPagesAreAvailableThroughPublicRoutes(): void
    {
        $application = $this->makeApplication();

        $aboutResponse = $application->handle(Request::capture("", [
            "REQUEST_URI" => "/about",
            "REQUEST_METHOD" => "GET",
        ]));
        $servicesResponse = $application->handle(Request::capture("", [
            "REQUEST_URI" => "/services",
            "REQUEST_METHOD" => "GET",
        ]));
        $contactResponse = $application->handle(Request::capture("", [
            "REQUEST_URI" => "/contact",
            "REQUEST_METHOD" => "GET",
        ]));

        self::assertSame(200, $aboutResponse->status());
        self::assertSame(200, $servicesResponse->status());
        self::assertSame(404, $contactResponse->status());
        self::assertStringContainsString("Who this starter is for", $aboutResponse->body());
        self::assertStringContainsString("Service websites", $servicesResponse->body());
    }

    public function testHealthRouteRedirectsToMaintenanceSurface(): void
    {
        $application = $this->makeApplication();

        $response = $application->handle(Request::capture("", [
            "REQUEST_URI" => "/health",
            "REQUEST_METHOD" => "GET",
        ]));

        self::assertSame(302, $response->status());
        self::assertSame("/maintenance/health", $response->headers()["Location"] ?? null);
    }

    public function testMaintenanceHealthPageIsAvailable(): void
    {
        $application = $this->makeApplication();

        $response = $application->handle(Request::capture("", [
            "REQUEST_URI" => "/maintenance/health",
            "REQUEST_METHOD" => "GET",
            "REMOTE_ADDR" => "127.0.0.1",
        ]));

        self::assertSame(200, $response->status());
        self::assertStringContainsString("Current snapshot", $response->body());
        self::assertStringContainsString("Open raw JSON", $response->body());
    }

    public function testFrameworkUpdatePageShowsReadyToApplyMessageAndInlineApplyAction(): void
    {
        config_set("framework_update", array_merge((array) config("framework_update", []), [
            "ui_enabled" => true,
            "ui_local_only" => true,
            "ui_apply_enabled" => true,
            "github_enabled" => true,
        ]));
        $_SESSION["_flash_old"]["framework_update_report"] = [
            "mode" => "check",
            "executed_at_utc" => "2026-07-13T10:00:00+00:00",
            "current_framework_version" => "1.0.0",
            "source_framework_version" => "1.1.0",
            "current_ui_version" => "1.0.0",
            "source_ui_version" => "1.1.0",
            "updates" => [
                "src/Support/PageMeta.php" => [
                    "action" => "update",
                ],
            ],
            "conflicts" => [],
            "local_only_changes" => [],
            "source_root" => "C:\\workspace\\fnlla",
            "headline_title" => "Update is ready to apply",
            "headline_text" => "FNLLA detected an upstream framework shift (FNLLA 1.0.0 -> 1.1.0, Runtime 1.0.0 -> 1.1.0) and prepared the safe portion of the update. You can apply the audited update directly from this page.",
            "version_transition_summary" => "FNLLA 1.0.0 -> 1.1.0, Runtime 1.0.0 -> 1.1.0",
            "update_ready" => true,
            "requires_manual_review" => false,
            "apply_action_available" => true,
            "can_apply_from_ui" => true,
            "recommended_apply_mode" => "apply",
        ];

        $application = $this->makeApplication();
        $response = $application->handle(Request::capture("", [
            "REQUEST_URI" => "/maintenance/framework-update",
            "REQUEST_METHOD" => "GET",
            "REMOTE_ADDR" => "127.0.0.1",
        ]));

        self::assertSame(200, $response->status());
        self::assertStringContainsString("Update is ready to apply", $response->body());
        self::assertStringContainsString("Apply this audited local update", $response->body());
        self::assertStringContainsString("Detected version shift:", $response->body());
        self::assertStringContainsString("FNLLA 1.0.0 -&gt; 1.1.0, Runtime 1.0.0 -&gt; 1.1.0", $response->body());
    }

    public function testFrameworkUpdatePageExplainsConflictNextStepInHumanLanguage(): void
    {
        config_set("framework_update", array_merge((array) config("framework_update", []), [
            "ui_enabled" => true,
            "ui_local_only" => true,
            "ui_apply_enabled" => true,
        ]));
        $_SESSION["_flash_old"]["framework_update_report"] = [
            "mode" => "check",
            "executed_at_utc" => "2026-07-13T10:00:00+00:00",
            "current_framework_version" => "1.0.0",
            "source_framework_version" => "1.1.0",
            "current_ui_version" => "1.0.0",
            "source_ui_version" => "1.1.0",
            "updates" => [],
            "conflicts" => [
                "views/maintenance/index.php" => [
                    "reason" => "framework-managed file changed both locally and upstream",
                    "summary" => "This project edited the same framework-managed file that FNLLA also changed in the maintained source.",
                    "next_step" => "Compare the local project file with the maintained source version, keep the intended project-specific edits, save the resolved file into the project, then rerun the framework update check.",
                ],
            ],
            "local_only_changes" => [],
            "source_root" => "C:\\workspace\\fnlla",
            "headline_title" => "Newer framework base detected, but manual review is required",
            "headline_text" => "FNLLA detected an upstream framework shift, but one or more framework-managed files changed both locally and upstream. Review those conflicts before applying the update.",
            "requires_manual_review" => true,
        ];

        $application = $this->makeApplication();
        $response = $application->handle(Request::capture("", [
            "REQUEST_URI" => "/maintenance/framework-update",
            "REQUEST_METHOD" => "GET",
            "REMOTE_ADDR" => "127.0.0.1",
        ]));

        self::assertSame(200, $response->status());
        self::assertStringContainsString("views/maintenance/index.php", $response->body());
        self::assertStringContainsString("This project edited the same framework-managed file that FNLLA also changed in the maintained source.", $response->body());
        self::assertStringContainsString("Next step:", $response->body());
        self::assertStringContainsString("Compare the local project file with the maintained source version", $response->body());
    }

    public function testApiHealthReturnsStructuredJsonPayload(): void
    {
        $application = $this->makeApplication();

        $response = $application->handle(Request::capture("", [
            "REQUEST_URI" => "/api/health",
            "REQUEST_METHOD" => "GET",
            "REMOTE_ADDR" => "127.0.0.1",
            "HTTP_ACCEPT" => "application/json",
        ]));

        self::assertSame(200, $response->status());
        self::assertSame("application/json; charset=UTF-8", $response->headers()["Content-Type"] ?? null);
        self::assertStringContainsString('"name": "' . (string) config("app.name") . '"', $response->body());
        self::assertStringContainsString('"api_health": "/api/health"', $response->body());
    }

    public function testApiHealthRendersBrowserFriendlyViewByDefault(): void
    {
        $application = $this->makeApplication();

        $response = $application->handle(Request::capture("", [
            "REQUEST_URI" => "/api/health",
            "REQUEST_METHOD" => "GET",
            "REMOTE_ADDR" => "127.0.0.1",
            "HTTP_ACCEPT" => "text/html",
        ]));

        self::assertSame(200, $response->status());
        self::assertStringContainsString("Browser-friendly API health", $response->body());
        self::assertStringContainsString("Open raw JSON", $response->body());
    }

    public function testApiHealthCanForceJsonFromBrowserWithFormatQuery(): void
    {
        $application = $this->makeApplication();

        $response = $application->handle(Request::capture("", [
            "REQUEST_URI" => "/api/health?format=json",
            "REQUEST_METHOD" => "GET",
            "REMOTE_ADDR" => "127.0.0.1",
            "HTTP_ACCEPT" => "text/html",
        ], [
            "format" => "json",
        ]));

        self::assertSame(200, $response->status());
        self::assertSame("application/json; charset=UTF-8", $response->headers()["Content-Type"] ?? null);
        self::assertStringContainsString('"status": "ok"', $response->body());
    }

    public function testMaintenanceHealthPageDropsReadinessSection(): void
    {
        $application = $this->makeApplication();

        $response = $application->handle(Request::capture("", [
            "REQUEST_URI" => "/maintenance/health",
            "REQUEST_METHOD" => "GET",
            "REMOTE_ADDR" => "127.0.0.1",
        ]));

        self::assertSame(200, $response->status());
        self::assertStringNotContainsString("The core downstream signals are grouped as operational checkpoints.", $response->body());
        self::assertStringContainsString("Open raw JSON", $response->body());
    }

    public function testEnabledMaintenanceRedirectsLockedPublicRequestsToMaintenance(): void
    {
        config_set("maintenance", array_merge((array) config("maintenance", []), [
            "enabled" => true,
            "password" => "client-preview",
        ]));

        $application = $this->makeApplication();

        $response = $application->handle(Request::capture("", [
            "REQUEST_URI" => "/",
            "REQUEST_METHOD" => "GET",
        ]));

        self::assertSame(302, $response->status());
        self::assertSame("/maintenance?redirect=%2F", $response->headers()["Location"] ?? null);
    }

    public function testMaintenanceScreenRendersUnlockFormWhenLocked(): void
    {
        config_set("maintenance", array_merge((array) config("maintenance", []), [
            "enabled" => true,
            "password" => "client-preview",
        ]));

        $application = $this->makeApplication();

        $response = $application->handle(Request::capture("", [
            "REQUEST_URI" => "/maintenance",
            "REQUEST_METHOD" => "GET",
        ]));

        self::assertSame(200, $response->status());
        self::assertStringContainsString("Unlock maintenance", $response->body());
        self::assertStringContainsString("Unlock access", $response->body());
        self::assertStringContainsString("maintenance_password", $response->body());
        self::assertStringContainsString("maintenance-unlock-modal", $response->body());
        self::assertStringContainsString("data-fnlla-modal-locked", $response->body());
        self::assertFalse(str_contains($response->body(), "Stay on fallback page"));
    }

    public function testCorrectMaintenancePasswordUnlocksSession(): void
    {
        config_set("maintenance", array_merge((array) config("maintenance", []), [
            "enabled" => true,
            "password" => "client-preview",
        ]));

        $application = $this->makeApplication();
        $token = csrf_token();

        $unlockResponse = $application->handle(Request::capture("", [
            "REQUEST_URI" => "/maintenance/unlock",
            "REQUEST_METHOD" => "POST",
            "REMOTE_ADDR" => "127.0.0.1",
        ], [], [
            "_token" => $token,
            "maintenance_redirect" => "/",
            "maintenance_password" => "client-preview",
        ]));

        self::assertSame(302, $unlockResponse->status());
        self::assertSame("/", $unlockResponse->headers()["Location"] ?? null);
        self::assertSame(true, $_SESSION["maintenance.access_unlocked"] ?? false);

        $homeResponse = $application->handle(Request::capture("", [
            "REQUEST_URI" => "/",
            "REQUEST_METHOD" => "GET",
            "REMOTE_ADDR" => "127.0.0.1",
        ]));

        self::assertSame(200, $homeResponse->status());
        self::assertStringContainsString("Starter-first development", $homeResponse->body());
    }

    public function testFreshStarterCanConfigureMaintenancePasswordFromMaintenancePage(): void
    {
        $this->temporaryEnvironmentDirectory = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . "fnlla-maintenance-setup-"
            . bin2hex(random_bytes(4));
        mkdir($this->temporaryEnvironmentDirectory, 0777, true);
        $envPath = $this->temporaryEnvironmentDirectory . DIRECTORY_SEPARATOR . ".env";
        $envExamplePath = $this->temporaryEnvironmentDirectory . DIRECTORY_SEPARATOR . ".env.example";
        file_put_contents($envExamplePath, "APP_ENV=development" . PHP_EOL . "APP_DEBUG=true" . PHP_EOL);

        config_set("maintenance", array_merge((array) config("maintenance", []), [
            "enabled" => false,
            "password" => "",
            "username" => "",
            "setup_ui_enabled" => true,
            "setup_ui_local_only" => true,
            "env_path" => $envPath,
            "env_example_path" => $envExamplePath,
        ]));
        config_set("developer_access", array_merge((array) config("developer_access", []), [
            "enabled" => true,
            "path" => "",
            "password" => "",
            "operations_nav_mode" => "visible",
        ]));

        $application = $this->makeApplication();
        $pageResponse = $application->handle(Request::capture("", [
            "REQUEST_URI" => "/maintenance",
            "REQUEST_METHOD" => "GET",
            "REMOTE_ADDR" => "127.0.0.1",
        ]));

        self::assertSame(200, $pageResponse->status());
        self::assertStringContainsString("Configure maintenance access", $pageResponse->body());
        self::assertStringNotContainsString("maintenance_setup_username", $pageResponse->body());

        $token = csrf_token();
        $setupResponse = $application->handle(Request::capture("", [
            "REQUEST_URI" => "/maintenance/setup-access",
            "REQUEST_METHOD" => "POST",
            "REMOTE_ADDR" => "127.0.0.1",
        ], [], [
            "_token" => $token,
            "maintenance_setup_username" => "",
            "maintenance_setup_password" => "client-preview",
            "maintenance_setup_password_confirmation" => "client-preview",
        ]));

        self::assertSame(302, $setupResponse->status());
        $developerPanelPath = (string) ($setupResponse->headers()["Location"] ?? "");
        self::assertTrue(str_starts_with($developerPanelPath, "/_dev-"));
        self::assertTrue(str_ends_with($developerPanelPath, "/panel"));
        $developerPath = substr($developerPanelPath, 0, -strlen("/panel"));
        self::assertFalse($_SESSION["maintenance.access_unlocked"] ?? false);
        self::assertSame(true, $_SESSION["developer.access_unlocked"] ?? false);
        self::assertFileExists($envPath);
        self::assertStringContainsString("MAINTENANCE_MODE_ENABLED=true", (string) file_get_contents($envPath));
        self::assertStringContainsString("MAINTENANCE_ACCESS_PASSWORD=client-preview", (string) file_get_contents($envPath));
        self::assertStringContainsString("DEVELOPER_ACCESS_PATH=" . $developerPath, (string) file_get_contents($envPath));
        self::assertStringContainsString("DEVELOPER_ACCESS_PASSWORD=client-preview", (string) file_get_contents($envPath));

        config_set("developer_access", array_merge((array) config("developer_access", []), [
            "path" => $developerPath,
            "password" => "client-preview",
            "operations_nav_mode" => "visible",
        ]));
        $developerApplication = $this->makeApplication();
        $developerResponse = $developerApplication->handle(Request::capture("", [
            "REQUEST_URI" => $developerPanelPath,
            "REQUEST_METHOD" => "GET",
            "REMOTE_ADDR" => "127.0.0.1",
        ]));

        self::assertSame(200, $developerResponse->status());
        self::assertStringContainsString("Developer Panel", $developerResponse->body());
        self::assertStringContainsString($developerPath, $developerResponse->body());
        self::assertStringContainsString("Regenerate private developer path", $developerResponse->body());

        $homeResponse = $application->handle(Request::capture("", [
            "REQUEST_URI" => "/",
            "REQUEST_METHOD" => "GET",
            "REMOTE_ADDR" => "127.0.0.1",
        ]));

        self::assertSame(200, $homeResponse->status());
        self::assertStringContainsString("Starter-first development", $homeResponse->body());
    }

    public function testExistingProjectCanActivateDeveloperPanelAfterFrameworkUpdate(): void
    {
        $this->temporaryEnvironmentDirectory = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . "fnlla-developer-activation-"
            . bin2hex(random_bytes(4));
        mkdir($this->temporaryEnvironmentDirectory, 0777, true);
        $envPath = $this->temporaryEnvironmentDirectory . DIRECTORY_SEPARATOR . ".env";
        $envExamplePath = $this->temporaryEnvironmentDirectory . DIRECTORY_SEPARATOR . ".env.example";
        file_put_contents($envExamplePath, "APP_ENV=development" . PHP_EOL . "APP_DEBUG=true" . PHP_EOL);
        file_put_contents(
            $envPath,
            "APP_ENV=development" . PHP_EOL
            . "APP_DEBUG=true" . PHP_EOL
            . "MAINTENANCE_MODE_ENABLED=true" . PHP_EOL
            . "MAINTENANCE_ACCESS_PASSWORD=legacy-preview" . PHP_EOL
        );

        config_set("maintenance", array_merge((array) config("maintenance", []), [
            "enabled" => true,
            "password" => "legacy-preview",
            "username" => "",
            "env_path" => $envPath,
            "env_example_path" => $envExamplePath,
        ]));
        config_set("developer_access", array_merge((array) config("developer_access", []), [
            "enabled" => true,
            "path" => "",
            "password" => "",
            "operations_nav_mode" => "visible",
            "setup_ui_enabled" => true,
            "setup_ui_local_only" => true,
        ]));

        $application = $this->makeApplication();
        $token = csrf_token();

        $unlockResponse = $application->handle(Request::capture("", [
            "REQUEST_URI" => "/maintenance/unlock",
            "REQUEST_METHOD" => "POST",
            "REMOTE_ADDR" => "127.0.0.1",
        ], [], [
            "_token" => $token,
            "maintenance_redirect" => "/maintenance",
            "maintenance_password" => "legacy-preview",
        ]));

        self::assertSame(302, $unlockResponse->status());
        self::assertSame("/maintenance", $unlockResponse->headers()["Location"] ?? null);
        self::assertSame(true, $_SESSION["maintenance.access_unlocked"] ?? false);

        $maintenanceResponse = $application->handle(Request::capture("", [
            "REQUEST_URI" => "/maintenance",
            "REQUEST_METHOD" => "GET",
            "REMOTE_ADDR" => "127.0.0.1",
        ]));

        self::assertSame(200, $maintenanceResponse->status());
        self::assertStringContainsString("Activate developer panel", $maintenanceResponse->body());
        self::assertStringNotContainsString("developer_operations_nav_mode", $maintenanceResponse->body());

        $activationResponse = $application->handle(Request::capture("", [
            "REQUEST_URI" => "/maintenance/setup-developer-access",
            "REQUEST_METHOD" => "POST",
            "REMOTE_ADDR" => "127.0.0.1",
        ], [], [
            "_token" => csrf_token(),
            "developer_setup_password" => "developer-secret",
            "developer_setup_password_confirmation" => "developer-secret",
        ]));

        self::assertSame(302, $activationResponse->status());
        $developerPanelPath = (string) ($activationResponse->headers()["Location"] ?? "");
        self::assertTrue(str_starts_with($developerPanelPath, "/_dev-"));
        self::assertTrue(str_ends_with($developerPanelPath, "/panel"));
        $developerPath = substr($developerPanelPath, 0, -strlen("/panel"));
        self::assertSame(true, $_SESSION["developer.access_unlocked"] ?? false);
        self::assertStringContainsString("DEVELOPER_ACCESS_ENABLED=true", (string) file_get_contents($envPath));
        self::assertStringContainsString("DEVELOPER_ACCESS_PATH=" . $developerPath, (string) file_get_contents($envPath));
        self::assertStringContainsString("DEVELOPER_ACCESS_PASSWORD=developer-secret", (string) file_get_contents($envPath));
        self::assertStringContainsString("DEVELOPER_OPERATIONS_NAV_MODE=hidden", (string) file_get_contents($envPath));

        config_set("developer_access", array_merge((array) config("developer_access", []), [
            "path" => $developerPath,
            "password" => "developer-secret",
            "operations_nav_mode" => "hidden",
        ]));
        $developerApplication = $this->makeApplication();
        $developerResponse = $developerApplication->handle(Request::capture("", [
            "REQUEST_URI" => $developerPanelPath,
            "REQUEST_METHOD" => "GET",
            "REMOTE_ADDR" => "127.0.0.1",
        ]));

        self::assertSame(200, $developerResponse->status());
        self::assertStringContainsString("Developer Panel", $developerResponse->body());
    }

    public function testLockedMaintenanceScreenCanStillSaveFirstPasswordWhenModeWasEnabledWithoutCredentials(): void
    {
        $this->temporaryEnvironmentDirectory = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . "fnlla-maintenance-locked-setup-"
            . bin2hex(random_bytes(4));
        mkdir($this->temporaryEnvironmentDirectory, 0777, true);
        $envPath = $this->temporaryEnvironmentDirectory . DIRECTORY_SEPARATOR . ".env";
        $envExamplePath = $this->temporaryEnvironmentDirectory . DIRECTORY_SEPARATOR . ".env.example";
        file_put_contents($envExamplePath, "APP_ENV=development" . PHP_EOL . "APP_DEBUG=true" . PHP_EOL);

        config_set("maintenance", array_merge((array) config("maintenance", []), [
            "enabled" => true,
            "password" => "",
            "username" => "",
            "setup_ui_enabled" => true,
            "setup_ui_local_only" => true,
            "env_path" => $envPath,
            "env_example_path" => $envExamplePath,
        ]));
        config_set("developer_access", array_merge((array) config("developer_access", []), [
            "enabled" => true,
            "path" => "",
            "password" => "",
            "operations_nav_mode" => "visible",
        ]));

        $application = $this->makeApplication();
        $pageResponse = $application->handle(Request::capture("", [
            "REQUEST_URI" => "/maintenance",
            "REQUEST_METHOD" => "GET",
            "REMOTE_ADDR" => "127.0.0.1",
        ]));

        self::assertSame(200, $pageResponse->status());
        self::assertStringContainsString("Set the first maintenance password from the starter itself", $pageResponse->body());

        $token = csrf_token();
        $setupResponse = $application->handle(Request::capture("", [
            "REQUEST_URI" => "/maintenance/setup-access",
            "REQUEST_METHOD" => "POST",
            "REMOTE_ADDR" => "127.0.0.1",
        ], [], [
            "_token" => $token,
            "maintenance_setup_username" => "",
            "maintenance_setup_password" => "preview-lock",
            "maintenance_setup_password_confirmation" => "preview-lock",
        ]));

        self::assertSame(302, $setupResponse->status());
        self::assertTrue(str_starts_with((string) ($setupResponse->headers()["Location"] ?? ""), "/_dev-"));
        self::assertFalse($_SESSION["maintenance.access_unlocked"] ?? false);
        self::assertSame(true, $_SESSION["developer.access_unlocked"] ?? false);
        self::assertStringContainsString("MAINTENANCE_ACCESS_PASSWORD=preview-lock", (string) file_get_contents($envPath));
        self::assertStringContainsString("DEVELOPER_ACCESS_PASSWORD=preview-lock", (string) file_get_contents($envPath));

        $homeResponse = $application->handle(Request::capture("", [
            "REQUEST_URI" => "/",
            "REQUEST_METHOD" => "GET",
            "REMOTE_ADDR" => "127.0.0.1",
        ]));

        self::assertSame(200, $homeResponse->status());
    }

    public function testMaintenanceSurfaceCanBeHiddenBehindDeveloperSession(): void
    {
        config_set("developer_access", array_merge((array) config("developer_access", []), [
            "enabled" => true,
            "path" => "/_dev-service",
            "password" => "operator-pass",
            "operations_nav_mode" => "hidden",
        ]));

        $application = $this->makeApplication();
        $maintenanceResponse = $application->handle(Request::capture("", [
            "REQUEST_URI" => "/maintenance",
            "REQUEST_METHOD" => "GET",
            "REMOTE_ADDR" => "127.0.0.1",
        ]));
        $developerLoginResponse = $application->handle(Request::capture("", [
            "REQUEST_URI" => "/_dev-service",
            "REQUEST_METHOD" => "GET",
            "REMOTE_ADDR" => "127.0.0.1",
        ]));
        $developerPanelResponse = $application->handle(Request::capture("", [
            "REQUEST_URI" => "/_dev-service/panel",
            "REQUEST_METHOD" => "GET",
            "REMOTE_ADDR" => "127.0.0.1",
        ]));
        $healthRedirectResponse = $application->handle(Request::capture("", [
            "REQUEST_URI" => "/health",
            "REQUEST_METHOD" => "GET",
            "REMOTE_ADDR" => "127.0.0.1",
        ]));

        self::assertSame(404, $maintenanceResponse->status());
        self::assertSame(404, $healthRedirectResponse->status());
        self::assertSame(200, $developerLoginResponse->status());
        self::assertSame(404, $developerPanelResponse->status());
        self::assertStringContainsString("Unlock developer session", $developerLoginResponse->body());
        self::assertStringNotContainsString("Lock developer panel", $developerLoginResponse->body());
    }

    public function testHiddenOperationsMenuReturnsDuringUnlockedDeveloperSession(): void
    {
        config_set("developer_access", array_merge((array) config("developer_access", []), [
            "enabled" => true,
            "path" => "/_dev-service",
            "password" => "operator-pass",
            "operations_nav_mode" => "hidden",
        ]));

        $application = $this->makeApplication();
        developer_access()->grantAccess();

        $response = $application->handle(Request::capture("", [
            "REQUEST_URI" => "/",
            "REQUEST_METHOD" => "GET",
            "REMOTE_ADDR" => "127.0.0.1",
        ]));

        self::assertSame(200, $response->status());
        self::assertStringContainsString("DEV OPERATIONS", $response->body());
        self::assertStringContainsString("href=\"/_dev-service/panel\"", $response->body());
        self::assertStringContainsString(">Home<", $response->body());
        self::assertStringContainsString(">About<", $response->body());
        self::assertStringContainsString(">Services<", $response->body());
        self::assertStringNotContainsString(">Operations<", $response->body());
        self::assertStringNotContainsString("Maintenance Dashboard", $response->body());
        self::assertStringNotContainsString("Maintenance access required", $response->body());
    }

    public function testDeveloperPanelPublicSiteLinkOpensInNewTab(): void
    {
        config_set("developer_access", array_merge((array) config("developer_access", []), [
            "enabled" => true,
            "path" => "/_dev-service",
            "password" => "operator-pass",
            "operations_nav_mode" => "hidden",
        ]));

        $application = $this->makeApplication();
        developer_access()->grantAccess();

        $response = $application->handle(Request::capture("", [
            "REQUEST_URI" => "/_dev-service/panel",
            "REQUEST_METHOD" => "GET",
            "REMOTE_ADDR" => "127.0.0.1",
        ]));

        self::assertSame(200, $response->status());
        self::assertStringContainsString("target=\"_blank\"", $response->body());
        self::assertStringContainsString("rel=\"noopener noreferrer\"", $response->body());
        self::assertStringContainsString(">Open public site<", $response->body());
    }

    public function testDeveloperPanelCanUpdateMaintenanceCredentials(): void
    {
        $this->temporaryEnvironmentDirectory = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . "fnlla-developer-panel-"
            . bin2hex(random_bytes(4));
        mkdir($this->temporaryEnvironmentDirectory, 0777, true);
        $envPath = $this->temporaryEnvironmentDirectory . DIRECTORY_SEPARATOR . ".env";
        $envExamplePath = $this->temporaryEnvironmentDirectory . DIRECTORY_SEPARATOR . ".env.example";
        file_put_contents($envExamplePath, "APP_ENV=development" . PHP_EOL . "APP_DEBUG=true" . PHP_EOL);

        config_set("maintenance", array_merge((array) config("maintenance", []), [
            "enabled" => true,
            "password" => "preview-lock",
            "username" => "",
            "env_path" => $envPath,
            "env_example_path" => $envExamplePath,
        ]));
        config_set("developer_access", array_merge((array) config("developer_access", []), [
            "enabled" => true,
            "path" => "/_dev-service",
            "password" => "operator-pass",
            "operations_nav_mode" => "hidden",
        ]));

        $application = $this->makeApplication();
        developer_access()->grantAccess();
        $token = csrf_token();

        $response = $application->handle(Request::capture("", [
            "REQUEST_URI" => "/_dev-service/panel/settings/maintenance",
            "REQUEST_METHOD" => "POST",
            "REMOTE_ADDR" => "127.0.0.1",
        ], [], [
            "_token" => $token,
            "maintenance_access_username" => "",
            "maintenance_access_password" => "new-preview-pass",
            "maintenance_access_password_confirmation" => "new-preview-pass",
        ]));

        self::assertSame(302, $response->status());
        self::assertSame("/_dev-service/panel#developer-maintenance-settings", $response->headers()["Location"] ?? null);
        self::assertStringContainsString("MAINTENANCE_ACCESS_PASSWORD=new-preview-pass", (string) file_get_contents($envPath));
        self::assertFalse($_SESSION["maintenance.access_unlocked"] ?? false);
    }

    private function makeApplication(): Application
    {
        $container = new Container();
        $providers = [];

        foreach ((array) config("app.providers", []) as $providerClass) {
            $provider = new $providerClass($container);
            $provider->register();
            $providers[] = $provider;
        }

        foreach ($providers as $provider) {
            $provider->boot();
        }

        $GLOBALS["fnlla_container"] = $container;
        $GLOBALS["fnlla_php_container"] = $container;
        $router = (static function (Container $container) {
            return require base_path("bootstrap/router.php");
        })($container);

        $application = new Application($router, $container, $container->make(ExceptionHandler::class));
        $application->middleware(["cors", "maintenance"]);

        return $application;
    }
}
