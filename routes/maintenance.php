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

use Fnlla\Php\Controllers\DeveloperAccessController;
use Fnlla\Php\Controllers\FrameworkUpdateController;
use Fnlla\Php\Controllers\HomeController;

$router->get("/maintenance", [HomeController::class, "maintenanceHome"])->name("maintenance.home");
$router->get("/developer-panel-setup", [HomeController::class, "developerSetupAlias"])->name("developer.setup");
$router->post("/maintenance/setup-access", [HomeController::class, "setupMaintenanceAccess"])->middleware("csrf")->name("maintenance.setup_access");
$router->post("/maintenance/setup-developer-access", [HomeController::class, "setupDeveloperAccess"])->middleware(["csrf", "developer-operations"])->name("maintenance.setup_developer_access");
$router->post("/maintenance/unlock", [HomeController::class, "unlockMaintenance"])->middleware("csrf")->name("maintenance.unlock");
$router->post("/maintenance/lock", [HomeController::class, "lockMaintenance"])->middleware(["csrf", "developer-operations"])->name("maintenance.lock");
$router->get("/maintenance/health", [HomeController::class, "healthPage"])->middleware("developer-operations")->name("health");
$router->get("/maintenance/framework-update", [FrameworkUpdateController::class, "show"])->middleware("developer-operations")->name("maintenance.framework_update");
$router->post("/maintenance/framework-update/run", [FrameworkUpdateController::class, "run"])->middleware(["csrf", "developer-operations"])->throttle(5, 1)->name("maintenance.framework_update.run");

