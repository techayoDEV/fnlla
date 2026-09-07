<?php

declare(strict_types=1);

namespace Fnlla\Php\Tests;

use Fnlla\Php\Application;
use Fnlla\Php\Container\Container;
use Fnlla\Php\Exceptions\ExceptionHandler;
use Fnlla\Php\Http\Request;
use PHPUnit\Framework\TestCase;

final class DocumentationBoundaryTest extends TestCase
{
    public function testRetiredDocumentationIsNotServedByTheApplication(): void
    {
        $previousContainer = $GLOBALS["fnlla_container"] ?? null;
        $previousLegacyContainer = $GLOBALS["fnlla_php_container"] ?? null;
        $previousSession = $_SESSION ?? [];
        $_SESSION = [];

        try {
            $container = new Container();
            $providers = [];
            foreach ((array) config("app.providers", []) as $providerClass) {
                $provider = new $providerClass($container);
                $provider->register();
                $providers[] = $provider;
            }
            foreach ($providers as $provider) { $provider->boot(); }
            $GLOBALS["fnlla_container"] = $container;
            $GLOBALS["fnlla_php_container"] = $container;
            $router = (static function (Container $container) {
                return require base_path("bootstrap/router.php");
            })($container);
            $application = new Application($router, $container, $container->make(ExceptionHandler::class));

            foreach (["/docs", "/docs/index.html", "/docs/assets/docs.css", "/docs/assets/docs.js", "/docs/README.md"] as $path) {
                $response = $application->handle(Request::capture("", [
                    "REQUEST_METHOD" => "GET",
                    "REQUEST_URI" => $path,
                    "HTTP_HOST" => "localhost",
                ], [], [], [], []));
                self::assertSame(404, $response->status(), $path);
                self::assertStringNotContainsString("FNLLA Documentation Map", $response->body());
            }
            self::assertSame(null, $router->routeByName("docs.home"));
            // Updated framework code must still boot older, project-owned layouts.
            self::assertFalse(has_local_docs_workspace());
        } finally {
            $GLOBALS["fnlla_container"] = $previousContainer;
            $GLOBALS["fnlla_php_container"] = $previousLegacyContainer;
            $_SESSION = $previousSession;
        }
    }
}
