<?php

declare(strict_types=1);

namespace Fnlla\Php\Controllers;

use Fnlla\Php\Http\Request;
use Fnlla\Php\Http\Response;
use Fnlla\Php\Http\UploadedFile;
use Fnlla\Php\Mail\Mailer;
use Fnlla\Php\Maintenance\CustomerAccessManager;
use Fnlla\Php\Maintenance\DeveloperActivityLog;
use Fnlla\Php\Maintenance\DeveloperAccessManager;
use Fnlla\Php\Maintenance\DeveloperControlManager;
use Fnlla\Php\Maintenance\MaintenanceAccessManager;
use Fnlla\Php\Support\DeveloperAnalyticsReport;
use Fnlla\Php\Support\DeveloperHeatmapReport;
use Fnlla\Php\Support\DeveloperNotificationCenter;
use Fnlla\Php\Support\DeveloperOperationsReport;
use Fnlla\Php\Support\DeveloperPanelPolicy;
use Fnlla\Php\Support\DeveloperPrivateTodo;
use Fnlla\Php\Support\DeveloperWorkspaceBoard;
use Fnlla\Php\Support\EnvironmentFileManager;
use Fnlla\Php\Support\FrameworkIdentity;
use Fnlla\Php\Support\Logger;
use Fnlla\Php\Support\ProjectLeadership;
use Fnlla\Php\Validation\ValidationException;

abstract class DeveloperPanelController extends Controller
{
    protected function projectSettings(?array $leadership = null): array
    {
        return [
            "name" => (string) config("app.name", "FNLLA Project"),
            "tagline" => (string) config("app.tagline", ""),
            "url" => (string) config("app.base_url", ""),
            "runtime_environment" => $this->runtimeEnvironmentSettings(),
            "leadership" => $leadership ?? project_leadership("admin"),
        ];
    }

    protected function developerDashboard(array $developerAccess, array $maintenanceAccess, ?array $leadership = null): array
    {
        $versions = $this->fnllaVersionFacts();
        $runtimeEnvironment = $this->runtimeEnvironmentSettings();

        return [
            "environment" => $runtimeEnvironment["environment"],
            "runtime_environment" => $runtimeEnvironment,
            "project_name" => (string) config("app.name", "FNLLA Project"),
            "project_tagline" => (string) config("app.tagline", ""),
            "project_url" => (string) config("app.base_url", ""),
            "project_leadership" => $leadership ?? project_leadership("admin"),
            "maintenance_enabled" => (bool) ($maintenanceAccess["enabled"] ?? false),
            "maintenance_configured" => (bool) ($maintenanceAccess["configured"] ?? false),
            "developer_session_minutes" => (int) ($developerAccess["unlock_ttl_minutes"] ?? 120),
            "developer_absolute_minutes" => (int) ($developerAccess["absolute_ttl_minutes"] ?? 480),
            "developer_nav_mode" => (string) ($developerAccess["operations_nav_mode"] ?? "hidden"),
            "framework_version" => $versions["framework_version"],
            "runtime_version" => $versions["runtime_version"],
            "framework_lock" => is_file(base_path(".fnlla/framework-lock.json")),
            "storage_writable" => is_dir(storage_path()) && is_writable(storage_path()),
            "session_storage_writable" => is_dir(storage_path("framework/sessions")) && is_writable(storage_path("framework/sessions")),
            "queue_storage_writable" => is_dir(storage_path("framework/queue")) && is_writable(storage_path("framework/queue")),
            "observability_enabled" => (bool) config("observability.metrics.enabled", false),
        ];
    }

    protected function fnllaVersionFacts(): array
    {
        return [
            "framework_version" => $this->readVersionFile(base_path("VERSION")) ?? "unknown",
            "runtime_version" => $this->readVersionFile(public_path("vendor/fnlla-runtime/VERSION")) ?? "unknown",
        ];
    }