if (developer_access()->enabled()) {
    $router->get("/developer", [DeveloperAccessController::class, "entry"])->name("developer.login");
    $router->post("/developer/unlock", [DeveloperAccessController::class, "unlock"])->middleware("csrf")->name("developer.login.unlock");
    $router->get("/developer/panel", [DeveloperAccessController::class, "show"])->middleware("developer-session")->name("developer.panel");
    $router->get("/developer/panel/project-identity", [DeveloperAccessController::class, "projectIdentity"])->middleware("developer-session")->name("developer.panel.project_identity");
    $router->get("/developer/panel/project-settings", [DeveloperAccessController::class, "projectSettingsPage"])->middleware("developer-session")->name("developer.panel.project_settings");
    $router->get("/developer/panel/access", [DeveloperAccessController::class, "accessSettings"])->middleware("developer-session")->name("developer.panel.access");
    $router->get("/developer/panel/profile", [DeveloperAccessController::class, "profile"])->middleware("developer-session")->name("developer.panel.profile");
    $router->get("/developer/panel/security", [DeveloperAccessController::class, "security"])->middleware("developer-session")->name("developer.panel.security");
    $router->get("/developer/panel/settings", [DeveloperAccessController::class, "panelSettings"])->middleware("developer-session")->name("developer.panel.settings");
    $router->get("/developer/panel/health", [DeveloperAccessController::class, "health"])->middleware("developer-session")->name("developer.panel.health");
    $router->get("/developer/panel/framework-updates", [DeveloperAccessController::class, "frameworkUpdates"])->middleware("developer-session")->name("developer.panel.framework_updates");
    $router->get("/developer/panel/operations", [DeveloperAccessController::class, "operations"])->middleware("developer-session")->name("developer.panel.operations");
    $router->get("/developer/panel/analytics", [DeveloperAccessController::class, "analytics"])->middleware("developer-session")->name("developer.panel.analytics");
    $router->get("/developer/panel/heatmap", [DeveloperAccessController::class, "heatmap"])->middleware("developer-session")->name("developer.panel.heatmap");
    $router->post("/developer/panel/heatmap/settings", [DeveloperAccessController::class, "updateHeatmapSettings"])->middleware(["csrf", "developer-session"])->name("developer.panel.heatmap.settings");
    $router->post("/developer/panel/analytics/settings", [DeveloperAccessController::class, "updateAnalyticsSettings"])->middleware(["csrf", "developer-session"])->name("developer.panel.analytics.settings");
    $router->get("/developer/panel/notifications", [DeveloperAccessController::class, "notifications"])->middleware("developer-session")->name("developer.panel.notifications");
    $router->post("/developer/panel/notifications/action", [DeveloperAccessController::class, "updateNotification"])->middleware(["csrf", "developer-session"])->name("developer.panel.notifications.action");
    $router->get("/developer/panel/release-readiness", [DeveloperAccessController::class, "releaseReadiness"])->middleware("developer-session")->name("developer.panel.release_readiness");
    $router->get("/developer/panel/integrations", [DeveloperAccessController::class, "integrations"])->middleware("developer-session")->name("developer.panel.integrations");
    $router->post("/developer/panel/integrations/settings", [DeveloperAccessController::class, "updateIntegrationSettings"])->middleware(["csrf", "developer-session"])->name("developer.panel.integrations.settings");
    $router->get("/developer/panel/workspace", [DeveloperAccessController::class, "workspace"])->middleware("developer-session")->name("developer.panel.workspace");
    $router->get("/developer/panel/policy", [DeveloperAccessController::class, "policy"])->middleware("developer-session")->name("developer.panel.policy");
    $router->get("/developer/panel/documentation", [DeveloperAccessController::class, "documentation"])->middleware("developer-session")->name("developer.panel.documentation");
    $router->get("/developer/panel/about", [DeveloperAccessController::class, "about"])->middleware("developer-session")->name("developer.panel.about");
    $router->get("/developer/panel/operations/audit-export", [DeveloperAccessController::class, "exportAuditLog"])->middleware("developer-session")->name("developer.panel.audit_export");
    $router->get("/developer/panel/operations/audit-export.csv", [DeveloperAccessController::class, "exportAuditLogCsv"])->middleware("developer-session")->name("developer.panel.audit_export_csv");
    $router->post("/developer/panel/lock", [DeveloperAccessController::class, "lock"])->middleware(["csrf", "developer-session"])->name("developer.lock");
    $router->post("/developer/panel/extend", [DeveloperAccessController::class, "extend"])->middleware(["csrf", "developer-session"])->name("developer.extend");
    $router->post("/developer/panel/settings/project", [DeveloperAccessController::class, "updateProjectSettings"])->middleware(["csrf", "developer-session"])->name("developer.settings.project");
    $router->post("/developer/panel/settings/project-leadership", [DeveloperAccessController::class, "updateProjectLeadership"])->middleware(["csrf", "developer-session"])->name("developer.settings.project_leadership");
    $router->post("/developer/panel/settings/project-leadership/confirmation", [DeveloperAccessController::class, "confirmProjectLeadership"])->middleware(["csrf", "developer-session"])->name("developer.settings.project_leadership.confirmation");
    $router->post("/developer/panel/settings/maintenance", [DeveloperAccessController::class, "updateMaintenanceCredentials"])->middleware(["csrf", "developer-session"])->name("developer.settings.maintenance");
    $router->post("/developer/panel/settings/service-control", [DeveloperAccessController::class, "updateServiceControl"])->middleware(["csrf", "developer-session"])->name("developer.settings.service_control");
    $router->post("/developer/panel/settings/password", [DeveloperAccessController::class, "updateDeveloperPassword"])->middleware(["csrf", "developer-session"])->name("developer.settings.password");
    $router->post("/developer/panel/settings/developer-account", [DeveloperAccessController::class, "saveDeveloperAccount"])->middleware(["csrf", "developer-session"])->name("developer.settings.developer_account");
    $router->post("/developer/panel/settings/developer-account/delete", [DeveloperAccessController::class, "deleteDeveloperAccount"])->middleware(["csrf", "developer-session"])->name("developer.settings.developer_account.delete");
    $router->post("/developer/panel/profile", [DeveloperAccessController::class, "saveDeveloperProfile"])->middleware(["csrf", "developer-session"])->name("developer.profile.save");
    $router->post("/developer/panel/security", [DeveloperAccessController::class, "updateDeveloperSecurity"])->middleware(["csrf", "developer-session"])->name("developer.security.save");
    $router->post("/developer/panel/workspace/tasks", [DeveloperAccessController::class, "createWorkspaceTask"])->middleware(["csrf", "developer-session"])->name("developer.workspace.tasks.create");
    $router->post("/developer/panel/workspace/tasks/update", [DeveloperAccessController::class, "updateWorkspaceTask"])->middleware(["csrf", "developer-session"])->name("developer.workspace.tasks.update");
    $router->post("/developer/panel/workspace/tasks/delete", [DeveloperAccessController::class, "deleteWorkspaceTask"])->middleware(["csrf", "developer-session"])->name("developer.workspace.tasks.delete");
    $router->post("/developer/panel/framework-updates/run", [DeveloperAccessController::class, "runFrameworkUpdate"])->middleware(["csrf", "developer-session"])->throttle(5, 1)->name("developer.panel.framework_updates.run");
    $router->post("/developer/panel/settings/panel", [DeveloperAccessController::class, "updateNavigationMode"])->middleware(["csrf", "developer-session"])->name("developer.settings.panel");
    $router->post("/developer/panel/settings/nav-mode", [DeveloperAccessController::class, "updateNavigationMode"])->middleware(["csrf", "developer-session"])->name("developer.settings.nav_mode");
}
