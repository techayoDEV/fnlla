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
use Fnlla\Php\Support\DeveloperWorkspaceBoard;
use Fnlla\Php\Support\EnvironmentFileManager;
use Fnlla\Php\Support\FrameworkIdentity;
use Fnlla\Php\Support\Logger;
use Fnlla\Php\Support\ProjectLeadership;
use Fnlla\Php\Validation\ValidationException;

final class DeveloperOperationsController extends DeveloperPanelController
{
    public function frameworkUpdates(Request $request, DeveloperAccessManager $developerAccess, MaintenanceAccessManager $maintenanceAccess): Response
    {
        if (!$this->ensureDeveloperCapability($developerAccess, "framework.update")) {
            return $this->redirect(route("developer.panel.operations"));
        }

        return $this->renderDeveloperPanel(
            $developerAccess,
            $maintenanceAccess,
            "developer/framework-updates",
            "Framework Updates",
            "framework-updates",
            app(FrameworkUpdateController::class)->viewData($request, [
                "pageTitle" => "Framework Updates",
                "pageTitleSection" => "Developer Panel",
                "layoutChromeMode" => "developer-panel",
                "frameworkUpdateRunRoute" => route("developer.panel.framework_updates.run"),
                "frameworkUpdateRefreshRoute" => route("developer.panel.framework_updates"),
            ])
        );
    }

    public function runFrameworkUpdate(Request $request, DeveloperAccessManager $developerAccess): Response
    {
        if (!$this->ensureDeveloperCapability($developerAccess, "framework.update")) {
            return $this->redirect(route("developer.panel.framework_updates"));
        }

        $mode = strtolower(trim((string) $request->input("mode", "check")));
        if (in_array($mode, ["apply", "github-apply", "upgrade-apply"], true) && !$developerAccess->can("framework.update.apply")) {
            flash_set("status", [
                "variant" => "warning",
                "title" => "Framework apply requires approval",
                "text" => "Your role can check and dry-run framework updates, but applying changes requires the framework.update.apply capability.",
                "toast" => false,
            ]);
            regenerate_csrf_token();

            return $this->redirect(route("developer.panel.framework_updates"));
        }

        return app(FrameworkUpdateController::class)->run($request);
    }

    public function operations(Request $request, DeveloperAccessManager $developerAccess, MaintenanceAccessManager $maintenanceAccess, DeveloperOperationsReport $report): Response
    {
        if (!$this->ensureDeveloperCapability($developerAccess, "operations.view")) {
            return $this->redirect(route("developer.panel"));
        }

        return $this->renderDeveloperPanel(
            $developerAccess,
            $maintenanceAccess,
            "developer/operations",
            "Operations",
            "operations",
            [
                "operationsReport" => $report->build(),
                "health" => app(HomeController::class)->healthPayload($request),
            ]
        );
    }

    public function projectLogs(Request $request, DeveloperAccessManager $developerAccess, MaintenanceAccessManager $maintenanceAccess, DeveloperActivityLog $activityLog): Response
    {
        if (!$this->ensureDeveloperCapability($developerAccess, "operations.view")) {
            return $this->redirect(route("developer.panel"));
        }

        return $this->renderDeveloperPanel(
            $developerAccess,
            $maintenanceAccess,
            "developer/project-logs",
            "Project Logs",
            "project-logs",
            [
                "projectLogReport" => $this->projectLogReport($activityLog->recent(120)),
            ]
        );
    }

    public function changelog(Request $request, DeveloperAccessManager $developerAccess, MaintenanceAccessManager $maintenanceAccess, DeveloperActivityLog $activityLog): Response
    {
        if (!$this->ensureDeveloperCapability($developerAccess, "operations.view")) {
            return $this->redirect(route("developer.panel"));
        }

        return $this->renderDeveloperPanel(
            $developerAccess,
            $maintenanceAccess,
            "developer/changelog",
            "Project Changelog",
            "project-changelog",
            [
                "projectChangelogReport" => $this->projectLogReport($activityLog->recent(120)),
            ]
        );
    }

    public function notifications(
        Request $request,
        DeveloperAccessManager $developerAccess,
        MaintenanceAccessManager $maintenanceAccess,
        DeveloperOperationsReport $operationsReport,
        DeveloperNotificationCenter $notifications
    ): Response {
        if (!$this->ensureDeveloperCapability($developerAccess, "operations.view")) {
            return $this->redirect(route("developer.panel"));
        }

        $developerAccessState = $developerAccess->viewState();
        $maintenanceAccessState = $maintenanceAccess->viewState();
        $dashboard = $this->developerDashboard($developerAccessState, $maintenanceAccessState);
        $operations = $operationsReport->build();

        return $this->renderDeveloperPanel(
            $developerAccess,
            $maintenanceAccess,
            "developer/notifications",
            "Review Queue",
            "notifications",
            [
                "notificationsReport" => $notifications->build($developerAccessState, $dashboard, $operations, developer_control()->state()),
            ]
        );
    }

    public function releaseReadiness(Request $request, DeveloperAccessManager $developerAccess, MaintenanceAccessManager $maintenanceAccess, DeveloperOperationsReport $report): Response
    {
        if (!$this->ensureDeveloperCapability($developerAccess, "operations.view")) {
            return $this->redirect(route("developer.panel"));
        }

        return $this->renderDeveloperPanel(
            $developerAccess,
            $maintenanceAccess,
            "developer/release-readiness",
            "Readiness & Health",
            "release-readiness",
            [
                "operationsReport" => $report->build(),
                "health" => app(HomeController::class)->healthPayload($request),
            ]
        );
    }

