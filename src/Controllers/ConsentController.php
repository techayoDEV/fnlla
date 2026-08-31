<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA CONTROLLER SOURCE
File: src\Controllers\ConsentController.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

Purpose:
- Receives privacy-light cookie consent telemetry without storing raw visitor
  identifiers.
*/

namespace Fnlla\Php\Controllers;

use Fnlla\Php\Http\Request;
use Fnlla\Php\Http\Response;
use Fnlla\Php\Observability\MetricsRecorder;

final class ConsentController extends Controller
{
    public function store(Request $request, MetricsRecorder $metrics): Response
    {
        $payload = is_array($request->json()) ? (array) $request->json() : $request->all();
        $preferences = is_array($payload["preferences"] ?? null) ? (array) $payload["preferences"] : $payload;

        $metrics->recordConsent([
            "analytics" => $this->truthy($preferences["analytics"] ?? false),
            "marketing" => $this->truthy($preferences["marketing"] ?? false),
            "source" => (string) ($payload["source"] ?? "cookie-banner"),
        ]);

        return Response::json([
            "ok" => true,
            "schema" => "fnlla.cookie_consent_event.v1",
        ], 202, [
            "Cache-Control" => "no-store",
        ]);
    }

    public function analyticsEvent(Request $request, MetricsRecorder $metrics): Response
    {
        $payload = is_array($request->json()) ? (array) $request->json() : $request->all();
        $consent = is_array($payload["consent"] ?? null) ? (array) $payload["consent"] : [];

        if (!$this->truthy($consent["analytics"] ?? false)) {
            return Response::json([
                "ok" => false,
                "schema" => "fnlla.behavior_event.v1",
                "reason" => "analytics_consent_required",
            ], 202, [
                "Cache-Control" => "no-store",
            ]);
        }

        $metrics->recordBehaviorEvent([
            "type" => (string) ($payload["type"] ?? ""),
            "path" => (string) ($payload["path"] ?? "/"),
            "device" => (string) ($payload["device"] ?? "unknown"),
            "viewport_width" => $payload["viewport"]["width"] ?? 0,
            "viewport_height" => $payload["viewport"]["height"] ?? 0,
            "x_percent" => $payload["position"]["x_percent"] ?? 0,
            "y_percent" => $payload["position"]["y_percent"] ?? 0,
            "depth_percent" => $payload["depth_percent"] ?? ($payload["depth"] ?? 0),
            "element" => (string) ($payload["element"] ?? "unknown"),
        ]);

        return Response::json([
            "ok" => true,
            "schema" => "fnlla.behavior_event.v1",
        ], 202, [
            "Cache-Control" => "no-store",
        ]);
    }

    private function truthy(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower(trim((string) $value)), ["1", "true", "yes", "on"], true);
    }
}
