<?php

declare(strict_types=1);

namespace Fnlla\Php\Support;

final class DeveloperPanelLabels
{
    public static function status(string $status, string $fallback = "Not configured"): string
    {
        $normalized = strtolower(trim($status));

        return match ($normalized) {
            "active" => "Active",
            "available_opt_in" => "Available opt-in",
            "configured" => "Configured",
            "disabled", "off" => "Disabled",
            "needs configuration" => "Needs configuration",
            "ready" => "Ready",
            "removed" => "Not included",
            default => $normalized !== "" ? self::humanizeIdentifier($normalized) : $fallback,
        };
    }

    public static function gate(string $gate): string
    {
        $normalized = strtolower(trim($gate));

        return match ($normalized) {
            "fnlla:cookies-updated" => "Cookie-consent event",
            "server-side policy" => "Server policy",
            "manual", "" => "Manual approval",
            default => self::humanizeIdentifier($normalized),
        };
    }

    public static function event(string $event): string
    {
        $normalized = strtolower(trim($event));

        return match ($normalized) {
            "fnlla:analytics-consent-granted" => "Analytics consent granted",
            "fnlla:cookies-updated" => "Cookie consent updated",
            default => $normalized !== "" ? self::humanizeIdentifier($normalized) : "Consent signal",
        };
    }

    public static function contract(string $schema, string $fallback = "Stable framework contract"): string
    {
        return match (trim($schema)) {
            "fnlla.behavior_event.v1" => "Behavior event payload",
            "fnlla.developer_heatmap.v1" => "Heatmap report payload",
            "fnlla.remote_control_plugin.v1" => "Adapter manifest",
            "fnlla.project_leadership.v1" => "Project leadership record",
            "fnlla.techayo_remote_control.v1" => "Signed control request",
            "fnlla.techayo_remote_control_state.v1", "fnlla.techayo_remote_control_state.v2" => "Runtime state response",
            "fnlla.developer_control_state.v2" => "Developer control state",
            "fnlla.developer_policy_boundary.v1" => "Developer policy boundary",
            default => $fallback,
        };
    }

    public static function capability(string $capability): string
    {
        $normalized = strtolower(trim($capability));

        return match ($normalized) {
            "audit.export" => "Export audit trail",
            "decision.approve" => "Approve decisions",
            "decision.review" => "Review decisions",
            "developer.accounts.write" => "Manage developer accounts",
            "developer.profile.write" => "Edit own developer profile",
            "developer.security.manage" => "Manage developer security",
            "framework.update" => "Check framework updates",
            "framework.update.apply" => "Apply framework updates",
            "operations.view" => "View operations",
            "panel.view" => "Open Developer Panel",
            "panel.settings.write" => "Change panel settings",
            "policy.view" => "View policy boundary",
            "preview.manage" => "Manage client preview",
            "project.identity.write" => "Change project identity",
            "service_control.write" => "Control public service",
            "workspace.write" => "Edit workspace",
            default => self::humanizeIdentifier($normalized),
        };
    }

    private static function humanizeIdentifier(string $value): string
    {
        $label = trim(str_replace([":", ".", "_", "-"], " ", $value));
        $label = preg_replace('/\s+/', " ", $label) ?: $value;

        return ucwords($label);
    }
}
