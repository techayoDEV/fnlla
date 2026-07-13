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
use Fnlla\Php\Maintenance\MaintenanceAccessManager;

final class EnforceMaintenanceAccess implements MiddlewareInterface
{
    public function __construct(private MaintenanceAccessManager $access)
    {
    }

    public function handle(Request $request, callable $next): mixed
    {
        if (!$this->access->enabled() || $this->access->isUnlocked() || developer_access()->isUnlocked() || $this->isAllowedWhileLocked($request)) {
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
            "/maintenance/setup-access",
            "/maintenance/unlock",
            "/maintenance/lock",
        ];

        if (in_array($request->path(), $allowedPaths, true)) {
            return true;
        }

        $developerPath = developer_access()->path();

        return $developerPath !== ""
            && ($request->path() === $developerPath || str_starts_with($request->path(), $developerPath . "/"));
    }

    private function lockedRedirectPath(Request $request): string
    {
        $requestUri = (string) $request->server("REQUEST_URI", $request->path());
        $relativeTarget = str_starts_with($requestUri, "/") ? $requestUri : $request->path();

        return route("maintenance.home") . "?redirect=" . rawurlencode($relativeTarget);
    }
}
