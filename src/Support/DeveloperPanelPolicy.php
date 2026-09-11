<?php

declare(strict_types=1);

namespace Fnlla\Php\Support;

final class DeveloperPanelPolicy
{
    private const REGULATED_PROFILES = [
        "regulated",
        "strict",
        "financial",
        "banking",
        "public_sector",
        "council",
        "education",
    ];

    public static function profile(): string
    {
        $profile = strtolower(trim((string) config("developer_control.policy_profile", "standard")));
        $profile = str_replace(["-", " "], "_", $profile);

        return preg_match('/^[a-z0-9_]{2,40}$/', $profile) === 1 ? $profile : "standard";
    }

    public static function regulatedMode(): bool
    {
        return (bool) config("developer_control.regulated_mode", false)
            || in_array(self::profile(), self::REGULATED_PROFILES, true);
    }

    public static function telemetryPolicy(): array
    {
        $regulated = self::regulatedMode();
        $retentionDays = max(1, (int) config("observability.analytics.retention_days", 90));
        $maxRegulatedRetention = max(1, (int) config("observability.regulated.max_retention_days", 30));

        if ($regulated) {
            $retentionDays = min($retentionDays, $maxRegulatedRetention);
        }

        return [
            "schema" => "fnlla.developer_telemetry_policy.v1",
            "profile" => self::profile(),
            "mode" => $regulated ? "regulated consent-only" : "privacy-light",
            "regulated" => $regulated,
            "consent_required" => true,
            "raw_ip_addresses" => false,
            "raw_user_agents" => false,
            "form_fields_recorded" => false,
            "query_strings_tracked" => $regulated ? false : (bool) config("observability.analytics.track_query_strings", false),
            "visitor_fingerprinting" => false,
            "external_analytics" => false,
            "retention_days" => $retentionDays,
            "excluded_paths" => self::listFromConfig("observability.regulated.excluded_paths", [
                "/developer",
                "/maintenance",
                "/client",
                "/api",
            ]),
            "heatmap_allowed" => !$regulated || (bool) config("observability.regulated.heatmap_enabled", false),
        ];
    }

    public static function frameworkApplyPolicy(): array
    {
        $strict = self::regulatedMode() || app_environment() === "production";
        $requirements = [
            [
                "key" => "dry_run",
                "label" => "Dry-run report retained",
                "met" => (bool) config("framework_update.dry_run_report_enabled", true),
                "evidence" => "storage/" . ltrim((string) config("framework_update.dry_run_report_path", "framework/updates/fnlla/dry-run-report.json"), "\\/"),
            ],
            [
                "key" => "backup",
                "label" => "Backup and restore evidence confirmed",
                "met" => !$strict || (bool) config("framework_update.apply_policy.backup_confirmed", false),
                "evidence" => "FRAMEWORK_UPDATE_APPLY_BACKUP_CONFIRMED",
            ],
            [
                "key" => "signed_artifact",
                "label" => "Signed or reviewed release artifact confirmed",
                "met" => !$strict || (bool) config("framework_update.apply_policy.signed_artifact_confirmed", false),
                "evidence" => "FRAMEWORK_UPDATE_APPLY_SIGNED_ARTIFACT_CONFIRMED",
            ],
            [
                "key" => "maintenance_window",
                "label" => "Maintenance window confirmed",
                "met" => !$strict || (bool) config("framework_update.apply_policy.maintenance_window_confirmed", false),
                "evidence" => "FRAMEWORK_UPDATE_APPLY_MAINTENANCE_WINDOW_CONFIRMED",
            ],
            [
                "key" => "ci_approval",
                "label" => "CI/CD approval confirmed",
                "met" => !$strict || (bool) config("framework_update.apply_policy.ci_approval_confirmed", false),
                "evidence" => "FRAMEWORK_UPDATE_APPLY_CI_APPROVAL_CONFIRMED",
            ],
        ];
        $missing = [];
        foreach ($requirements as $requirement) {
            if ($requirement["met"] !== true) {
                $missing[] = $requirement;
            }
        }
        $strictMissing = $strict ? $missing : [];

        return [
            "schema" => "fnlla.framework_apply_policy.v1",
            "profile" => self::profile(),
            "strict" => $strict,
            "regulated" => self::regulatedMode(),
            "production" => app_environment() === "production",
            "requirements" => $requirements,
            "missing" => $strictMissing,
            "allows_browser_apply" => $strictMissing === [],
            "message" => $strictMissing !== []
                ? "Browser apply requires policy, backup, signed-artifact, maintenance-window and CI/CD approval evidence in this environment."
                : "Browser apply policy is satisfied for this environment.",
        ];
    }

    public static function reviewItemMetadata(string $source, string $severity, string $kind, string $createdAt = "", string $key = ""): array
    {
        $created = self::dateFrom($createdAt);
        $severity = strtolower(trim($severity));
        $dueDays = match ($severity) {
            "critical" => 1,
            "warning" => 7,
            default => 30,
        };
        $expiryDays = $severity === "critical" ? 30 : 90;

        return [
            "id" => self::reviewItemId($source, $kind, $key, $createdAt),
            "owner" => self::reviewOwner($source, $kind),
            "due_at_utc" => $created->modify("+" . $dueDays . " days")->format(DATE_ATOM),
            "expires_at_utc" => $created->modify("+" . $expiryDays . " days")->format(DATE_ATOM),
            "decision_required" => $severity !== "success",
            "approval_capability" => self::approvalCapability($source),
        ];
    }

    private static function reviewOwner(string $source, string $kind): string
    {
        $source = strtolower($source);

        if (str_contains($source, "access") || str_contains($source, "security")) {
            return "Security reviewer";
        }

        if (str_contains($source, "framework") || str_contains($source, "readiness") || str_contains($source, "health")) {
            return "Operations engineer";
        }

        if (str_contains($source, "analytics") || str_contains($source, "heatmap")) {
            return "Operations engineer";
        }

        if (str_contains($source, "workspace") || $kind === "checklist") {
            return "Lead developer";
        }

        return "Lead developer";
    }

    private static function approvalCapability(string $source): string
    {
        $source = strtolower($source);

        if (str_contains($source, "framework")) {
            return "framework.update.apply";
        }

        if (str_contains($source, "access") || str_contains($source, "security")) {
            return "developer.security.manage";
        }

        if (str_contains($source, "readiness") || str_contains($source, "health")) {
            return "decision.approve";
        }

        return "decision.review";
    }

    private static function reviewItemId(string $source, string $kind, string $key, string $createdAt): string
    {
        $key = trim($key) !== "" ? $key : $source . "|" . $kind . "|" . $createdAt;

        return substr(hash("sha256", strtolower($source . "|" . $kind . "|" . $key)), 0, 24);
    }

    private static function dateFrom(string $value): \DateTimeImmutable
    {
        try {
            return trim($value) !== "" ? new \DateTimeImmutable($value) : new \DateTimeImmutable("now", new \DateTimeZone("UTC"));
        } catch (\Throwable) {
            return new \DateTimeImmutable("now", new \DateTimeZone("UTC"));
        }
    }

    private static function listFromConfig(string $key, array $default): array
    {
        $value = config($key, $default);
        $items = is_array($value)
            ? $value
            : explode(",", (string) $value);
        $normalized = [];

        foreach ($items as $item) {
            $item = trim((string) $item);
            if ($item !== "") {
                $normalized[$item] = $item;
            }
        }

        return array_values($normalized);
    }
}
