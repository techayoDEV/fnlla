<?php

declare(strict_types=1);

namespace Fnlla\Php\Tests;

use Fnlla\Php\Http\Request;
use Fnlla\Php\Http\HttpException;
use Fnlla\Php\Http\Response;
use Fnlla\Php\Middleware\RequireDeveloperModule;
use Fnlla\Php\Routing\Router;
use Fnlla\Php\Support\DeveloperModules;
use PHPUnit\Framework\TestCase;

final class DeveloperModulesTest extends TestCase
{
    public function testModuleInputsUseAnAllowlistAndRequireExplicitOptIn(): void
    {
        $values = DeveloperModules::environmentValues([
            "fnlla_module_workspace" => "1", "fnlla_module_analytics" => ["1"],
            "FNLLA_MODULE_CUSTOMER_PORTAL" => "1", "APP_DEBUG" => "true",
        ]);
        self::assertSame([
            "FNLLA_MODULE_WORKSPACE" => true, "FNLLA_MODULE_ANALYTICS" => false,
            "FNLLA_MODULE_HEATMAP" => false, "FNLLA_MODULE_CUSTOMER_PORTAL" => false,
        ], $values);
        self::assertTrue(DeveloperModules::environmentValues(["fnlla_module_heatmap" => "1"])["FNLLA_MODULE_ANALYTICS"]);
        $saved = config("modules");
        try {
            DeveloperModules::applyEnvironmentValues($values);
            self::assertTrue(DeveloperModules::enabled("workspace"));
            self::assertFalse(DeveloperModules::enabled("analytics"));
        } finally { config_set("modules", $saved); }
    }

    public function testModuleDecisionsRemainLiveAfterRouteCaching(): void
    {
        $saved = config("modules");
        try {
            $router = new Router($GLOBALS["fnlla_container"]);
            $router->get("/optional", [ModuleFixtureController::class, "show"])
                ->name("customer.panel.kanban")->middleware(RequireDeveloperModule::class);
            $cached = new Router($GLOBALS["fnlla_container"]);
            $cached->loadCachedRoutes($router->exportCache());
            config_set("modules.customer_portal", true);
            config_set("modules.workspace", true);
            self::assertSame(200, $cached->dispatch(new Request("GET", "/optional"))->status());
            foreach (["workspace", "customer_portal"] as $module) {
                config_set("modules.workspace", true);
                config_set("modules.customer_portal", true);
                config_set("modules." . $module, false);
                try {
                    $cached->dispatch(new Request("GET", "/optional"));
                    self::fail("Disabled cached module was dispatched.");
                } catch (HttpException $error) {
                    self::assertSame(404, $error->statusCode());
                }
            }
            self::assertFalse(DeveloperModules::enabled("unknown"));
            self::assertSame([], DeveloperModules::forRoute("developer.panel.debug"));
        } finally {
            config_set("modules", $saved);
        }
    }
}

final class ModuleFixtureController
{
    public function show(): Response
    {
        return Response::html("available");
    }
}
