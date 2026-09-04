<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA ROUTE DEFINITION
File: routes\maintenance.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Registers the framework-maintenance page kept as a framework-managed surface
  so downstream applications can update their framework base without reopening
  the full maintainer workspace.
*/

use Fnlla\Php\Controllers\CustomerAccessController;
use Fnlla\Php\Controllers\DeveloperAccessController;
use Fnlla\Php\Controllers\FrameworkUpdateController;
use Fnlla\Php\Controllers\HomeController;

/*
Route-map convention:
- keep framework-managed URLs declared as small data arrays
- keep route names stable; views and tests rely on the named-route contract
- put write routes behind CSRF and protected operator routes behind the matching
  session middleware
*/
$registerRoute = static function (
    string $method,
    string $path,
    string $controller,
    string $action,
    string $name,
    array|string|null $middleware = null,
    ?array $throttle = null,
) use ($router): void {
    $route = $router->{$method}($path, [$controller, $action]);

    if ($middleware !== null) {
        $route->middleware($middleware);
    }

    if ($throttle !== null) {
        $route->throttle((int) $throttle[0], (int) ($throttle[1] ?? 1));
    }

    $route->name($name);
};

$maintenanceRoutes = [
    ["get", "/maintenance", HomeController::class, "maintenanceHome", "maintenance.home"],
    ["get", "/developer-panel-setup", HomeController::class, "developerSetupAlias", "developer.setup"],
    ["post", "/maintenance/setup-access", HomeController::class, "setupMaintenanceAccess", "maintenance.setup_access", "csrf"],
    ["post", "/maintenance/setup-developer-access", HomeController::class, "setupDeveloperAccess", "maintenance.setup_developer_access", ["csrf", "developer-operations"]],
    ["post", "/maintenance/unlock", HomeController::class, "unlockMaintenance", "maintenance.unlock", "csrf"],
    ["post", "/maintenance/lock", HomeController::class, "lockMaintenance", "maintenance.lock", ["csrf", "developer-operations"]],
    ["get", "/maintenance/health", HomeController::class, "healthPage", "health", "developer-operations"],
    ["get", "/maintenance/framework-update", FrameworkUpdateController::class, "show", "maintenance.framework_update", "developer-operations"],
    ["post", "/maintenance/framework-update/run", FrameworkUpdateController::class, "run", "maintenance.framework_update.run", ["csrf", "developer-operations"], [5, 1]],
];

foreach ($maintenanceRoutes as $route) {
    $registerRoute(...$route);
}

