<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA SUPPORT SOURCE
File: src\Support\TechAyoRemoteControlPlugin.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

Purpose:
- Describes the public FNLLA-side contract for the optional TechAyo remote
  developer control plugin.
*/

namespace Fnlla\Php\Support;

final class TechAyoRemoteControlPlugin
{
    public function manifest(): array
    {
        $enabled = (bool) config("developer_control.remote.enabled", false);
        $endpoint = trim((string) config("developer_control.remote.endpoint", ""));
        $projectId = trim((string) config("developer_control.remote.project_id", ""));
        $schema = trim((string) config("developer_control.remote.schema", "fnlla.techayo_remote_control.v1"));

        return [
            "schema" => "fnlla.remote_control_plugin.v1",
            "name" => "TechAyo Remote Control",
            "provider" => "TechAyo Limited",
            "admin_surface" => "https://techayo.co.uk/admin",
            "enabled" => $enabled,
            "status" => $enabled && $endpoint !== "" && $projectId !== "" ? "ready" : "disabled",
            "fnlla_runtime_contract" => [
                "request_schema" => $schema,
                "response_schema" => "fnlla.techayo_remote_control_state.v2",
                "method" => "GET",
                "endpoint" => $this->redactEndpoint($endpoint),
                "headers" => [
                    "Accept: application/json",
                    "Authorization: Bearer <token>",
                    "X-FNLLA-Control-Schema: " . $schema,
                    "X-FNLLA-Control-Tenant: <tenant>",
                    "X-FNLLA-Control-Project: <project id>",
                    "X-FNLLA-Control-Timestamp: <unix timestamp>",
                    "X-FNLLA-Control-Signature: sha256=<hmac>",
                ],
            ],
            "admin_responsibilities" => [
                "authenticate each TechAyo operator separately",
                "authorize which project can be remotely controlled",
                "return only the open, disabled or suspended service state required by FNLLA",
                "store the central audit trail outside the public project",
                "avoid sending private customer or FIONN AI knowledge to FNLLA",
            ],
            "project_responsibilities" => [
                "configure endpoint, project id, token and optional signature secret",
                "keep fail-closed disabled unless an SLA explicitly requires it",
                "show remote disable or suspension state inside the Developer Panel",
                "keep product business logic outside this plugin",
            ],
            "expected_response" => [
                "schema" => "fnlla.techayo_remote_control_state.v2",
                "status" => "open|disabled|suspended",
                "disabled" => false,
                "reason" => "billing",
                "provider" => "TechAyo Limited",
                "title" => "Services suspended",
                "message" => "Your services have been suspended. Please contact your service provider.",
                "contact" => "support@techayo.co.uk",
                "updated_at" => gmdate(DATE_ATOM),
                "updated_by" => "techayo-admin",
                "command_id" => "optional remote audit id",
                "expires_at" => "optional ISO-8601 expiry",
            ],
        ];
    }

    private function redactEndpoint(string $endpoint): string
    {
        if ($endpoint === "") {
            return "";
        }

        $parts = parse_url($endpoint);

        if (!is_array($parts)) {
            return "";
        }

        $scheme = (string) ($parts["scheme"] ?? "");
        $host = (string) ($parts["host"] ?? "");
        $path = (string) ($parts["path"] ?? "");

        return $scheme !== "" && $host !== "" ? $scheme . "://" . $host . $path : "";
    }
}
