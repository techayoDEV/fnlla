<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA OBSERVABILITY SOURCE
File: src\Observability\RequestObserver.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

Purpose:
- Adds request timing, structured access logs and local metrics to HTTP responses.
*/

namespace Fnlla\Php\Observability;

use Fnlla\Php\Http\Request;
use Fnlla\Php\Http\Response;
use Fnlla\Php\Support\Logger;

final class RequestObserver
{
    public function __construct(private MetricsRecorder $metrics)
    {
    }

    public function observe(Request $request, Response $response, float $durationMs): Response
    {
        $final = $this->withResponseTime($response, $durationMs);

        if ((bool) config("observability.access_log.enabled", true)) {
            Logger::write("access", "HTTP request", [
                "request_id" => $request->requestId(),
                "method" => $request->method(),
                "path" => $request->path(),
                "route" => $this->routeName(),
                "status" => $final->status(),
                "duration_ms" => round($durationMs, 3),
                "ip" => $request->ip(),
                "user_agent" => (string) $request->header("User-Agent", ""),
            ]);
        }

        $this->metrics->record($request, $final, $durationMs);

        return $final;
    }

    private function withResponseTime(Response $response, float $durationMs): Response
    {
        if (!(bool) config("observability.response_time_header.enabled", true)) {
            return $response;
        }

        $name = trim((string) config("observability.response_time_header.name", "X-Response-Time"));

        if ($name === "") {
            return $response;
        }

        return $response->withHeader($name, sprintf("%.2fms", $durationMs));
    }

    private function routeName(): string
    {
        return trim((string) ($_SERVER["FNLLA_ROUTE_NAME"] ?? ""));
    }
}
