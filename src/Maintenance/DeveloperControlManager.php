<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA MAINTENANCE SOURCE
File: src\Maintenance\DeveloperControlManager.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

Purpose:
- Provides the public project disable switch and remote-control contract without
  embedding private TechAyo admin logic in the open FNLLA framework.
*/

namespace Fnlla\Php\Maintenance;

final class DeveloperControlManager
{
    public function state(): array
    {
        $local = $this->readJson($this->localPath());
        $remote = $this->remoteState();
        $remoteDisabled = (bool) ($remote["disabled"] ?? false);
        $localDisabled = (bool) ($local["disabled"] ?? false);
        $disabled = $remoteDisabled || $localDisabled;
        $source = $remoteDisabled ? "remote" : ($localDisabled ? "local" : "none");
        $status = $disabled ? (string) (($remote["status"] ?? "") ?: ($local["status"] ?? "disabled")) : "open";
        $reason = (string) (($remote["reason"] ?? "") ?: ($local["reason"] ?? ""));

        return [
            "schema" => "fnlla.developer_control_state.v2",
            "disabled" => $disabled,
            "local_disabled" => $localDisabled,
            "remote_disabled" => $remoteDisabled,
            "status" => $status,
            "reason" => $reason,
            "source" => $source,
            "provider" => (string) (($remote["provider"] ?? "") ?: ($local["provider"] ?? config("developer_control.service_provider", "TechAyo Limited"))),
            "title" => (string) (($remote["title"] ?? "") ?: ($local["title"] ?? $this->defaultTitle($status))),
            "message" => (string) (($remote["message"] ?? "") ?: ($local["message"] ?? $this->defaultMessage($status))),
            "contact" => (string) (($remote["contact"] ?? "") ?: ($local["contact"] ?? config("developer_control.disabled_contact", ""))),
            "contact_phone" => (string) (($remote["contact_phone"] ?? "") ?: ($local["contact_phone"] ?? $this->defaultContactPhone($status))),
            "updated_at" => (string) (($remote["updated_at"] ?? "") ?: ($local["updated_at"] ?? "")),
            "updated_by" => (string) (($remote["updated_by"] ?? "") ?: ($local["updated_by"] ?? "")),
            "command_id" => (string) ($remote["command_id"] ?? ""),
            "expires_at" => (string) ($remote["expires_at"] ?? ""),
            "remote_enabled" => (bool) config("developer_control.remote.enabled", false),
            "remote_contract" => $this->remoteContract(),
        ];
    }

    public function disabled(): bool
    {
        return (bool) $this->state()["disabled"];
    }

    public function disable(
        string $message = "",
        string $contact = "",
        array $developer = [],
        string $status = "disabled",
        string $reason = "developer",
        string $title = "",
        string $contactPhone = ""
    ): void
    {
        $status = $this->normaliseStatus($status);
        $status = $status === "open" ? "disabled" : $status;
        $reason = $this->clean($reason !== "" ? $reason : ($status === "suspended" ? "billing" : "developer"), 80);

        $this->writeLocal([
            "schema" => "fnlla.developer_control.v1",
            "disabled" => true,
            "status" => $status,
            "reason" => $reason,
            "title" => trim($title) !== "" ? $this->clean($title, 120) : $this->defaultTitle($status),
            "message" => trim($message) !== "" ? trim($message) : $this->defaultMessage($status),
            "contact" => trim($contact) !== "" ? trim($contact) : (string) config("developer_control.disabled_contact", ""),
            "contact_phone" => trim($contactPhone) !== "" ? trim($contactPhone) : $this->defaultContactPhone($status),
            "provider" => (string) config("developer_control.service_provider", "TechAyo Limited"),
            "updated_at" => gmdate("c"),
            "updated_by" => (string) ($developer["email"] ?? "developer"),
        ]);
    }

    public function enable(array $developer = []): void
    {
        $this->writeLocal([
            "schema" => "fnlla.developer_control.v1",
            "disabled" => false,
            "status" => "open",
            "reason" => "",
            "title" => (string) config("developer_control.disabled_title", "Service paused by developer"),
            "message" => "",
            "contact" => "",
            "contact_phone" => "",
            "provider" => (string) config("developer_control.service_provider", "TechAyo Limited"),
            "updated_at" => gmdate("c"),
            "updated_by" => (string) ($developer["email"] ?? "developer"),
        ]);
    }

