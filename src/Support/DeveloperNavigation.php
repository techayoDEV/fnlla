<?php

declare(strict_types=1);

namespace Fnlla\Php\Support;

/** Navigation visibility mirrors read permissions; controllers still enforce every request. */
final class DeveloperNavigation
{
    private const ITEMS = [
        ["Workspace", "workspace", "Project Kanban", "workspace", null, "workspace"],
        ["Workspace", "technical-debt", "Technical debt", "technical_debt", "operations.view", null],
        ["Project setup", "identity", "Project identity", "project_identity", null, null],
        ["Project setup", "access", "Access & security", "access", null, null],
        ["Project setup", "settings", "Panel settings", "settings", null, null],
        ["Operations", "debug", "Error monitor", "debug", "operations.view", null],
        ["Operations", "analytics", "Traffic analytics", "analytics", "operations.view", "analytics"],
        ["Operations", "heatmap", "Behavior heatmap", "heatmap", "operations.view", "heatmap"],
        ["Operations", "release-readiness", "Readiness & health", "release_readiness", "operations.view", null],
        ["Operations", "framework-updates", "Framework updates", "framework_updates", null, null],
        ["Operations", "project-logs", "Project logs", "project_logs", "operations.view", null],
        ["Operations", "integrations", "API hooks & AI", "integrations", "operations.view", null],
        ["Reference", "documentation", "Documentation & policy", "documentation", "policy.view", null],
    ];

    public static function groups(array $capabilities, callable $url): array
    {
        $groups = [];
        foreach (self::ITEMS as [$group, $key, $label, $route, $capability, $module]) {
            if ($capability !== null && !in_array($capability, $capabilities, true)) { continue; }
            if ($module !== null && !DeveloperModules::enabled($module)) { continue; }
            $groups[$group][$key] = ["label" => $label, "href" => $url("developer.panel." . $route)];
        }
        return $groups;
    }
}