if (developer_access()->enabled()) {
    $developerPath = developer_access()->path();
    $developerPanelPath = $developerPath . "/panel";

    $developerPanel = static fn (string $path = ""): string => $developerPanelPath . $path;
    $developerRoutes = [
        ["get", $developerPath, DeveloperAccessController::class, "entry", "developer.login"],
        ["post", $developerPath . "/unlock", DeveloperAccessController::class, "unlock", "developer.login.unlock", "csrf"],
        ["get", $developerPanel(), DeveloperAccessController::class, "show", "developer.panel", "developer-session"],
        ["get", $developerPanel("/setup-checklist"), DeveloperAccessController::class, "setupChecklist", "developer.panel.setup_checklist", "developer-session"],
        ["get", $developerPanel("/project-identity"), DeveloperAccessController::class, "projectIdentity", "developer.panel.project_identity", "developer-session"],
        ["get", $developerPanel("/project-settings"), DeveloperAccessController::class, "projectSettingsPage", "developer.panel.project_settings", "developer-session"],
        ["get", $developerPanel("/access"), DeveloperAccessController::class, "accessSettings", "developer.panel.access", "developer-session"],
        ["get", $developerPanel("/profile"), DeveloperAccessController::class, "profile", "developer.panel.profile", "developer-session"],
        ["get", $developerPanel("/security"), DeveloperAccessController::class, "security", "developer.panel.security", "developer-session"],
        ["get", $developerPanel("/settings"), DeveloperAccessController::class, "panelSettings", "developer.panel.settings", "developer-session"],
        ["get", $developerPanel("/health"), DeveloperAccessController::class, "health", "developer.panel.health", "developer-session"],
        ["get", $developerPanel("/framework-updates"), DeveloperAccessController::class, "frameworkUpdates", "developer.panel.framework_updates", "developer-session"],
        ["get", $developerPanel("/operations"), DeveloperAccessController::class, "operations", "developer.panel.operations", "developer-session"],
        ["get", $developerPanel("/project-logs"), DeveloperAccessController::class, "projectLogs", "developer.panel.project_logs", "developer-session"],
        ["get", $developerPanel("/analytics"), DeveloperAccessController::class, "analytics", "developer.panel.analytics", "developer-session"],
        ["get", $developerPanel("/heatmap"), DeveloperAccessController::class, "heatmap", "developer.panel.heatmap", "developer-session"],
        ["post", $developerPanel("/heatmap/settings"), DeveloperAccessController::class, "updateHeatmapSettings", "developer.panel.heatmap.settings", ["csrf", "developer-session"]],
        ["post", $developerPanel("/analytics/settings"), DeveloperAccessController::class, "updateAnalyticsSettings", "developer.panel.analytics.settings", ["csrf", "developer-session"]],
        ["get", $developerPanel("/notifications"), DeveloperAccessController::class, "notifications", "developer.panel.notifications", "developer-session"],
        ["post", $developerPanel("/notifications/action"), DeveloperAccessController::class, "updateNotification", "developer.panel.notifications.action", ["csrf", "developer-session"]],
        ["get", $developerPanel("/release-readiness"), DeveloperAccessController::class, "releaseReadiness", "developer.panel.release_readiness", "developer-session"],
        ["get", $developerPanel("/integrations"), DeveloperAccessController::class, "integrations", "developer.panel.integrations", "developer-session"],
        ["post", $developerPanel("/integrations/settings"), DeveloperAccessController::class, "updateIntegrationSettings", "developer.panel.integrations.settings", ["csrf", "developer-session"]],
        ["get", $developerPanel("/workspace"), DeveloperAccessController::class, "workspace", "developer.panel.workspace", "developer-session"],
        ["get", $developerPanel("/policy"), DeveloperAccessController::class, "policy", "developer.panel.policy", "developer-session"],
        ["get", $developerPanel("/documentation"), DeveloperAccessController::class, "documentation", "developer.panel.documentation", "developer-session"],
        ["get", $developerPanel("/about"), DeveloperAccessController::class, "about", "developer.panel.about", "developer-session"],
        ["get", $developerPanel("/operations/audit-export"), DeveloperAccessController::class, "exportAuditLog", "developer.panel.audit_export", "developer-session"],
        ["get", $developerPanel("/operations/audit-export.csv"), DeveloperAccessController::class, "exportAuditLogCsv", "developer.panel.audit_export_csv", "developer-session"],
        ["post", $developerPanel("/lock"), DeveloperAccessController::class, "lock", "developer.lock", ["csrf", "developer-session"]],
        ["post", $developerPanel("/extend"), DeveloperAccessController::class, "extend", "developer.extend", ["csrf", "developer-session"]],
        ["post", $developerPanel("/settings/project"), DeveloperAccessController::class, "updateProjectSettings", "developer.settings.project", ["csrf", "developer-session"]],
        ["post", $developerPanel("/settings/project-leadership"), DeveloperAccessController::class, "updateProjectLeadership", "developer.settings.project_leadership", ["csrf", "developer-session"]],
        ["post", $developerPanel("/settings/project-leadership/confirmation"), DeveloperAccessController::class, "confirmProjectLeadership", "developer.settings.project_leadership.confirmation", ["csrf", "developer-session"]],
        ["post", $developerPanel("/settings/maintenance"), DeveloperAccessController::class, "updateMaintenanceCredentials", "developer.settings.maintenance", ["csrf", "developer-session"]],
        ["post", $developerPanel("/settings/service-control"), DeveloperAccessController::class, "updateServiceControl", "developer.settings.service_control", ["csrf", "developer-session"]],
        ["post", $developerPanel("/settings/password"), DeveloperAccessController::class, "updateDeveloperPassword", "developer.settings.password", ["csrf", "developer-session"]],
        ["post", $developerPanel("/settings/developer-account"), DeveloperAccessController::class, "saveDeveloperAccount", "developer.settings.developer_account", ["csrf", "developer-session"]],
        ["post", $developerPanel("/settings/developer-account/delete"), DeveloperAccessController::class, "deleteDeveloperAccount", "developer.settings.developer_account.delete", ["csrf", "developer-session"]],
        ["post", $developerPanel("/settings/customer-account"), DeveloperAccessController::class, "saveCustomerAccount", "developer.settings.customer_account", ["csrf", "developer-session"]],
        ["post", $developerPanel("/settings/customer-account/delete"), DeveloperAccessController::class, "deleteCustomerAccount", "developer.settings.customer_account.delete", ["csrf", "developer-session"]],
        ["post", $developerPanel("/profile"), DeveloperAccessController::class, "saveDeveloperProfile", "developer.profile.save", ["csrf", "developer-session"]],
        ["post", $developerPanel("/security"), DeveloperAccessController::class, "updateDeveloperSecurity", "developer.security.save", ["csrf", "developer-session"]],
        ["post", $developerPanel("/workspace/tasks"), DeveloperAccessController::class, "createWorkspaceTask", "developer.workspace.tasks.create", ["csrf", "developer-session"]],
        ["post", $developerPanel("/workspace/tasks/update"), DeveloperAccessController::class, "updateWorkspaceTask", "developer.workspace.tasks.update", ["csrf", "developer-session"]],
        ["post", $developerPanel("/workspace/tasks/delete"), DeveloperAccessController::class, "deleteWorkspaceTask", "developer.workspace.tasks.delete", ["csrf", "developer-session"]],
        ["post", $developerPanel("/framework-updates/run"), DeveloperAccessController::class, "runFrameworkUpdate", "developer.panel.framework_updates.run", ["csrf", "developer-session"], [5, 1]],
        ["post", $developerPanel("/settings/panel"), DeveloperAccessController::class, "updateNavigationMode", "developer.settings.panel", ["csrf", "developer-session"]],
        ["post", $developerPanel("/settings/nav-mode"), DeveloperAccessController::class, "updateNavigationMode", "developer.settings.nav_mode", ["csrf", "developer-session"]],
    ];

    foreach ($developerRoutes as $route) {
        $registerRoute(...$route);
    }
}

