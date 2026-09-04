<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA MIDDLEWARE SOURCE
File: src\Middleware\RequireCustomerSession.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

Purpose:
- Restricts the customer portal to authenticated customer sessions.
*/

namespace Fnlla\Php\Middleware;

use Fnlla\Php\Http\Request;
use Fnlla\Php\Http\Response;

final class RequireCustomerSession implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): mixed
    {
        if (customer_access()->isUnlocked()) {
            customer_access()->extendAccess();

            return $next($request);
        }

        if ($request->expectsJson() || str_starts_with($request->path(), "/api/")) {
            return Response::json([
                "error" => "Customer Access Required",
                "message" => "Sign in with your customer portal account to view this project.",
                "unlock_path" => route("customer.login"),
                "request_id" => $request->requestId(),
            ], 401);
        }

        return Response::redirect(route("customer.login"));
    }
}
