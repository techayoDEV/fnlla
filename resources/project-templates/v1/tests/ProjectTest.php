<?php

declare(strict_types=1);

namespace App\Tests;

use Fnlla\Php\Application;
use Fnlla\Php\Container\Container;
use Fnlla\Php\Exceptions\ExceptionHandler;
use Fnlla\Php\Http\Request;
use Fnlla\Php\Routing\Router;
use PHPUnit\Framework\TestCase;

final class ProjectTest extends TestCase
{
    private array $config;
    private array $session;
    private Application $application;
    private mixed $previousContainer;

    protected function setUp(): void
    {
        $this->config = config();
        $this->session = $_SESSION ?? [];
        $this->previousContainer = $GLOBALS["fnlla_container"] ?? null;
        $_SESSION = [];
        config_set("maintenance.enabled", false);
        config_set("maintenance.username", "");
        config_set("maintenance.password", "");
        config_set("developer_access.enabled", true);
        config_set("developer_access.path", "/developer");
        config_set("developer_access.users", "");
        config_set("developer_access.setup_ui_enabled", true);
        config_set("developer_access.setup_ui_local_only", true);
        config_set("observability.metrics.enabled", false);
        config_set("modules", array_fill_keys(array_keys(\Fnlla\Php\Support\DeveloperModules::OPTIONS), true));
        $container = $this->freshContainer();
        $GLOBALS["fnlla_container"] = $container;
        $GLOBALS["fnlla_php_container"] = $container;
        $rebuildRouteCache = true;
        $router = require base_path("bootstrap/router.php");
        $container->instance(Router::class, $router);
        $this->application = new Application($router, $container, $container->make(ExceptionHandler::class));
    }

    protected function tearDown(): void
    {
        $GLOBALS["fnlla_config"] = $this->config;
        $GLOBALS["fnlla_php_config"] = $this->config;
        $_SESSION = $this->session;
        $GLOBALS["fnlla_container"] = $this->previousContainer;
        $GLOBALS["fnlla_php_container"] = $this->previousContainer;
    }

    public function testLocalUnconfiguredProjectOffersSetup(): void
    {
        $response = $this->application->handle(new Request("GET", "/", server: ["REMOTE_ADDR" => "127.0.0.1"]));
        self::assertSame(200, $response->status());
        self::assertStringContainsString('name="developer_setup_email"', $response->body());
        self::assertStringNotContainsString('name="fnlla_module_workspace"', $response->body());
        self::assertStringContainsString("Prepare the handoff in private.", $response->body());
        self::assertStringContainsString("Build from blueprint.", $response->body());
        self::assertStringContainsString("Framework created &amp; maintained by", $response->body());
        self::assertStringNotContainsString("Local first", $response->body());
        self::assertStringContainsString("Optional information", $response->body());
        self::assertStringNotContainsString("Optional responsibility information", $response->body());
        self::assertStringNotContainsString("Modules move to the panel", $response->body());
        self::assertStringNotContainsString("project-setup-flow", $response->body());
    }

    public function testDeveloperPanelRequiresAuthentication(): void
    {
        $response = $this->application->handle(new Request("GET", "/developer/panel"));
        self::assertSame(302, $response->status());
        self::assertFalse($_SESSION["developer.access_unlocked"] ?? false);
    }

    public function testSetupRejectsMissingCsrfWithoutCreatingAnAccount(): void
    {
        $response = $this->application->handle(new Request("POST", "/maintenance/setup-developer-access", server: ["REMOTE_ADDR" => "127.0.0.1"], headers: ["accept" => "application/json"]));
        self::assertSame(419, $response->status());
        self::assertSame("", config("developer_access.users"));
    }

    public function testApplicationHealthRouteResponds(): void
    {
        $response = $this->application->handle(new Request("GET", "/api/health"));
        self::assertSame(200, $response->status());
    }

    private function freshContainer(): Container
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

        return $container;
    }
}
