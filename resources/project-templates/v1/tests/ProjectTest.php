<?php

declare(strict_types=1);

namespace App\Tests;

use Fnlla\Php\Application;
use Fnlla\Php\Exceptions\ExceptionHandler;
use Fnlla\Php\Http\Request;
use Fnlla\Php\Routing\Router;
use PHPUnit\Framework\TestCase;

final class ProjectTest extends TestCase
{
    private array $config;
    private array $session;
    private Application $application;
    private Router $previousRouter;

    protected function setUp(): void
    {
        $this->config = config();
        $this->session = $_SESSION ?? [];
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
        $container = $GLOBALS["fnlla_container"];
        $this->previousRouter = $container->make(Router::class);
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
        $GLOBALS["fnlla_container"]->instance(Router::class, $this->previousRouter);
    }

    public function testLocalUnconfiguredProjectOffersSetup(): void
    {
        $response = $this->application->handle(new Request("GET", "/", server: ["REMOTE_ADDR" => "127.0.0.1"]));
        self::assertSame(200, $response->status());
        self::assertStringContainsString('name="developer_setup_email"', $response->body());
        self::assertStringContainsString('name="fnlla_module_workspace"', $response->body());
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
}
