<?php

declare(strict_types=1);

namespace Fnlla\Php\Tests;

use Fnlla\Php\Http\HttpException;
use Fnlla\Php\Http\Request;
use PHPUnit\Framework\TestCase;

final class HttpBoundaryTest extends TestCase
{
    public function testProxyChainStopsAtFirstUntrustedHopInsteadOfAcceptingSpoofedPrefix(): void
    {
        $previous = $_ENV["TRUSTED_PROXIES"] ?? null;
        $_ENV["TRUSTED_PROXIES"] = "10.0.0.0/24,2001:db8:1::/48";
        try {
            foreach ([
                ["10.0.0.2", "127.0.0.1, 198.51.100.4, 10.0.0.1", "198.51.100.4"],
                ["10.0.0.2", "203.0.113.7, 10.0.0.1", "203.0.113.7"],
                ["2001:db8:1::2", "::1, 2001:db8:2::5, 2001:db8:1::1", "2001:db8:2::5"],
                ["198.51.100.4", "127.0.0.1", "198.51.100.4"],
                ["10.0.0.2", "127.0.0.1, malformed, 10.0.0.1", "10.0.0.2"],
                ["10.0.0.2", "10.0.0.1", "10.0.0.1"],
                ["10.0.0.2", str_repeat("10.0.0.1,", 65) . "10.0.0.1", "10.0.0.2"],
                ["10.0.0.2", str_repeat(" ", 8193) . "127.0.0.1", "10.0.0.2"],
            ] as [$remote, $header, $expected]) {
                $request = Request::capture("", ["REMOTE_ADDR" => $remote, "HTTP_X_FORWARDED_FOR" => $header]);
                self::assertSame($expected, $request->ip());
            }
            $_ENV["TRUSTED_PROXIES"] = "10.0.0.0/2.4";
            self::assertFalse(framework_request_comes_from_trusted_proxy(["REMOTE_ADDR" => "10.0.0.2"]));
        } finally {
            if ($previous === null) { unset($_ENV["TRUSTED_PROXIES"]); } else { $_ENV["TRUSTED_PROXIES"] = $previous; }
        }
    }

    public function testRequestBodyBoundariesAndMalformedContentLengths(): void
    {
        $previous = config("security.request.max_body_bytes");
        config_set("security.request.max_body_bytes", 4);
        try {
            self::assertSame("1234", Request::capture("1234", ["CONTENT_LENGTH" => "4"])->rawBody());
            foreach ([["12345", [], 413], ["", ["CONTENT_LENGTH" => "5"], 413], ["", ["CONTENT_LENGTH" => "-1"], 400], ["", ["CONTENT_LENGTH" => "1, 1"], 400], ["", ["CONTENT_LENGTH" => "not-a-number"], 400]] as [$body, $server, $status]) {
                try {
                    Request::capture($body, $server);
                    self::fail("Invalid body boundary was accepted.");
                } catch (HttpException $error) {
                    self::assertSame($status, $error->statusCode());
                }
            }
            $stream = fopen("php://temp", "w+b");
            try {
                fwrite($stream, str_repeat("x", 10000));
                rewind($stream);
                try {
                    (new \ReflectionMethod(Request::class, "readBoundedBody"))->invoke(null, $stream);
                    self::fail("Oversized stream was accepted.");
                } catch (HttpException $error) {
                    self::assertSame(413, $error->statusCode());
                    self::assertSame(5, ftell($stream));
                }
            } finally {
                fclose($stream);
            }
        } finally {
            config_set("security.request.max_body_bytes", $previous);
        }
    }

    public function testEmptyFastCgiLengthIsAbsentButBodyLimitsStillApply(): void
    {
        $previous = config("security.request.max_body_bytes");
        config_set("security.request.max_body_bytes", 4);
        try {
            foreach (["GET", "HEAD", "POST"] as $method) {
                $request = Request::capture("", ["REQUEST_METHOD" => $method, "CONTENT_LENGTH" => ""]);
                self::assertSame("", $request->rawBody());
                self::assertNull($request->header("content-length"));
            }
            self::assertSame("1234", Request::capture("1234", ["CONTENT_LENGTH" => ""])->rawBody());
            self::assertSame("4", Request::capture("1234", ["HTTP_CONTENT_LENGTH" => "4", "CONTENT_LENGTH" => ""])->header("content-length"));
            foreach ([
                ["12345", ["CONTENT_LENGTH" => ""], 413],
                ["", ["CONTENT_LENGTH" => "", "HTTP_CONTENT_LENGTH" => "5"], 413],
                ["", ["CONTENT_LENGTH" => "", "HTTP_CONTENT_LENGTH" => ""], 400],
                ["", ["CONTENT_LENGTH" => " "], 400],
                ["", ["CONTENT_LENGTH" => false], 400],
            ] as [$body, $server, $status]) {
                try {
                    Request::capture($body, $server);
                    self::fail("Invalid body boundary was accepted.");
                } catch (HttpException $error) {
                    self::assertSame($status, $error->statusCode());
                }
            }
        } finally {
            config_set("security.request.max_body_bytes", $previous);
        }
    }

    public function testForwardedSchemeRequiresOneCanonicalValueFromTrustedIngress(): void
    {
        $server = $_SERVER;
        $environment = $_ENV;
        $_ENV["TRUSTED_PROXIES"] = "10.0.0.1";
        $_ENV["APP_URL"] = "http://example.test";
        try {
            foreach (["https" => true, "http" => false, "https, http" => false, "http, https" => false, "ftp" => false, "" => false] as $header => $secure) {
                $_SERVER = ["REMOTE_ADDR" => "10.0.0.1", "HTTP_X_FORWARDED_PROTO" => $header];
                self::assertSame($secure, app_request_is_secure());
            }
            $_SERVER = ["REMOTE_ADDR" => "198.51.100.7", "HTTP_X_FORWARDED_PROTO" => "https"];
            self::assertFalse(app_request_is_secure());
            $_SERVER["HTTPS"] = "on";
            self::assertTrue(app_request_is_secure());
        } finally { $_SERVER = $server; $_ENV = $environment; }
    }
}
