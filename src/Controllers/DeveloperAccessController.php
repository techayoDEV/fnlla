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

final class DeveloperAccessController extends DeveloperPanelController
{
    public function entry(Request $request, DeveloperAccessManager $developerAccess, MaintenanceAccessManager $maintenanceAccess): Response
    {
        if (!$developerAccess->enabled()) {
            return $this->view("pages/not-found", [
                "pageTitle" => "Not Found",
            ], 404);
        }

        if (!$developerAccess->configured()) {
            return app(OnboardingController::class)->developerEntrySetup($request, $developerAccess, $maintenanceAccess);
        }

        if ($developerAccess->isUnlocked()) {
            return $this->redirect(route("developer.panel"));
        }

        return $this->view("developer/entry", [
            "pageTitle" => "Developer Sign In",
            "pageTitleSection" => "Operations",
            "developerAccess" => $developerAccess->viewState(),
            "maintenanceAccess" => $maintenanceAccess->viewState(),
            "developerLinks" => [
                "home" => route("home"),
            ],
            "projectSettings" => $this->projectSettings(),
            "developerNotice" => flash("developer_access_notice"),
            "developerTotpRequired" => (bool) flash("developer_access_totp_required", false),
        ], 200, "layouts/developer")->withHeaders(["Cache-Control" => "private, no-store", "Referrer-Policy" => "no-referrer"]);
    }

    public function unlock(Request $request, DeveloperAccessManager $developerAccess, MaintenanceAccessManager $maintenanceAccess): Response
    {
        if (!$developerAccess->enabled() || !$developerAccess->configured()) {
            return $this->view("pages/not-found", [
                "pageTitle" => "Not Found",
            ], 404);
        }

        $email = trim((string) $request->input("developer_access_email", ""));
        $password = trim((string) $request->input("developer_access_password", ""));
        $totpCode = trim((string) $request->input("developer_access_totp", ""));
        try {
            $this->validate([
                "developer_access_email" => $email,
                "developer_access_password" => $password,
            ], [
                "developer_access_email" => ["required", "email", "max:160"],
                "developer_access_password" => ["required", "string", "min:8", "max:255"],
            ]);
        } catch (ValidationException $exception) {
            flash_set("errors", $exception->errors());
            flash_set("old", ["developer_access_email" => $email]);
            flash_set("status", [
                "variant" => "warning",
                "title" => "Developer access still needs attention",
                "text" => "Enter your developer email and password to unlock this hidden panel.",
                "toast" => false,
            ]);
            regenerate_csrf_token();

            return $this->redirect(route("developer.login"));
        }

        $result = $developerAccess->unlock($request, $password, $email, $totpCode);

        if (!$result["success"]) {
            flash_set("old", ["developer_access_email" => $email]);
            flash_set("developer_access_totp_required", (bool) ($result["totp_required"] ?? false));
            flash_set("status", [
                "variant" => "warning",
                "title" => "Developer access denied",
                "text" => (string) $result["error"],
                "toast" => false,
            ]);
            regenerate_csrf_token();

            return $this->redirect(route("developer.login"));
        }

        flash_set("status", [
            "variant" => "success",
            "title" => "Developer session unlocked",
            "text" => "The hidden developer panel is available in this browser session for the configured access window.",
            "toast" => true,
        ]);
        $maintenanceAccess->lock();
        regenerate_csrf_token();

        return $this->redirect(route("developer.panel"));
    }

    public function lock(Request $request, DeveloperAccessManager $developerAccess): Response
    {
        $developerAccess->lock();
        Logger::write("notice", "Developer session locked", [
            "event" => "developer_session_locked",
            "ip" => $request->ip(),
        ]);
        flash_set("status", [
            "variant" => "info",
            "title" => "Developer session locked",
            "text" => "The private developer panel now requires its password again.",
            "toast" => false,
        ]);
        regenerate_csrf_token();

        return $this->redirect(route("developer.login"));
    }

    public function extend(Request $request, DeveloperAccessManager $developerAccess): Response
    {
        if (!$developerAccess->extendAccess()) {
            return $this->redirect(route("developer.login"));
        }

        Logger::write("notice", "Developer session extended", [
            "event" => "developer_session_extended",
            "ip" => $request->ip(),
        ]);
        flash_set("status", [
            "variant" => "success",
            "title" => "Developer session extended",
            "text" => "The current developer session window was refreshed without changing the absolute session limit.",
            "toast" => true,
        ]);
        regenerate_csrf_token();

        return $this->redirect(route("developer.panel"));
    }
}
