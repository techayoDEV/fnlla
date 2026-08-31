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
- Confirms the project base behaves like the public application surface while
  maintenance and health remain linked framework capabilities.
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
    private mixed $developerControlConfigBackup;
    private mixed $developerWorkspaceConfigBackup;
    private mixed $integrationsConfigBackup;
    private mixed $observabilityConfigBackup;
    private mixed $clientPreviewConfigBackup;
    private mixed $frameworkUpdateConfigBackup;
    private mixed $appConfigBackup;
    private mixed $mailConfigBackup;
    private ?string $temporaryEnvironmentDirectory = null;

    protected function setUp(): void
    {
        $this->containerBackup = $GLOBALS["fnlla_container"] ?? $GLOBALS["fnlla_php_container"] ?? null;
        $this->sessionBackup = $_SESSION ?? [];
        $this->maintenanceConfigBackup = config("maintenance");
        $this->developerAccessConfigBackup = config("developer_access");
        $this->developerControlConfigBackup = config("developer_control");
        $this->developerWorkspaceConfigBackup = config("developer_workspace");
        $this->integrationsConfigBackup = config("integrations");
        $this->observabilityConfigBackup = config("observability");
        $this->clientPreviewConfigBackup = config("client_preview");
        $this->frameworkUpdateConfigBackup = config("framework_update");
        $this->appConfigBackup = config("app");
        $this->mailConfigBackup = config("mail");
        $_SESSION = [];
        config_set("maintenance", array_merge((array) config("maintenance", []), [
            "enabled" => false,
            "username" => "",
            "password" => "",
        ]));
        config_set("developer_access", array_merge((array) config("developer_access", []), [
            "enabled" => true,
            "path" => "",
            "email" => "",
            "password" => "",
            "password_hash" => "",
            "users" => "",
            "operations_nav_mode" => "visible",
        ]));
        config_set("developer_control", array_merge((array) config("developer_control", []), [
            "local_state_path" => "framework/testing/developer-control-default.json",
            "remote" => array_merge((array) config("developer_control.remote", []), [
                "enabled" => false,
            ]),
        ]));
        config_set("developer_workspace", array_merge((array) config("developer_workspace", []), [
            "path" => "framework/testing/developer-workspace-default.json",
            "notifications_state_path" => "framework/testing/developer-notifications-default.json",
        ]));
        config_set("client_preview", array_merge((array) config("client_preview", []), [
            "enabled" => false,
            "login_disabled" => false,
        ]));
    }

    protected function tearDown(): void
    {
        $GLOBALS["fnlla_container"] = $this->containerBackup;
        $GLOBALS["fnlla_php_container"] = $this->containerBackup;
        $_SESSION = $this->sessionBackup;
        config_set("maintenance", $this->maintenanceConfigBackup);
        config_set("developer_access", $this->developerAccessConfigBackup);
        config_set("developer_control", $this->developerControlConfigBackup);
        config_set("developer_workspace", $this->developerWorkspaceConfigBackup);
        config_set("integrations", $this->integrationsConfigBackup);
        config_set("observability", $this->observabilityConfigBackup);
        config_set("client_preview", $this->clientPreviewConfigBackup);
        config_set("framework_update", $this->frameworkUpdateConfigBackup);
        config_set("app", $this->appConfigBackup);
        config_set("mail", $this->mailConfigBackup);

        foreach ([
            storage_path("framework/testing/developer-workspace-default.json"),
            storage_path("framework/testing/developer-notifications-default.json"),
            storage_path("framework/testing/developer-control-default.json"),
            storage_path("framework/testing/consent-metrics-default.json"),
            storage_path("framework/developer/activity.jsonl"),
            storage_path("framework/developer/form-submissions.jsonl"),
            storage_path("framework/testing/contact-mail/" . gmdate("Ymd") . ".log"),
        ] as $path) {
            if (is_file($path)) {
                unlink($path);
            }

            if (is_file($path . ".lock")) {
                unlink($path . ".lock");
            }
        }

        foreach (glob(storage_path("framework/testing/developer-control-*.json")) ?: [] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

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

    public function testHomePageRendersProjectOwnedContent(): void
    {
        $application = $this->makeApplication();

        $response = $application->handle(Request::capture("", [
            "REQUEST_URI" => "/",
            "REQUEST_METHOD" => "GET",
        ]));

        self::assertSame(200, $response->status());
        self::assertStringContainsString("A server-rendered project base", $response->body());
        self::assertStringContainsString("Services", $response->body());
        self::assertStringContainsString("About", $response->body());
        self::assertStringContainsString("class=\"project-footer-developer-link\" href=\"/developer\"", $response->body());
        self::assertStringContainsString("href=\"/terms\"", $response->body());
        self::assertStringContainsString("href=\"/privacy\"", $response->body());
        self::assertStringContainsString("data-fnlla-cookie-banner", $response->body());
        self::assertStringContainsString("data-fnlla-cookie-settings-open", $response->body());
        self::assertStringContainsString("fnlla_cookie_consent_v1", $response->body());
        self::assertStringContainsString("/fnlla/consent", $response->body());
        self::assertStringContainsString("fnlla.cookie_consent_event.v1", $response->body());
        self::assertStringNotContainsString("project-navbar-actions", $response->body());
        self::assertStringNotContainsString("DEV OPERATIONS", $response->body());
        self::assertStringNotContainsString(">Operations<", $response->body());
    }

    public function testCookieConsentEndpointRecordsAggregateTelemetry(): void
    {
        config_set("observability", array_merge((array) config("observability", []), [
            "metrics" => array_merge((array) config("observability.metrics", []), [
                "enabled" => true,
                "path" => "framework/testing/consent-metrics-default.json",
            ]),
        ]));

        $application = $this->makeApplication();
        $response = $application->handle(Request::capture(json_encode([
            "schema" => "fnlla.cookie_consent_event.v1",
            "source" => "cookie-banner",
            "preferences" => [
                "analytics" => true,
                "marketing" => false,
            ],
        ], JSON_THROW_ON_ERROR), [
            "REQUEST_URI" => "/fnlla/consent",
            "REQUEST_METHOD" => "POST",
            "REMOTE_ADDR" => "203.0.113.99",
            "HTTP_USER_AGENT" => "Mozilla/5.0 private-test",
            "CONTENT_TYPE" => "application/json",
            "HTTP_ACCEPT" => "application/json",
        ]));

        self::assertSame(202, $response->status());
        self::assertStringContainsString("fnlla.cookie_consent_event.v1", $response->body());

        $metrics = json_decode((string) file_get_contents(storage_path("framework/testing/consent-metrics-default.json")), true);
        $encodedMetrics = json_encode($metrics, JSON_THROW_ON_ERROR);

        self::assertSame("fnlla.metrics.v1", $metrics["schema"] ?? null);
        self::assertSame(1, (int) ($metrics["consent_events_total"] ?? 0));
        self::assertSame(1, (int) ($metrics["consent_counts"]["analytics_only"] ?? 0));
        self::assertSame(1, (int) ($metrics["consent_analytics_allowed"] ?? 0));
        self::assertSame(0, (int) ($metrics["consent_marketing_allowed"] ?? 0));
        self::assertSame("analytics_only", $metrics["last_consent_event"]["state"] ?? null);
        self::assertStringNotContainsString("203.0.113.99", $encodedMetrics);
        self::assertStringNotContainsString("private-test", $encodedMetrics);
    }

    public function testAnalyticsEventEndpointRecordsConsentAwareBehaviorTelemetry(): void
    {
        config_set("observability", array_merge((array) config("observability", []), [
            "metrics" => array_merge((array) config("observability.metrics", []), [
                "enabled" => true,
                "path" => "framework/testing/consent-metrics-default.json",
            ]),
            "analytics" => array_merge((array) config("observability.analytics", []), [
                "enabled" => true,
            ]),
            "heatmap" => [
                "enabled" => true,
                "sample_rate" => 100,
                "click_grid_columns" => 5,
                "click_grid_rows" => 5,
            ],
        ]));

        $application = $this->makeApplication();
        $blockedResponse = $application->handle(Request::capture(json_encode([
            "schema" => "fnlla.behavior_event.v1",
            "type" => "click",
            "path" => "/services",
            "consent" => ["analytics" => false],
        ], JSON_THROW_ON_ERROR), [
            "REQUEST_URI" => "/fnlla/analytics/event",
            "REQUEST_METHOD" => "POST",
            "CONTENT_TYPE" => "application/json",
            "HTTP_ACCEPT" => "application/json",
        ]));
        $allowedResponse = $application->handle(Request::capture(json_encode([
            "schema" => "fnlla.behavior_event.v1",
            "type" => "click",
            "path" => "/services?private=value",
            "device" => "desktop",
            "element" => "button",
            "position" => ["x_percent" => 50, "y_percent" => 50],
            "consent" => ["analytics" => true],
        ], JSON_THROW_ON_ERROR), [
            "REQUEST_URI" => "/fnlla/analytics/event",
            "REQUEST_METHOD" => "POST",
            "REMOTE_ADDR" => "203.0.113.88",
            "HTTP_USER_AGENT" => "Mozilla/5.0 private-test",
            "CONTENT_TYPE" => "application/json",
            "HTTP_ACCEPT" => "application/json",
        ]));

        self::assertSame(202, $blockedResponse->status());
        self::assertStringContainsString("analytics_consent_required", $blockedResponse->body());
        self::assertSame(202, $allowedResponse->status());
        self::assertStringContainsString("fnlla.behavior_event.v1", $allowedResponse->body());

        $metrics = json_decode((string) file_get_contents(storage_path("framework/testing/consent-metrics-default.json")), true);
        $encodedMetrics = json_encode($metrics, JSON_THROW_ON_ERROR);

        self::assertSame(1, (int) ($metrics["behavior_events_total"] ?? 0));
        self::assertSame(1, (int) ($metrics["behavior_event_counts"]["click"] ?? 0));
        self::assertSame(1, (int) ($metrics["heatmap_click_elements"]["button"] ?? 0));
        self::assertArrayHasKey("r3c3", (array) ($metrics["heatmap_click_zones"]["/services"] ?? []));
        self::assertStringNotContainsString("private=value", $encodedMetrics);
        self::assertStringNotContainsString("203.0.113.88", $encodedMetrics);
        self::assertStringNotContainsString("private-test", $encodedMetrics);
    }

    public function testLocalFreshProjectRendersDeveloperOnboardingOnHome(): void
    {
        $this->temporaryEnvironmentDirectory = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . "fnlla-home-bootstrap-"
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
        config_set("app", array_merge((array) config("app", []), [
            "name" => "FNLLA",
            "tagline" => "",
            "base_url" => "",
        ]));

        $application = $this->makeApplication();
        $response = $application->handle(Request::capture("", [
            "REQUEST_URI" => "/",
            "REQUEST_METHOD" => "GET",
            "REMOTE_ADDR" => "127.0.0.1",
        ]));

        self::assertSame(200, $response->status());
        self::assertStringContainsString("<title>Project Setup | Developer Onboarding | FNLLA</title>", $response->body());
        self::assertStringContainsString("Project name", $response->body());
        self::assertStringContainsString("Public URL", $response->body());
        self::assertStringContainsString("Set the project identity and private developer entry", $response->body());
        self::assertStringContainsString("Save project setup and private access", $response->body());

        $setupResponse = $application->handle(Request::capture("", [
            "REQUEST_URI" => "/developer-panel-setup",
            "REQUEST_METHOD" => "GET",
            "REMOTE_ADDR" => "127.0.0.1",
        ]));

        self::assertSame(302, $setupResponse->status());
        self::assertSame("/", $setupResponse->headers()["Location"] ?? null);

        $maintenanceResponse = $application->handle(Request::capture("", [
            "REQUEST_URI" => "/maintenance",
            "REQUEST_METHOD" => "GET",
            "REMOTE_ADDR" => "127.0.0.1",
        ]));

        self::assertSame(302, $maintenanceResponse->status());
        self::assertSame("/", $maintenanceResponse->headers()["Location"] ?? null);
    }

    public function testProjectPagesAreAvailableThroughPublicRoutes(): void
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
        $termsResponse = $application->handle(Request::capture("", [
            "REQUEST_URI" => "/terms",
            "REQUEST_METHOD" => "GET",
        ]));
        $privacyResponse = $application->handle(Request::capture("", [
            "REQUEST_URI" => "/privacy",
            "REQUEST_METHOD" => "GET",
        ]));

        self::assertSame(200, $aboutResponse->status());
        self::assertSame(200, $servicesResponse->status());
        self::assertSame(200, $contactResponse->status());
        self::assertSame(200, $termsResponse->status());
        self::assertSame(200, $privacyResponse->status());
        self::assertStringContainsString("Built for ownership", $aboutResponse->body());
        self::assertStringContainsString("Business websites", $servicesResponse->body());
        self::assertStringContainsString("starter-page-title-panel", $aboutResponse->body());
        self::assertStringContainsString("starter-page-title-panel", $servicesResponse->body());
        self::assertStringContainsString("Start with a working contact form", $contactResponse->body());
        self::assertStringContainsString("action=\"/contact\"", $contactResponse->body());
        self::assertStringContainsString("name=\"contact_name\"", $contactResponse->body());
        self::assertStringContainsString("name=\"contact_email\"", $contactResponse->body());
        self::assertStringContainsString("name=\"contact_subject\"", $contactResponse->body());
        self::assertStringContainsString("name=\"contact_message\"", $contactResponse->body());
        self::assertStringContainsString("name=\"contact_consent\"", $contactResponse->body());
        self::assertStringContainsString("name=\"contact_website\"", $contactResponse->body());
        self::assertStringContainsString("Starter legal copy", $termsResponse->body());
        self::assertStringContainsString("Use of this website", $termsResponse->body());
        self::assertStringContainsString("starter-page-title-panel", $termsResponse->body());
        self::assertStringContainsString("Privacy Policy cookie section", $privacyResponse->body());
        self::assertStringContainsString("id=\"cookies\"", $privacyResponse->body());
    }

    public function testStarterContactFormValidatesAndSendsThroughLogMailer(): void
    {
        config_set("mail", array_merge((array) config("mail", []), [
            "default" => "log",
            "log_path" => "framework/testing/contact-mail",
            "contact_recipient" => "team@example.test",
            "from" => [
                "address" => "no-reply@example.test",
                "name" => "FNLLA",
            ],
        ]));

        $application = $this->makeApplication();
        $invalidResponse = $application->handle(Request::capture("", [
            "REQUEST_URI" => "/contact",
            "REQUEST_METHOD" => "POST",
        ], [], [
            "_token" => csrf_token(),
            "contact_name" => "",
            "contact_email" => "not-an-email",
            "contact_subject" => "",
            "contact_message" => "short",
            "contact_consent" => "",
            "contact_website" => "",
        ]));
        self::assertSame(302, $invalidResponse->status());
        self::assertSame("/contact#contact-form", $invalidResponse->headers()["Location"] ?? null);
        $_SESSION["_flash_old"] = $_SESSION["_flash"] ?? [];
        $_SESSION["_flash"] = [];
        $invalidPage = $application->handle(Request::capture("", [
            "REQUEST_URI" => "/contact",
            "REQUEST_METHOD" => "GET",
        ]));
        self::assertStringContainsString("Contact form needs attention", $invalidPage->body());
        self::assertStringContainsString("This field is required.", $invalidPage->body());
        self::assertStringContainsString("not-an-email", $invalidPage->body());

        $validResponse = $application->handle(Request::capture("", [
            "REQUEST_URI" => "/contact",
            "REQUEST_METHOD" => "POST",
            "HTTP_X_REQUEST_ID" => "contact-test-request",
        ], [], [
            "_token" => csrf_token(),
            "contact_name" => "Alex Visitor",
            "contact_email" => "alex@example.test",
            "contact_phone" => "01234 567890",
            "contact_subject" => "Project enquiry",
            "contact_message" => "I would like to discuss a small website build.",
            "contact_consent" => "1",
            "contact_website" => "",
        ]));

        self::assertSame(302, $validResponse->status());
        self::assertSame("/contact#contact-form", $validResponse->headers()["Location"] ?? null);

        $mailLogPath = storage_path("framework/testing/contact-mail/" . gmdate("Ymd") . ".log");
        $formLogPath = storage_path("framework/developer/form-submissions.jsonl");
        self::assertFileExists($mailLogPath);
        self::assertFileExists($formLogPath);
        self::assertStringContainsString("Alex Visitor", (string) file_get_contents($mailLogPath));
        self::assertStringContainsString("alex@example.test", (string) file_get_contents($mailLogPath));
        self::assertStringContainsString("contact-test-request", (string) file_get_contents($mailLogPath));
        self::assertStringContainsString("\"status\":\"sent\"", (string) file_get_contents($formLogPath));
        self::assertStringContainsString("\"schema\":\"fnlla.public_contact_submission.v1\"", (string) file_get_contents($formLogPath));
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
            "mode" => "github-check",
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
            "headline_text" => "FNLLA detected an upstream framework shift (FNLLA 1.0.0 -> 1.1.0) and prepared the safe portion of the update. You can apply the audited update directly from this page.",
            "version_transition_summary" => "FNLLA 1.0.0 -> 1.1.0",
            "update_ready" => true,
            "requires_manual_review" => false,
            "apply_action_available" => true,
            "can_apply_from_ui" => true,
            "recommended_apply_mode" => "github-apply",
        ];

        $application = $this->makeApplication();
        $response = $application->handle(Request::capture("", [
            "REQUEST_URI" => "/maintenance/framework-update",
            "REQUEST_METHOD" => "GET",
            "REMOTE_ADDR" => "127.0.0.1",
        ]));

        self::assertSame(200, $response->status());
        self::assertStringContainsString("Update is ready to apply", $response->body());
        self::assertStringContainsString("Apply this audited GitHub update", $response->body());
        self::assertStringContainsString("Detected version shift:", $response->body());
        self::assertStringContainsString("FNLLA 1.0.0 -&gt; 1.1.0", $response->body());
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

    public function testFrameworkUpdatePageIncludesMajorUpgradeGui(): void
    {
        config_set("framework_update", array_merge((array) config("framework_update", []), [
            "ui_enabled" => true,
            "ui_local_only" => true,
            "ui_apply_enabled" => true,
        ]));
        $_SESSION["_flash_old"]["framework_upgrade_report"] = [
            "target_version" => "2.1.1",
            "executed_at_utc" => "2026-07-13T10:00:00+00:00",
            "summary" => [
                "passed" => 6,
                "warnings" => 0,
                "failures" => 0,
            ],
            "checks" => [
                [
                    "id" => "required-files",
                    "status" => "pass",
                    "detail" => "Required release files are present.",
                ],
            ],
            "plan" => [
                "actions" => [
                    [
                        "id" => "write-upgrade-plan",
                        "title" => "Persist machine-readable upgrade plan",
                        "detail" => "Write the upgrade report to storage.",
                        "safe_to_apply" => true,
                    ],
                ],
            ],
        ];

        $application = $this->makeApplication();
        $response = $application->handle(Request::capture("", [
            "REQUEST_URI" => "/maintenance/framework-update",
            "REQUEST_METHOD" => "GET",
            "REMOTE_ADDR" => "127.0.0.1",
        ]));

        self::assertSame(200, $response->status());
        self::assertStringContainsString("Major upgrade safety", $response->body());
        self::assertStringContainsString("Check major readiness", $response->body());
        self::assertStringContainsString("Apply safe actions", $response->body());
        self::assertStringContainsString("Persist machine-readable upgrade plan", $response->body());
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
        self::assertStringContainsString('"name": "' . $this->expectedProjectName() . '"', $response->body());
        self::assertStringContainsString('"api_health": "/api/health"', $response->body());
    }

    public function testApiProfileReturnsFrameworkCapabilityPayload(): void
    {
        $application = $this->makeApplication();

        $response = $application->handle(Request::capture("", [
            "REQUEST_URI" => "/api/profile",
            "REQUEST_METHOD" => "GET",
            "REMOTE_ADDR" => "127.0.0.1",
            "HTTP_ACCEPT" => "application/json",
        ]));

        self::assertSame(200, $response->status());
        self::assertSame("application/json; charset=UTF-8", $response->headers()["Content-Type"] ?? null);
        self::assertStringContainsString('"name": "' . $this->expectedProjectName() . '"', $response->body());
        self::assertStringContainsString('"supports": [', $response->body());
        self::assertStringContainsString('"queues"', $response->body());
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

    public function testApiHealthSupportsLiveAndDeepLevels(): void
    {
        $application = $this->makeApplication();

        $live = $application->handle(Request::capture("", [
            "REQUEST_URI" => "/api/health?format=json&level=live",
            "REQUEST_METHOD" => "GET",
            "REMOTE_ADDR" => "127.0.0.1",
            "HTTP_ACCEPT" => "text/html",
        ], [
            "format" => "json",
            "level" => "live",
        ]));
        $deep = $application->handle(Request::capture("", [
            "REQUEST_URI" => "/api/health?format=json&level=deep",
            "REQUEST_METHOD" => "GET",
            "REMOTE_ADDR" => "127.0.0.1",
            "HTTP_ACCEPT" => "text/html",
        ], [
            "format" => "json",
            "level" => "deep",
        ]));

        self::assertStringContainsString('"level": "live"', $live->body());
        self::assertStringNotContainsString('"dependencies"', $live->body());
        self::assertStringContainsString('"level": "deep"', $deep->body());
        self::assertStringContainsString('"dependencies"', $deep->body());
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
        self::assertStringContainsString("A server-rendered project base", $homeResponse->body());
    }

    private function expectedProjectName(): string
    {
        return (string) config("app.name");
    }

    public function testMaintenanceScreenRendersBrandedClientPreviewWhenEnabled(): void
    {
        config_set("maintenance", array_merge((array) config("maintenance", []), [
            "enabled" => true,
            "password" => "client-preview",
        ]));
        config_set("client_preview", array_merge((array) config("client_preview", []), [
            "enabled" => true,
            "title" => "Preview Title",
            "unlock_button_label" => "Unlock preview",
        ]));

        $application = $this->makeApplication();

        $response = $application->handle(Request::capture("", [
            "REQUEST_URI" => "/maintenance",
            "REQUEST_METHOD" => "GET",
            "REMOTE_ADDR" => "127.0.0.1",
        ]));

        self::assertSame(200, $response->status());
        self::assertStringContainsString("Preview Title", $response->body());
        self::assertStringContainsString("client-preview-access", $response->body());
        self::assertStringContainsString("Unlock preview", $response->body());
        self::assertStringNotContainsString("maintenance-unlock-modal", $response->body());
    }

    public function testClientPreviewDisabledUnlockRedirectsBackToClientPreviewAnchor(): void
    {
        config_set("maintenance", array_merge((array) config("maintenance", []), [
            "enabled" => true,
            "password" => "client-preview",
        ]));
        config_set("client_preview", array_merge((array) config("client_preview", []), [
            "enabled" => true,
            "login_disabled" => true,
            "locked_notice" => "Unlocks are paused.",
        ]));

        $application = $this->makeApplication();
        $token = csrf_token();

        $unlockResponse = $application->handle(Request::capture("", [
            "REQUEST_URI" => "/maintenance/unlock",
            "REQUEST_METHOD" => "POST",
            "REMOTE_ADDR" => "127.0.0.1",
        ], [], [
            "_token" => $token,
            "maintenance_password" => "client-preview",
        ]));

        self::assertSame(302, $unlockResponse->status());
        self::assertSame("/maintenance#client-preview-access", $unlockResponse->headers()["Location"] ?? null);
        self::assertFalse($_SESSION["maintenance.access_unlocked"] ?? false);
    }

    public function testClientPreviewLockRedirectsBackToClientPreviewAnchor(): void
    {
        config_set("maintenance", array_merge((array) config("maintenance", []), [
            "enabled" => true,
            "password" => "client-preview",
        ]));
        config_set("client_preview", array_merge((array) config("client_preview", []), [
            "enabled" => true,
        ]));

        $application = $this->makeApplication();
        $token = csrf_token();

        $unlockResponse = $application->handle(Request::capture("", [
            "REQUEST_URI" => "/maintenance/unlock",
            "REQUEST_METHOD" => "POST",
            "REMOTE_ADDR" => "127.0.0.1",
        ], [], [
            "_token" => $token,
            "maintenance_password" => "client-preview",
        ]));

        self::assertSame(302, $unlockResponse->status());
        self::assertSame(true, $_SESSION["maintenance.access_unlocked"] ?? false);

        $lockResponse = $application->handle(Request::capture("", [
            "REQUEST_URI" => "/maintenance/lock",
            "REQUEST_METHOD" => "POST",
            "REMOTE_ADDR" => "127.0.0.1",
        ], [], [
            "_token" => csrf_token(),
        ]));

        self::assertSame(302, $lockResponse->status());
        self::assertSame("/maintenance#client-preview-access", $lockResponse->headers()["Location"] ?? null);
        self::assertFalse($_SESSION["maintenance.access_unlocked"] ?? false);
    }

    public function testFreshProjectCanConfigureDeveloperAccessBeforeMaintenance(): void
    {
        $this->temporaryEnvironmentDirectory = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . "fnlla-developer-onboarding-"
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
            "password" => "",
            "password_hash" => "",
            "operations_nav_mode" => "visible",
        ]));

        $application = $this->makeApplication();
        $pageResponse = $application->handle(Request::capture("", [
            "REQUEST_URI" => "/",
            "REQUEST_METHOD" => "GET",
            "REMOTE_ADDR" => "127.0.0.1",
        ]));

        self::assertSame(200, $pageResponse->status());
        self::assertStringContainsString("Set the project identity and private developer entry", $pageResponse->body());
        self::assertStringContainsString("Save project setup and private access", $pageResponse->body());
        self::assertStringNotContainsString("Configure maintenance access", $pageResponse->body());

        $maintenanceResponse = $application->handle(Request::capture("", [
            "REQUEST_URI" => "/maintenance",
            "REQUEST_METHOD" => "GET",
            "REMOTE_ADDR" => "127.0.0.1",
        ]));

        self::assertSame(302, $maintenanceResponse->status());
        self::assertSame("/", $maintenanceResponse->headers()["Location"] ?? null);

        $token = csrf_token();
        $setupResponse = $application->handle(Request::capture("", [
            "REQUEST_URI" => "/maintenance/setup-developer-access",
            "REQUEST_METHOD" => "POST",
            "REMOTE_ADDR" => "127.0.0.1",
        ], [], [
            "_token" => $token,
            "project_name" => "Qwerty Client Portal",
            "project_tagline" => "Business systems delivered clearly",
            "project_url" => "https://qwerty.example.test",
            "developer_setup_email" => "dev@example.test",
            "developer_setup_password" => "developer-secret",
            "developer_setup_password_confirmation" => "developer-secret",
        ]));

        self::assertSame(302, $setupResponse->status());
        self::assertSame("/developer/panel", $setupResponse->headers()["Location"] ?? null);
        self::assertFalse($_SESSION["maintenance.access_unlocked"] ?? false);
        self::assertSame(true, $_SESSION["developer.access_unlocked"] ?? false);
        self::assertFileExists($envPath);
        self::assertStringContainsString('APP_NAME="Qwerty Client Portal"', (string) file_get_contents($envPath));
        self::assertStringContainsString('APP_TAGLINE="Business systems delivered clearly"', (string) file_get_contents($envPath));
        self::assertStringContainsString("APP_URL=https://qwerty.example.test", (string) file_get_contents($envPath));
        self::assertStringContainsString("DEVELOPER_ACCESS_EMAIL=dev@example.test", (string) file_get_contents($envPath));
        self::assertStringContainsString("DEVELOPER_ACCESS_USERS=dev@example.test|", (string) file_get_contents($envPath));
        self::assertStringContainsString("DEVELOPER_ACCESS_PASSWORD=", (string) file_get_contents($envPath));
        self::assertStringContainsString("DEVELOPER_ACCESS_PASSWORD_HASH=", (string) file_get_contents($envPath));
        self::assertStringNotContainsString("DEVELOPER_ACCESS_PASSWORD=developer-secret", (string) file_get_contents($envPath));
        self::assertStringNotContainsString("MAINTENANCE_MODE_ENABLED=true", (string) file_get_contents($envPath));
        self::assertStringNotContainsString("MAINTENANCE_ACCESS_PASSWORD=", (string) file_get_contents($envPath));

        config_set("developer_access", array_merge((array) config("developer_access", []), [
            "email" => "dev@example.test",
            "password" => "",
            "password_hash" => (string) config("developer_access.password_hash", ""),
            "users" => (string) config("developer_access.users", ""),
            "operations_nav_mode" => "visible",
        ]));
        $developerApplication = $this->makeApplication();
        $developerResponse = $developerApplication->handle(Request::capture("", [
            "REQUEST_URI" => "/developer/panel",
            "REQUEST_METHOD" => "GET",
            "REMOTE_ADDR" => "127.0.0.1",
        ]));

        self::assertSame(200, $developerResponse->status());
        self::assertStringContainsString("<title>Dashboard | Developer Panel | Qwerty Client Portal - Business systems delivered clearly</title>", $developerResponse->body());
        self::assertStringContainsString("data-fnlla-title-tagline=\"Business systems delivered clearly\"", $developerResponse->body());
        self::assertStringContainsString(">Dashboard<", $developerResponse->body());
        self::assertStringContainsString("developer-workspace-layout", $developerResponse->body());
        self::assertStringContainsString("developer-workspace-header", $developerResponse->body());
        self::assertStringContainsString("project-navbar-actions", $developerResponse->body());
        self::assertStringContainsString("project-brand-mark\" aria-hidden=\"true\">QC</span>", $developerResponse->body());
        self::assertStringNotContainsString("DEV OPERATIONS", $developerResponse->body());
        self::assertStringContainsString('FNLLA by <a class="developer-workspace-brand-link" href="https://techayo.co.uk" target="_blank" rel="noopener noreferrer">TechAyo</a> Limited', $developerResponse->body());
        self::assertStringContainsString("developer-header-tools", $developerResponse->body());
        self::assertStringContainsString("aria-label=\"Open notification center\"", $developerResponse->body());
        self::assertStringNotContainsString("aria-label=\"Open developer to-do list\"", $developerResponse->body());
        self::assertStringNotContainsString("My to-do", $developerResponse->body());
        self::assertStringContainsString("developer-dropdown-avatar", $developerResponse->body());
        self::assertStringNotContainsString("developer-topbar-session", $developerResponse->body());
        self::assertStringContainsString("developer-dropdown-session-icon", $developerResponse->body());
        self::assertStringContainsString("developer-panel-sidebar", $developerResponse->body());
        self::assertStringNotContainsString("developer-panel-sidebar-icon", $developerResponse->body());
        self::assertStringNotContainsString("developer-dashboard-card-icon", $developerResponse->body());
        self::assertStringNotContainsString("developer-dashboard-mini-icon", $developerResponse->body());
        self::assertStringNotContainsString("Session locks in", $developerResponse->body());
        self::assertStringNotContainsString("developer-panel-brand", $developerResponse->body());
        self::assertStringContainsString("developer-panel-page-head", $developerResponse->body());
        self::assertStringContainsString("data-fnlla-session-countdown", $developerResponse->body());
        self::assertStringContainsString("action=\"/developer/panel/extend\"", $developerResponse->body());
        self::assertStringContainsString(">Extend session<", $developerResponse->body());
        self::assertStringContainsString("Lock session", $developerResponse->body());
        self::assertStringContainsString("href=\"/developer/panel/project-identity\"", $developerResponse->body());
        self::assertStringContainsString("href=\"/developer/panel/project-settings\"", $developerResponse->body());
        self::assertStringContainsString("href=\"/developer/panel/workspace\"", $developerResponse->body());
        self::assertStringContainsString("href=\"/developer/panel/profile\"", $developerResponse->body());
        self::assertStringContainsString(">Developer profile</a>", $developerResponse->body());
        self::assertSame(1, substr_count($developerResponse->body(), "href=\"/developer/panel/profile\""));
        self::assertStringContainsString("href=\"/developer/panel/analytics\"", $developerResponse->body());
        self::assertStringContainsString("href=\"/developer/panel/notifications\"", $developerResponse->body());
        self::assertStringContainsString("href=\"/developer/panel/release-readiness\"", $developerResponse->body());
        self::assertStringContainsString("href=\"/developer/panel/integrations\"", $developerResponse->body());
        self::assertStringContainsString("href=\"/developer/panel/settings\"", $developerResponse->body());
        self::assertStringNotContainsString("href=\"/developer/panel/health\"", $developerResponse->body());
        self::assertStringContainsString("href=\"/developer/panel/framework-updates\"", $developerResponse->body());
        self::assertStringContainsString("href=\"/developer/panel/operations\"", $developerResponse->body());
        self::assertStringContainsString("href=\"/developer/panel/policy\"", $developerResponse->body());
        self::assertStringContainsString("href=\"/developer/panel/heatmap\"", $developerResponse->body());
        self::assertStringContainsString("href=\"/developer/panel/documentation\"", $developerResponse->body());
        self::assertStringContainsString("href=\"/developer/panel/about\"", $developerResponse->body());
        self::assertStringContainsString(">Workspace<", $developerResponse->body());
        self::assertStringContainsString(">Project setup<", $developerResponse->body());
        self::assertStringContainsString(">Operations<", $developerResponse->body());
        self::assertStringContainsString(">Security &amp; policy<", $developerResponse->body());
        self::assertStringNotContainsString("<p>Core</p>", $developerResponse->body());
        self::assertStringNotContainsString("<p>Project</p>", $developerResponse->body());
        self::assertStringNotContainsString("<p>Access</p>", $developerResponse->body());
        self::assertStringContainsString("href=\"/developer/panel/framework-updates\">Open update</a>", $developerResponse->body());
        self::assertStringContainsString("href=\"/developer/panel/operations\">Open operations</a>", $developerResponse->body());
        self::assertStringContainsString("Operational snapshot for identity, access, preview mode and framework readiness.", $developerResponse->body());
        self::assertStringContainsString("Framework lock", $developerResponse->body());
        self::assertStringContainsString("Developer activity", $developerResponse->body());
        self::assertStringNotContainsString("project-footer", $developerResponse->body());
        self::assertStringNotContainsString("<form class=\"form stack gap-md\" action=\"/developer/panel/settings/project\"", $developerResponse->body());

        $identityResponse = $developerApplication->handle(Request::capture("", [
            "REQUEST_URI" => "/developer/panel/project-identity",
            "REQUEST_METHOD" => "GET",
            "REMOTE_ADDR" => "127.0.0.1",
        ]));
        $projectSettingsResponse = $developerApplication->handle(Request::capture("", [
            "REQUEST_URI" => "/developer/panel/project-settings",
            "REQUEST_METHOD" => "GET",
            "REMOTE_ADDR" => "127.0.0.1",
        ]));
        $panelSettingsResponse = $developerApplication->handle(Request::capture("", [
            "REQUEST_URI" => "/developer/panel/settings",
            "REQUEST_METHOD" => "GET",
            "REMOTE_ADDR" => "127.0.0.1",
        ]));
        $profileResponse = $developerApplication->handle(Request::capture("", [
            "REQUEST_URI" => "/developer/panel/profile",
            "REQUEST_METHOD" => "GET",
            "REMOTE_ADDR" => "127.0.0.1",
        ]));
        $securityResponse = $developerApplication->handle(Request::capture("", [
            "REQUEST_URI" => "/developer/panel/security",
            "REQUEST_METHOD" => "GET",
            "REMOTE_ADDR" => "127.0.0.1",
        ]));
        $analyticsResponse = $developerApplication->handle(Request::capture("", [
            "REQUEST_URI" => "/developer/panel/analytics",
            "REQUEST_METHOD" => "GET",
            "REMOTE_ADDR" => "127.0.0.1",
        ]));
        $heatmapResponse = $developerApplication->handle(Request::capture("", [
            "REQUEST_URI" => "/developer/panel/heatmap",
            "REQUEST_METHOD" => "GET",
            "REMOTE_ADDR" => "127.0.0.1",
        ]));
        $notificationsResponse = $developerApplication->handle(Request::capture("", [
            "REQUEST_URI" => "/developer/panel/notifications",
            "REQUEST_METHOD" => "GET",
            "REMOTE_ADDR" => "127.0.0.1",
        ]));
        $releaseReadinessResponse = $developerApplication->handle(Request::capture("", [
            "REQUEST_URI" => "/developer/panel/release-readiness",
            "REQUEST_METHOD" => "GET",
            "REMOTE_ADDR" => "127.0.0.1",
        ]));
        $integrationsResponse = $developerApplication->handle(Request::capture("", [
            "REQUEST_URI" => "/developer/panel/integrations",
            "REQUEST_METHOD" => "GET",
            "REMOTE_ADDR" => "127.0.0.1",
        ]));
        $healthResponse = $developerApplication->handle(Request::capture("", [
            "REQUEST_URI" => "/developer/panel/health",
            "REQUEST_METHOD" => "GET",
            "REMOTE_ADDR" => "127.0.0.1",
        ]));
        $frameworkUpdatesResponse = $developerApplication->handle(Request::capture("", [
            "REQUEST_URI" => "/developer/panel/framework-updates",
            "REQUEST_METHOD" => "GET",
            "REMOTE_ADDR" => "127.0.0.1",
        ]));
        $operationsResponse = $developerApplication->handle(Request::capture("", [
            "REQUEST_URI" => "/developer/panel/operations",
            "REQUEST_METHOD" => "GET",
            "REMOTE_ADDR" => "127.0.0.1",
        ]));
        $workspaceResponse = $developerApplication->handle(Request::capture("", [
            "REQUEST_URI" => "/developer/panel/workspace",
            "REQUEST_METHOD" => "GET",
            "REMOTE_ADDR" => "127.0.0.1",
        ]));
        $policyResponse = $developerApplication->handle(Request::capture("", [
            "REQUEST_URI" => "/developer/panel/policy",
            "REQUEST_METHOD" => "GET",
            "REMOTE_ADDR" => "127.0.0.1",
        ]));
        $documentationResponse = $developerApplication->handle(Request::capture("", [
            "REQUEST_URI" => "/developer/panel/documentation",
            "REQUEST_METHOD" => "GET",
            "REMOTE_ADDR" => "127.0.0.1",
        ]));
        $aboutFnllaResponse = $developerApplication->handle(Request::capture("", [
            "REQUEST_URI" => "/developer/panel/about",
            "REQUEST_METHOD" => "GET",
            "REMOTE_ADDR" => "127.0.0.1",
        ]));
        developer_activity()->record(
            "test_event",
            "Test audit event",
            "Audit export includes shared developer-panel events.",
            developer_access()->currentDeveloper()
        );
        $auditExportResponse = $developerApplication->handle(Request::capture("", [
            "REQUEST_URI" => "/developer/panel/operations/audit-export",
            "REQUEST_METHOD" => "GET",
            "REMOTE_ADDR" => "127.0.0.1",
        ]));
        $auditCsvExportResponse = $developerApplication->handle(Request::capture("", [
            "REQUEST_URI" => "/developer/panel/operations/audit-export.csv",
            "REQUEST_METHOD" => "GET",
            "REMOTE_ADDR" => "127.0.0.1",
        ]));

        self::assertSame(200, $identityResponse->status());
        self::assertSame(200, $projectSettingsResponse->status());
        self::assertSame(200, $panelSettingsResponse->status());
        self::assertSame(200, $profileResponse->status());
        self::assertSame(200, $securityResponse->status());
        self::assertSame(200, $analyticsResponse->status());
        self::assertSame(200, $heatmapResponse->status());
        self::assertSame(200, $notificationsResponse->status());
        self::assertSame(200, $releaseReadinessResponse->status());
        self::assertSame(200, $integrationsResponse->status());
        self::assertSame(302, $healthResponse->status());
        self::assertSame("/developer/panel/release-readiness", $healthResponse->headers()["Location"] ?? null);
        self::assertSame(200, $frameworkUpdatesResponse->status());
        self::assertSame(200, $operationsResponse->status());
        self::assertSame(200, $workspaceResponse->status());
        self::assertSame(200, $policyResponse->status());
        self::assertSame(200, $documentationResponse->status());
        self::assertSame(200, $aboutFnllaResponse->status());
        self::assertSame(200, $auditExportResponse->status());
        self::assertSame(200, $auditCsvExportResponse->status());
        foreach ([
            $identityResponse,
            $projectSettingsResponse,
            $panelSettingsResponse,
            $profileResponse,
            $securityResponse,
            $analyticsResponse,
            $heatmapResponse,
            $notificationsResponse,
            $releaseReadinessResponse,
            $integrationsResponse,
            $frameworkUpdatesResponse,
            $operationsResponse,
            $workspaceResponse,
            $policyResponse,
            $documentationResponse,
            $aboutFnllaResponse,
        ] as $panelResponse) {
            self::assertStringContainsString("data-developer-panel-frame", $panelResponse->body());
        }
        self::assertSame("application/json; charset=UTF-8", $auditExportResponse->headers()["Content-Type"] ?? null);
        self::assertSame("text/csv; charset=UTF-8", $auditCsvExportResponse->headers()["Content-Type"] ?? null);
        self::assertStringContainsString("<title>Project Identity | Developer Panel | Qwerty Client Portal - Business systems delivered clearly</title>", $identityResponse->body());
        self::assertStringContainsString("<title>Framework Updates | Developer Panel | Qwerty Client Portal - Business systems delivered clearly</title>", $frameworkUpdatesResponse->body());
        self::assertStringContainsString("<title>Operations | Developer Panel | Qwerty Client Portal - Business systems delivered clearly</title>", $operationsResponse->body());
        self::assertStringContainsString("<title>Project Workspace | Developer Panel | Qwerty Client Portal - Business systems delivered clearly</title>", $workspaceResponse->body());
        self::assertStringContainsString("<title>Policy Boundary | Developer Panel | Qwerty Client Portal - Business systems delivered clearly</title>", $policyResponse->body());
        self::assertStringContainsString("<title>Heatmap | Developer Panel | Qwerty Client Portal - Business systems delivered clearly</title>", $heatmapResponse->body());
        self::assertStringContainsString("<title>Documentation | Developer Panel | Qwerty Client Portal - Business systems delivered clearly</title>", $documentationResponse->body());
        self::assertStringContainsString("<title>About FNLLA | Developer Panel | Qwerty Client Portal - Business systems delivered clearly</title>", $aboutFnllaResponse->body());
        self::assertStringContainsString("action=\"/developer/panel/framework-updates/run\"", $frameworkUpdatesResponse->body());
        self::assertStringContainsString("<title>Developer Profile | Developer Panel | Qwerty Client Portal - Business systems delivered clearly</title>", $profileResponse->body());
        self::assertStringContainsString("<title>Access &amp; Security | Developer Panel | Qwerty Client Portal - Business systems delivered clearly</title>", $securityResponse->body());
        self::assertStringContainsString('<option value="lead_developer"', $securityResponse->body());
        self::assertStringContainsString('<option value="application_developer"', $securityResponse->body());
        self::assertStringNotContainsString('<option value="operations_engineer"', $securityResponse->body());
        self::assertStringNotContainsString('<option value="security_reviewer"', $securityResponse->body());
        self::assertStringNotContainsString('<option value="client"', $securityResponse->body());
        self::assertStringContainsString("<title>Analytics | Developer Panel | Qwerty Client Portal - Business systems delivered clearly</title>", $analyticsResponse->body());
        self::assertStringContainsString("<title>Notifications | Developer Panel | Qwerty Client Portal - Business systems delivered clearly</title>", $notificationsResponse->body());
        self::assertStringContainsString("<title>Release Readiness | Developer Panel | Qwerty Client Portal - Business systems delivered clearly</title>", $releaseReadinessResponse->body());
        self::assertStringContainsString("<title>Integrations | Developer Panel | Qwerty Client Portal - Business systems delivered clearly</title>", $integrationsResponse->body());
        self::assertStringContainsString("Privacy-light analytics", $operationsResponse->body());
        self::assertStringContainsString("Open analytics", $operationsResponse->body());
        self::assertStringContainsString("Performance probes", $operationsResponse->body());
        self::assertStringContainsString("Form inbox", $operationsResponse->body());
        self::assertStringContainsString("Audit log", $operationsResponse->body());
        self::assertStringContainsString("Release readiness", $operationsResponse->body());
        self::assertStringContainsString("Consent-aware integrations", $operationsResponse->body());
        self::assertStringContainsString("Opt-in heatmaps", $operationsResponse->body());
        self::assertStringContainsString("Export audit log", $operationsResponse->body());
        self::assertStringContainsString("Export CSV", $operationsResponse->body());
        self::assertStringContainsString("Authenticator setup", $securityResponse->body());
        self::assertStringContainsString("Passkey contract", $securityResponse->body());
        self::assertStringContainsString("data-fnlla-modal-open=\"#developer-account-modal\"", $securityResponse->body());
        self::assertStringContainsString("developer-account-row", $securityResponse->body());
        self::assertStringContainsString("Save developer account", $securityResponse->body());
        self::assertStringContainsString("Privacy-light traffic cockpit", $analyticsResponse->body());
        self::assertStringContainsString("Notification center", $notificationsResponse->body());
        self::assertStringContainsString("Open alerts", $notificationsResponse->body());
        self::assertStringContainsString("action=\"/developer/panel/notifications/action\"", $notificationsResponse->body());
        self::assertStringContainsString("name=\"developer_notification_action\"", $notificationsResponse->body());
        self::assertStringContainsString(">Mark read<", $notificationsResponse->body());
        self::assertStringContainsString(">Archive<", $notificationsResponse->body());
        self::assertStringContainsString("Production gate commands", $releaseReadinessResponse->body());
        self::assertStringContainsString("Runtime health", $releaseReadinessResponse->body());
        self::assertStringContainsString("Release command center", $releaseReadinessResponse->body());
        self::assertStringContainsString("Framework update command center", $frameworkUpdatesResponse->body());
        self::assertStringContainsString("Qwerty Client Portal", $releaseReadinessResponse->body());
        $versionLines = file(base_path("VERSION"), FILE_IGNORE_NEW_LINES);
        self::assertStringContainsString("FNLLA " . trim((string) ($versionLines[0] ?? "")), $releaseReadinessResponse->body());
        self::assertStringContainsString("No external calls by default", $integrationsResponse->body());
        self::assertStringContainsString("Why \"No external calls by default\"?", $integrationsResponse->body());
        self::assertStringContainsString("developer-integrations-stack", $integrationsResponse->body());
        self::assertStringContainsString("developer-integrations-actions", $integrationsResponse->body());
        self::assertStringContainsString("data-fnlla-modal-open=\"#developer-integration-ga4-settings\"", $integrationsResponse->body());
        self::assertStringContainsString("data-fnlla-modal-open=\"#developer-integration-fionn-settings\"", $integrationsResponse->body());
        self::assertStringContainsString("Adapter settings", $integrationsResponse->body());
        self::assertStringContainsString("action=\"/developer/panel/integrations/settings\"", $integrationsResponse->body());
        self::assertStringContainsString("Save GA4 settings", $integrationsResponse->body());
        self::assertStringContainsString("Save Fionn settings", $integrationsResponse->body());
        self::assertStringContainsString("name=\"ai_fionn_enabled\"", $integrationsResponse->body());
        self::assertStringContainsString("Leave blank to keep current token", $integrationsResponse->body());
        self::assertStringContainsString("TechAyo Remote Control plugin", $integrationsResponse->body());
        self::assertStringContainsString("https://techayo.co.uk/admin", $integrationsResponse->body());
        self::assertStringContainsString("Analytics command center", $analyticsResponse->body());
        self::assertStringContainsString("developer-analytics-metric-grid", $analyticsResponse->body());
        self::assertStringContainsString("developer-analytics-workbench", $analyticsResponse->body());
        self::assertStringContainsString("Traffic timeline", $analyticsResponse->body());
        self::assertStringContainsString("Internal analytics settings", $analyticsResponse->body());
        self::assertStringContainsString("Save analytics settings", $analyticsResponse->body());
        self::assertStringContainsString("First-party aggregate data", $analyticsResponse->body());
        self::assertStringContainsString("Response time by route", $analyticsResponse->body());
        self::assertStringContainsString("name=\"observability_analytics_retention_days\"", $analyticsResponse->body());
        self::assertStringContainsString("action=\"/developer/panel/analytics/settings\"", $analyticsResponse->body());
        self::assertStringContainsString("FNLLA Heatmap", $heatmapResponse->body());
        self::assertStringContainsString("first-party aggregate heatmap", $heatmapResponse->body());
        self::assertStringContainsString("Click intensity", $heatmapResponse->body());
        self::assertStringContainsString("action=\"/developer/panel/heatmap/settings\"", $heatmapResponse->body());
        self::assertStringContainsString("/fnlla/analytics/event", $heatmapResponse->body());
        self::assertStringContainsString("No external calls by default", $heatmapResponse->body());
        self::assertStringContainsString("Policy contract active", $policyResponse->body());
        self::assertStringContainsString("Technical schema", $policyResponse->body());
        self::assertStringContainsString("developer-policy-map", $policyResponse->body());
        self::assertStringNotContainsString("developer-dashboard-status is-active\">fnlla.developer_policy_boundary.v1", $policyResponse->body());
        self::assertStringContainsString("FNLLA Public", $documentationResponse->body());
        self::assertStringContainsString("Developer Panel", $documentationResponse->body());
        self::assertStringContainsString("Technical contracts", $documentationResponse->body());
        self::assertStringContainsString("About FNLLA", $aboutFnllaResponse->body());
        self::assertStringContainsString("techayo.co.uk", $aboutFnllaResponse->body());
        self::assertStringContainsString("Project Kanban", $workspaceResponse->body());
        self::assertStringNotContainsString("My to-do", $workspaceResponse->body());
        self::assertStringContainsString("Assigned to me", $workspaceResponse->body());
        self::assertStringContainsString("data-developer-kanban", $workspaceResponse->body());
        self::assertStringContainsString("data-developer-kanban-task", $workspaceResponse->body());
        self::assertStringContainsString("data-developer-kanban-modal", $workspaceResponse->body());
        self::assertStringContainsString("data-developer-kanban-search", $workspaceResponse->body());
        self::assertStringContainsString("data-developer-kanban-move-form", $workspaceResponse->body());
        self::assertStringContainsString("developer-kanban-participants", $workspaceResponse->body());
        self::assertStringContainsString("developer-kanban-avatar", $workspaceResponse->body());
        self::assertStringContainsString("developer-kanban-modal", $workspaceResponse->body());
        self::assertStringContainsString("data-fnlla-modal-open=\"#developer-kanban-create-", $workspaceResponse->body());
        self::assertStringContainsString("data-fnlla-modal-close", $workspaceResponse->body());
        self::assertStringContainsString("name=\"developer_workspace_type\"", $workspaceResponse->body());
        self::assertStringContainsString("name=\"developer_workspace_color\"", $workspaceResponse->body());
        self::assertStringContainsString("name=\"developer_workspace_subtask\"", $workspaceResponse->body());
        self::assertStringContainsString("name=\"developer_workspace_due_date\"", $workspaceResponse->body());
        self::assertStringContainsString("name=\"developer_workspace_estimate\"", $workspaceResponse->body());
        self::assertStringContainsString("name=\"developer_workspace_checklist\"", $workspaceResponse->body());
        self::assertStringNotContainsString("developer-kanban-quick-create", $workspaceResponse->body());
        self::assertStringNotContainsString("developer-kanban-subtask-form", $workspaceResponse->body());
        self::assertStringNotContainsString("developer-kanban-task-editor", $workspaceResponse->body());
        self::assertStringNotContainsString("developer-kanban-task-open", $workspaceResponse->body());
        self::assertStringNotContainsString("aria-label=\"Create workspace task\"", $workspaceResponse->body());
        self::assertStringContainsString(">Blocked<", $workspaceResponse->body());
        self::assertStringContainsString("action=\"/developer/panel/workspace/tasks\"", $workspaceResponse->body());
        self::assertStringContainsString("Framework boundary", $policyResponse->body());
        self::assertStringContainsString("FNLLA-managed", $policyResponse->body());
        self::assertStringContainsString("Project-owned", $policyResponse->body());
        self::assertStringContainsString("Never in FNLLA core", $policyResponse->body());
        self::assertStringContainsString("project.identity.write", $policyResponse->body());
        self::assertStringContainsString("fnlla.developer_activity_export.v1", $auditExportResponse->body());
        self::assertStringContainsString("Test audit event", $auditExportResponse->body());
        self::assertStringContainsString("event_hash", $auditExportResponse->body());
        self::assertStringContainsString("Test audit event", $auditCsvExportResponse->body());

        $createTaskResponse = $developerApplication->handle(Request::capture("", [
            "REQUEST_URI" => "/developer/panel/workspace/tasks",
            "REQUEST_METHOD" => "POST",
            "REMOTE_ADDR" => "127.0.0.1",
        ], [], [
            "_token" => csrf_token(),
            "developer_workspace_title" => "Prepare commercial product baseline",
            "developer_workspace_notes" => "Confirm starter flow before first product feature.",
            "developer_workspace_status" => "in_progress",
            "developer_workspace_priority" => "high",
            "developer_workspace_type" => "release",
            "developer_workspace_color" => "indigo",
            "developer_workspace_assignee" => "dev@example.test",
            "developer_workspace_due_date" => "2026-09-04",
            "developer_workspace_estimate" => "45m",
            "developer_workspace_blocked" => "1",
            "developer_workspace_checklist" => "[x] Confirm routes\n[ ] Run release checks",
        ]));
        $workspaceUpdatedResponse = $developerApplication->handle(Request::capture("", [
            "REQUEST_URI" => "/developer/panel/workspace",
            "REQUEST_METHOD" => "GET",
            "REMOTE_ADDR" => "127.0.0.1",
        ]));

        self::assertSame(302, $createTaskResponse->status());
        self::assertSame("/developer/panel/workspace", $createTaskResponse->headers()["Location"] ?? null);
        self::assertSame(200, $workspaceUpdatedResponse->status());
        self::assertStringContainsString("Prepare commercial product baseline", $workspaceUpdatedResponse->body());
        self::assertStringContainsString("Confirm starter flow before first product feature.", $workspaceUpdatedResponse->body());
        self::assertStringContainsString("Release", $workspaceUpdatedResponse->body());
        self::assertStringContainsString("45m", $workspaceUpdatedResponse->body());
        self::assertStringContainsString("1/2 checklist", $workspaceUpdatedResponse->body());
        self::assertStringContainsString("Due 2026-09-04", $workspaceUpdatedResponse->body());
        self::assertStringContainsString("Changed by dev@example.test", $workspaceUpdatedResponse->body());

        $workspaceState = json_decode((string) file_get_contents(storage_path("framework/testing/developer-workspace-default.json")), true);
        $createdTask = null;

        foreach ((array) ($workspaceState["tasks"] ?? []) as $task) {
            if (($task["title"] ?? "") === "Prepare commercial product baseline") {
                $createdTask = $task;
                break;
            }
        }

        self::assertTrue(is_array($createdTask));
        $createdTaskId = (string) ($createdTask["id"] ?? "");
        self::assertNotSame("", $createdTaskId);

        $workspaceCardUpdateResponse = $developerApplication->handle(Request::capture("", [
            "REQUEST_URI" => "/developer/panel/workspace/tasks/update",
            "REQUEST_METHOD" => "POST",
            "REMOTE_ADDR" => "127.0.0.1",
        ], [], [
            "_token" => csrf_token(),
            "developer_workspace_task_id" => $createdTaskId,
            "developer_workspace_title" => "Prepare commercial product baseline",
            "developer_workspace_notes" => "Confirm starter flow before first product feature.",
            "developer_workspace_status" => "review",
            "developer_workspace_position" => "250",
            "developer_workspace_priority" => "urgent",
            "developer_workspace_type" => "release",
            "developer_workspace_color" => "sky",
            "developer_workspace_assignee" => "dev@example.test",
            "developer_workspace_due_date" => "2026-09-04",
            "developer_workspace_estimate" => "45m",
            "developer_workspace_blocked" => "1",
            "developer_workspace_checklist" => "[x] Confirm routes\n[ ] Run release checks",
            "developer_workspace_subtask" => "Capture QA screenshot",
            "developer_workspace_subtask_color" => "sky",
            "developer_workspace_comment" => "First review note from the active developer.",
            "developer_workspace_attachment_label" => "Feature spec",
            "developer_workspace_attachment_url" => "https://example.test/spec",
        ]));
        $workspaceTaskActionResponse = $developerApplication->handle(Request::capture("", [
            "REQUEST_URI" => "/developer/panel/workspace/tasks/update",
            "REQUEST_METHOD" => "POST",
            "REMOTE_ADDR" => "127.0.0.1",
        ], [], [
            "_token" => csrf_token(),
            "developer_workspace_task_id" => $createdTaskId,
            "developer_workspace_title" => "Prepare commercial product baseline",
            "developer_workspace_notes" => "Confirm starter flow before first product feature.",
            "developer_workspace_status" => "review",
            "developer_workspace_position" => "250",
            "developer_workspace_priority" => "urgent",
            "developer_workspace_type" => "release",
            "developer_workspace_color" => "sky",
            "developer_workspace_assignee" => "dev@example.test",
            "developer_workspace_due_date" => "2026-09-04",
            "developer_workspace_estimate" => "45m",
            "developer_workspace_blocked" => "1",
            "developer_workspace_checklist" => "[x] Confirm routes\n[ ] Run release checks\n[ ] Capture QA screenshot",
            "developer_workspace_toggle_subtask_index" => "1",
            "developer_workspace_edit_subtask_index" => "2",
            "developer_workspace_edit_subtask_text" => "QA screenshot approved",
            "developer_workspace_edit_subtask_color" => "sky",
        ]));
        $workspaceDetailedResponse = $developerApplication->handle(Request::capture("", [
            "REQUEST_URI" => "/developer/panel/workspace",
            "REQUEST_METHOD" => "GET",
            "REMOTE_ADDR" => "127.0.0.1",
        ]));

        self::assertSame(302, $workspaceCardUpdateResponse->status());
        self::assertSame(302, $workspaceTaskActionResponse->status());
        self::assertSame(200, $workspaceDetailedResponse->status());
        self::assertStringContainsString("2/3 checklist", $workspaceDetailedResponse->body());
        self::assertStringContainsString("QA screenshot approved", $workspaceDetailedResponse->body());
        self::assertStringContainsString("First review note from the active developer.", $workspaceDetailedResponse->body());
        self::assertStringContainsString("Feature spec", $workspaceDetailedResponse->body());
        self::assertStringContainsString("https://example.test/spec", $workspaceDetailedResponse->body());
        self::assertStringContainsString("data-developer-kanban-position=\"250\"", $workspaceDetailedResponse->body());
        self::assertStringContainsString("developer-kanban-subtask-sky", $workspaceDetailedResponse->body());

        $notificationActionResponse = $developerApplication->handle(Request::capture("", [
            "REQUEST_URI" => "/developer/panel/notifications/action",
            "REQUEST_METHOD" => "POST",
            "REMOTE_ADDR" => "127.0.0.1",
        ], [], [
            "_token" => csrf_token(),
            "developer_notification_key" => "developer-totp-disabled",
            "developer_notification_action" => "archive",
        ]));
        $notificationsArchivedResponse = $developerApplication->handle(Request::capture("", [
            "REQUEST_URI" => "/developer/panel/notifications",
            "REQUEST_METHOD" => "GET",
            "REMOTE_ADDR" => "127.0.0.1",
        ]));

        self::assertSame(302, $notificationActionResponse->status());
        self::assertSame("/developer/panel/notifications", $notificationActionResponse->headers()["Location"] ?? null);
        self::assertSame(200, $notificationsArchivedResponse->status());
        self::assertStringContainsString("Archived notifications", $notificationsArchivedResponse->body());
        self::assertStringContainsString(">Restore<", $notificationsArchivedResponse->body());
        self::assertStringContainsString("Save project identity", $identityResponse->body());
        self::assertStringContainsString("Project slogan", $identityResponse->body());
        self::assertStringContainsString("Save maintenance settings", $projectSettingsResponse->body());
        self::assertStringContainsString("Save service control", $projectSettingsResponse->body());
        self::assertStringContainsString("Maintenance mode is currently off.", $projectSettingsResponse->body());
        self::assertStringContainsString("Save panel settings", $panelSettingsResponse->body());
        self::assertStringContainsString("Save profile", $profileResponse->body());
        self::assertStringContainsString("Only a Lead developer can change account roles", $profileResponse->body());
        self::assertStringNotContainsString("name=\"developer_profile_role\"", $profileResponse->body());
        self::assertStringContainsString("name=\"developer_profile_avatar_file\"", $profileResponse->body());
        self::assertStringContainsString("developer-profile-file-input", $profileResponse->body());
        self::assertStringContainsString("name=\"developer_profile_generate_avatar\"", $profileResponse->body());
        self::assertStringContainsString("data-fnlla-modal-open=\"#developer-profile-2fa-modal\"", $profileResponse->body());
        self::assertStringContainsString("Generate authenticator setup key", $profileResponse->body());
        self::assertStringContainsString("Save new password", $profileResponse->body());

        $integrationUpdateResponse = $developerApplication->handle(Request::capture("", [
            "REQUEST_URI" => "/developer/panel/integrations/settings",
            "REQUEST_METHOD" => "POST",
            "REMOTE_ADDR" => "127.0.0.1",
        ], [], [
            "_token" => csrf_token(),
            "fnlla_integration_ga4_enabled" => "1",
            "fnlla_integration_ga4_measurement_id" => "G-TEST12345",
            "fnlla_integration_sentry_enabled" => "1",
            "fnlla_integration_sentry_dsn" => "https://public@example.invalid/1",
            "developer_control_remote_enabled" => "1",
            "developer_control_remote_endpoint" => "https://techayo.co.uk/admin/api/fnlla-control",
            "developer_control_remote_project_id" => "qwerty",
            "ai_fionn_enabled" => "1",
            "ai_fionn_endpoint" => "http://127.0.0.1:11434",
            "ai_fionn_chat_path" => "/api/chat",
            "ai_fionn_api_token" => "local-test-token",
            "ai_fionn_allowed_hosts" => "127.0.0.1,localhost",
            "ai_fionn_allow_insecure_localhost" => "1",
        ]));

        self::assertSame(302, $integrationUpdateResponse->status());
        self::assertSame("/developer/panel/integrations", $integrationUpdateResponse->headers()["Location"] ?? null);
        self::assertSame(true, config("integrations.ga4.enabled"));
        self::assertSame("G-TEST12345", config("integrations.ga4.measurement_id"));
        self::assertSame(true, config("developer_control.remote.enabled"));
        self::assertSame(true, config("ai.runtime.fionn.enabled"));
        self::assertSame("http://127.0.0.1:11434", config("ai.runtime.fionn.endpoint"));
        self::assertSame("/api/chat", config("ai.runtime.fionn.chat_path"));
        self::assertSame(["127.0.0.1", "localhost"], config("ai.runtime.fionn.allowed_hosts"));
        config_set("ai.runtime.fionn.enabled", false);
        config_set("ai.runtime.fionn.endpoint", "");
        config_set("ai.runtime.fionn.chat_path", "/api/chat");
        config_set("ai.runtime.fionn.api_token", "");
        config_set("ai.runtime.fionn.allowed_hosts", ["127.0.0.1", "localhost"]);
        config_set("ai.runtime.fionn.allow_insecure_localhost", true);

        $homeResponse = $application->handle(Request::capture("", [
            "REQUEST_URI" => "/",
            "REQUEST_METHOD" => "GET",
            "REMOTE_ADDR" => "127.0.0.1",
        ]));

        self::assertSame(200, $homeResponse->status());
        self::assertStringContainsString("A server-rendered project base", $homeResponse->body());
        self::assertStringContainsString("googletagmanager.com/gtag/js", $homeResponse->body());
        self::assertStringContainsString("fnlla.integration_event.v1", $homeResponse->body());
    }

    public function testDeveloperPanelCanUpdateProjectSettings(): void
    {
        $this->temporaryEnvironmentDirectory = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . "fnlla-developer-project-settings-"
            . bin2hex(random_bytes(4));
        mkdir($this->temporaryEnvironmentDirectory, 0777, true);
        $envPath = $this->temporaryEnvironmentDirectory . DIRECTORY_SEPARATOR . ".env";
        $envExamplePath = $this->temporaryEnvironmentDirectory . DIRECTORY_SEPARATOR . ".env.example";
        file_put_contents($envExamplePath, "APP_ENV=development" . PHP_EOL . "APP_DEBUG=true" . PHP_EOL);

        config_set("maintenance", array_merge((array) config("maintenance", []), [
            "enabled" => false,
            "password" => "",
            "username" => "",
            "env_path" => $envPath,
            "env_example_path" => $envExamplePath,
        ]));
        config_set("app", array_merge((array) config("app", []), [
            "name" => "Starter Project",
            "base_url" => "",
        ]));
        config_set("developer_access", array_merge((array) config("developer_access", []), [
            "enabled" => true,
            "password" => "operator-pass",
            "operations_nav_mode" => "hidden",
        ]));

        $application = $this->makeApplication();
        developer_access()->grantAccess();
        $response = $application->handle(Request::capture("", [
            "REQUEST_URI" => "/developer/panel/settings/project",
            "REQUEST_METHOD" => "POST",
            "REMOTE_ADDR" => "127.0.0.1",
        ], [], [
            "_token" => csrf_token(),
            "project_name" => "Client Operations Hub",
            "project_tagline" => "Client systems without noise",
            "project_url" => "https://client.example.test",
        ]));

        self::assertSame(302, $response->status());
        self::assertSame("/developer/panel/project-identity", $response->headers()["Location"] ?? null);
        self::assertStringContainsString('APP_NAME="Client Operations Hub"', (string) file_get_contents($envPath));
        self::assertStringContainsString('APP_TAGLINE="Client systems without noise"', (string) file_get_contents($envPath));
        self::assertStringContainsString("APP_URL=https://client.example.test", (string) file_get_contents($envPath));
        self::assertSame("Client Operations Hub", config("app.name"));
        self::assertSame("Client systems without noise", config("app.tagline"));
        self::assertSame("https://client.example.test", config("app.base_url"));
    }

    public function testProjectLeadershipRequiresNamedPersonConfirmationBeforePublicDisplay(): void
    {
        $this->temporaryEnvironmentDirectory = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . "fnlla-project-leadership-"
            . bin2hex(random_bytes(4));
        mkdir($this->temporaryEnvironmentDirectory, 0777, true);
        $envPath = $this->temporaryEnvironmentDirectory . DIRECTORY_SEPARATOR . ".env";
        $envExamplePath = $this->temporaryEnvironmentDirectory . DIRECTORY_SEPARATOR . ".env.example";
        file_put_contents($envExamplePath, "APP_ENV=development" . PHP_EOL . "APP_DEBUG=true" . PHP_EOL);

        config_set("maintenance", array_merge((array) config("maintenance", []), [
            "enabled" => false,
            "env_path" => $envPath,
            "env_example_path" => $envExamplePath,
        ]));
        config_set("app", array_merge((array) config("app", []), [
            "name" => "Qwerty Client Portal",
            "tagline" => "",
            "base_url" => "",
            "project_leadership" => [
                "organization" => "",
                "person_name" => "",
                "person_email" => "",
                "person_role" => "",
                "responsibility" => "",
                "profile_url" => "",
                "visibility" => "disabled",
                "status" => "pending",
                "confirmed_by" => "",
                "confirmed_at" => "",
            ],
        ]));

        $application = $this->makeApplication();
        $setupDeveloper = [
            "email" => "setup@example.test",
            "name" => "Setup Developer",
            "role" => "lead_developer",
            "avatar" => "",
            "password_hash" => password_hash("operator-pass", PASSWORD_DEFAULT),
        ];
        $productLead = [
            "email" => "marcin@example.test",
            "name" => "Marcin Kordyaczny",
            "role" => "application_developer",
            "avatar" => "",
            "password_hash" => password_hash("operator-pass", PASSWORD_DEFAULT),
        ];
        config_set("developer_access", array_merge((array) config("developer_access", []), [
            "enabled" => true,
            "email" => "setup@example.test",
            "password" => "",
            "password_hash" => "",
            "users" => developer_access()->serializeAccounts([$setupDeveloper, $productLead]),
            "operations_nav_mode" => "hidden",
        ]));
        developer_access()->grantAccess($setupDeveloper);

        $saveResponse = $application->handle(Request::capture("", [
            "REQUEST_URI" => "/developer/panel/settings/project-leadership",
            "REQUEST_METHOD" => "POST",
            "REMOTE_ADDR" => "127.0.0.1",
        ], [], [
            "_token" => csrf_token(),
            "project_leadership_organization" => "TechAyo Limited",
            "project_leadership_person_name" => "Marcin Kordyaczny",
            "project_leadership_person_email" => "marcin@example.test",
            "project_leadership_person_role" => "Director of TechAyo",
            "project_leadership_responsibility" => "product direction, roadmap and technical delivery",
            "project_leadership_profile_url" => "https://techayo.co.uk/contact",
            "project_leadership_visibility" => "public",
        ]));

        self::assertSame(302, $saveResponse->status());
        self::assertSame("/developer/panel/project-identity#project-leadership", $saveResponse->headers()["Location"] ?? null);
        self::assertStringContainsString("PROJECT_LEADERSHIP_PERSON_NAME=\"Marcin Kordyaczny\"", (string) file_get_contents($envPath));
        self::assertStringContainsString("PROJECT_LEADERSHIP_PERSON_EMAIL=marcin@example.test", (string) file_get_contents($envPath));
        self::assertStringContainsString("PROJECT_LEADERSHIP_VISIBILITY=public", (string) file_get_contents($envPath));
        self::assertStringContainsString("PROJECT_LEADERSHIP_STATUS=pending", (string) file_get_contents($envPath));

        $aboutBeforeConfirmation = $application->handle(Request::capture("", [
            "REQUEST_URI" => "/about",
            "REQUEST_METHOD" => "GET",
            "REMOTE_ADDR" => "127.0.0.1",
        ]));
        $notificationsForSetup = $application->handle(Request::capture("", [
            "REQUEST_URI" => "/developer/panel/notifications",
            "REQUEST_METHOD" => "GET",
            "REMOTE_ADDR" => "127.0.0.1",
        ]));

        self::assertSame(200, $aboutBeforeConfirmation->status());
        self::assertStringNotContainsString("Qwerty Client Portal is led by", $aboutBeforeConfirmation->body());
        self::assertStringContainsString("Project leadership pending", $notificationsForSetup->body());

        developer_access()->grantAccess($productLead);
        $identityForLead = $application->handle(Request::capture("", [
            "REQUEST_URI" => "/developer/panel/project-identity",
            "REQUEST_METHOD" => "GET",
            "REMOTE_ADDR" => "127.0.0.1",
        ]));
        $notificationsForLead = $application->handle(Request::capture("", [
            "REQUEST_URI" => "/developer/panel/notifications",
            "REQUEST_METHOD" => "GET",
            "REMOTE_ADDR" => "127.0.0.1",
        ]));

        self::assertSame(200, $identityForLead->status());
        self::assertStringContainsString("Confirm responsibility", $identityForLead->body());
        self::assertStringContainsString("Leadership confirmation required", $notificationsForLead->body());

        $confirmResponse = $application->handle(Request::capture("", [
            "REQUEST_URI" => "/developer/panel/settings/project-leadership/confirmation",
            "REQUEST_METHOD" => "POST",
            "REMOTE_ADDR" => "127.0.0.1",
        ], [], [
            "_token" => csrf_token(),
            "project_leadership_action" => "confirm",
        ]));

        self::assertSame(302, $confirmResponse->status());
        self::assertSame("/developer/panel/project-identity#project-leadership", $confirmResponse->headers()["Location"] ?? null);
        self::assertSame("confirmed", config("app.project_leadership.status"));
        self::assertStringContainsString("PROJECT_LEADERSHIP_STATUS=confirmed", (string) file_get_contents($envPath));

        $aboutAfterConfirmation = $application->handle(Request::capture("", [
            "REQUEST_URI" => "/about",
            "REQUEST_METHOD" => "GET",
            "REMOTE_ADDR" => "127.0.0.1",
        ]));

        self::assertSame(200, $aboutAfterConfirmation->status());
        self::assertStringContainsString("Product leadership", $aboutAfterConfirmation->body());
        self::assertStringContainsString("Qwerty Client Portal is led by", $aboutAfterConfirmation->body());
        self::assertStringContainsString("Marcin Kordyaczny", $aboutAfterConfirmation->body());
        self::assertStringContainsString("responsible for product direction, roadmap and technical delivery", $aboutAfterConfirmation->body());
    }

    public function testDeveloperRoleCapabilitiesBlockRestrictedPanelActions(): void
    {
        $this->temporaryEnvironmentDirectory = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . "fnlla-developer-capabilities-"
            . bin2hex(random_bytes(4));
        mkdir($this->temporaryEnvironmentDirectory, 0777, true);
        $envPath = $this->temporaryEnvironmentDirectory . DIRECTORY_SEPARATOR . ".env";
        $envExamplePath = $this->temporaryEnvironmentDirectory . DIRECTORY_SEPARATOR . ".env.example";
        file_put_contents($envExamplePath, "APP_ENV=development" . PHP_EOL . "APP_DEBUG=true" . PHP_EOL);

        $clientDeveloper = [
            "email" => "client-reviewer@example.test",
            "name" => "Client Reviewer",
            "role" => "client",
            "avatar" => "",
            "password_hash" => password_hash("client-secret", PASSWORD_DEFAULT),
        ];

        config_set("maintenance", array_merge((array) config("maintenance", []), [
            "enabled" => false,
            "env_path" => $envPath,
            "env_example_path" => $envExamplePath,
        ]));
        config_set("app", array_merge((array) config("app", []), [
            "name" => "Original Project",
            "tagline" => "",
            "base_url" => "",
        ]));
        config_set("developer_access", array_merge((array) config("developer_access", []), [
            "enabled" => true,
            "email" => "client-reviewer@example.test",
            "password" => "",
            "password_hash" => "",
            "users" => developer_access()->serializeAccounts([$clientDeveloper]),
            "operations_nav_mode" => "hidden",
        ]));

        $application = $this->makeApplication();
        developer_access()->grantAccess($clientDeveloper);

        $policyResponse = $application->handle(Request::capture("", [
            "REQUEST_URI" => "/developer/panel/policy",
            "REQUEST_METHOD" => "GET",
            "REMOTE_ADDR" => "127.0.0.1",
        ]));
        $projectMutationResponse = $application->handle(Request::capture("", [
            "REQUEST_URI" => "/developer/panel/settings/project",
            "REQUEST_METHOD" => "POST",
            "REMOTE_ADDR" => "127.0.0.1",
        ], [], [
            "_token" => csrf_token(),
            "project_name" => "Blocked Project",
            "project_tagline" => "Should not save",
            "project_url" => "https://blocked.example.test",
        ]));
        $auditExportResponse = $application->handle(Request::capture("", [
            "REQUEST_URI" => "/developer/panel/operations/audit-export",
            "REQUEST_METHOD" => "GET",
            "REMOTE_ADDR" => "127.0.0.1",
        ]));

        self::assertSame(200, $policyResponse->status());
        self::assertStringContainsString("Policy Boundary", $policyResponse->body());
        self::assertSame(302, $projectMutationResponse->status());
        self::assertSame("/developer/panel/project-identity", $projectMutationResponse->headers()["Location"] ?? null);
        self::assertSame("Original Project", config("app.name"));
        self::assertFalse(is_file($envPath));
        self::assertSame(302, $auditExportResponse->status());
        self::assertSame("/developer/panel/operations", $auditExportResponse->headers()["Location"] ?? null);
    }

    public function testDeveloperProfileCanUpdateCurrentDeveloperAccount(): void
    {
        $this->temporaryEnvironmentDirectory = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . "fnlla-developer-profile-"
            . bin2hex(random_bytes(4));
        mkdir($this->temporaryEnvironmentDirectory, 0777, true);
        $envPath = $this->temporaryEnvironmentDirectory . DIRECTORY_SEPARATOR . ".env";
        $envExamplePath = $this->temporaryEnvironmentDirectory . DIRECTORY_SEPARATOR . ".env.example";
        file_put_contents($envExamplePath, "APP_ENV=development" . PHP_EOL . "APP_DEBUG=true" . PHP_EOL);

        config_set("maintenance", array_merge((array) config("maintenance", []), [
            "enabled" => false,
            "password" => "",
            "username" => "",
            "env_path" => $envPath,
            "env_example_path" => $envExamplePath,
        ]));
        config_set("developer_access", array_merge((array) config("developer_access", []), [
            "enabled" => true,
            "email" => "dev@example.test",
            "password" => "",
            "password_hash" => "",
            "operations_nav_mode" => "hidden",
        ]));

        $application = $this->makeApplication();
        $developerAccount = [
            "email" => "dev@example.test",
            "name" => "Developer",
            "role" => "lead_developer",
            "avatar" => "",
            "password_hash" => password_hash("operator-pass", PASSWORD_DEFAULT),
        ];
        $serializedAccounts = developer_access()->serializeAccounts([$developerAccount]);
        config_set("developer_access", array_merge((array) config("developer_access", []), [
            "users" => $serializedAccounts,
        ]));
        developer_access()->grantAccess($developerAccount);

        $response = $application->handle(Request::capture("", [
            "REQUEST_URI" => "/developer/panel/profile",
            "REQUEST_METHOD" => "POST",
            "REMOTE_ADDR" => "127.0.0.1",
        ], [], [
            "_token" => csrf_token(),
            "developer_profile_name" => "Marcin Kordyaczny",
            "developer_profile_role" => "operations_engineer",
            "developer_profile_avatar" => "MD",
        ]));

        self::assertSame(302, $response->status());
        self::assertSame("/developer/panel/profile", $response->headers()["Location"] ?? null);
        self::assertStringContainsString("DEVELOPER_ACCESS_EMAIL=dev@example.test", (string) file_get_contents($envPath));
        self::assertStringContainsString("DEVELOPER_ACCESS_PASSWORD=", (string) file_get_contents($envPath));
        self::assertStringContainsString("DEVELOPER_ACCESS_PASSWORD_HASH=", (string) file_get_contents($envPath));
        self::assertStringContainsString("DEVELOPER_ACCESS_USERS=dev@example.test|", (string) file_get_contents($envPath));
        self::assertStringNotContainsString("operator-pass", (string) file_get_contents($envPath));
        self::assertSame("Marcin Kordyaczny", developer_access()->currentDeveloper()["name"] ?? null);
        self::assertSame("lead_developer", developer_access()->currentDeveloper()["role"] ?? null);
        self::assertSame("MD", developer_access()->currentDeveloper()["avatar"] ?? null);

        $panelResponse = $application->handle(Request::capture("", [
            "REQUEST_URI" => "/developer/panel",
            "REQUEST_METHOD" => "GET",
            "REMOTE_ADDR" => "127.0.0.1",
        ]));

        self::assertSame(200, $panelResponse->status());
        self::assertStringContainsString("Marcin K.", $panelResponse->body());
        self::assertStringNotContainsString("DEV OPERATIONS", $panelResponse->body());
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
            "password" => "",
            "password_hash" => "",
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
            "developer_setup_email" => "legacy-dev@example.test",
            "developer_setup_password" => "developer-secret",
            "developer_setup_password_confirmation" => "developer-secret",
        ]));

        self::assertSame(302, $activationResponse->status());
        self::assertSame("/developer/panel", $activationResponse->headers()["Location"] ?? null);
        self::assertSame(true, $_SESSION["developer.access_unlocked"] ?? false);
        self::assertStringContainsString("DEVELOPER_ACCESS_ENABLED=true", (string) file_get_contents($envPath));
        self::assertStringContainsString("DEVELOPER_ACCESS_EMAIL=legacy-dev@example.test", (string) file_get_contents($envPath));
        self::assertStringContainsString("DEVELOPER_ACCESS_USERS=legacy-dev@example.test|", (string) file_get_contents($envPath));
        self::assertStringContainsString("DEVELOPER_ACCESS_PASSWORD=", (string) file_get_contents($envPath));
        self::assertStringContainsString("DEVELOPER_ACCESS_PASSWORD_HASH=", (string) file_get_contents($envPath));
        self::assertStringNotContainsString("DEVELOPER_ACCESS_PASSWORD=developer-secret", (string) file_get_contents($envPath));
        self::assertStringContainsString("DEVELOPER_OPERATIONS_NAV_MODE=hidden", (string) file_get_contents($envPath));

        config_set("developer_access", array_merge((array) config("developer_access", []), [
            "email" => "legacy-dev@example.test",
            "password" => "",
            "password_hash" => (string) config("developer_access.password_hash", ""),
            "users" => (string) config("developer_access.users", ""),
            "operations_nav_mode" => "hidden",
        ]));
        $developerApplication = $this->makeApplication();
        $developerResponse = $developerApplication->handle(Request::capture("", [
            "REQUEST_URI" => "/developer/panel",
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
        self::assertStringContainsString("Set the first maintenance password from the project itself", $pageResponse->body());

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
            "developer_setup_email" => "locked-dev@example.test",
        ]));

        self::assertSame(302, $setupResponse->status());
        self::assertSame("/developer/panel", $setupResponse->headers()["Location"] ?? null);
        self::assertFalse($_SESSION["maintenance.access_unlocked"] ?? false);
        self::assertSame(true, $_SESSION["developer.access_unlocked"] ?? false);
        self::assertStringContainsString("MAINTENANCE_ACCESS_PASSWORD=preview-lock", (string) file_get_contents($envPath));
        self::assertStringContainsString("DEVELOPER_ACCESS_EMAIL=locked-dev@example.test", (string) file_get_contents($envPath));
        self::assertStringContainsString("DEVELOPER_ACCESS_USERS=locked-dev@example.test|", (string) file_get_contents($envPath));
        self::assertStringContainsString("DEVELOPER_ACCESS_PASSWORD=", (string) file_get_contents($envPath));
        self::assertStringContainsString("DEVELOPER_ACCESS_PASSWORD_HASH=", (string) file_get_contents($envPath));
        self::assertStringNotContainsString("DEVELOPER_ACCESS_PASSWORD=preview-lock", (string) file_get_contents($envPath));

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
            "REQUEST_URI" => "/developer",
            "REQUEST_METHOD" => "GET",
            "REMOTE_ADDR" => "127.0.0.1",
        ]));
        $developerPanelResponse = $application->handle(Request::capture("", [
            "REQUEST_URI" => "/developer/panel",
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
        self::assertSame(302, $developerPanelResponse->status());
        self::assertSame("/developer", $developerPanelResponse->headers()["Location"] ?? null);
        self::assertStringContainsString("Unlock developer session", $developerLoginResponse->body());
        self::assertStringNotContainsString("Lock developer panel", $developerLoginResponse->body());
        self::assertStringNotContainsString("Lock developer session", $developerLoginResponse->body());
    }

    public function testHiddenOperationsMenuReturnsDuringUnlockedDeveloperSession(): void
    {
        config_set("developer_access", array_merge((array) config("developer_access", []), [
            "enabled" => true,
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
        self::assertStringNotContainsString("DEV OPERATIONS", $response->body());
        self::assertStringNotContainsString("project-navbar-actions", $response->body());
        self::assertStringContainsString("href=\"/developer/panel\"", $response->body());
        self::assertStringContainsString(">Home<", $response->body());
        self::assertStringContainsString(">About<", $response->body());
        self::assertStringContainsString(">Services<", $response->body());
        self::assertStringContainsString(">Contact<", $response->body());
        self::assertStringNotContainsString(">Operations<", $response->body());
        self::assertStringNotContainsString("Maintenance Dashboard", $response->body());
        self::assertStringNotContainsString("Maintenance access required", $response->body());
    }

    public function testDeveloperPanelPublicSiteLinkOpensInNewTab(): void
    {
        config_set("developer_access", array_merge((array) config("developer_access", []), [
            "enabled" => true,
            "password" => "operator-pass",
            "operations_nav_mode" => "hidden",
        ]));

        $application = $this->makeApplication();
        developer_access()->grantAccess();

        $response = $application->handle(Request::capture("", [
            "REQUEST_URI" => "/developer/panel",
            "REQUEST_METHOD" => "GET",
            "REMOTE_ADDR" => "127.0.0.1",
        ]));

        self::assertSame(200, $response->status());
        self::assertStringContainsString("target=\"_blank\"", $response->body());
        self::assertStringContainsString("rel=\"noopener noreferrer\"", $response->body());
        self::assertStringContainsString(">Go to public website<", $response->body());
        self::assertStringNotContainsString(">Quick actions<", $response->body());
        self::assertStringNotContainsString("developer-panel-sidebar-action", $response->body());
    }

    public function testDeveloperLoginRequiresEmailForNamedAccountsAndRendersDismissibleAlert(): void
    {
        $users = developer_access()->serializeAccounts([
            [
                "email" => "alpha@example.test",
                "name" => "Alpha Developer",
                "role" => "admin",
                "password_hash" => password_hash("alpha-secret", PASSWORD_DEFAULT),
            ],
            [
                "email" => "beta@example.test",
                "name" => "Beta Developer",
                "role" => "developer",
                "password_hash" => password_hash("beta-secret", PASSWORD_DEFAULT),
            ],
        ]);
        config_set("developer_access", array_merge((array) config("developer_access", []), [
            "enabled" => true,
            "email" => "alpha@example.test",
            "password" => "",
            "password_hash" => "",
            "users" => $users,
            "operations_nav_mode" => "hidden",
        ]));

        $application = $this->makeApplication();
        $missingEmailResponse = $application->handle(Request::capture("", [
            "REQUEST_URI" => "/developer/unlock",
            "REQUEST_METHOD" => "POST",
            "REMOTE_ADDR" => "127.0.0.1",
        ], [], [
            "_token" => csrf_token(),
            "developer_access_password" => "alpha-secret",
        ]));

        self::assertSame(302, $missingEmailResponse->status());
        self::assertSame("/developer", $missingEmailResponse->headers()["Location"] ?? null);

        $_SESSION["_flash_old"]["status"] = [
            "variant" => "warning",
            "title" => "Developer access still needs attention",
            "text" => "Enter your developer email and password to unlock this hidden panel.",
            "toast" => false,
        ];
        $loginResponse = $application->handle(Request::capture("", [
            "REQUEST_URI" => "/developer",
            "REQUEST_METHOD" => "GET",
            "REMOTE_ADDR" => "127.0.0.1",
        ]));

        self::assertSame(200, $loginResponse->status());
        self::assertStringContainsString("name=\"developer_access_email\"", $loginResponse->body());
        self::assertStringContainsString("data-fnlla-alert-close", $loginResponse->body());

        $successResponse = $application->handle(Request::capture("", [
            "REQUEST_URI" => "/developer/unlock",
            "REQUEST_METHOD" => "POST",
            "REMOTE_ADDR" => "127.0.0.1",
        ], [], [
            "_token" => csrf_token(),
            "developer_access_email" => "beta@example.test",
            "developer_access_password" => "beta-secret",
        ]));

        self::assertSame(302, $successResponse->status());
        self::assertSame("/developer/panel", $successResponse->headers()["Location"] ?? null);
        self::assertSame("beta@example.test", $_SESSION["developer.identity"]["email"] ?? null);
    }

    public function testDeveloperServiceControlDisablesPublicRoutesButKeepsDeveloperEntryOpen(): void
    {
        config_set("developer_control", array_merge((array) config("developer_control", []), [
            "local_state_path" => "framework/testing/developer-control-" . bin2hex(random_bytes(4)) . ".json",
            "remote" => array_merge((array) config("developer_control.remote", []), [
                "enabled" => false,
            ]),
        ]));
        config_set("developer_access", array_merge((array) config("developer_access", []), [
            "enabled" => true,
            "password" => "operator-pass",
            "password_hash" => "",
            "users" => "",
            "operations_nav_mode" => "hidden",
        ]));

        $application = $this->makeApplication();
        developer_access()->grantAccess();
        $response = $application->handle(Request::capture("", [
            "REQUEST_URI" => "/developer/panel/settings/service-control",
            "REQUEST_METHOD" => "POST",
            "REMOTE_ADDR" => "127.0.0.1",
        ], [], [
            "_token" => csrf_token(),
            "developer_control_disabled" => "1",
            "developer_control_message" => "Service disabled by developer. Contact the developer team.",
            "developer_control_contact" => "developer@example.test",
        ]));

        self::assertSame(302, $response->status());
        self::assertSame("/developer/panel/project-settings", $response->headers()["Location"] ?? null);

        developer_access()->lock();
        $homeResponse = $application->handle(Request::capture("", [
            "REQUEST_URI" => "/",
            "REQUEST_METHOD" => "GET",
            "REMOTE_ADDR" => "127.0.0.1",
        ]));
        $developerResponse = $application->handle(Request::capture("", [
            "REQUEST_URI" => "/developer",
            "REQUEST_METHOD" => "GET",
            "REMOTE_ADDR" => "127.0.0.1",
        ]));

        self::assertSame(503, $homeResponse->status());
        self::assertStringContainsString("Service disabled by developer", $homeResponse->body());
        self::assertStringContainsString("developer@example.test", $homeResponse->body());
        self::assertSame(200, $developerResponse->status());
    }

    public function testDeveloperSessionCanBeExtendedWithoutResettingAbsoluteWindow(): void
    {
        config_set("developer_access", array_merge((array) config("developer_access", []), [
            "enabled" => true,
            "password" => "operator-pass",
            "operations_nav_mode" => "hidden",
            "unlock_ttl_minutes" => 120,
            "absolute_ttl_minutes" => 480,
        ]));

        $application = $this->makeApplication();
        developer_access()->grantAccess();

        $unlockedAtKey = (string) config("developer_access.unlocked_at_key", "developer.access_unlocked_at");
        $expiresAtKey = (string) config("developer_access.expires_at_key", "developer.access_expires_at");
        $_SESSION[$unlockedAtKey] = time() - (430 * 60);
        $_SESSION[$expiresAtKey] = time() + (5 * 60);

        $response = $application->handle(Request::capture("", [
            "REQUEST_URI" => "/developer/panel/extend",
            "REQUEST_METHOD" => "POST",
            "REMOTE_ADDR" => "127.0.0.1",
        ], [], [
            "_token" => csrf_token(),
        ]));

        self::assertSame(302, $response->status());
        self::assertSame("/developer/panel", $response->headers()["Location"] ?? null);
        self::assertTrue((int) $_SESSION[$expiresAtKey] > time() + (40 * 60));
        self::assertTrue((int) $_SESSION[$expiresAtKey] <= (int) $_SESSION[$unlockedAtKey] + (480 * 60));
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
            "enabled" => false,
            "password" => "",
            "username" => "",
            "env_path" => $envPath,
            "env_example_path" => $envExamplePath,
        ]));
        config_set("developer_access", array_merge((array) config("developer_access", []), [
            "enabled" => true,
            "password" => "operator-pass",
            "operations_nav_mode" => "hidden",
        ]));

        $application = $this->makeApplication();
        developer_access()->grantAccess();
        $token = csrf_token();

        $response = $application->handle(Request::capture("", [
            "REQUEST_URI" => "/developer/panel/settings/maintenance",
            "REQUEST_METHOD" => "POST",
            "REMOTE_ADDR" => "127.0.0.1",
        ], [], [
            "_token" => $token,
            "maintenance_access_username" => "",
            "maintenance_access_password" => "new-preview-pass",
            "maintenance_access_password_confirmation" => "new-preview-pass",
            "maintenance_access_enabled" => "1",
        ]));

        self::assertSame(302, $response->status());
        self::assertSame("/developer/panel/project-settings", $response->headers()["Location"] ?? null);
        self::assertStringContainsString("MAINTENANCE_MODE_ENABLED=true", (string) file_get_contents($envPath));
        self::assertStringContainsString("MAINTENANCE_ACCESS_PASSWORD=new-preview-pass", (string) file_get_contents($envPath));
        self::assertFalse($_SESSION["maintenance.access_unlocked"] ?? false);
    }

    public function testDeveloperPanelCanUpdatePanelSettings(): void
    {
        $this->temporaryEnvironmentDirectory = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . "fnlla-developer-panel-settings-"
            . bin2hex(random_bytes(4));
        mkdir($this->temporaryEnvironmentDirectory, 0777, true);
        $envPath = $this->temporaryEnvironmentDirectory . DIRECTORY_SEPARATOR . ".env";
        $envExamplePath = $this->temporaryEnvironmentDirectory . DIRECTORY_SEPARATOR . ".env.example";
        file_put_contents($envExamplePath, "APP_ENV=development" . PHP_EOL . "APP_DEBUG=true" . PHP_EOL);

        config_set("maintenance", array_merge((array) config("maintenance", []), [
            "enabled" => false,
            "password" => "",
            "username" => "",
            "env_path" => $envPath,
            "env_example_path" => $envExamplePath,
        ]));
        config_set("developer_access", array_merge((array) config("developer_access", []), [
            "enabled" => true,
            "password" => "operator-pass",
            "operations_nav_mode" => "hidden",
            "unlock_ttl_minutes" => 120,
            "absolute_ttl_minutes" => 480,
        ]));

        $application = $this->makeApplication();
        developer_access()->grantAccess();

        $response = $application->handle(Request::capture("", [
            "REQUEST_URI" => "/developer/panel/settings/panel",
            "REQUEST_METHOD" => "POST",
            "REMOTE_ADDR" => "127.0.0.1",
        ], [], [
            "_token" => csrf_token(),
            "developer_operations_nav_mode" => "developer_session_only",
            "developer_access_ttl_minutes" => "45",
            "developer_access_absolute_ttl_minutes" => "180",
        ]));

        self::assertSame(302, $response->status());
        self::assertSame("/developer/panel/settings", $response->headers()["Location"] ?? null);
        self::assertStringContainsString("DEVELOPER_OPERATIONS_NAV_MODE=developer_session_only", (string) file_get_contents($envPath));
        self::assertStringContainsString("DEVELOPER_ACCESS_TTL_MINUTES=45", (string) file_get_contents($envPath));
        self::assertStringContainsString("DEVELOPER_ACCESS_ABSOLUTE_TTL_MINUTES=180", (string) file_get_contents($envPath));
        self::assertSame("developer_session_only", config("developer_access.operations_nav_mode"));
        self::assertSame(45, config("developer_access.unlock_ttl_minutes"));
        self::assertSame(180, config("developer_access.absolute_ttl_minutes"));
    }

    public function testDeveloperTotpMustPassBeforePanelSessionUnlocks(): void
    {
        $secret = "JBSWY3DPEHPK3PXP";
        $passwordHash = password_hash("secure-password", PASSWORD_DEFAULT);
        $encode = static fn (string $value): string => rtrim(strtr(base64_encode($value), "+/", "-_"), "=");
        $users = implode("|", [
            "secure@example.test",
            $encode("Secure Developer"),
            $encode("admin"),
            $encode(""),
            $passwordHash,
            $encode(json_encode([
                "totp_secret" => $secret,
                "totp_enabled" => true,
                "passkey_adapter" => "disabled",
                "passkey_label" => "",
            ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)),
        ]);

        config_set("developer_access", array_merge((array) config("developer_access", []), [
            "enabled" => true,
            "email" => "secure@example.test",
            "password" => "",
            "password_hash" => "",
            "users" => $users,
            "operations_nav_mode" => "hidden",
        ]));

        $application = $this->makeApplication();
        $entryResponse = $application->handle(Request::capture("", [
            "REQUEST_URI" => "/developer",
            "REQUEST_METHOD" => "GET",
            "REMOTE_ADDR" => "127.0.0.1",
        ]));
        $wrongTotpResponse = $application->handle(Request::capture("", [
            "REQUEST_URI" => "/developer/unlock",
            "REQUEST_METHOD" => "POST",
            "REMOTE_ADDR" => "127.0.0.1",
        ], [], [
            "_token" => csrf_token(),
            "developer_access_email" => "secure@example.test",
            "developer_access_password" => "secure-password",
            "developer_access_totp" => "000000",
        ]));

        self::assertSame(302, $wrongTotpResponse->status());
        self::assertSame("/developer", $wrongTotpResponse->headers()["Location"] ?? null);
        self::assertFalse((bool) ($_SESSION["developer.access_unlocked"] ?? false));

        $validTotpResponse = $application->handle(Request::capture("", [
            "REQUEST_URI" => "/developer/unlock",
            "REQUEST_METHOD" => "POST",
            "REMOTE_ADDR" => "127.0.0.1",
        ], [], [
            "_token" => csrf_token(),
            "developer_access_email" => "secure@example.test",
            "developer_access_password" => "secure-password",
            "developer_access_totp" => $this->totpForSecret($secret),
        ]));

        self::assertSame(200, $entryResponse->status());
        self::assertStringContainsString("developer_access_totp", $entryResponse->body());
        self::assertSame(302, $validTotpResponse->status());
        self::assertSame("/developer/panel", $validTotpResponse->headers()["Location"] ?? null);
        self::assertTrue((bool) ($_SESSION["developer.access_unlocked"] ?? false));
    }

    private function totpForSecret(string $secret): string
    {
        $alphabet = "ABCDEFGHIJKLMNOPQRSTUVWXYZ234567";
        $secret = strtoupper(preg_replace('/[^A-Z2-7]/', "", $secret) ?? "");
        $bits = "";

        foreach (str_split($secret) as $char) {
            $value = strpos($alphabet, $char);

            if ($value !== false) {
                $bits .= str_pad(decbin($value), 5, "0", STR_PAD_LEFT);
            }
        }

        $key = "";

        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $key .= chr(bindec($byte));
            }
        }

        $counter = (int) floor(time() / 30);
        $time = pack("N*", 0) . pack("N*", $counter);
        $hash = hash_hmac("sha1", $time, $key, true);
        $offset = ord(substr($hash, -1)) & 0x0F;
        $binary = ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF);

        return str_pad((string) ($binary % 1000000), 6, "0", STR_PAD_LEFT);
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
