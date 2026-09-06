<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA CONTROLLER SOURCE
File: src\Controllers\CustomerAccessController.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

Purpose:
- Serves the simplified customer portal for project progress, public preview,
  analytics and heatmap visibility.
*/

namespace Fnlla\Php\Controllers;

use Fnlla\Php\Http\Request;
use Fnlla\Php\Http\Response;
use Fnlla\Php\Maintenance\CustomerAccessManager;
use Fnlla\Php\Support\DeveloperAnalyticsReport;
use Fnlla\Php\Support\DeveloperHeatmapReport;
use Fnlla\Php\Support\DeveloperWorkspaceBoard;
use Fnlla\Php\Support\EnvironmentFileManager;
use Fnlla\Php\Validation\ValidationException;

final class CustomerAccessController extends Controller
{
    protected function view(string $template, array $data = [], int $status = 200, ?string $layout = "layouts/developer"): Response
    {
        return parent::view($template, $data, $status, $layout);
    }

    public function entry(Request $request, CustomerAccessManager $customerAccess): Response
    {
        if (!$customerAccess->enabled() || !$customerAccess->configured()) {
            return $this->view("pages/not-found", [
                "pageTitle" => "Not Found",
            ], 404);
        }

        if ($customerAccess->isUnlocked()) {
            return $this->redirect(route("customer.panel"));
        }

        return $this->view("customer/entry", [
            "pageTitle" => "Customer Portal Sign In",
            "pageTitleSection" => "Customer Portal",
            "layoutChromeMode" => "developer-panel",
            "customerAccess" => $customerAccess->viewState(),
            "customerLinks" => $this->customerLinks(),
            "projectSettings" => $this->projectSettings(),
            "customerNotice" => flash("customer_access_notice"),
        ]);
    }

    public function unlock(Request $request, CustomerAccessManager $customerAccess): Response
    {
        $result = $customerAccess->unlock(
            $request,
            (string) $request->input("customer_access_email", ""),
            (string) $request->input("customer_access_password", "")
        );

        if (($result["success"] ?? false) !== true) {
            flash_set("customer_access_notice", [
                "variant" => "warning",
                "title" => "Customer access was not unlocked",
                "text" => (string) ($result["error"] ?? "Check your customer credentials and try again."),
            ]);
            regenerate_csrf_token();

            return $this->redirect(route("customer.login"));
        }

        regenerate_csrf_token();

        return $this->redirect(route("customer.panel"));
    }

    public function invite(Request $request, CustomerAccessManager $customerAccess): Response
    {
        if (!$customerAccess->enabled() || !$customerAccess->configured()) {
            return $this->view("pages/not-found", [
                "pageTitle" => "Not Found",
            ], 404);
        }

        $token = trim((string) $request->query("token", ""));
        $invitation = $token !== "" ? $customerAccess->invitationForToken($token) : null;

        return $this->view("customer/invite", [
            "pageTitle" => "Set Customer Portal Password",
            "pageTitleSection" => "Customer Portal",
            "layoutChromeMode" => "developer-panel",
            "customerAccess" => $customerAccess->viewState(),
            "customerLinks" => $this->customerLinks(),
            "projectSettings" => $this->projectSettings(),
            "customerNotice" => flash("customer_access_notice"),
            "invitation" => $invitation,
            "token" => $token,
        ], $invitation === null ? 404 : 200);
    }

    public function setInvitePassword(
        Request $request,
        CustomerAccessManager $customerAccess,
        EnvironmentFileManager $environmentFileManager
    ): Response {
        $payload = [
            "token" => trim((string) $request->input("customer_invite_token", "")),
            "password" => trim((string) $request->input("customer_invite_password", "")),
            "password_confirmation" => trim((string) $request->input("customer_invite_password_confirmation", "")),
        ];

        try {
            $this->validate($payload, [
                "token" => ["required", "string"],
                "password" => ["required", "string", "min:8", "max:255", "confirmed"],
            ]);
        } catch (ValidationException $exception) {
            flash_set("errors", $exception->errors());
            flash_set("customer_access_notice", [
                "variant" => "warning",
                "title" => "Password still needs attention",
                "text" => "Use at least 8 characters and confirm the same password.",
            ]);
            regenerate_csrf_token();

            return $this->redirect(route("customer.invite") . "?token=" . rawurlencode($payload["token"]));
        }

        $result = $customerAccess->consumeInvitation($payload["token"], $payload["password"]);

        if (($result["success"] ?? false) !== true || !is_array($result["account"] ?? null)) {
            flash_set("customer_access_notice", [
                "variant" => "warning",
                "title" => "Invitation could not be accepted",
                "text" => (string) ($result["error"] ?? "Ask the project team for a fresh customer portal invitation."),
            ]);
            regenerate_csrf_token();

            return $this->redirect(route("customer.invite") . "?token=" . rawurlencode($payload["token"]));
        }

        $serializedAccounts = $customerAccess->serializeAccounts((array) ($result["accounts"] ?? []));

        try {
            $environmentFileManager->write([
                "CUSTOMER_ACCESS_USERS" => $serializedAccounts,
            ]);
            $environmentFileManager->apply([
                "CUSTOMER_ACCESS_USERS" => $serializedAccounts,
            ]);
        } catch (\RuntimeException $exception) {
            flash_set("customer_access_notice", [
                "variant" => "danger",
                "title" => "Customer password could not be saved",
                "text" => $exception->getMessage(),
            ]);
            regenerate_csrf_token();

            return $this->redirect(route("customer.invite") . "?token=" . rawurlencode($payload["token"]));
        }

        config_set("customer_access", array_merge((array) config("customer_access", []), [
            "users" => $serializedAccounts,
        ]));
        $customerAccess->grantAccess((array) $result["account"]);
        developer_activity()->record(
            "customer_access",
            "Customer portal password set",
            "A customer accepted an invitation and created a customer portal password.",
            ["email" => (string) ($result["account"]["email"] ?? ""), "name" => (string) ($result["account"]["name"] ?? "Customer")]
        );
        flash_set("status", [
            "variant" => "success",
            "title" => "Customer portal ready",
            "text" => "Your password has been saved and the customer portal is now open.",
            "toast" => true,
        ]);
        regenerate_csrf_token();

        return $this->redirect(route("customer.panel"));
    }