    protected function runtimeEnvironmentSettings(): array
    {
        $environment = strtolower(trim(app_environment()));
        $mode = $environment === "production" ? "production" : "development";
        $debugEnabled = app_debug();
        $debugToolbarEnabled = (bool) config("debug.toolbar", false);
        $requestHistoryEnabled = (bool) config("debug.history.enabled", false);
        $trustedHosts = $this->normalizeRuntimeTrustedHosts((array) config("security.trusted_hosts", []));
        $appUrl = trim((string) config("app.base_url", ""));
        $appUrlIsHttps = str_starts_with(strtolower($appUrl), "https://");
        $diagnosticsSafe = !$debugEnabled && !$debugToolbarEnabled && !$requestHistoryEnabled;
        $productionReady = $mode !== "production" || ($diagnosticsSafe && $appUrlIsHttps && $trustedHosts !== []);

        return [
            "environment" => $environment !== "" ? $environment : $mode,
            "mode" => $mode,
            "label" => ucfirst($mode),
            "debug_enabled" => $debugEnabled,
            "debug_toolbar_enabled" => $debugToolbarEnabled,
            "request_history_enabled" => $requestHistoryEnabled,
            "trusted_hosts" => $trustedHosts,
            "trusted_hosts_value" => implode(",", $trustedHosts),
            "app_url" => $appUrl,
            "app_url_https" => $appUrlIsHttps,
            "diagnostics_safe" => $diagnosticsSafe,
            "production_ready" => $productionReady,
            "checks" => [
                [
                    "label" => "APP_DEBUG",
                    "value" => $debugEnabled ? "On" : "Off",
                    "ready" => $mode !== "production" || !$debugEnabled,
                    "text" => $mode === "production" ? "Must be off in production." : "Detailed errors are allowed during development.",
                ],
                [
                    "label" => "Debug tools",
                    "value" => ($debugToolbarEnabled || $requestHistoryEnabled) ? "On" : "Off",
                    "ready" => $mode !== "production" || (!$debugToolbarEnabled && !$requestHistoryEnabled),
                    "text" => "Toolbar and request history are forced off when production is selected here.",
                ],
                [
                    "label" => "APP_URL",
                    "value" => $appUrlIsHttps ? "HTTPS" : ($appUrl !== "" ? "HTTP/local" : "Not set"),
                    "ready" => $mode !== "production" || $appUrlIsHttps,
                    "text" => "Production deployments should use an HTTPS public URL.",
                ],
                [
                    "label" => "TRUSTED_HOSTS",
                    "value" => $trustedHosts !== [] ? (string) count($trustedHosts) : "None",
                    "ready" => $mode !== "production" || $trustedHosts !== [],
                    "text" => "Production deployments should pin the accepted host names.",
                ],
            ],
        ];
    }

    /**
     * @param string|array<array-key, mixed> $hosts
     * @return list<string>
     */
    protected function normalizeRuntimeTrustedHosts(string|array $hosts): array
    {
        $raw = is_array($hosts)
            ? implode(",", array_map(static fn (mixed $host): string => (string) $host, $hosts))
            : $hosts;
        $entries = preg_split('/[\s,;]+/', $raw) ?: [];
        $normalized = [];

        foreach ($entries as $entry) {
            $host = strtolower(trim((string) $entry));

            if ($host === "" || str_contains($host, "\r") || str_contains($host, "\n") || str_contains($host, "\0")) {
                continue;
            }

            if (str_contains($host, "://")) {
                $parsedHost = parse_url($host, PHP_URL_HOST);
                $host = is_string($parsedHost) ? strtolower(trim($parsedHost)) : "";
            }

            if ($host === "") {
                continue;
            }

            $host = explode("/", $host, 2)[0];

            if (str_starts_with($host, "[")) {
                $end = strpos($host, "]");
                $host = $end === false ? "" : substr($host, 0, $end + 1);
            } elseif (substr_count($host, ":") === 1 && preg_match('/:\d+$/', $host) === 1) {
                $host = (string) preg_replace('/:\d+$/', "", $host);
            }

            $host = trim($host, ". \t\n\r\0\x0B");

            if (
                $host === ""
                || strlen($host) > 120
                || (
                    $host !== "*"
                    && preg_match('/^\*\.[a-z0-9.-]+$/', $host) !== 1
                    && preg_match('/^[a-z0-9.-]+$/', $host) !== 1
                    && preg_match('/^\[[a-f0-9:]+\]$/', $host) !== 1
                )
            ) {
                continue;
            }

            $normalized[$host] = $host;
        }

        return array_values($normalized);
    }

