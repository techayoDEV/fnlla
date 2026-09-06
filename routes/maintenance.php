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
use Fnlla\Php\Controllers\DeveloperDebtController;
use Fnlla\Php\Controllers\DeveloperDebugController;
use Fnlla\Php\Controllers\DeveloperOverviewController;
use Fnlla\Php\Controllers\DeveloperProjectController;
use Fnlla\Php\Controllers\DeveloperAccountController;
use Fnlla\Php\Controllers\DeveloperProfileController;
use Fnlla\Php\Controllers\DeveloperSettingsController;
use Fnlla\Php\Controllers\DeveloperInsightsController;
use Fnlla\Php\Controllers\DeveloperOperationsController;
use Fnlla\Php\Controllers\DeveloperWorkspaceController;
use Fnlla\Php\Controllers\DeveloperAccessController;
use Fnlla\Php\Controllers\DeveloperRecoveryController;
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
    if (\Fnlla\Php\Support\DeveloperModules::forRoute($name) !== []) {
        $route->middleware(\Fnlla\Php\Middleware\RequireDeveloperModule::class);
    }

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
        ["get", $developerPanel("/technical-debt"), DeveloperDebtController::class, "show", "developer.panel.technical_debt", "developer-session"],
        ["post", $developerPanel("/technical-debt"), DeveloperDebtController::class, "save", "developer.panel.technical_debt.save", ["csrf", "developer-session"], [20, 1]],
        ["get", $developerPanel("/debug"), DeveloperDebugController::class, "show", "developer.panel.debug", "developer-session"],
        ["post", $developerPanel("/debug"), DeveloperDebugController::class, "save", "developer.panel.debug.save", ["csrf", "developer-session"]],
        ["get", $developerPath, DeveloperAccessController::class, "entry", "developer.login"],
        ["get", $developerPath . "/forgot-password", DeveloperRecoveryController::class, "show", "developer.password.forgot"],
        ["post", $developerPath . "/forgot-password", DeveloperRecoveryController::class, "send", "developer.password.email", "csrf", [10, 1]],
        ["get", $developerPath . "/reset-password", DeveloperRecoveryController::class, "edit", "developer.password.reset", null, [30, 1]],
        ["post", $developerPath . "/reset-password", DeveloperRecoveryController::class, "update", "developer.password.update", "csrf", [10, 1]],
        ["post", $developerPath . "/unlock", DeveloperAccessController::class, "unlock", "developer.login.unlock", "csrf"],
        ["get", $developerPanel(), DeveloperOverviewController::class, "show", "developer.panel", "developer-session"],
        ["get", $developerPanel("/setup-checklist"), DeveloperOverviewController::class, "setupChecklist", "developer.panel.setup_checklist", "developer-session"],
        ["get", $developerPanel("/project-identity"), DeveloperProjectController::class, "projectIdentity", "developer.panel.project_identity", "developer-session"],
        ["get", $developerPanel("/project-settings"), DeveloperProjectController::class, "projectSettingsPage", "developer.panel.project_settings", "developer-session"],
        ["get", $developerPanel("/access"), DeveloperAccountController::class, "accessSettings", "developer.panel.access", "developer-session"],
        ["get", $developerPanel("/profile"), DeveloperProfileController::class, "profile", "developer.panel.profile", "developer-session"],
        ["get", $developerPanel("/security"), DeveloperProfileController::class, "security", "developer.panel.security", "developer-session"],
        ["get", $developerPanel("/settings"), DeveloperSettingsController::class, "panelSettings", "developer.panel.settings", "developer-session"],
        ["get", $developerPanel("/health"), DeveloperOverviewController::class, "health", "developer.panel.health", "developer-session"],
        ["get", $developerPanel("/framework-updates"), DeveloperOperationsController::class, "frameworkUpdates", "developer.panel.framework_updates", "developer-session"],
        ["get", $developerPanel("/operations"), DeveloperOperationsController::class, "operations", "developer.panel.operations", "developer-session"],
        ["get", $developerPanel("/project-logs"), DeveloperOperationsController::class, "projectLogs", "developer.panel.project_logs", "developer-session"],
        ["get", $developerPanel("/analytics"), DeveloperInsightsController::class, "analytics", "developer.panel.analytics", "developer-session"],
        ["get", $developerPanel("/heatmap"), DeveloperInsightsController::class, "heatmap", "developer.panel.heatmap", "developer-session"],
        ["post", $developerPanel("/heatmap/settings"), DeveloperInsightsController::class, "updateHeatmapSettings", "developer.panel.heatmap.settings", ["csrf", "developer-session"]],
        ["post", $developerPanel("/analytics/settings"), DeveloperInsightsController::class, "updateAnalyticsSettings", "developer.panel.analytics.settings", ["csrf", "developer-session"]],
        ["get", $developerPanel("/notifications"), DeveloperOperationsController::class, "notifications", "developer.panel.notifications", "developer-session"],
        ["post", $developerPanel("/notifications/action"), DeveloperOperationsController::class, "updateNotification", "developer.panel.notifications.action", ["csrf", "developer-session"]],
        ["get", $developerPanel("/release-readiness"), DeveloperOperationsController::class, "releaseReadiness", "developer.panel.release_readiness", "developer-session"],
        ["get", $developerPanel("/integrations"), DeveloperSettingsController::class, "integrations", "developer.panel.integrations", "developer-session"],
        ["post", $developerPanel("/integrations/settings"), DeveloperSettingsController::class, "updateIntegrationSettings", "developer.panel.integrations.settings", ["csrf", "developer-session"]],
        ["get", $developerPanel("/workspace"), DeveloperWorkspaceController::class, "workspace", "developer.panel.workspace", "developer-session"],
        ["get", $developerPanel("/policy"), DeveloperOverviewController::class, "policy", "developer.panel.policy", "developer-session"],
        ["get", $developerPanel("/documentation"), DeveloperOverviewController::class, "documentation", "developer.panel.documentation", "developer-session"],
        ["get", $developerPanel("/about"), DeveloperOverviewController::class, "about", "developer.panel.about", "developer-session"],
        ["get", $developerPanel("/operations/audit-export"), DeveloperOperationsController::class, "exportAuditLog", "developer.panel.audit_export", "developer-session"],
        ["get", $developerPanel("/operations/audit-export.csv"), DeveloperOperationsController::class, "exportAuditLogCsv", "developer.panel.audit_export_csv", "developer-session"],
        ["post", $developerPanel("/lock"), DeveloperAccessController::class, "lock", "developer.lock", ["csrf", "developer-session"]],
        ["post", $developerPanel("/extend"), DeveloperAccessController::class, "extend", "developer.extend", ["csrf", "developer-session"]],
        ["post", $developerPanel("/settings/project"), DeveloperProjectController::class, "updateProjectSettings", "developer.settings.project", ["csrf", "developer-session"]],
        ["post", $developerPanel("/settings/project-leadership"), DeveloperProjectController::class, "updateProjectLeadership", "developer.settings.project_leadership", ["csrf", "developer-session"]],
        ["post", $developerPanel("/settings/project-leadership/confirmation"), DeveloperProjectController::class, "confirmProjectLeadership", "developer.settings.project_leadership.confirmation", ["csrf", "developer-session"]],
        ["post", $developerPanel("/settings/maintenance"), DeveloperProjectController::class, "updateMaintenanceCredentials", "developer.settings.maintenance", ["csrf", "developer-session"]],
        ["post", $developerPanel("/settings/service-control"), DeveloperProjectController::class, "updateServiceControl", "developer.settings.service_control", ["csrf", "developer-session"]],
        ["post", $developerPanel("/settings/password"), DeveloperProfileController::class, "updateDeveloperPassword", "developer.settings.password", ["csrf", "developer-session"]],
        ["post", $developerPanel("/settings/developer-account"), DeveloperAccountController::class, "saveDeveloperAccount", "developer.settings.developer_account", ["csrf", "developer-session"]],
        ["post", $developerPanel("/settings/developer-account/delete"), DeveloperAccountController::class, "deleteDeveloperAccount", "developer.settings.developer_account.delete", ["csrf", "developer-session"]],
        ["post", $developerPanel("/settings/customer-account"), DeveloperAccountController::class, "saveCustomerAccount", "developer.settings.customer_account", ["csrf", "developer-session"]],
        ["post", $developerPanel("/settings/customer-account/delete"), DeveloperAccountController::class, "deleteCustomerAccount", "developer.settings.customer_account.delete", ["csrf", "developer-session"]],
        ["post", $developerPanel("/profile"), DeveloperProfileController::class, "saveDeveloperProfile", "developer.profile.save", ["csrf", "developer-session"]],
        ["post", $developerPanel("/security"), DeveloperProfileController::class, "updateDeveloperSecurity", "developer.security.save", ["csrf", "developer-session"]],
        ["post", $developerPanel("/workspace/tasks"), DeveloperWorkspaceController::class, "createWorkspaceTask", "developer.workspace.tasks.create", ["csrf", "developer-session"]],
        ["post", $developerPanel("/workspace/tasks/update"), DeveloperWorkspaceController::class, "updateWorkspaceTask", "developer.workspace.tasks.update", ["csrf", "developer-session"]],
        ["post", $developerPanel("/workspace/tasks/delete"), DeveloperWorkspaceController::class, "deleteWorkspaceTask", "developer.workspace.tasks.delete", ["csrf", "developer-session"]],
        ["post", $developerPanel("/framework-updates/run"), DeveloperOperationsController::class, "runFrameworkUpdate", "developer.panel.framework_updates.run", ["csrf", "developer-session"], [5, 1]],
        ["post", $developerPanel("/settings/panel"), DeveloperSettingsController::class, "updateNavigationMode", "developer.settings.panel", ["csrf", "developer-session"]],
        ["post", $developerPanel("/settings/nav-mode"), DeveloperSettingsController::class, "updateNavigationMode", "developer.settings.nav_mode", ["csrf", "developer-session"]],
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
