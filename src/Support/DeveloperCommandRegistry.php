<?php

declare(strict_types=1);

namespace Fnlla\Php\Support;

final class DeveloperCommandRegistry
{
    private const DEFINITIONS = [
        [
            "id" => "review_queue",
            "label" => "Review queue",
            "link" => "notifications",
            "group" => "Operations",
            "description" => "Open unresolved decisions across setup, access and release work.",
            "aliases" => ["alerts", "notifications", "decisions", "queue"],
            "kind" => "Review",
            "priority" => 5,
            "capability" => "operations.view",
            "environment_policy" => "all",
        ],
        [
            "id" => "dashboard",
            "label" => "Dashboard",
            "link" => "overview",
            "group" => "Developer Panel",
            "description" => "Operational snapshot, alerts and project status.",
            "aliases" => ["home", "overview"],
            "kind" => "Open",
            "priority" => 10,
            "environment_policy" => "all",
        ],
        [
            "id" => "access_security",
            "label" => "Access & security",
            "link" => "access",
            "group" => "Operations",
            "description" => "Manage developer accounts, roles, 2FA and customer review access.",
            "aliases" => ["security", "access", "2fa", "totp", "mfa"],
            "kind" => "Configure",
            "priority" => 12,
            "environment_policy" => "all",
        ],
        [
            "id" => "readiness_health",
            "label" => "Readiness & health",
            "link" => "release_readiness",
            "group" => "Release & readiness",
            "description" => "Run the production gate for release, cache and runtime health.",
            "aliases" => ["release", "health", "gate"],
            "kind" => "Review",
            "priority" => 14,
            "capability" => "operations.view",
            "environment_policy" => "all",
        ],
        [
            "id" => "project_identity",
            "label" => "Project identity",
            "link" => "identity",
            "group" => "Workspace",
            "description" => "Confirm public name, URL, leadership and runtime posture.",
            "aliases" => ["setup", "name", "url", "leadership"],
            "kind" => "Configure",
            "priority" => 16,
            "environment_policy" => "all",
        ],
        [
            "id" => "project_work",
            "label" => "Project work",
            "link" => "workspace",
            "group" => "Workspace",
            "description" => "Open shared tasks, timeline, Gantt, debt and project changelog links.",
            "aliases" => ["tasks", "kanban", "timeline", "gantt", "debt", "changelog"],
            "kind" => "Open",
            "priority" => 17,
            "module" => "workspace",
            "environment_policy" => "all",
        ],
        [
            "id" => "technical_debt",
            "label" => "Technical debt",
            "link" => "technical_debt",
            "group" => "Project work",
            "description" => "Triage architecture debt before it becomes release risk.",
            "aliases" => ["debt", "architecture", "triage", "adr", "issue"],
            "kind" => "Triage",
            "priority" => 18,
            "capability" => "operations.view",
            "environment_policy" => "all",
        ],
        [
            "id" => "runtime_environment",
            "label" => "Runtime environment",
            "link" => "identity",
            "fragment" => "runtime-environment",
            "group" => "Workspace",
            "description" => "Switch development or production runtime posture.",
            "aliases" => ["app_env", "app_debug", "trusted hosts"],
            "kind" => "Configure",
            "priority" => 20,
            "capability" => "project.identity.write",
            "environment_policy" => "all",
        ],
        [
            "id" => "framework_updates",
            "label" => "Framework updates",
            "link" => "framework_updates",
            "group" => "Release & readiness",
            "description" => "Check official releases, run dry-runs and open the governed apply workflow.",
            "aliases" => ["update", "upgrade", "release", "dry-run", "apply"],
            "kind" => "Review",
            "priority" => 19,
            "capability" => "framework.update",
            "environment_policy" => "local_or_policy_approved",
        ],
        [
            "id" => "observability",
            "label" => "Observability",
            "link" => "operations",
            "fragment" => "developer-operations-observability",
            "group" => "Operations",
            "description" => "Open logs, runtime errors, public-only analytics and heatmap signals.",
            "aliases" => ["logs", "errors", "analytics", "heatmap", "telemetry"],
            "kind" => "Open",
            "priority" => 20,
            "capability" => "operations.view",
            "environment_policy" => "all",
        ],
        [
            "id" => "client_preview",
            "label" => "Client preview",
            "link" => "project_settings",
            "group" => "Operations",
            "description" => "Maintenance password and public service control.",
            "aliases" => ["maintenance", "preview"],
            "kind" => "Configure",
            "priority" => 21,
            "capability" => "preview.manage",
            "environment_policy" => "all",
        ],
        [
            "id" => "project_changelog",
            "label" => "Project changelog",
            "link" => "project_changelog",
            "group" => "Project work",
            "description" => "Open shared project changes recorded from Developer Panel work.",
            "aliases" => ["changes", "history", "handoff", "activity"],
            "kind" => "Review",
            "priority" => 22,
            "capability" => "operations.view",
            "environment_policy" => "all",
        ],
        [
            "id" => "api_hooks_ai",
            "label" => "Adapters & AI",
            "link" => "integrations",
            "group" => "Operations",
            "description" => "Configure consent-aware outbound adapters and AI providers.",
            "aliases" => ["integrations", "webhooks", "ai", "adapter", "api hooks"],
            "kind" => "Configure",
            "priority" => 23,
            "capability" => "operations.view",
            "environment_policy" => "all",
        ],
        [
            "id" => "project_logs",
            "label" => "Project logs",
            "link" => "project_logs",
            "group" => "Observability",
            "description" => "Inspect the developer activity and project operation audit trail.",
            "aliases" => ["audit", "activity", "history", "events"],
            "kind" => "Review",
            "priority" => 24,
            "capability" => "operations.view",
            "environment_policy" => "all",
        ],
        [
            "id" => "error_monitor",
            "label" => "Error monitor",
            "link" => "debug",
            "group" => "Observability",
            "description" => "Review runtime issue candidates and promote real work to the shared task board.",
            "aliases" => ["debug", "errors", "runtime", "exceptions"],
            "kind" => "Review",
            "priority" => 25,
            "capability" => "operations.view",
            "environment_policy" => "local",
        ],
        [
            "id" => "traffic_analytics",
            "label" => "Traffic analytics",
            "link" => "analytics",
            "group" => "Observability",
            "description" => "Review public-only aggregate traffic, conversion and consent signals.",
            "aliases" => ["metrics", "traffic", "routes", "consent"],
            "kind" => "Review",
            "priority" => 26,
            "capability" => "operations.view",
            "module" => "analytics",
            "environment_policy" => "public_only",
        ],
        [
            "id" => "behavior_heatmap",
            "label" => "Behavior heatmap",
            "link" => "heatmap",
            "group" => "Observability",
            "description" => "Review public-only aggregate click zones and scroll depth.",
            "aliases" => ["clicks", "scroll", "pages", "heatmap"],
            "kind" => "Review",
            "priority" => 27,
            "capability" => "operations.view",
            "module" => "heatmap",
            "environment_policy" => "public_only",
        ],
        [
            "id" => "my_todo",
            "label" => "My to-do",
            "link" => "private_todo",
            "group" => "Workspace",
            "description" => "Personal developer checklist outside shared project work.",
            "aliases" => ["todo", "private", "tasks", "checklist"],
            "kind" => "Capture",
            "priority" => 45,
            "capability" => "workspace.write",
            "module" => "workspace",
            "environment_policy" => "private",
        ],
        [
            "id" => "add_private_action",
            "label" => "Add private action",
            "link" => "private_todo",
            "fragment" => "developer-private-todo-capture",
            "group" => "Workspace",
            "description" => "Create a private reminder without adding shared project work.",
            "aliases" => ["todo", "private", "note"],
            "kind" => "Create",
            "priority" => 46,
            "capability" => "workspace.write",
            "module" => "workspace",
            "environment_policy" => "private",
        ],
        [
            "id" => "audit_json",
            "label" => "Export audit log",
            "link" => "audit_export",
            "group" => "Operations",
            "description" => "Download the JSON developer activity trail.",
            "aliases" => ["audit", "json", "download"],
            "kind" => "Export",
            "priority" => 30,
            "capability" => "audit.export",
            "environment_policy" => "all",
        ],
        [
            "id" => "audit_csv",
            "label" => "Export audit CSV",
            "link" => "audit_export_csv",
            "group" => "Operations",
            "description" => "Download the CSV developer activity trail.",
            "aliases" => ["audit", "csv", "download"],
            "kind" => "Export",
            "priority" => 31,
            "capability" => "audit.export",
            "environment_policy" => "all",
        ],
        [
            "id" => "developer_profile",
            "label" => "Developer profile",
            "link" => "profile",
            "group" => "Developer Panel",
            "description" => "Avatar, profile and account settings.",
            "aliases" => ["account", "password", "totp"],
            "kind" => "Configure",
            "priority" => 40,
            "capability" => "developer.profile.write",
            "environment_policy" => "all",
        ],
        [
            "id" => "panel_settings",
            "label" => "Panel settings",
            "link" => "settings",
            "group" => "Developer Panel",
            "description" => "Navigation, footer and storage controls.",
            "aliases" => ["settings", "panel"],
            "kind" => "Configure",
            "priority" => 42,
            "capability" => "panel.settings.write",
            "environment_policy" => "all",
        ],
        [
            "id" => "public_site",
            "label" => "Public website",
            "link" => "home",
            "group" => "Project",
            "description" => "Open the public application in a new tab.",
            "aliases" => ["preview", "site"],
            "kind" => "Open",
            "priority" => 80,
            "external" => true,
            "target" => "_blank",
            "environment_policy" => "all",
        ],
    ];

