<?php

declare(strict_types=1);

namespace Fnlla\Php\Tests;

use Fnlla\Php\Support\DeveloperNavigation;
use PHPUnit\Framework\TestCase;

final class DeveloperNavigationTest extends TestCase
{
    public function testNavigationUsesCapabilitiesAndModuleFlags(): void
    {
        $saved = config("modules");
        try {
            config_set("modules.workspace", false);
            $restricted = DeveloperNavigation::groups([], static fn (string $route): string => $route);
            self::assertFalse(isset($restricted["Workspace"]));
            self::assertFalse(isset($restricted["Operations"]["debug"]));
            self::assertFalse(isset($restricted["Reference"]));
            $operator = DeveloperNavigation::groups(["operations.view", "policy.view"], static fn (string $route): string => "/" . $route);
            self::assertSame("/developer.panel.debug", $operator["Operations"]["debug"]["href"]);
            self::assertTrue(isset($operator["Workspace"]["technical-debt"]));
            self::assertFalse(isset($operator["Workspace"]["workspace"]));
            self::assertFalse(isset($operator["Workspace"]["private-todo"]));
            config_set("modules.workspace", true);
            $workspace = DeveloperNavigation::groups(["operations.view"], static fn (string $route): string => "/" . $route);
            self::assertSame("/developer.panel.workspace", $workspace["Workspace"]["workspace"]["href"]);
            self::assertFalse(isset($workspace["Workspace"]["private-todo"]));
            self::assertSame("Error monitor", $workspace["Operations"]["debug"]["label"]);
        } finally { config_set("modules", $saved); }
    }
}
