<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA PHP MIDDLEWARE SOURCE
File: src\Middleware\HandleCors.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA PHP is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA PHP framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Implements middleware behavior for request hardening, policy and response shaping.
*/

namespace Fnlla\Php\Middleware;

use Fnlla\Php\Http\Request;
use Fnlla\Php\Http\Response;

final class HandleCors implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): mixed
    {
        $headers = $this->headersForRequest($request);

        if ($request->method() === "OPTIONS") {
            return Response::empty(204, $headers);
        }

        $result = $next($request);

        if ($result instanceof Response) {
            return $result->withHeaders($headers);
        }

        if (is_array($result)) {
            return Response::json($result, 200, $headers);
        }

        if (is_string($result)) {
            return Response::html($result, 200, $headers);
        }

        if ($result === null) {
            return Response::empty(204, $headers);
        }

        return Response::json($result, 200, $headers);
    }

    private function headersForRequest(Request $request): array
    {
        $origin = (string) $request->header("Origin", "");
        $allowedOrigins = (array) config("cors.allowed_origins", ["*"]);
        $allowOrigin = in_array("*", $allowedOrigins, true) || in_array($origin, $allowedOrigins, true)
            ? ($origin !== "" && !in_array("*", $allowedOrigins, true) ? $origin : "*")
            : "";

        $headers = [
            "Access-Control-Allow-Methods" => implode(", ", (array) config("cors.allowed_methods", ["GET", "POST", "PUT", "PATCH", "DELETE", "OPTIONS"])),
            "Access-Control-Allow-Headers" => implode(", ", (array) config("cors.allowed_headers", ["Content-Type", "Authorization", "X-Requested-With", "X-Request-Id", "X-CSRF-TOKEN"])),
            "Access-Control-Max-Age" => (string) config("cors.max_age", 3600),
        ];

        if ($allowOrigin !== "") {
            $headers["Access-Control-Allow-Origin"] = $allowOrigin;
        }

        if ((bool) config("cors.supports_credentials", false)) {
            $headers["Access-Control-Allow-Credentials"] = "true";
        }

        return $headers;
    }
}