    protected function renderDeveloperPanel(
        DeveloperAccessManager $developerAccess,
        MaintenanceAccessManager $maintenanceAccess,
        string $view,
        string $pageTitle,
        string $activeSection,
        array $extraData = []
    ): Response {
        if (!$developerAccess->enabled() || !$developerAccess->configured()) {
            return $this->view("pages/not-found", [
                "pageTitle" => "Not Found",
            ], 404);
        }

        if (!$developerAccess->isUnlocked()) {
            return $this->redirect(route("developer.login"));
        }

        $maintenanceAccess->lock();
        $developerAccessState = $developerAccess->viewState();
        $maintenanceAccessState = $maintenanceAccess->viewState();
        $projectLeadership = project_leadership("admin");
        $developerControl = developer_control()->state();
        $developerDashboard = $this->developerDashboard($developerAccessState, $maintenanceAccessState, $projectLeadership);
        $customerAccessState = customer_access()->viewState();
        $customerLinks = customer_access()->enabled() ? [
            "customer_login" => route("customer.login"),
            "customer_panel" => route("customer.panel"),
            "customer_account" => route("developer.settings.customer_account"),
            "customer_account_delete" => route("developer.settings.customer_account.delete"),
        ] : [];
        $developerLinks = [
            "home" => route("home"),
            "overview" => route("developer.panel"),
            "setup_checklist" => route("developer.panel.project_identity"),
            "identity" => route("developer.panel.project_identity"),
            "project_identity_details" => route("developer.panel.project_identity.identity"),
            "runtime_environment" => route("developer.panel.project_identity.runtime"),
            "project_leadership_screen" => route("developer.panel.project_identity.leadership"),
            "project_settings" => route("developer.panel.project_identity.access"),
            "access" => route("developer.panel.access"),
            "profile" => route("developer.panel.profile"),
            "security" => route("developer.panel.access"),
            "settings" => route("developer.panel.settings"),
            "health" => route("developer.panel.release_readiness"),
            "framework_updates" => route("developer.panel.framework_updates"),
            "framework_updates_run" => route("developer.panel.framework_updates.run"),
            "technical_debt" => route("developer.panel.technical_debt"),
            "debug" => route("developer.panel.debug"),
            "debug_live" => route("developer.panel.debug.live"),
            "operations" => route("developer.panel.operations"),
            "project_logs" => route("developer.panel.project_logs"),
            "project_changelog" => route("developer.panel.changelog"),
            "analytics" => route("developer.panel.analytics"),
            "heatmap" => route("developer.panel.heatmap"),
            "notifications" => route("developer.panel.notifications"),
            "notifications_action" => route("developer.panel.notifications.action"),
            "release_readiness" => route("developer.panel.release_readiness"),
            "integrations" => route("developer.panel.integrations"),
            "workspace" => route("developer.panel.workspace"),
            "private_todo" => route("developer.panel.private_todo"),
            "policy" => route("developer.panel.policy"),
            "documentation" => route("developer.panel.documentation"),
            "about" => route("developer.panel.about"),
            "audit_export" => route("developer.panel.audit_export"),
            "audit_export_csv" => route("developer.panel.audit_export_csv"),
            "project_leadership" => route("developer.settings.project_leadership"),
            "project_leadership_confirmation" => route("developer.settings.project_leadership.confirmation"),
        ] + $customerLinks;
        $projectSetupChecklist = $this->projectSetupChecklist($developerAccessState, $maintenanceAccessState, $projectLeadership);
        $operationsForNotifications = is_array($extraData["operationsReport"] ?? null) ? (array) $extraData["operationsReport"] : [];
        $developerHeaderNotifications = is_array($extraData["notificationsReport"] ?? null)
            ? (array) $extraData["notificationsReport"]
            : (new DeveloperNotificationCenter())->build($developerAccessState, $developerDashboard, $operationsForNotifications, $developerControl);
        $developerReviewQueue = $this->developerReviewQueue(
            $projectSetupChecklist,
            $developerHeaderNotifications,
            $developerLinks,
            (array) ($developerAccessState["current_developer"] ?? [])
        );
        $developerHeaderPrivateTodo = is_array($extraData["privateTodo"] ?? null)
            ? (array) $extraData["privateTodo"]
            : (new DeveloperPrivateTodo())->state($developerAccess->currentDeveloper());

        return $this->view($view, array_merge([
            "pageTitle" => $pageTitle,
            "pageTitleSection" => $pageTitle === "Dashboard" ? "Developer Panel" : "Developer Panel",
            "layoutChromeMode" => "developer-panel",
            "developerPanelActive" => $activeSection,
            "developerAccess" => $developerAccessState,
            "maintenanceAccess" => $maintenanceAccessState,
            "developerActivity" => developer_activity()->recent(),
            "developerControl" => $developerControl,
            "customerAccess" => $customerAccessState,
            "developerHeaderNotifications" => $developerHeaderNotifications,
            "developerReviewQueue" => $developerReviewQueue,
            "developerHeaderPrivateTodo" => $developerHeaderPrivateTodo,
            "developerLinks" => $developerLinks,
            "projectSettings" => $this->projectSettings($projectLeadership),
            "projectSetupChecklist" => $projectSetupChecklist,
            "developerDashboard" => $developerDashboard,
            "developerNotice" => flash("developer_access_notice"),
        ], $extraData), 200, "layouts/developer");
    }