$customerPath = customer_access()->path();
$customerPanelPath = $customerPath . "/panel";
$customerPanel = static fn (string $path = ""): string => $customerPanelPath . $path;
$customerRoutes = [
    ["get", $customerPath, CustomerAccessController::class, "entry", "customer.login"],
    ["post", $customerPath . "/unlock", CustomerAccessController::class, "unlock", "customer.login.unlock", "csrf"],
    ["get", $customerPath . "/invite", CustomerAccessController::class, "invite", "customer.invite"],
    ["post", $customerPath . "/invite/password", CustomerAccessController::class, "setInvitePassword", "customer.invite.password", "csrf"],
    ["get", $customerPanel(), CustomerAccessController::class, "show", "customer.panel", "customer-session"],
    ["get", $customerPanel("/kanban"), CustomerAccessController::class, "kanban", "customer.panel.kanban", "customer-session"],
    ["get", $customerPanel("/analytics"), CustomerAccessController::class, "analytics", "customer.panel.analytics", "customer-session"],
    ["get", $customerPanel("/heatmap"), CustomerAccessController::class, "heatmap", "customer.panel.heatmap", "customer-session"],
    ["post", $customerPanel("/lock"), CustomerAccessController::class, "lock", "customer.lock", ["csrf", "customer-session"]],
];

foreach ($customerRoutes as $route) {
    $registerRoute(...$route);
}
