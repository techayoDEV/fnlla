<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA EXCEPTION SOURCE
File: src\Exceptions\ExceptionHandler.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Implements framework-level exception reporting and rendering behavior.
*/

namespace Fnlla\Php\Exceptions;

use Fnlla\Php\Http\Request;
use Fnlla\Php\Http\Response;
use Fnlla\Php\Support\Logger;
use Fnlla\Php\View\View;
use Throwable;

final class ExceptionHandler
{
    public function report(Throwable $exception, Request $request): void
    {
        Logger::exception($exception, [
            "request_id" => $request->requestId(),
            "method" => $request->method(),
            "path" => $request->path(),
            "ip" => $request->ip(),
        ]);
    }

    public function render(Throwable $exception, Request $request): Response
    {
        $debugMessage = app_debug()
            ? $exception->getMessage()
            : "The application hit an unexpected error while processing the request.";

        if ($request->expectsJson() || str_starts_with($request->path(), "/api/")) {
            return Response::json([
                "error" => "Server Error",
                "message" => $debugMessage,
                "request_id" => $request->requestId(),
            ], 500);
        }

        return Response::html(View::render("pages/error", [
            "pageTitle" => "Application Error",
            "headline" => "Something went wrong",
            "message" => $debugMessage,
            "requestReference" => $request->requestId(),
        ]), 500);
    }
}