    protected function developerReviewQueue(array $projectSetupChecklist, array $notificationsReport, array $developerLinks, array $developer = []): array
    {
        $items = [];
        $archivedItems = [];
        $seen = [];
        $severityRank = ["critical" => 0, "warning" => 1, "info" => 2, "success" => 3];
        $reviewState = (new DeveloperNotificationCenter())->records($developer);
        $actionKeyFor = static function (string $kind, string $sourceKey, string $title, string $href, string $source): string {
            $kind = strtolower(trim($kind)) !== "" ? strtolower(trim($kind)) : "review";
            $key = strtolower(trim($sourceKey));

            if ($key === "") {
                $key = substr(hash("sha256", $title . "|" . $href . "|" . $source), 0, 24);
            }

            if ($kind !== "notification" && !str_starts_with($key, $kind . ":")) {
                $key = $kind . ":" . $key;
            }

            $key = (string) preg_replace('/[^a-z0-9_.:-]+/', "-", $key);

            return substr(trim($key, "-"), 0, 160);
        };
        $add = static function (array $item) use (&$items, &$archivedItems, &$seen, $severityRank, $reviewState, $actionKeyFor): void {
            $title = trim((string) ($item["title"] ?? ""));
            $href = trim((string) ($item["href"] ?? ""));
            if ($title === "" || $href === "") {
                return;
            }

            $severity = strtolower(trim((string) ($item["severity"] ?? "info")));
            $severity = array_key_exists($severity, $severityRank) ? $severity : "info";
            $source = trim((string) ($item["source"] ?? "Developer Panel"));
            $key = strtolower($title . "|" . $href . "|" . $source);
            if (isset($seen[$key])) {
                return;
            }

            $seen[$key] = true;
            $kind = trim((string) ($item["kind"] ?? "review"));
            $sourceKey = trim((string) (($item["notification_key"] ?? "") ?: ($item["check_key"] ?? "")));
            $actionKey = $actionKeyFor($kind, $sourceKey, $title, $href, $source);
            $record = is_array($reviewState[$actionKey] ?? null) ? (array) $reviewState[$actionKey] : [];
            $createdAt = trim((string) (($item["time"] ?? "") ?: (($item["created_at_utc"] ?? "") ?: ($item["updated_at_utc"] ?? ""))));
            $metadata = DeveloperPanelPolicy::reviewItemMetadata($source, $severity, $kind, $createdAt, $sourceKey !== "" ? $sourceKey : $title);
            $auditTrail = [
                [
                    "event" => "generated",
                    "actor" => "system",
                    "at" => $createdAt !== "" ? $createdAt : gmdate(DATE_ATOM),
                ],
            ];

            $acknowledgedAt = trim((string) (($record["acknowledged_at"] ?? "") ?: ($item["acknowledged_at"] ?? "")));
            $acknowledgedBy = trim((string) (($record["acknowledged_by"] ?? "") ?: ($item["acknowledged_by"] ?? "")));
            $archivedAt = trim((string) (($record["archived_at"] ?? "") ?: ($item["archived_at"] ?? "")));
            $archivedBy = trim((string) (($record["archived_by"] ?? "") ?: ($item["archived_by"] ?? "")));

            if ($acknowledgedAt !== "") {
                $auditTrail[] = [
                    "event" => "reviewed",
                    "actor" => $acknowledgedBy !== "" ? $acknowledgedBy : "developer",
                    "at" => $acknowledgedAt,
                ];
            }

            if ($archivedAt !== "") {
                $auditTrail[] = [
                    "event" => "archived",
                    "actor" => $archivedBy !== "" ? $archivedBy : "developer",
                    "at" => $archivedAt,
                ];
            }

            $item = array_merge($metadata, $item);
            $item["key"] = $actionKey;
            $item["notification_key"] = $actionKey;
            $item["severity"] = $severity;
            $item["source"] = $source !== "" ? $source : "Developer Panel";
            $item["rank"] = $severityRank[$severity];
            $item["action"] = trim((string) ($item["action"] ?? "Review"));
            $item["reason"] = trim((string) ($item["reason"] ?? ($item["text"] ?? "Review this Developer Panel decision.")));
            $item["evidence"] = is_array($item["evidence"] ?? null) ? (array) $item["evidence"] : [
                "label" => "Open source screen",
                "href" => $href,
                "source_key" => $sourceKey !== "" ? $sourceKey : $title,
            ];
            $item["audit_trail"] = $auditTrail;
            $item["acknowledged_at"] = $acknowledgedAt;
            $item["acknowledged_by"] = $acknowledgedBy;
            $item["archived_at"] = $archivedAt;
            $item["archived_by"] = $archivedBy;
            $item["decision_status"] = $acknowledgedAt !== "" || strtolower((string) ($item["status"] ?? "")) === "read" ? "in_review" : "open";

            if ($archivedAt !== "") {
                $archivedItems[] = $item;

                return;
            }

            $items[] = $item;
        };

        foreach ((array) ($notificationsReport["items"] ?? []) as $notification) {
            if (!is_array($notification)) { continue; }
            $severity = strtolower((string) ($notification["severity"] ?? "info"));
            if ($severity === "success") { continue; }
            $add([
                "title" => (string) ($notification["title"] ?? "Panel notification"),
                "text" => (string) ($notification["text"] ?? "Review this panel notification before release work continues."),
                "source" => DeveloperNotificationCenter::sourceFor($notification),
                "kind" => "notification",
                "notification_key" => (string) ($notification["key"] ?? ""),
                "severity" => $severity,
                "status" => (string) ($notification["acknowledged_at"] ?? "") !== "" ? "Read" : "Unread",
                "action" => (string) ($notification["action"] ?? "Review"),
                "href" => DeveloperNotificationCenter::routeFor($notification, $developerLinks),
                "time" => (string) ($notification["time"] ?? ""),
                "acknowledged_at" => (string) ($notification["acknowledged_at"] ?? ""),
                "acknowledged_by" => (string) ($notification["acknowledged_by"] ?? ""),
                "reason" => (string) ($notification["text"] ?? "Review this panel notification before release work continues."),
            ]);
        }

        foreach ((array) ($projectSetupChecklist["items"] ?? []) as $check) {
            if (!is_array($check)) { continue; }
            $status = strtolower((string) ($check["status"] ?? ""));
            if (!in_array($status, ["attention", "review"], true)) { continue; }
            $href = (string) ($check["href"] ?? ($developerLinks["identity"] ?? route("developer.panel.project_identity")));
            $add([
                "title" => (string) ($check["label"] ?? "Setup review"),
                "text" => (string) ($check["text"] ?? "Review this project setup decision."),
                "source" => $this->reviewQueueSourceFromHref($href),
                "kind" => "checklist",
                "check_key" => (string) ($check["key"] ?? ($check["label"] ?? "")),
                "severity" => $status === "attention" ? "warning" : "info",
                "status" => (string) ($check["status_label"] ?? ucfirst($status)),
                "action" => "Review",
                "href" => $href,
                "reason" => (string) ($check["text"] ?? "Review this project setup decision."),
                "evidence" => [
                    "label" => "Open checklist source",
                    "href" => $href,
                    "source_key" => (string) ($check["key"] ?? ($check["label"] ?? "setup")),
                ],
            ]);
        }

        usort($items, static function (array $first, array $second): int {
            $rank = (int) $first["rank"] <=> (int) $second["rank"];
            if ($rank !== 0) { return $rank; }
            $source = strcmp((string) $first["source"], (string) $second["source"]);
            if ($source !== 0) { return $source; }
            return strcmp((string) ($first["title"] ?? ""), (string) ($second["title"] ?? ""));
        });

        return [
            "schema" => "fnlla.developer_review_queue.v2",
            "generated_at_utc" => gmdate(DATE_ATOM),
            "items" => $items,
            "archived_items" => $archivedItems,
            "total_count" => count($items),
            "decision_count" => count(array_filter($items, static fn (array $item): bool => ($item["decision_required"] ?? true) === true)),
            "critical_count" => count(array_filter($items, static fn (array $item): bool => $item["severity"] === "critical")),
            "warning_count" => count(array_filter($items, static fn (array $item): bool => $item["severity"] === "warning")),
            "review_count" => count(array_filter($items, static fn (array $item): bool => $item["severity"] === "info")),
        ];
    }

