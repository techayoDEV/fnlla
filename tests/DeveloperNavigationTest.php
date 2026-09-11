<?php

declare(strict_types=1);

namespace Fnlla\Php\Tests;

use Fnlla\Php\Support\DeveloperCommandRegistry;
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
            self::assertSame("developer.panel.project_identity", $restricted["Workspace"]["identity"]["href"]);
            self::assertSame(
                ["identity-overview", "project-identity-details", "project-identity-runtime", "project-identity-leadership", "project-identity-access"],
                array_keys($restricted["Workspace"]["identity"]["children"])
            );
            self::assertFalse(isset($restricted["Workspace"]["workspace"]));
            self::assertFalse(isset($restricted["Operations"]["debug"]));
            self::assertFalse(isset($restricted["Reference"]));
            $operator = DeveloperNavigation::groups(["operations.view", "policy.view"], static fn (string $route): string => "/" . $route);
            self::assertSame("/developer.panel.project_identity", $operator["Workspace"]["identity"]["href"]);
            self::assertSame("/developer.panel.project_identity.runtime", $operator["Workspace"]["identity"]["children"]["project-identity-runtime"]["href"]);
            self::assertSame("Access & preview", $operator["Workspace"]["identity"]["children"]["project-identity-access"]["label"]);
            self::assertFalse(isset($operator["Workspace"]["technical-debt"]));
            self::assertFalse(isset($operator["Workspace"]["project-changelog"]));
            self::assertSame("Access & security", $operator["Operations"]["access"]["label"]);
            self::assertSame("Review queue", $operator["Operations"]["notifications"]["label"]);
            self::assertSame("Release & readiness", $operator["Operations"]["release-readiness"]["label"]);
            self::assertSame("Observability", $operator["Operations"]["observability"]["label"]);
            self::assertSame("/developer.panel.operations#developer-operations-observability", $operator["Operations"]["observability"]["href"]);
            self::assertSame(["release-readiness"], array_keys($operator["Operations"]["release-readiness"]["children"]));
            self::assertSame("Readiness & health", $operator["Operations"]["release-readiness"]["children"]["release-readiness"]["label"]);
            self::assertSame(["project-logs", "debug", "analytics", "heatmap"], array_keys($operator["Operations"]["observability"]["children"]));
            self::assertSame("Traffic analytics", $operator["Operations"]["observability"]["children"]["analytics"]["label"]);
            self::assertSame("Behavior heatmap", $operator["Operations"]["observability"]["children"]["heatmap"]["label"]);
            self::assertSame("Adapters & AI", $operator["Operations"]["integrations"]["label"]);
            self::assertSame("Documentation & policy", $operator["Reference"]["documentation"]["label"]);
            self::assertSame(["identity"], array_keys($operator["Workspace"]));
            $expectedOperationsOrder = [
                "access",
                "notifications",
                "release-readiness",
                "observability",
                "integrations",
            ];
            self::assertSame(
                array_values(array_filter($expectedOperationsOrder, static fn (string $key): bool => isset($operator["Operations"][$key]))),
                array_keys($operator["Operations"])
            );
            self::assertFalse(isset($operator["Operations"]["project-changelog"]));
            self::assertFalse(isset($operator["Operations"]["analytics"]));
            self::assertFalse(isset($operator["Operations"]["heatmap"]));
            self::assertFalse(isset($operator["Operations"]["debug"]));
            self::assertFalse(isset($operator["Workspace"]["project-work"]));
            self::assertFalse(isset($operator["Workspace"]["private-todo"]));
            config_set("modules.workspace", true);
            $workspace = DeveloperNavigation::groups(["operations.view"], static fn (string $route): string => "/" . $route);
            self::assertSame("/developer.panel.workspace", $workspace["Workspace"]["project-work"]["href"]);
            self::assertSame("Project work", $workspace["Workspace"]["project-work"]["label"]);
            self::assertSame(["workspace", "technical-debt", "project-changelog"], $workspace["Workspace"]["project-work"]["active_aliases"]);
            self::assertSame(["workspace", "technical-debt", "project-changelog"], array_keys($workspace["Workspace"]["project-work"]["children"]));
            self::assertSame("Tasks", $workspace["Workspace"]["project-work"]["children"]["workspace"]["label"]);
            self::assertSame("/developer.panel.workspace", $workspace["Workspace"]["project-work"]["children"]["workspace"]["href"]);
            self::assertSame("/developer.panel.technical_debt", $workspace["Workspace"]["project-work"]["children"]["technical-debt"]["href"]);
            self::assertSame("/developer.panel.changelog", $workspace["Workspace"]["project-work"]["children"]["project-changelog"]["href"]);
            self::assertFalse(isset($workspace["Project setup"]));
            self::assertFalse(isset($workspace["Workspace"]["private-todo"]));
            self::assertSame("Observability", $workspace["Operations"]["observability"]["label"]);
            self::assertContains("analytics", $workspace["Operations"]["observability"]["active_aliases"]);
            self::assertSame("/developer.panel.analytics", $workspace["Operations"]["observability"]["children"]["analytics"]["href"]);
            self::assertSame("/developer.panel.heatmap", $workspace["Operations"]["observability"]["children"]["heatmap"]["href"]);
            self::assertFalse(isset($workspace["Operations"]["release-readiness"]["children"]["framework-updates"]));

            $releaseDeveloper = DeveloperNavigation::groups(["operations.view", "framework.update"], static fn (string $route): string => "/" . $route);
            self::assertSame(["release-readiness", "framework-updates"], array_keys($releaseDeveloper["Operations"]["release-readiness"]["children"]));
            self::assertSame("/developer.panel.framework_updates", $releaseDeveloper["Operations"]["release-readiness"]["children"]["framework-updates"]["href"]);
        } finally { config_set("modules", $saved); }
    }

    public function testCommandRegistryAddsCapabilityAndPolicyMetadata(): void
    {
        $saved = config("modules");
        try {
            config_set("modules.workspace", true);
            $links = [
                "overview" => "/developer/panel",
                "notifications" => "/developer/panel/notifications",
                "access" => "/developer/panel/access",
                "release_readiness" => "/developer/panel/release-readiness",
                "identity" => "/developer/panel/project-identity",
                "technical_debt" => "/developer/panel/technical-debt",
                "debug" => "/developer/panel/debug",
                "framework_updates" => "/developer/panel/framework-updates",
                "integrations" => "/developer/panel/integrations",
                "operations" => "/developer/panel/operations",
                "workspace" => "/developer/panel/tasks",
                "project_logs" => "/developer/panel/project-logs",
                "analytics" => "/developer/panel/analytics",
                "heatmap" => "/developer/panel/heatmap",
                "audit_export" => "/developer/panel/operations/audit-export",
                "audit_export_csv" => "/developer/panel/operations/audit-export.csv",
                "project_changelog" => "/developer/panel/changelog",
                "profile" => "/developer/panel/profile",
                "settings" => "/developer/panel/settings",
                "home" => "/",
                "private_todo" => "/developer/panel/my-tasks",
                "runtime_environment" => "/developer/panel/project-identity/runtime",
                "project_settings" => "/developer/panel/project-identity/access-preview",
            ];
            $registry = new DeveloperCommandRegistry();
            $items = $registry->items($links, ["operations.view", "workspace.write", "framework.update"], []);
            $labels = array_column($items, "label");

            self::assertContains("Review queue", $labels);
            self::assertContains("Framework updates", $labels);
            self::assertContains("Project changelog", $labels);
            self::assertContains("Project logs", $labels);
            self::assertContains("Error monitor", $labels);
            self::assertContains("Traffic analytics", $labels);
            self::assertContains("Behavior heatmap", $labels);
            self::assertContains("Adapters & AI", $labels);
            self::assertContains("Project work", $labels);
            self::assertContains("My Tasks", $labels);
            self::assertNotContains("Export audit log", $labels);

            $changelog = array_values(array_filter($items, static fn (array $item): bool => ($item["id"] ?? "") === "project_changelog"))[0] ?? [];
            self::assertSame("Project work", $changelog["group"] ?? null);

            $framework = array_values(array_filter($items, static fn (array $item): bool => ($item["id"] ?? "") === "framework_updates"))[0] ?? [];
            self::assertSame("framework.update", $framework["capability"] ?? null);
            self::assertSame("Release & readiness", $framework["group"] ?? null);
            self::assertSame("local_or_policy_approved", $framework["environment_policy"] ?? null);
            self::assertStringContainsString("dry-run", (string) ($framework["search"] ?? ""));
        } finally {
            config_set("modules", $saved);
        }
    }
}
