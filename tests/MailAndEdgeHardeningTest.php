<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA TEST CASE
File: tests\MailAndEdgeHardeningTest.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

Purpose:
- Validates mail, trusted-host and edge-header hardening for business-facing
  deployments.
*/

namespace Fnlla\Php\Tests;

use Fnlla\Php\Http\Request;
use Fnlla\Php\Http\Response;
use Fnlla\Php\Mail\Mailer;
use Fnlla\Php\Middleware\EnforceTrustedHosts;
use Fnlla\Php\Support\SecurityAuditReport;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class MailAndEdgeHardeningTest extends TestCase
{
    private array $previousConfig = [];

    protected function setUp(): void
    {
        $this->previousConfig = config();
    }

    protected function tearDown(): void
    {
        $GLOBALS["fnlla_config"] = $this->previousConfig;
        $GLOBALS["fnlla_php_config"] = $this->previousConfig;
    }

    public function testTrustedHostMiddlewareRejectsUnexpectedHost(): void
    {
        config_set("security.trusted_hosts", ["example.com", "*.example.org"]);

        $middleware = new EnforceTrustedHosts();
        $response = $middleware->handle(Request::capture("", [
            "REQUEST_URI" => "/",
            "REQUEST_METHOD" => "GET",
            "HTTP_HOST" => "attacker.test",
        ]), static fn (): Response => Response::text("ok"));

        self::assertInstanceOf(Response::class, $response);
        self::assertSame(400, $response->status());
    }

    public function testTrustedHostMiddlewareAllowsConfiguredWildcardSubdomain(): void
    {
        config_set("security.trusted_hosts", ["example.com", "*.example.org"]);

        $middleware = new EnforceTrustedHosts();
        $response = $middleware->handle(Request::capture("", [
            "REQUEST_URI" => "/",
            "REQUEST_METHOD" => "GET",
            "HTTP_HOST" => "client.example.org:443",
        ]), static fn (): Response => Response::text("ok"));

        self::assertInstanceOf(Response::class, $response);
        self::assertSame(200, $response->status());
    }

    public function testMailerRejectsHeaderInjectionInRecipientsAndSubjects(): void
    {
        $this->expectException(RuntimeException::class);

        (new Mailer())->send("team@example.com\r\nBcc: attacker@example.com", "Contact", "<p>Hello</p>");
    }

    public function testMailerWritesEscapedFormSubmissionsToLogTransport(): void
    {
        $directory = storage_path("framework/cache/mail-test-" . bin2hex(random_bytes(4)));
        config_set("mail.default", "log");
        config_set("mail.log_path", str_replace(storage_path() . DIRECTORY_SEPARATOR, "", $directory));
        config_set("mail.from.address", "no-reply@example.com");

        try {
            (new Mailer())->sendFormSubmission("team@example.com", "Contact form", [
                "Name" => "Ada",
                "Message" => "<script>alert(1)</script>",
            ]);

            $path = $directory . DIRECTORY_SEPARATOR . gmdate("Ymd") . ".log";
            self::assertFileExists($path);
            $contents = (string) file_get_contents($path);
            self::assertStringContainsString("alert(1)", $contents);
            self::assertStringNotContainsString("<script>alert(1)</script>", $contents);
        } finally {
            if (is_file($directory . DIRECTORY_SEPARATOR . gmdate("Ymd") . ".log")) {
                unlink($directory . DIRECTORY_SEPARATOR . gmdate("Ymd") . ".log");
            }

            if (is_dir($directory)) {
                rmdir($directory);
            }
        }
    }

    public function testNativeMailerMustBeExplicitlyEnabled(): void
    {
        config_set("mail.default", "native");
        config_set("mail.native_enabled", false);

        $this->expectException(RuntimeException::class);
        (new Mailer())->send("team@example.com", "Contact", "<p>Hello</p>");
    }

    public function testHttpMailerRequiresConfiguredHttpsEndpoint(): void
    {
        config_set("mail.default", "http");
        config_set("mail.http.endpoint", "http://mail.example.test/send");
        config_set("mail.http.require_https", true);

        $this->expectException(RuntimeException::class);
        (new Mailer())->send("team@example.com", "Contact", "<p>Hello</p>");
    }

    public function testHttpMailerPinsAllowedHostsWhenConfigured(): void
    {
        config_set("mail.default", "http");
        config_set("mail.http.endpoint", "https://unexpected.example.test/send");
        config_set("mail.http.require_https", true);
        config_set("mail.http.allowed_hosts", ["mail.example.test"]);

        $this->expectException(RuntimeException::class);
        (new Mailer())->send("team@example.com", "Contact", "<p>Hello</p>");
    }

    public function testEmptyDefaultSecurityHeadersAreNotEmitted(): void
    {
        config_set("http.security_headers.Strict-Transport-Security", "");

        $headers = Response::text("ok")->headers();
        self::assertArrayNotHasKey("Strict-Transport-Security", $headers);
    }

    public function testSecurityAuditIncludesEnterpriseEdgeChecks(): void
    {
        $report = (new SecurityAuditReport())->build();
        $checks = [];

        foreach ((array) $report["checks"] as $check) {
            $checks[$check["id"]] = $check;
        }

        self::assertArrayHasKey("trusted_hosts", $checks);
        self::assertArrayHasKey("browser_security_headers", $checks);
        self::assertArrayHasKey("mail_transport", $checks);
        self::assertArrayHasKey("native_mail_explicit", $checks);
        self::assertArrayHasKey("http_mail_transport", $checks);
        self::assertArrayHasKey("http_mail_host_allowlist", $checks);
        self::assertArrayHasKey("runtime_ai_bundle", $checks);
        self::assertArrayHasKey("runtime_ai_local_driver", $checks);
        self::assertArrayHasKey("runtime_ai_learning_path", $checks);
    }
}