    public function updateNotification(Request $request, DeveloperAccessManager $developerAccess, DeveloperNotificationCenter $notifications): Response
    {
        if (!$this->ensureDeveloperCapability($developerAccess, "operations.view")) {
            return $this->redirect(route("developer.panel"));
        }

        $key = strtolower(trim((string) $request->input("developer_notification_key", "")));
        $action = strtolower(trim((string) $request->input("developer_notification_action", "")));

        if ($key === "" || !in_array($action, ["review", "acknowledge", "archive", "restore"], true)) {
            flash_set("status", [
                "variant" => "warning",
                "title" => "Notification action was not applied",
                "text" => "Choose a valid notification action before updating the inbox.",
                "toast" => true,
            ]);
            regenerate_csrf_token();

            return $this->redirect(route("developer.panel.notifications"));
        }

        $developer = $developerAccess->currentDeveloper();
        $redirectTo = route("developer.panel.notifications");

        if ($action === "review") {
            $notifications->acknowledge($key, $developer);
            $message = "Notification opened for review";
            $redirectTo = $this->developerNotificationActionRedirect($request);
        } elseif ($action === "acknowledge") {
            $notifications->acknowledge($key, $developer);
            $message = "Notification marked as read";
            $redirectTo = $this->developerNotificationActionRedirect($request);
        } elseif ($action === "archive") {
            $notifications->archive($key, $developer);
            $message = "Notification archived";
            $redirectTo = $this->developerNotificationActionRedirect($request);
        } else {
            $notifications->restore($key, $developer);
            $message = "Notification restored";
            $redirectTo = $this->developerNotificationActionRedirect($request);
        }

        developer_activity()->record(
            "developer_notification",
            $message,
            "A developer changed notification state for {$key}.",
            $developer
        );

        flash_set("status", [
            "variant" => "success",
            "title" => $message,
            "text" => "Your review queue state was saved without hiding the item for other developers.",
            "toast" => true,
        ]);
        regenerate_csrf_token();

        return $this->redirect($redirectTo);
    }

    public function exportAuditLog(Request $request, DeveloperAccessManager $developerAccess, DeveloperActivityLog $activityLog): Response
    {
        if (!$this->ensureDeveloperCapability($developerAccess, "audit.export")) {
            return $this->redirect(route("developer.panel.operations"));
        }

        return Response::json($activityLog->exportPayload(500), 200, [
            "Content-Disposition" => "attachment; filename=\"fnlla-developer-audit-log.json\"",
            "Cache-Control" => "no-store",
        ]);
    }

    public function exportAuditLogCsv(Request $request, DeveloperAccessManager $developerAccess, DeveloperActivityLog $activityLog): Response
    {
        if (!$this->ensureDeveloperCapability($developerAccess, "audit.export")) {
            return $this->redirect(route("developer.panel.operations"));
        }

        return Response::text($activityLog->exportCsv(500), 200, [
            "Content-Disposition" => "attachment; filename=\"fnlla-developer-audit-log.csv\"",
            "Content-Type" => "text/csv; charset=UTF-8",
            "Cache-Control" => "no-store",
        ]);
    }

    private function developerNotificationActionRedirect(Request $request): string
    {
        $candidate = trim((string) $request->input("developer_notification_redirect", ""));

        if ($candidate === "" || str_contains($candidate, "\r") || str_contains($candidate, "\n")) {
            return route("developer.panel.notifications");
        }

        $parts = parse_url($candidate);

        if (!is_array($parts) || isset($parts["scheme"]) || isset($parts["host"])) {
            return route("developer.panel.notifications");
        }

        $path = (string) ($parts["path"] ?? "");
        $developerPanelPath = rtrim((string) developer_access()->path(), "/") . "/panel";

        if ($path === $developerPanelPath || str_starts_with($path, $developerPanelPath . "/")) {
            return $candidate;
        }

        return route("developer.panel.notifications");
    }

    private function projectLogReport(array $events): array
    {
        $items = [];

        foreach ($events as $event) {
            if (!is_array($event)) {
                continue;
            }

            $event["category"] = $this->projectLogCategory((string) ($event["action"] ?? ""));
            $items[] = $event;
        }

        $today = gmdate("Y-m-d");
        $todayCount = 0;
        $categoryCounts = [];
        $lastActor = "No activity yet";

        foreach ($items as $index => $event) {
            $time = (string) ($event["time"] ?? "");
            $category = (string) $event["category"];
            $categoryCounts[$category] = ($categoryCounts[$category] ?? 0) + 1;

            if (substr($time, 0, 10) === $today) {
                $todayCount++;
            }

            if ($index === 0) {
                $developer = (array) ($event["developer"] ?? []);
                $lastActor = (string) (($developer["name"] ?? "") ?: ($developer["email"] ?? "") ?: "Developer");
            }
        }

        return [
            "items" => $items,
            "total" => count($items),
            "today" => $todayCount,
            "categories" => $categoryCounts,
            "last_actor" => $lastActor,
            "latest_time" => (string) ($items[0]["time"] ?? ""),
        ];
    }

    private function projectLogCategory(string $action): string
    {
        $action = strtolower($action);

        if (str_contains($action, "workspace") || str_contains($action, "identity") || str_contains($action, "leadership") || str_contains($action, "task") || str_contains($action, "kanban") || str_contains($action, "attachment") || str_contains($action, "subtask")) {
            return "Workspace";
        }

        if (str_contains($action, "developer") || str_contains($action, "security") || str_contains($action, "profile") || str_contains($action, "password") || str_contains($action, "totp")) {
            return "Access";
        }

        if (str_contains($action, "project") || str_contains($action, "preview") || str_contains($action, "maintenance") || str_contains($action, "framework") || str_contains($action, "release") || str_contains($action, "analytics") || str_contains($action, "heatmap") || str_contains($action, "integration") || str_contains($action, "notification")) {
            return "Operations";
        }

        return "Project";
    }
}
