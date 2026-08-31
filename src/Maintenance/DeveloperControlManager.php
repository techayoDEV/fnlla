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
        $disabled = (bool) ($remote["disabled"] ?? false) || (bool) ($local["disabled"] ?? false);
        $source = (bool) ($remote["disabled"] ?? false) ? "remote" : ((bool) ($local["disabled"] ?? false) ? "local" : "none");

        return [
            "disabled" => $disabled,
            "source" => $source,
            "title" => (string) (($remote["title"] ?? "") ?: ($local["title"] ?? config("developer_control.disabled_title", "Service disabled by developer"))),
            "message" => (string) (($remote["message"] ?? "") ?: ($local["message"] ?? config("developer_control.disabled_message", ""))),
            "contact" => (string) (($remote["contact"] ?? "") ?: ($local["contact"] ?? config("developer_control.disabled_contact", ""))),
            "updated_at" => (string) (($remote["updated_at"] ?? "") ?: ($local["updated_at"] ?? "")),
            "updated_by" => (string) (($remote["updated_by"] ?? "") ?: ($local["updated_by"] ?? "")),
            "remote_enabled" => (bool) config("developer_control.remote.enabled", false),
            "remote_contract" => $this->remoteContract(),
        ];
    }

    public function disabled(): bool
    {
        return (bool) $this->state()["disabled"];
    }

    public function disable(string $message = "", string $contact = "", array $developer = []): void
    {
        $this->writeLocal([
            "schema" => "fnlla.developer_control.v1",
            "disabled" => true,
            "title" => (string) config("developer_control.disabled_title", "Service disabled by developer"),
            "message" => trim($message) !== "" ? trim($message) : (string) config("developer_control.disabled_message", ""),
            "contact" => trim($contact) !== "" ? trim($contact) : (string) config("developer_control.disabled_contact", ""),
            "updated_at" => gmdate("c"),
            "updated_by" => (string) ($developer["email"] ?? "developer"),
        ]);
    }

    public function enable(array $developer = []): void
    {
        $this->writeLocal([
            "schema" => "fnlla.developer_control.v1",
            "disabled" => false,
            "title" => (string) config("developer_control.disabled_title", "Service disabled by developer"),
            "message" => "",
            "contact" => "",
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
            return $this->readJson($cachePath);
        }

        $endpoint = trim((string) config("developer_control.remote.endpoint", ""));

        if (!$this->remoteEndpointAllowed($endpoint)) {
            return (bool) config("developer_control.remote.fail_closed", false)
                ? ["disabled" => true, "source" => "remote", "message" => "Remote developer control endpoint is not allowed."]
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
                ? ["disabled" => true, "source" => "remote", "message" => "Remote developer control is unavailable."]
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
        return [
            "disabled" => (bool) ($state["disabled"] ?? false),
            "title" => $this->clean((string) ($state["title"] ?? ""), 120),
            "message" => $this->clean((string) ($state["message"] ?? ""), 240),
            "contact" => $this->clean((string) ($state["contact"] ?? ""), 160),
            "updated_at" => $this->clean((string) ($state["updated_at"] ?? ""), 80),
            "updated_by" => $this->clean((string) ($state["updated_by"] ?? "techayo-control"), 120),
        ];
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
                "schema" => "fnlla.techayo_remote_control_state.v1",
                "disabled" => "bool",
                "title" => "string",
                "message" => "string",
                "contact" => "string",
                "updated_at" => "ISO-8601 string",
                "updated_by" => "operator label",
            ],
        ];
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