    private function remoteState(): array
    {
        if (!(bool) config("developer_control.remote.enabled", false)) {
            return [];
        }

        $cachePath = $this->remoteCachePath();
        $cacheTtl = max(5, (int) config("developer_control.remote.cache_ttl_seconds", 60));

        if (is_file($cachePath) && filemtime($cachePath) !== false && filemtime($cachePath) + $cacheTtl > time()) {
            return $this->normaliseRemoteState($this->readJson($cachePath));
        }

        $endpoint = trim((string) config("developer_control.remote.endpoint", ""));

        if (!$this->remoteEndpointAllowed($endpoint)) {
            return (bool) config("developer_control.remote.fail_closed", false)
                ? ["disabled" => true, "status" => "disabled", "source" => "remote", "reason" => "remote_endpoint_not_allowed", "message" => "Remote developer control endpoint is not allowed."]
                : [];
        }

        $context = stream_context_create([
            "http" => [
                "method" => "GET",
                "timeout" => max(1, (int) config("developer_control.remote.timeout_seconds", 5)),
                "header" => $this->remoteHeaders(),
            ],
        ]);
        $contents = @file_get_contents($endpoint, false, $context);

        if (!is_string($contents) || $contents === "") {
            return (bool) config("developer_control.remote.fail_closed", false)
                ? ["disabled" => true, "status" => "disabled", "source" => "remote", "reason" => "remote_unavailable", "message" => "Remote developer control is unavailable."]
                : [];
        }

        $decoded = json_decode($contents, true);
        $state = is_array($decoded) ? $this->normaliseRemoteState($decoded) : [];

        $directory = dirname($cachePath);

        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        file_put_contents($cachePath, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL, LOCK_EX);

        return $state;
    }

    private function normaliseRemoteState(array $state): array
    {
        $status = $this->normaliseStatus((string) ($state["status"] ?? $state["mode"] ?? ""));
        if ((bool) ($state["suspended"] ?? false) === true) {
            $status = "suspended";
        }
        $disabled = (bool) ($state["disabled"] ?? false) || in_array($status, ["disabled", "suspended"], true);
        if (!$disabled) {
            $status = "open";
        } elseif ($status === "open") {
            $status = "disabled";
        }
        $reason = $this->clean((string) ($state["reason"] ?? ""), 80);
        if ($reason === "" && $status === "suspended") {
            $reason = "billing";
        }

        return [
            "schema" => $this->clean((string) ($state["schema"] ?? "fnlla.techayo_remote_control_state.v2"), 80),
            "disabled" => $disabled,
            "status" => $status,
            "reason" => $reason,
            "provider" => $this->clean((string) ($state["provider"] ?? config("developer_control.service_provider", "TechAyo Limited")), 120),
            "title" => $this->clean((string) ($state["title"] ?? $this->defaultTitle($status)), 120),
            "message" => $this->clean((string) ($state["message"] ?? $this->defaultMessage($status)), 240),
            "contact" => $this->clean((string) ($state["contact"] ?? ""), 160),
            "contact_phone" => $this->clean((string) ($state["contact_phone"] ?? $state["phone"] ?? ""), 60),
            "updated_at" => $this->clean((string) ($state["updated_at"] ?? ""), 80),
            "updated_by" => $this->clean((string) ($state["updated_by"] ?? "techayo-control"), 120),
            "command_id" => $this->clean((string) ($state["command_id"] ?? ""), 120),
            "expires_at" => $this->clean((string) ($state["expires_at"] ?? ""), 80),
        ];
    }

    private function normaliseStatus(string $status): string
    {
        $status = strtolower(trim(str_replace("-", "_", $status)));

        return match ($status) {
            "suspend", "suspended", "billing", "billing_suspended", "payment_required" => "suspended",
            "disable", "disabled", "locked", "closed" => "disabled",
            default => "open",
        };
    }

    private function remoteEndpointAllowed(string $endpoint): bool
    {
        if ($endpoint === "") {
            return false;
        }

        $parts = parse_url($endpoint);
        $scheme = strtolower((string) ($parts["scheme"] ?? ""));
        $host = strtolower((string) ($parts["host"] ?? ""));

        if ($scheme !== "https" || $host === "") {
            return false;
        }

        $allowedHosts = array_map("strtolower", (array) config("developer_control.remote.allowed_hosts", []));

        return in_array($host, $allowedHosts, true);
    }