    protected function reviewQueueSourceFromHref(string $href): string
    {
        if (str_contains($href, "/access")) {
            return "Access & security";
        }

        if (str_contains($href, "/release-readiness")) {
            return "Release & readiness";
        }

        if (str_contains($href, "/framework-updates")) {
            return "Release & readiness";
        }

        if (
            str_contains($href, "/debug")
            || str_contains($href, "/project-logs")
            || str_contains($href, "/analytics")
            || str_contains($href, "/heatmap")
        ) {
            return "Observability";
        }

        if (str_contains($href, "/integrations")) {
            return "Adapters & AI";
        }

        if (str_contains($href, "/settings")) {
            return "Panel settings";
        }

        if (str_contains($href, "developer-access-preview")) {
            return "Operations";
        }

        if (str_contains($href, "/project-identity")) {
            return "Workspace";
        }

        return "Developer Panel";
    }

    protected function projectSetupChecklist(array $developerAccess, array $maintenanceAccess, array $leadership): array
    {
        $projectName = trim((string) config("app.name", ""));
        $projectTagline = trim((string) config("app.tagline", ""));
        $projectUrl = trim((string) config("app.base_url", ""));
        $developerPath = trim((string) ($developerAccess["path"] ?? "/developer"));
        $developerNavMode = trim((string) ($developerAccess["operations_nav_mode"] ?? "hidden"));
        $security = is_array($developerAccess["security"] ?? null) ? (array) $developerAccess["security"] : [];
        $securityHeaders = (array) config("http.security_headers", []);
        $csp = trim((string) ($securityHeaders["Content-Security-Policy"] ?? ""));
        $hsts = trim((string) ($securityHeaders["Strict-Transport-Security"] ?? ""));
        $leadershipConfigured = (bool) ($leadership["configured"] ?? false);
        $leadershipVisibility = (string) ($leadership["visibility"] ?? "disabled");
        $leadershipStatus = (string) ($leadership["status"] ?? "pending");
        $runtimeEnvironment = $this->runtimeEnvironmentSettings();
        $runtimeMode = (string) ($runtimeEnvironment["mode"] ?? "development");
        $runtimeProductionReady = (bool) ($runtimeEnvironment["production_ready"] ?? false);
        $developerPathIsDefault = $developerPath === "/developer";
        $maintenanceConfigured = (bool) ($maintenanceAccess["configured"] ?? false);
        $maintenanceEnabled = (bool) ($maintenanceAccess["enabled"] ?? false);

        $items = [
            [
                "label" => "Project name",
                "status" => $projectName !== "" ? "ready" : "attention",
                "status_label" => $projectName !== "" ? "Set" : "Missing",
                "text" => $projectName !== "" ? $projectName : "Add the public project name.",
                "href" => route("developer.panel.project_identity.identity"),
            ],
            [
                "label" => "Project slogan",
                "status" => $projectTagline !== "" ? "ready" : "optional",
                "status_label" => $projectTagline !== "" ? "Set" : "Optional",
                "text" => $projectTagline !== "" ? $projectTagline : "Leave empty or add a browser-title suffix.",
                "href" => route("developer.panel.project_identity.identity"),
            ],
            [
                "label" => "Public URL",
                "status" => $projectUrl !== "" ? "ready" : "optional",
                "status_label" => $projectUrl !== "" ? "Set" : "Local",
                "text" => $projectUrl !== "" ? $projectUrl : "Add this when staging or production exists.",
                "href" => route("developer.panel.project_identity.identity"),
            ],
            [
                "label" => "Developer account",
                "status" => ((bool) ($developerAccess["configured"] ?? false) && (int) ($developerAccess["users_count"] ?? 0) > 0) ? "ready" : "attention",
                "status_label" => (string) max(0, (int) ($developerAccess["users_count"] ?? 0)),
                "text" => ((bool) ($developerAccess["configured"] ?? false)) ? "Named developer access is configured." : "Create at least one named developer account.",
                "href" => route("developer.panel.access"),
            ],
            [
                "label" => "Developer URL",
                "status" => $developerPathIsDefault ? "review" : "ready",
                "status_label" => $developerPathIsDefault ? "Default" : "Unique",
                "text" => $developerPathIsDefault ? "Consider a unique private entry path." : $developerPath,
                "href" => route("developer.panel.settings"),
            ],
            [
                "label" => "Footer Developer link",
                "status" => $developerNavMode === "developer_session_only" ? "ready" : "review",
                "status_label" => $developerNavMode === "developer_session_only" ? "Hidden" : "Visible",
                "text" => $developerNavMode === "developer_session_only" ? "Public footer stays clean." : "Footer link appears after developer setup.",
                "href" => route("developer.panel.settings"),
            ],
            [
                "label" => "Runtime environment",
                "status" => $runtimeProductionReady ? ($runtimeMode === "production" ? "ready" : "review") : "attention",
                "status_label" => ucfirst($runtimeMode),
                "text" => $runtimeMode === "production"
                    ? ($runtimeProductionReady ? "Production-safe runtime switches are applied." : "Review HTTPS URL, trusted hosts or debug switches.")
                    : "Development keeps setup and diagnostics available for local work.",
                "href" => route("developer.panel.project_identity.runtime"),
            ],
            [
                "label" => "Leadership visibility",
                "status" => $leadershipVisibility === "public" && $leadershipStatus !== "confirmed" ? "review" : ($leadershipConfigured ? "ready" : "optional"),
                "status_label" => $leadershipVisibility === "disabled" ? "Off" : ucfirst($leadershipVisibility),
                "text" => $leadershipConfigured ? "Responsibility record is configured." : "Optional responsibility record is empty.",
                "href" => route("developer.panel.project_identity.leadership"),
            ],
            [
                "label" => "Client preview",
                "status" => $maintenanceConfigured ? "ready" : "review",
                "status_label" => $maintenanceEnabled ? "Locked" : ($maintenanceConfigured ? "Prepared" : "Open"),
                "text" => $maintenanceConfigured ? "Preview password is configured." : "Add a preview password before sharing a private build.",
                "href" => route("developer.panel.project_identity.access"),
            ],
            [
                "label" => "Developer TOTP",
                "status" => (bool) ($security["totp_enabled"] ?? false) ? "ready" : "review",
                "status_label" => (bool) ($security["totp_enabled"] ?? false) ? "On" : "Off",
                "text" => (bool) ($security["totp_enabled"] ?? false) ? "Authenticator challenge is enforced for this account." : "Enable TOTP before production handover.",
                "href" => route("developer.panel.access"),
            ],
            [
                "label" => "Security headers",
                "status" => $csp !== "" && ($hsts !== "" || !str_starts_with(strtolower((string) config("app.base_url", "")), "https://")) ? "ready" : "review",
                "status_label" => $csp !== "" ? "CSP" : "Review",
                "text" => $csp !== "" ? "Browser security headers are configured." : "Add CSP/HSTS policy before production.",
                "href" => route("developer.panel.release_readiness"),
            ],
        ];

        $summary = [
            "ready" => 0,
            "review" => 0,
            "attention" => 0,
            "optional" => 0,
        ];

        foreach ($items as $item) {
            $status = $item["status"];
            $summary[$status]++;
        }

        return [
            "items" => $items,
            "summary" => $summary,
            "ready_count" => $summary["ready"],
            "total_count" => count($items),
        ];
    }