    public function items(array $developerLinks, array $capabilities, array $navigationGroups): array
    {
        $items = [];

        foreach (self::DEFINITIONS as $definition) {
            $this->add($items, $this->resolve($definition, $developerLinks, $capabilities));
        }

        foreach ($navigationGroups as $group => $groupItems) {
            foreach ((array) $groupItems as $item) {
                $this->addNavigationItem($items, (array) $item, (string) $group);

                foreach ((array) ($item["children"] ?? []) as $child) {
                    if (is_array($child)) {
                        $this->addNavigationItem($items, $child, (string) ($item["label"] ?? $group));
                    }
                }
            }
        }

        usort($items, static function (array $first, array $second): int {
            $priority = (int) ($first["priority"] ?? 50) <=> (int) ($second["priority"] ?? 50);
            if ($priority !== 0) {
                return $priority;
            }

            return strcmp((string) ($first["label"] ?? ""), (string) ($second["label"] ?? ""));
        });

        return $items;
    }

    private function addNavigationItem(array &$items, array $item, string $group): void
    {
        $this->add($items, $this->normalize([
            "id" => "nav_" . substr(hash("sha256", (string) ($item["href"] ?? "") . (string) ($item["label"] ?? "")), 0, 12),
            "label" => (string) ($item["label"] ?? ""),
            "href" => (string) ($item["href"] ?? ""),
            "group" => $group,
            "description" => "Open this Developer Panel section.",
            "aliases" => [],
            "kind" => "Open",
            "priority" => 60,
            "environment_policy" => "all",
            "capability" => "",
            "module" => "",
            "external" => false,
            "destructive" => false,
            "target" => "",
        ]));
    }

