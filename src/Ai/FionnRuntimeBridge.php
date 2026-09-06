<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA AI SOURCE
File: src\Ai\FionnRuntimeBridge.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

Purpose:
- Provides an opt-in HTTP boundary to a separate Fionn AI service without
  coupling FNLLA to Fionn source, model files, memory, queues or private data.
*/

namespace Fnlla\Php\Ai;

use RuntimeException;

final class FionnRuntimeBridge implements RuntimeAiProviderInterface
{
    private const SENSITIVE_KEY_PATTERN = '/(password|secret|token|cookie|session|credential|key|authorization|csrf|private|signature)/i';
    private mixed $transport;

    public function __construct(?callable $transport = null)
    {
        $this->transport = $transport;
    }

    public function answer(string $input, array $context = []): array
    {
        $startedAt = microtime(true);
        $config = $this->config();
        $input = $this->normaliseText($input, (int) config("ai.runtime.max_input_chars", 2000));

        if ($input === "") {
            throw new RuntimeException("Fionn AI bridge requires a non-empty input.");
        }

        $endpoint = $this->resolveEndpoint($config);
        $policy = $this->endpointPolicy($endpoint, $config);

        if (($config["enabled"] ?? false) !== true) {
            throw new RuntimeException("Fionn AI bridge is disabled. Set AI_FIONN_BRIDGE_ENABLED=true and AI_RUNTIME_DRIVER=fionn after reviewing the endpoint policy.");
        }

        if (!$policy["allowed"]) {
            throw new RuntimeException("Fionn AI bridge is not allowed: " . $policy["reason"]);
        }

        $payload = [
            "schema" => "fnlla.fionn_bridge.request.v1",
            "message" => $input,
            "learning_mode" => false,
            "reply_mode" => $this->mode((string) ($config["reply_mode"] ?? "memory_assisted"), ["memory_assisted", "model_only"], "memory_assisted"),
            "knowledge_mode" => $this->mode((string) ($config["knowledge_mode"] ?? "auto"), ["auto", "strict_knowledge", "creative"], "auto"),
            "context" => $this->safeContext($context, (int) ($config["max_context_chars"] ?? 2000)),
            "client" => [
                "name" => "fnlla",
                "schema" => "fnlla.runtime_ai.answer.v1",
                "external_learning" => false,
            ],
        ];

        $response = $this->postJson($endpoint, $payload, $config);
        $statusCode = (int) ($response["status"] ?? 0);
        $body = json_decode((string) ($response["body"] ?? ""), true);

        if (!is_array($body)) {
            throw new RuntimeException("Fionn AI bridge returned invalid JSON.");
        }

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new RuntimeException("Fionn AI bridge request failed: " . (string) ($body["error"] ?? ("HTTP " . $statusCode)));
        }

        $reply = $this->normaliseText((string) ($body["reply"] ?? $body["answer"] ?? ""), 8000);

        if ($reply === "") {
            throw new RuntimeException("Fionn AI bridge returned an empty reply.");
        }

        $replySource = $this->normaliseText((string) ($body["reply_source"] ?? "fionn"), 120);