    protected function readVersionFile(string $path): ?string
    {
        if (!is_file($path)) {
            return null;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES);
        $version = is_array($lines) ? trim((string) ($lines[0] ?? "")) : "";

        return $version !== "" ? $version : null;
    }

    protected function developerAccountByEmail(array $accounts, string $email): ?array
    {
        $email = strtolower(trim($email));

        if ($email === "") {
            return null;
        }

        foreach ($accounts as $account) {
            if (($account["email"] ?? "") === $email) {
                return $account;
            }
        }

        return null;
    }

    protected function ensureDeveloperCapability(DeveloperAccessManager $developerAccess, string $capability): bool
    {
        if ($developerAccess->can($capability)) {
            return true;
        }

        flash_set("status", [
            "variant" => "warning",
            "title" => "Developer permission required",
            "text" => "Your current developer role does not include the '{$capability}' capability.",
            "toast" => true,
        ]);
        regenerate_csrf_token();

        return false;
    }

    protected function uploadErrorMessage(string $subject, int $error): string
    {
        return match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => $subject . " exceeds the configured upload size limit.",
            UPLOAD_ERR_PARTIAL => $subject . " was only partially received.",
            UPLOAD_ERR_NO_TMP_DIR => "The server upload directory is not available.",
            UPLOAD_ERR_CANT_WRITE => $subject . " could not be written to storage.",
            UPLOAD_ERR_EXTENSION => $subject . " was blocked by a server extension.",
            default => $subject . " is not valid.",
        };
    }
}
