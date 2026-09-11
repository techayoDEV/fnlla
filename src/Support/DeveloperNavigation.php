<?php

declare(strict_types=1);

namespace Fnlla\Php\Support;

/** Navigation visibility mirrors read permissions; controllers still enforce every request. */
final class DeveloperNavigation
{
    private const ITEMS = [
        [
            "group" => "Workspace",
            "key" => "identity",
            "label" => "Project identity",
            "route" => "project_identity",
            "active_aliases" => ["identity", "project-identity-details", "project-identity-runtime", "project-identity-leadership", "project-identity-access"],
            "children" => [
                ["key" => "identity-overview", "label" => "Overview", "route" => "project_identity"],
                ["key" => "project-identity-details", "label" => "Identity", "route" => "project_identity.identity"],
                ["key" => "project-identity-runtime", "label" => "Runtime", "route" => "project_identity.runtime"],
                ["key" => "project-identity-leadership", "label" => "Leadership", "route" => "project_identity.leadership"],
                ["key" => "project-identity-access", "label" => "Access & preview", "route" => "project_identity.access"],
            ],
        ],
        [
            "group" => "Workspace",
            "key" => "project-work",
            "label" => "Project work",
            "route" => "workspace",
            "module" => "workspace",
            "active_aliases" => ["workspace", "technical-debt", "project-changelog"],
            "children" => [
                ["key" => "workspace", "label" => "Tasks", "route" => "workspace"],
                ["key" => "technical-debt", "label" => "Technical debt", "route" => "technical_debt", "capability" => "operations.view"],
                ["key" => "project-changelog", "label" => "Project changelog", "route" => "changelog", "capability" => "operations.view"],
            ],
        ],
        ["group" => "Operations", "key" => "access", "label" => "Access & security", "route" => "access"],
        ["group" => "Operations", "key" => "notifications", "label" => "Review queue", "route" => "notifications", "capability" => "operations.view"],
        [
            "group" => "Operations",
            "key" => "release-readiness",
            "label" => "Release & readiness",
            "route" => "release_readiness",
            "capability" => "operations.view",
            "active_aliases" => ["framework-updates", "health"],
            "children" => [
                ["key" => "release-readiness", "label" => "Readiness & health", "route" => "release_readiness", "capability" => "operations.view"],
                ["key" => "framework-updates", "label" => "Framework updates", "route" => "framework_updates", "capability" => "framework.update"],
            ],
        ],
        [
            "group" => "Operations",
            "key" => "observability",
            "label" => "Observability",
            "route" => "operations",
            "capability" => "operations.view",
            "fragment" => "developer-operations-observability",
            "active_aliases" => ["operations", "debug", "project-logs", "analytics", "heatmap"],
            "children" => [
                ["key" => "project-logs", "label" => "Project logs", "route" => "project_logs", "capability" => "operations.view"],
                ["key" => "debug", "label" => "Error monitor", "route" => "debug", "capability" => "operations.view"],
                ["key" => "analytics", "label" => "Traffic analytics", "route" => "analytics", "capability" => "operations.view", "module" => "analytics"],
                ["key" => "heatmap", "label" => "Behavior heatmap", "route" => "heatmap", "capability" => "operations.view", "module" => "heatmap"],
            ],
        ],
        ["group" => "Operations", "key" => "integrations", "label" => "Adapters & AI", "route" => "integrations", "capability" => "operations.view"],
        ["group" => "Reference", "key" => "documentation", "label" => "Documentation & policy", "route" => "documentation", "capability" => "policy.view", "active_aliases" => ["policy", "about"]],
    ];

    public static function groups(array $capabilities, callable $url): array
    {
        $groups = [];
        foreach (self::ITEMS as $item) {
            if (!self::visible($item, $capabilities)) {
                continue;
            }

            $group = (string) $item["group"];
            $key = (string) $item["key"];
            $groups[$group][$key] = self::normalise($item, $url);

            $children = [];
            foreach ((array) ($item["children"] ?? []) as $child) {
                if (!self::visible($child, $capabilities)) {
                    continue;
                }

                $children[(string) $child["key"]] = self::normalise($child, $url);
            }

            if ($children !== []) {
                $groups[$group][$key]["children"] = $children;
            }
        }

        return $groups;
    }

    private static function visible(array $item, array $capabilities): bool
    {
        $capability = $item["capability"] ?? null;
        $module = $item["module"] ?? null;

        if (is_string($capability) && !in_array($capability, $capabilities, true)) {
            return false;
        }

        if (is_string($module) && !DeveloperModules::enabled($module)) {
            return false;
        }

        return true;
    }

    private static function normalise(array $item, callable $url): array
    {
        $href = $url("developer.panel." . (string) $item["route"]);
        $fragment = trim((string) ($item["fragment"] ?? ""));

        if ($fragment !== "") {
            $href .= "#" . ltrim($fragment, "#");
        }

        return [
            "key" => (string) $item["key"],
            "label" => (string) $item["label"],
            "href" => $href,
            "active_aliases" => array_values(array_filter((array) ($item["active_aliases"] ?? []), static fn (mixed $alias): bool => is_string($alias) && trim($alias) !== "")),
        ];
    }
}
