<?php

declare(strict_types=1);

namespace Fnlla\Php\Tests\Interop;

use Fnlla\Php\Application;
use Fnlla\Php\Container\Container;
use Fnlla\Php\Exceptions\ExceptionHandler;
use Fnlla\Php\Http\Request;
use Fnlla\Php\Http\Response;
use Fnlla\Php\Routing\Router;
use Fnlla\Psr\HttpHandler;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;

final class HttpHandlerTest extends TestCase
{
    public function testBodyAttributesCookiesAndRepeatedResponseHeadersRoundTrip(): void
    {
        $container = new Container();
        $router = new Router($container);
        $router->post("/echo", static fn (Request $r): Response => Response::json([
            "value" => $r->input("value"), "identity" => $r->attribute("identity"),
            "cookie" => $r->cookie("session"), "authorization" => $r->header("authorization"),
        ], 201, ["Set-Cookie" => ["first=1; HttpOnly", "second=2; HttpOnly"]]));
        $factory = new Psr17Factory();
        $handler = new HttpHandler(new Application($router, $container, new ExceptionHandler()), $factory, $factory);
        $body = $factory->createStream('{"value":42}');
        $body->seek(3);
        $request = $factory->createServerRequest("POST", "https://example.test/echo", ["HTTP_AUTHORIZATION" => "stale"])
            ->withHeader("Content-Type", "application/json")->withBody($body)
            ->withAttribute("identity", "alice")->withCookieParams(["session" => "test"]);
        $response = $handler->handle($request);
        self::assertSame(201, $response->getStatusCode());
        self::assertSame(["value" => 42, "identity" => "alice", "cookie" => "test", "authorization" => null],
            json_decode((string) $response->getBody(), true));
        self::assertCount(2, $response->getHeader("Set-Cookie"));
        self::assertNotSame("", $response->getHeaderLine("X-Request-Id"));
        self::assertSame(3, $body->tell());
    }

    public function testUploadsAndHeadErrorsAreBounded(): void
    {
        $container = new Container();
        $router = new Router($container);
        $router->post("/upload", static fn (Request $r): array => ["name" => $r->file("group")["file"]->originalName(),
            "size" => $r->file("group")["file"]->size(), "valid" => $r->file("group")["file"]->isValid()]);
        $factory = new Psr17Factory();
        $handler = new HttpHandler(new Application($router, $container, new ExceptionHandler()), $factory, $factory);
        $file = $factory->createUploadedFile($factory->createStream("hello"), 5, UPLOAD_ERR_OK, "hello.txt", "text/plain");
        $request = $factory->createServerRequest("POST", "/upload")->withUploadedFiles(["group" => ["file" => $file]]);
        $response = $handler->handle($request);
        self::assertSame(["name" => "hello.txt", "size" => 5, "valid" => true], json_decode((string) $response->getBody(), true));
        $previous = config("security.request.max_body_bytes");
        config_set("security.request.max_body_bytes", 2);
        try {
            $response = $handler->handle($factory->createServerRequest("HEAD", "/upload")
                ->withHeader("Accept", "application/json")->withBody($factory->createStream("long")));
            self::assertSame(413, $response->getStatusCode());
            self::assertSame("", (string) $response->getBody());
        } finally { config_set("security.request.max_body_bytes", $previous); }
    }
}
