<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA TEST CASE
File: tests\HardeningTest.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Validates security hardening for process execution, request IDs, cache files
  and response headers.
*/

namespace Fnlla\Php\Tests;

use Fnlla\Php\Cache\FileCacheStore;
use Fnlla\Php\Http\HttpException;
use Fnlla\Php\Http\Request;
use Fnlla\Php\Http\Response;
use Fnlla\Php\Http\UploadedFile;
use Fnlla\Php\Support\Logger;
use Fnlla\Php\Support\ProcessRunner;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class HardeningTest extends TestCase
{
    /** @var string[] */
    private array $tempDirectories = [];

    protected function tearDown(): void
    {
        foreach ($this->tempDirectories as $directory) {
            $this->removeDirectory($directory);
        }
    }

    public function testProcessRunnerPreservesArgumentBoundaries(): void
    {
        $result = ProcessRunner::run([
            PHP_BINARY,
            "-r",
            "echo \$argv[1];",
            "value with spaces and \"quotes\"",
        ]);

        self::assertSame(0, $result["exit_code"], $result["output"]);
        self::assertSame("value with spaces and \"quotes\"", $result["output"]);
    }

    public function testProcessRunnerTimesOutLongRunningProcesses(): void
    {
        $result = ProcessRunner::run([
            PHP_BINARY,
            "-r",
            "sleep(2);",
        ], null, 1);

        self::assertSame(124, $result["exit_code"], $result["output"]);
        self::assertTrue($result["timed_out"]);
    }

    public function testRequestIdRejectsHeaderInjectionCharacters(): void
    {
        $request = Request::capture("", [
            "REQUEST_URI" => "/status",
            "REQUEST_METHOD" => "GET",
            "REMOTE_ADDR" => "127.0.0.1",
            "HTTP_X_REQUEST_ID" => "bad\r\nX-Injected: yes",
        ]);

        self::assertNotSame("bad\r\nX-Injected: yes", $request->requestId());
        self::assertSame(1, preg_match('/^[a-f0-9]{32}$/', $request->requestId()));
    }

    public function testFileCacheDoesNotHydrateObjectsFromDisk(): void
    {
        $directory = $this->makeTempDirectory("fnlla-cache-hardening-");
        $cache = new FileCacheStore($directory);
        $key = "object-payload";
        $path = $directory . DIRECTORY_SEPARATOR . sha1($key) . ".cache";

        file_put_contents($path, serialize([
            "expires_at" => time() + 3600,
            "value" => new \stdClass(),
        ]));

        self::assertSame("fallback", $cache->get($key, "fallback"));
    }

    public function testFileCacheWritesJsonPayloadsByDefault(): void
    {
        $directory = $this->makeTempDirectory("fnlla-cache-json-");
        $cache = new FileCacheStore($directory);
        $key = "json-payload";
        $path = $directory . DIRECTORY_SEPARATOR . sha1($key) . ".cache";

        $cache->put($key, ["answer" => 42], 60);

        self::assertSame(["answer" => 42], $cache->get($key));
        self::assertSame("{", substr(trim((string) file_get_contents($path)), 0, 1));
    }

    public function testRequestCaptureRejectsOversizedBodies(): void
    {
        $previousConfig = config("security.request.max_body_bytes");
        config_set("security.request.max_body_bytes", 4);

        try {
            $this->expectException(HttpException::class);
            Request::capture("toolarge", [
                "REQUEST_URI" => "/submit",
                "REQUEST_METHOD" => "POST",
                "CONTENT_LENGTH" => "7",
            ]);
        } finally {
            config_set("security.request.max_body_bytes", $previousConfig);
        }
    }

    public function testUploadedFileValidationRejectsUnexpectedMimeTypes(): void
    {
        $directory = $this->makeTempDirectory("fnlla-upload-hardening-");
        $path = $directory . DIRECTORY_SEPARATOR . "payload.txt";
        file_put_contents($path, "plain text");
        $file = new UploadedFile($path, "payload.txt", "text/plain", 10, UPLOAD_ERR_OK);

        $this->expectException(RuntimeException::class);
        $file->validate(100, ["application/pdf"]);
    }

    public function testResponseRejectsUnsafeHeaderValues(): void
    {
        $this->expectException(RuntimeException::class);

        Response::text("ok")->withHeader("X-Test", "safe\r\nX-Injected: yes");
    }

    public function testLoggerRedactsSensitiveContextKeys(): void
    {
        $directory = $this->makeTempDirectory("fnlla-log-hardening-");
        $previousLogPath = config("app.log_path");
        config_set("app.log_path", $directory . DIRECTORY_SEPARATOR . "app.log");

        try {
            Logger::write("info", "login attempt", [
                "password" => "secret-value",
                "nested" => [
                    "authorization" => "Bearer secret",
                ],
                "safe" => "visible",
            ]);

            $contents = (string) file_get_contents($directory . DIRECTORY_SEPARATOR . "app.log");
            self::assertStringNotContainsString("secret-value", $contents);
            self::assertStringNotContainsString("Bearer secret", $contents);
            self::assertStringContainsString("[redacted]", $contents);
            self::assertStringContainsString("visible", $contents);
        } finally {
            config_set("app.log_path", $previousLogPath);
        }
    }

    private function makeTempDirectory(string $prefix): string
    {
        $directory = rtrim(sys_get_temp_dir(), "\\/") . DIRECTORY_SEPARATOR . $prefix . bin2hex(random_bytes(4));
        mkdir($directory, 0777, true);
        $this->tempDirectories[] = $directory;

        return $directory;
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($path);
    }
}
