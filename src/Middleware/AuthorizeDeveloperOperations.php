<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA MIDDLEWARE SOURCE
File: src\Middleware\AuthorizeDeveloperOperations.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Restricts operator-only routes once the public operations navigation should
  no longer remain visible outside an unlocked developer session.
*/

namespace Fnlla\Php\Middleware;

use Fnlla\Php\Http\Request;
use Fnlla\Php\Http\Response;
use Fnlla\Php\View\View;

final class AuthorizeDeveloperOperations implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): mixed
    {
        if (developer_access()->canAccessOperations()) {
            return $next($request);
        }

        if ($request->expectsJson() || str_starts_with($request->path(), "/api/")) {
            return Response::json([
                "error" => "Not Found",
                "message" => "This route is not available.",
                "request_id" => $request->requestId(),
            ], 404);
        }

        return Response::html(View::render("pages/not-found", [
            "pageTitle" => "Not Found",
        ]), 404);
    }
}