        return [
            "schema" => "fnlla.runtime_ai.answer.v1",
            "driver" => "fionn",
            "input" => $input,
            "answer" => $reply,
            "confidence" => $this->confidence($body),
            "intent" => "fionn.remote",
            "title" => "Fionn",
            "actions" => [],
            "sources" => [$replySource !== "" ? "fionn:" . $replySource : "fionn"],
            "context" => $payload["context"],
            "usage" => $this->usage($input, $reply),
            "estimated_cost_gbp" => 0.0,
            "latency_ms" => $this->latencyMs($startedAt),
            "provider" => [
                "schema" => "fnlla.fionn_bridge.response.v1",
                "reply_source" => $replySource,
                "reply_mode" => $this->normaliseText((string) ($body["reply_mode"] ?? $payload["reply_mode"]), 80),
                "knowledge_mode" => $this->normaliseText((string) ($body["knowledge_mode"] ?? $payload["knowledge_mode"]), 80),
                "learning_mode" => false,
                "privacy" => is_array($body["privacy"] ?? null) ? (array) $body["privacy"] : [],
                "grounding" => is_array($body["grounding"] ?? null) ? (array) $body["grounding"] : [],
            ],
        ];
    }

    public function status(): array
    {
        $config = $this->config();
        $configuredEnabled = ($config["enabled"] ?? false) === true;
        $endpoint = $this->resolveEndpoint($config);
        $endpointConfigured = $endpoint !== "";
        $policy = $this->endpointPolicy($endpoint, $config);
        $ready = $configuredEnabled && $endpointConfigured && $policy["allowed"];

        return [
            "schema" => "fnlla.runtime_ai.provider.fionn.v1",
            "driver" => "fionn",
            "enabled" => $configuredEnabled,
            "configured_enabled" => $configuredEnabled,
            "provider_ready" => $ready,
            "external_calls" => $ready,
            "integration_state" => $ready ? "ready" : ($configuredEnabled ? "misconfigured" : "available_opt_in"),
            "allowed_operations" => $ready ? ["chat"] : ["status_contract_only"],
            "contract" => [
                "expected_capabilities" => [
                    "chat",
                    "model_status",
                ],
                "forbidden_operations" => [
                    "training_mutations",
                    "learning_queue_writes",
                    "secret_forwarding",
                    "session_cookie_forwarding",
                    "admin_endpoint_calls",
                ],
                "request_schema" => "fnlla.fionn_bridge.request.v1",
                "response_schema" => "fnlla.fionn_bridge.response.v1",
                "http_method" => "POST",
                "allowed_path" => $this->normalisePath((string) ($config["chat_path"] ?? "/api/chat")),
                "redaction" => [
                    "sensitive_context_keys_dropped" => true,
                    "scalar_context_only" => true,
                    "max_context_chars" => max(0, (int) ($config["max_context_chars"] ?? 2000)),
                ],
            ],
            "endpoint_configured" => $endpointConfigured,
            "endpoint_allowed" => $policy["allowed"],
            "endpoint_policy_reason" => $policy["reason"],
            "endpoint_host" => $policy["host"],
            "endpoint_scheme" => $policy["scheme"],
            "chat_path" => $this->normalisePath((string) ($config["chat_path"] ?? "/api/chat")),
            "token_configured" => trim((string) ($config["api_token"] ?? "")) !== "",
            "timeout_seconds" => max(1, (int) ($config["timeout_seconds"] ?? 10)),
            "accounting" => $this->accountingPolicy(),
        ];
    }

    private function config(): array
    {
        $config = config("ai.runtime.fionn", []);

        return is_array($config) ? $config : [];
    }

    private function resolveEndpoint(array $config): string
    {
        $base = trim((string) ($config["endpoint"] ?? ""));

        if ($base === "") {
            return "";
        }

        return rtrim($base, "/") . $this->normalisePath((string) ($config["chat_path"] ?? "/api/chat"));
    }

    private function endpointPolicy(string $endpoint, array $config): array
    {
        if ($endpoint === "") {
            return $this->policy(false, "Endpoint is not configured.");
        }

        $parts = parse_url($endpoint);

        if (!is_array($parts) || !is_string($parts["scheme"] ?? null) || !is_string($parts["host"] ?? null)) {
            return $this->policy(false, "Endpoint must be an absolute HTTP(S) URL.");
        }

        $scheme = strtolower((string) $parts["scheme"]);
        $host = strtolower(trim((string) $parts["host"], "[]"));
        $path = $this->normalisePath((string) ($parts["path"] ?? ""));
        $allowedPath = $this->normalisePath((string) ($config["chat_path"] ?? "/api/chat"));

        if (!in_array($scheme, ["http", "https"], true)) {
            return $this->policy(false, "Only HTTP(S) endpoints are supported.", $scheme, $host);
        }

        if (isset($parts["user"]) || isset($parts["pass"]) || isset($parts["query"]) || isset($parts["fragment"])) {
            return $this->policy(false, "Endpoint must not contain userinfo, query strings or fragments.", $scheme, $host);
        }

        if ($path !== $allowedPath || str_starts_with($path, "/api/learn") || str_starts_with($path, "/admin")) {
            return $this->policy(false, "Only the configured chat endpoint is allowed.", $scheme, $host);
        }

        $local = $this->isLocalHost($host);

        if ($scheme !== "https" && !($local && ($config["allow_insecure_localhost"] ?? true) === true)) {
            return $this->policy(false, "Endpoint must use HTTPS outside localhost.", $scheme, $host);
        }

        $allowedHosts = $this->allowedHosts((array) ($config["allowed_hosts"] ?? []));

        if ($allowedHosts !== [] && !in_array($host, $allowedHosts, true)) {
            return $this->policy(false, "Endpoint host is not in AI_FIONN_ALLOWED_HOSTS.", $scheme, $host);
        }

        if (!$local && trim((string) ($config["api_token"] ?? "")) === "") {
            return $this->policy(false, "Non-local Fionn endpoints require AI_FIONN_API_TOKEN.", $scheme, $host);
        }

        return $this->policy(true, "Endpoint policy passed.", $scheme, $host);
    }

    private function policy(bool $allowed, string $reason, string $scheme = "", string $host = ""): array
    {
        return [
            "allowed" => $allowed,
            "reason" => $reason,
            "scheme" => $scheme,
            "host" => $host,
        ];
    }

    private function postJson(string $endpoint, array $payload, array $config): array
    {
        $headers = [
            "Content-Type: application/json",
            "Accept: application/json",
            "User-Agent: FNLLA-FionnBridge/1.0",
        ];
        $token = trim((string) ($config["api_token"] ?? ""));

        if ($token !== "") {
            $headers[] = "Authorization: Bearer " . $token;
        }

        if (is_callable($this->transport)) {
            return (array) call_user_func($this->transport, $endpoint, $payload, $headers, max(1, (int) ($config["timeout_seconds"] ?? 10)));
        }

        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $httpResponseHeaders = [];
        $stream = stream_context_create([
            "http" => [
                "method" => "POST",
                "header" => implode("\r\n", $headers),
                "content" => $body,
                "timeout" => max(1, (int) ($config["timeout_seconds"] ?? 10)),
                "ignore_errors" => true,
            ],
        ]);
        $http_response_header = [];
        $raw = @file_get_contents($endpoint, false, $stream);
        $httpResponseHeaders = $http_response_header;

        if ($raw === false) {
            throw new RuntimeException("Fionn AI bridge request could not be completed.");
        }

        return [
            "status" => $this->statusCode($httpResponseHeaders),
            "body" => $raw,
            "headers" => $httpResponseHeaders,
        ];
    }

    private function statusCode(array $headers): int
    {
        foreach ($headers as $header) {
            if (preg_match('/^HTTP\/\S+\s+(\d+)/', (string) $header, $matches) === 1) {
                return (int) $matches[1];
            }
        }

        return 200;
    }

    private function safeContext(array $context, int $limit): array
    {
        if ($limit <= 0) {
            return [];
        }

        $safe = [];
        $used = 0;

        foreach ($context as $key => $value) {
            if ($used >= $limit) {
                break;
            }

            if (!is_scalar($value) && $value !== null) {
                continue;
            }

            $key = $this->normaliseKey((string) $key);

            if ($key === "" || preg_match(self::SENSITIVE_KEY_PATTERN, $key) === 1) {
                continue;
            }

            $value = $this->normaliseText((string) $value, min(240, max(1, $limit - $used)));

            if ($value === "" && $used >= $limit) {
                break;
            }

            $safe[$key] = $value;
            $used += strlen($key) + strlen($value);

            if ($used >= $limit) {
                break;
            }
        }

        return $safe;
    }

    private function allowedHosts(array $hosts): array
    {
        return array_values(array_unique(array_filter(array_map(
            static fn (mixed $host): string => strtolower(trim((string) $host, " []\t\n\r\0\x0B")),
            $hosts
        ))));
    }

    private function normalisePath(string $path): string
    {
        $path = "/" . ltrim(trim($path), "/");

        return $path === "/" ? "/api/chat" : $path;
    }

    private function normaliseText(string $value, int $limit): string
    {
        $value = trim(strip_tags(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]+/', " ", $value) ?? ""));

        return substr($value, 0, max(1, $limit));
    }

    private function normaliseKey(string $key): string
    {
        return trim(strtolower(preg_replace('/[^a-zA-Z0-9_.-]+/', "-", $key) ?? ""), "-");
    }

    private function mode(string $value, array $allowed, string $fallback): string
    {
        $value = trim($value);

        return in_array($value, $allowed, true) ? $value : $fallback;
    }

    private function isLocalHost(string $host): bool
    {
        return in_array($host, ["localhost", "127.0.0.1", "::1"], true);
    }

    private function confidence(array $body): int
    {
        if (is_numeric($body["confidence"] ?? null)) {
            return max(0, min(100, (int) $body["confidence"]));
        }

        return 60;
    }

    private function usage(string $input, string $output): array
    {
        $inputTokens = count($this->tokens($input));
        $outputTokens = count($this->tokens($output));

        return [
            "input_tokens" => $inputTokens,
            "output_tokens" => $outputTokens,
            "total_tokens" => $inputTokens + $outputTokens,
            "metering" => "fionn-estimate",
        ];
    }

    private function tokens(string $value): array
    {
        $value = strtolower($value);
        preg_match_all('/[a-z0-9][a-z0-9_-]{1,}/', $value, $matches);

        return array_values(array_unique($matches[0]));
    }

    private function latencyMs(float $startedAt): int
    {
        return max(0, (int) round((microtime(true) - $startedAt) * 1000));
    }

    private function accountingPolicy(): array
    {
        return [
            "token_fields" => ["input_tokens", "output_tokens", "total_tokens"],
            "cost_field" => "estimated_cost_gbp",
            "latency_field" => "latency_ms",
            "remote_provider_required" => true,
        ];
    }
}
