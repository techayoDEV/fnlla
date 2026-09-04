<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA MIDDLEWARE SOURCE
File: src\Middleware\EnforceMaintenanceAccess.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Redirects locked maintenance requests into the maintenance access screen while
  preserving machine-facing JSON responses for API clients.
*/

namespace Fnlla\Php\Middleware;

use Fnlla\Php\Http\Request;
use Fnlla\Php\Http\Response;
use Fnlla\Php\Maintenance\DeveloperControlManager;
use Fnlla\Php\Maintenance\MaintenanceAccessManager;
use Fnlla\Php\View\View;

final class EnforceMaintenanceAccess implements MiddlewareInterface
{
    public function __construct(
        private MaintenanceAccessManager $access,
        private DeveloperControlManager $developerControl
    )
    {
    }

    public function handle(Request $request, callable $next): mixed
    {
        if ($this->developerControl->disabled() && !$this->isAllowedDuringDeveloperDisable($request)) {
            $state = $this->developerControl->state();

            if ($request->expectsJson() || str_starts_with($request->path(), "/api/")) {
                return Response::json([
                    "error" => "Service Disabled",
                    "message" => (string) ($state["message"] ?? "This service is temporarily disabled by the developer team."),
                    "contact" => (string) ($state["contact"] ?? ""),
                    "source" => (string) ($state["source"] ?? "local"),
                    "request_id" => $request->requestId(),
                ], 503, [
                    "Retry-After" => "60",
                ]);
            }

            return Response::html(View::render("maintenance/service-disabled", [
                "pageTitle" => "Service Disabled",
                "layoutChromeMode" => "client-preview",
                "developerControl" => $state,
            ]), 503);
        }

        if ($this->shouldRedirectFreshSetup($request)) {
            return Response::redirect($this->freshSetupRedirectPath($request));
        }

        if (!$this->access->enabled() || $this->access->isUnlocked() || developer_access()->isUnlocked() || customer_access()->isUnlocked() || $this->isAllowedWhileLocked($request)) {
            return $next($request);
        }

        if ($request->expectsJson() || str_starts_with($request->path(), "/api/")) {
            return Response::json([
                "error" => "Maintenance Locked",
                "message" => "This application is temporarily protected by a maintenance password.",
                "unlock_path" => route("maintenance.home"),
                "request_id" => $request->requestId(),
            ], 503, [
                "Retry-After" => "60",
            ]);
        }

        return Response::redirect($this->lockedRedirectPath($request));
    }

    private function isAllowedWhileLocked(Request $request): bool
    {
        $allowedPaths = [
            "/maintenance",
            "/developer-panel-setup",
            "/maintenance/setup-access",
            "/maintenance/unlock",
            "/maintenance/lock",
        ];

        if (in_array($request->path(), $allowedPaths, true)) {
            return true;
        }

        return $this->isDeveloperPath($request) || $this->isCustomerPath($request);
    }

    private function isAllowedDuringDeveloperDisable(Request $request): bool
    {
        if (developer_access()->isUnlocked() || customer_access()->isUnlocked()) {
            return true;
        }

        if ($request->path() === "/developer-panel-setup") {
            return true;
        }

        return $this->isDeveloperPath($request) || $this->isCustomerPath($request);
    }

    private function isDeveloperPath(Request $request): bool
    {
        $developerPath = developer_access()->path();

        return $developerPath !== ""
            && ($request->path() === $developerPath || str_starts_with($request->path(), $developerPath . "/"));
    }

    private function isCustomerPath(Request $request): bool
    {
        $customerPath = customer_access()->path();

        return $customerPath !== ""
            && ($request->path() === $customerPath || str_starts_with($request->path(), $customerPath . "/"));
    }

    private function shouldRedirectFreshSetup(Request $request): bool
    {
        if ($this->access->enabled() || $this->access->configured() || developer_access()->configured() || $this->isAllowedWhileLocked($request)) {
            return false;
        }

        if ($request->expectsJson() || str_starts_with($request->path(), "/api/")) {
            return false;
        }

        if ($request->path() === "/") {
            return false;
        }

        if ($request->path() === "/maintenance" || str_starts_with($request->path(), "/maintenance/")) {
            return false;
        }

        if (!(bool) config("maintenance.setup_ui_enabled", app_environment() !== "production")) {
            return false;
        }

        $localOnly = (bool) config("maintenance.setup_ui_local_only", true);
        $isLocalRequest = in_array($request->ip(), ["127.0.0.1", "::1"], true);

        if ($localOnly && !$isLocalRequest) {
            return false;
        }

        return (new \Fnlla\Php\Support\EnvironmentFileManager())->isWritable();
    }

    private function freshSetupRedirectPath(Request $request): string
    {
        return route("home");
    }

    private function lockedRedirectPath(Request $request): string
    {
        $requestUri = (string) $request->server("REQUEST_URI", $request->path());
        $relativeTarget = str_starts_with($requestUri, "/") ? $requestUri : $request->path();

        return route("maintenance.home") . "?redirect=" . rawurlencode($relativeTarget);
    }
}
