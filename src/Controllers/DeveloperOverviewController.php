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

final class DeveloperOverviewController extends DeveloperPanelController
{
    public function show(Request $request, DeveloperAccessManager $developerAccess, MaintenanceAccessManager $maintenanceAccess): Response
    {
        return $this->renderDeveloperPanel(
            $developerAccess,
            $maintenanceAccess,
            "developer/panel",
            "Dashboard",
            "overview"
        );
    }

    public function setupChecklist(Request $request, DeveloperAccessManager $developerAccess, MaintenanceAccessManager $maintenanceAccess): Response
    {
        return $this->redirect(route("developer.panel.project_identity") . "#developer-setup-checklist");
    }

    public function health(Request $request, DeveloperAccessManager $developerAccess, MaintenanceAccessManager $maintenanceAccess): Response
    {
        return $this->redirect(route("developer.panel.release_readiness"));
    }

    public function documentation(Request $request, DeveloperAccessManager $developerAccess, MaintenanceAccessManager $maintenanceAccess): Response
    {
        if (!$this->ensureDeveloperCapability($developerAccess, "policy.view")) {
            return $this->redirect(route("developer.panel"));
        }

        return $this->renderDeveloperPanel(
            $developerAccess,
            $maintenanceAccess,
            "developer/documentation",
            "Documentation & Policy",
            "documentation",
            [
                "developerPolicy" => $this->developerPolicy($developerAccess),
                "aboutFnlla" => $this->fnllaInstallationFacts(),
            ]
        );
    }

    public function about(Request $request, DeveloperAccessManager $developerAccess, MaintenanceAccessManager $maintenanceAccess): Response
    {
        if (!$this->ensureDeveloperCapability($developerAccess, "policy.view")) {
            return $this->redirect(route("developer.panel"));
        }

        return $this->renderDeveloperPanel(
            $developerAccess,
            $maintenanceAccess,
            "developer/about",
            "About FNLLA",
            "documentation",
            [
                "aboutFnlla" => $this->fnllaInstallationFacts(),
            ]
        );
    }

    public function policy(Request $request, DeveloperAccessManager $developerAccess, MaintenanceAccessManager $maintenanceAccess): Response
    {
        if (!$this->ensureDeveloperCapability($developerAccess, "policy.view")) {
            return $this->redirect(route("developer.panel"));
        }

        return $this->renderDeveloperPanel(
            $developerAccess,
            $maintenanceAccess,
            "developer/policy",
            "Policy Boundary",
            "documentation",
            [
                "developerPolicy" => $this->developerPolicy($developerAccess),
            ]
        );
    }

    private function fnllaInstallationFacts(): array
    {
        return array_merge($this->fnllaVersionFacts(), [
            "app_name" => (string) config("app.name", "FNLLA"),
            "environment" => app_environment(),
            "maintainer" => (string) config("framework.maintainer_name", FrameworkIdentity::MAINTAINER_NAME),
            "maintainer_url" => (string) config("framework.maintainer_url", FrameworkIdentity::MAINTAINER_URL),
            "official_url" => (string) config("framework.official_url", FrameworkIdentity::OFFICIAL_URL),
            "repository" => (string) config("framework.repository_web_url", FrameworkIdentity::REPOSITORY_WEB_URL),
            "support_email" => (string) config("framework.support_email", FrameworkIdentity::SUPPORT_EMAIL),
            "license" => "MIT",
        ]);
    }

    private function developerPolicy(DeveloperAccessManager $developerAccess): array
    {
        return [
            "schema" => "fnlla.developer_policy_boundary.v1",
            "boundary" => [
                "fnlla_managed" => [
                    "Developer access, sessions, capability checks and local developer profiles.",
                    "Client-preview, maintenance and developer service-control contracts.",
                    "Customer portal invitations, customer session lock flow and read-only delivery review routes.",
                    "Framework update checks, update audit, runtime validation and release readiness signals.",
                    "Privacy-light operational summaries, consent-aware integration hooks and reserved Fionn bridge policy.",
                    "The lightweight technical workspace used to coordinate framework/project setup tasks.",
                    "Optional project leadership records that name responsibility without storing customer data.",
                ],
                "project_owned" => [
                    "Business domain models, database schema, customer records and product workflows.",
                    "Application admin panels, CRM, billing, bookings, documents, orders, dashboards and reports.",
                    "Business user roles, customer permissions, product-specific auth journeys and account policies.",
                    "Customer portals that need writable records, CRM data, billing, support tickets or product-specific approvals.",
                    "Brand copy, client content, uploaded business files, production secrets and private operating knowledge.",
                    "Whether a client system exposes TechAyo or named-lead information publicly, privately or not at all.",
                    "External SaaS integrations after the project explicitly connects and audits them.",
                ],
                "forbidden_in_fnlla_core" => [
                    "Customer data or private client workflows.",
                    "The private Fionn brain, learned memory, model weights, queues or eval data.",
                    "Hard-coded TechAyo central-control business logic beyond the public API contract.",
                    "Product-specific CRM, CMS, billing or industry workflows.",
                    "Tracking pixels, heatmap scripts or analytics tags enabled without explicit consent and project opt-in.",
                ],
            ],
            "capabilities" => $developerAccess->capabilityCatalog(),
            "roles" => array_map(
                fn (string $role, string $label): array => [
                    "role" => $role,
                    "label" => $label,
                    "capabilities" => $developerAccess->capabilitiesFor($role),
                ],
                array_keys($developerAccess->roleOptions()),
                array_values($developerAccess->roleOptions())
            ),
            "storage" => [
                "default" => "File-backed JSON in storage/framework/developer for starter portability.",
                "production_note" => "High-change teams should move long-lived workspace and audit data to project-owned database tables without changing the public panel contract.",
            ],
        ];
    }
}
