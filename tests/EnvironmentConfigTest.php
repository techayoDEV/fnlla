<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA TEST CASE
File: tests\EnvironmentConfigTest.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Validates maintained framework behavior inside the repository-local test harness.
*/

namespace Fnlla\Php\Tests;

use Fnlla\Php\Support\Logger;
use PHPUnit\Framework\TestCase;

final class EnvironmentConfigTest extends TestCase
{
    private array $serverBackup = [];
    private mixed $appUrlBackup;
    private mixed $appLogPathBackup;
    private mixed $sessionSecureBackup;
    private mixed $trustedProxiesBackup;

    protected function setUp(): void
    {
        $this->serverBackup = $_SERVER;
        $this->appUrlBackup = $_ENV["APP_URL"] ?? null;
        $this->appLogPathBackup = $_ENV["APP_LOG_PATH"] ?? null;
        $this->sessionSecureBackup = $_ENV["SESSION_SECURE"] ?? null;
        $this->trustedProxiesBackup = $_ENV["TRUSTED_PROXIES"] ?? null;
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->serverBackup;

        if ($this->appUrlBackup === null) {
            unset($_ENV["APP_URL"], $_SERVER["APP_URL"]);
            putenv("APP_URL");
        } else {
            $_ENV["APP_URL"] = $this->appUrlBackup;
            $_SERVER["APP_URL"] = (string) $this->appUrlBackup;
            putenv("APP_URL=" . (string) $this->appUrlBackup);
        }

        if ($this->appLogPathBackup === null) {
            unset($_ENV["APP_LOG_PATH"], $_SERVER["APP_LOG_PATH"]);
            putenv("APP_LOG_PATH");
        } else {
            $_ENV["APP_LOG_PATH"] = $this->appLogPathBackup;
            $_SERVER["APP_LOG_PATH"] = (string) $this->appLogPathBackup;
            putenv("APP_LOG_PATH=" . (string) $this->appLogPathBackup);
        }

        if ($this->sessionSecureBackup === null) {
            unset($_ENV["SESSION_SECURE"], $_SERVER["SESSION_SECURE"]);
            putenv("SESSION_SECURE");
        } else {
            $_ENV["SESSION_SECURE"] = $this->sessionSecureBackup;
            $_SERVER["SESSION_SECURE"] = (string) $this->sessionSecureBackup;
            putenv("SESSION_SECURE=" . (string) $this->sessionSecureBackup);
        }

        if ($this->trustedProxiesBackup === null) {
            unset($_ENV["TRUSTED_PROXIES"], $_SERVER["TRUSTED_PROXIES"]);
            putenv("TRUSTED_PROXIES");
        } else {
            $_ENV["TRUSTED_PROXIES"] = $this->trustedProxiesBackup;
            $_SERVER["TRUSTED_PROXIES"] = (string) $this->trustedProxiesBackup;
            putenv("TRUSTED_PROXIES=" . (string) $this->trustedProxiesBackup);
        }
    }

    public function testSessionConfigDefaultsToNonSecureCookiesOnLocalHttp(): void
    {
        $_ENV["APP_URL"] = "http://127.0.0.1:8080";
        $_SERVER["APP_URL"] = "http://127.0.0.1:8080";
        putenv("APP_URL=http://127.0.0.1:8080");
        unset($_ENV["SESSION_SECURE"], $_SERVER["SESSION_SECURE"]);
        putenv("SESSION_SECURE");

        $config = require base_path("config/session.php");

        self::assertFalse($config["secure"]);
    }

    public function testSessionConfigDefaultsToSecureCookiesOnHttps(): void
    {
        $_ENV["APP_URL"] = "https://fnlla.example.test";
        $_SERVER["APP_URL"] = "https://fnlla.example.test";
        putenv("APP_URL=https://fnlla.example.test");
        unset($_ENV["SESSION_SECURE"], $_SERVER["SESSION_SECURE"]);
        putenv("SESSION_SECURE");

        $config = require base_path("config/session.php");

        self::assertTrue($config["secure"]);
    }

    public function testSessionSecureDetectionIgnoresForwardedHttpsWithoutTrustedProxy(): void
    {
        $_ENV["APP_URL"] = "http://fnlla.example.test";
        $_SERVER["APP_URL"] = "http://fnlla.example.test";
        $_SERVER["REMOTE_ADDR"] = "198.51.100.20";
        $_SERVER["HTTP_X_FORWARDED_PROTO"] = "https";
        putenv("APP_URL=http://fnlla.example.test");
        unset($_ENV["SESSION_SECURE"], $_SERVER["SESSION_SECURE"], $_ENV["TRUSTED_PROXIES"], $_SERVER["TRUSTED_PROXIES"]);
        putenv("SESSION_SECURE");
        putenv("TRUSTED_PROXIES");

        $config = require base_path("config/session.php");

        self::assertFalse($config["secure"]);
    }

    public function testSessionSecureDetectionHonorsForwardedHttpsFromTrustedProxy(): void
    {
        $_ENV["APP_URL"] = "http://fnlla.example.test";
        $_SERVER["APP_URL"] = "http://fnlla.example.test";
        $_SERVER["REMOTE_ADDR"] = "10.0.0.10";
        $_SERVER["HTTP_X_FORWARDED_PROTO"] = "https";
        $_ENV["TRUSTED_PROXIES"] = "10.0.0.10";
        $_SERVER["TRUSTED_PROXIES"] = "10.0.0.10";
        putenv("APP_URL=http://fnlla.example.test");
        putenv("TRUSTED_PROXIES=10.0.0.10");
        unset($_ENV["SESSION_SECURE"], $_SERVER["SESSION_SECURE"]);
        putenv("SESSION_SECURE");

        $config = require base_path("config/session.php");

        self::assertTrue($config["secure"]);
    }

    public function testBlankAppLogPathFallsBackToDefaultLogFile(): void
    {
        $_ENV["APP_LOG_PATH"] = "";
        $_SERVER["APP_LOG_PATH"] = "";
        putenv("APP_LOG_PATH=");
        config_set("app", require base_path("config/app.php"));

        $config = require base_path("config/app.php");

        self::assertSame(storage_path("logs/app.log"), $config["log_path"]);
        self::assertSame(storage_path("logs/app.log"), Logger::configuredPath());
    }
}