    private function resolve(array $definition, array $developerLinks, array $capabilities): ?array
    {
        $capability = (string) ($definition["capability"] ?? "");
        if ($capability !== "" && !in_array($capability, $capabilities, true)) {
            return null;
        }

        $module = (string) ($definition["module"] ?? "");
        if ($module !== "" && !DeveloperModules::enabled($module)) {
            return null;
        }

        $link = (string) ($definition["link"] ?? "");
        $href = (string) ($developerLinks[$link] ?? "");
        if ($href === "") {
            return null;
        }

        $fragment = trim((string) ($definition["fragment"] ?? ""));
        if ($fragment !== "") {
            $href .= "#" . ltrim($fragment, "#");
        }

        $definition["href"] = $href;

        return $this->normalize($definition);
    }

    private function normalize(array $item): ?array
    {
        $label = trim((string) ($item["label"] ?? ""));
        $href = trim((string) ($item["href"] ?? ""));
        $group = trim((string) ($item["group"] ?? ""));

        if ($label === "" || $href === "" || $group === "") {
            return null;
        }

        $aliases = array_values(array_filter((array) ($item["aliases"] ?? []), static fn (mixed $alias): bool => is_string($alias) && trim($alias) !== ""));
        $kind = trim((string) ($item["kind"] ?? "Open"));
        $description = trim((string) ($item["description"] ?? ""));
        $search = strtolower(trim(implode(" ", array_merge(
            [$label, $group, $kind, $description, $href, (string) ($item["capability"] ?? ""), (string) ($item["environment_policy"] ?? "")],
            $aliases
        ))));

        return [
            "id" => (string) ($item["id"] ?? substr(hash("sha256", $label . "|" . $href), 0, 12)),
            "label" => $label,
            "href" => $href,
            "group" => $group,
            "kind" => $kind !== "" ? $kind : "Open",
            "priority" => (int) ($item["priority"] ?? 50),
            "external" => (bool) ($item["external"] ?? false),
            "destructive" => (bool) ($item["destructive"] ?? false),
            "target" => (string) ($item["target"] ?? ""),
            "capability" => (string) ($item["capability"] ?? ""),
            "module" => (string) ($item["module"] ?? ""),
            "environment_policy" => (string) ($item["environment_policy"] ?? "all"),
            "description" => $description !== "" ? $description : $group,
            "aliases" => $aliases,
            "search" => $search,
        ];
    }

    private function add(array &$items, ?array $candidate): void
    {
        if ($candidate === null) {
            return;
        }

        $key = strtolower((string) ($candidate["label"] ?? "") . "|" . (string) ($candidate["href"] ?? "") . "|" . (string) ($candidate["group"] ?? ""));
        if (isset($items[$key]) && (int) ($items[$key]["priority"] ?? 50) <= (int) ($candidate["priority"] ?? 50)) {
            return;
        }

        $items[$key] = $candidate;
    }
}