    private function remoteHeaders(): string
    {
        $token = trim((string) config("developer_control.remote.token", ""));
        $projectId = trim((string) config("developer_control.remote.project_id", ""));
        $tenant = trim((string) config("developer_control.remote.tenant", "techayo"));
        $schema = trim((string) config("developer_control.remote.schema", "fnlla.techayo_remote_control.v1"));
        $timestamp = (string) time();
        $signatureSecret = trim((string) config("developer_control.remote.signature_secret", ""));
        $headers = [
            "Accept: application/json",
            "X-FNLLA-Control-Schema: " . $schema,
            "X-FNLLA-Control-Tenant: " . $this->headerValue($tenant),
            "X-FNLLA-Control-Project: " . $this->headerValue($projectId),
            "X-FNLLA-Control-Timestamp: " . $timestamp,
        ];

        if ($token !== "") {
            $headers[] = "Authorization: Bearer " . $this->headerValue($token);
        }

        if ($signatureSecret !== "" && $projectId !== "") {
            $payload = implode("|", [$schema, $tenant, $projectId, $timestamp]);
            $headers[] = "X-FNLLA-Control-Signature: sha256=" . hash_hmac("sha256", $payload, $signatureSecret);
        }

        return implode("\r\n", $headers) . "\r\n";
    }

    private function remoteContract(): array
    {
        return [
            "schema" => (string) config("developer_control.remote.schema", "fnlla.techayo_remote_control.v1"),
            "enabled" => (bool) config("developer_control.remote.enabled", false),
            "endpoint" => $this->redactEndpoint((string) config("developer_control.remote.endpoint", "")),
            "project_id" => (string) config("developer_control.remote.project_id", ""),
            "tenant" => (string) config("developer_control.remote.tenant", "techayo"),
            "signed" => trim((string) config("developer_control.remote.signature_secret", "")) !== "",
            "allowed_hosts" => array_values((array) config("developer_control.remote.allowed_hosts", [])),
            "expected_response" => [
                "schema" => "fnlla.techayo_remote_control_state.v2",
                "status" => "open|disabled|suspended",
                "disabled" => "bool, accepted for v1 compatibility",
                "reason" => "string, for example billing",
                "provider" => "service provider label",
                "title" => "string",
                "message" => "string",
                "contact" => "string",
                "contact_phone" => "optional phone string",
                "updated_at" => "ISO-8601 string",
                "updated_by" => "operator label",
                "command_id" => "optional idempotency/audit identifier",
                "expires_at" => "optional ISO-8601 string",
            ],
        ];
    }

    private function defaultTitle(string $status): string
    {
        return $status === "suspended"
            ? (string) config("developer_control.suspended_title", "Service has been suspended")
            : (string) config("developer_control.disabled_title", "Service paused by developer");
    }

    private function defaultMessage(string $status): string
    {
        return $status === "suspended"
            ? (string) config("developer_control.suspended_message", "Your services have been suspended. Please contact your service provider.")
            : (string) config("developer_control.disabled_message", "");
    }

    private function defaultContactPhone(string $status): string
    {
        return $status === "suspended"
            ? (string) config("developer_control.suspended_contact_phone", "")
            : (string) config("developer_control.disabled_contact_phone", "");
    }

    private function headerValue(string $value): string
    {
        return str_replace(["\r", "\n"], "", $value);
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

    private function writeLocal(array $state): void
    {
        $path = $this->localPath();
        $directory = dirname($path);

        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        file_put_contents($path, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL, LOCK_EX);
    }

    private function readJson(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    private function localPath(): string
    {
        return storage_path((string) config("developer_control.local_state_path", "framework/developer/control.json"));
    }

    private function remoteCachePath(): string
    {
        return storage_path((string) config("developer_control.remote.cache_path", "framework/developer/remote-control.json"));
    }

    private function clean(string $value, int $maxLength): string
    {
        $value = trim((string) preg_replace('/\s+/', ' ', $value));

        return strlen($value) <= $maxLength ? $value : substr($value, 0, $maxLength);
    }
}
