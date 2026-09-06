<?php

declare(strict_types=1);

namespace Fnlla\Php\Tests;

use Fnlla\Php\Application;
use Fnlla\Php\Container\Container;
use Fnlla\Php\Exceptions\ExceptionHandler;
use Fnlla\Php\Http\Request;
use Fnlla\Php\Http\Response;
use Fnlla\Php\Http\RequestLifecycleObserver;
use Fnlla\Php\Http\Resources\JsonResource;
use Fnlla\Php\Routing\Router;
use PHPUnit\Framework\TestCase;

final class RequestLifecycleTest extends TestCase
{
    public function testMissingErrorViewHasFrameworkIndependentFallback(): void
    {
        $code = 'define("FNLLA_RUNTIME_SKIP_AUTO_GUARD", true); define("VIEW_ROOT", __DIR__ . "/missing-error-views"); require $argv[1];'
            . '$request = \\Fnlla\\Php\\Http\\Request::capture("", ["REQUEST_URI"=>"/broken","REQUEST_METHOD"=>"GET"]);'
            . '$response = (new \\Fnlla\\Php\\Exceptions\\ExceptionHandler())->render(new RuntimeException("private diagnostic"), $request);'
            . 'echo json_encode(["status"=>$response->status(), "body"=>$response->body()]);';
        $process = proc_open([PHP_BINARY, "-r", $code, base_path("tests/bootstrap.php")],
            [0 => ["pipe", "r"], 1 => ["pipe", "w"], 2 => ["pipe", "w"]], $pipes);
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        self::assertSame(0, proc_close($process), $errors);
        $response = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(500, $response["status"]);
        self::assertStringNotContainsString("private diagnostic", $response["body"]);
    }

    public function testObserverFailureDoesNotReplaceResponseAndStateResets(): void
    {
        $container = new Container();
        $observer = new class implements RequestLifecycleObserver {
            public int $resets = 0;
            public function begin(Request $request): void {}
            public function finish(Request $request, Response $response, float $durationMs): Response { throw new \RuntimeException("Synthetic metrics outage"); }
            public function reset(): void { $this->resets++; }
        };
        $container->instance(RequestLifecycleObserver::class, $observer);
        $router = new Router($container);
        $router->get("/ok", static fn (): string => "committed")->name("test.ok");
        $app = new Application($router, $container, new ExceptionHandler());
        foreach (["GET", "HEAD"] as $method) {
            $response = $app->handle(Request::capture("", ["REQUEST_URI" => "/ok", "REQUEST_METHOD" => $method]));
            self::assertSame(200, $response->status());
            self::assertSame($method === "HEAD" ? "" : "committed", $response->body());
            self::assertFalse(isset($_SERVER["FNLLA_ROUTE_NAME"]));
        }
        self::assertSame(2, $observer->resets);
    }

    public function testResourceSerializationFailureIsRenderedAsHttpError(): void
    {
        $container = new Container();
        $router = new Router($container);
        $router->get("/broken", static fn (): JsonResource => new class([]) extends JsonResource {
            public function toArray(): array { throw new \RuntimeException("Synthetic serializer failure"); }
        });
        $app = new Application($router, $container, new ExceptionHandler());
        $response = $app->handle(Request::capture("", ["REQUEST_URI" => "/broken", "REQUEST_METHOD" => "GET", "HTTP_ACCEPT" => "application/json"]));
        self::assertSame(500, $response->status());
        self::assertArrayHasKey("X-Request-Id", $response->headers());
    }
}