    public function show(
        Request $request,
        CustomerAccessManager $customerAccess,
        DeveloperWorkspaceBoard $workspace,
        DeveloperAnalyticsReport $analyticsReport,
        DeveloperHeatmapReport $heatmapReport
    ): Response {
        return $this->renderCustomerPanel($customerAccess, "customer/panel", "Customer Portal", "overview", [
            "workspaceBoard" => $workspace->customerState($customerAccess->currentCustomer()),
            "analyticsReport" => $analyticsReport->build(),
            "heatmapReport" => $heatmapReport->build(),
        ]);
    }

    public function kanban(Request $request, CustomerAccessManager $customerAccess, DeveloperWorkspaceBoard $workspace): Response
    {
        if (!$this->ensureCustomerPermission($customerAccess, "kanban")) {
            return $this->redirect(route("customer.panel"));
        }

        return $this->renderCustomerPanel($customerAccess, "customer/kanban", "Project Kanban", "kanban", [
            "workspaceBoard" => $workspace->customerState($customerAccess->currentCustomer()),
        ]);
    }

    public function analytics(Request $request, CustomerAccessManager $customerAccess, DeveloperAnalyticsReport $report): Response
    {
        if (!$this->ensureCustomerPermission($customerAccess, "analytics")) {
            return $this->redirect(route("customer.panel"));
        }

        return $this->renderCustomerPanel($customerAccess, "customer/analytics", "Analytics", "analytics", [
            "analyticsReport" => $report->build(),
        ]);
    }

    public function heatmap(Request $request, CustomerAccessManager $customerAccess, DeveloperHeatmapReport $report): Response
    {
        if (!$this->ensureCustomerPermission($customerAccess, "heatmap")) {
            return $this->redirect(route("customer.panel"));
        }

        return $this->renderCustomerPanel($customerAccess, "customer/heatmap", "Heatmap", "heatmap", [
            "heatmapReport" => $report->build(),
        ]);
    }

    public function lock(Request $request, CustomerAccessManager $customerAccess): Response
    {
        $customerAccess->lock();
        flash_set("customer_access_notice", [
            "variant" => "success",
            "title" => "Customer portal locked",
            "text" => "Sign in again when you need to review project progress.",
        ]);
        regenerate_csrf_token();

        return $this->redirect(route("customer.login"));
    }

    private function renderCustomerPanel(
        CustomerAccessManager $customerAccess,
        string $view,
        string $pageTitle,
        string $activeSection,
        array $extraData = []
    ): Response {
        if (!$customerAccess->enabled() || !$customerAccess->configured()) {
            return $this->view("pages/not-found", [
                "pageTitle" => "Not Found",
            ], 404);
        }

        if (!$customerAccess->isUnlocked()) {
            return $this->redirect(route("customer.login"));
        }

        $customerAccessState = $customerAccess->viewState();

        return $this->view($view, array_merge([
            "pageTitle" => $pageTitle,
            "pageTitleSection" => "Customer Portal",
            "layoutChromeMode" => "developer-panel",
            "customerPanelActive" => $activeSection,
            "customerAccess" => $customerAccessState,
            "customerLinks" => $this->customerLinks(),
            "projectSettings" => $this->projectSettings(),
            "customerNotice" => flash("customer_access_notice"),
        ], $extraData));
    }

    private function ensureCustomerPermission(CustomerAccessManager $customerAccess, string $permission): bool
    {
        if ($customerAccess->can($permission)) {
            return true;
        }

        flash_set("status", [
            "variant" => "warning",
            "title" => "Customer permission required",
            "text" => "This customer account does not include access to that project view.",
            "toast" => true,
        ]);
        regenerate_csrf_token();

        return false;
    }

    private function customerLinks(): array
    {
        return [
            "home" => route("home"),
            "login" => route("customer.login"),
            "overview" => route("customer.panel"),
            "kanban" => route("customer.panel.kanban"),
            "analytics" => route("customer.panel.analytics"),
            "heatmap" => route("customer.panel.heatmap"),
            "lock" => route("customer.lock"),
        ];
    }

    private function projectSettings(): array
    {
        return [
            "name" => (string) config("app.name", "FNLLA Project"),
            "tagline" => (string) config("app.tagline", ""),
            "url" => (string) config("app.base_url", ""),
            "preview_url" => route("home"),
        ];
    }
}
