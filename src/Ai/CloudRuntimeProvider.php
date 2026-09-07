<?php

declare(strict_types=1);

namespace Fnlla\Php\Ai;

use RuntimeException;
use Throwable;

/** Text-only, explicitly enabled providers. Never imports application context. */
abstract class CloudRuntimeProvider implements RuntimeAiProviderInterface
{
    protected const DRIVER = "";
    protected const ENDPOINT = "";
    private const RESPONSE_LIMIT = 1048576;

    private mixed $transport;

    public function __construct(?callable $transport = null)
    {
        $this->transport = $transport;
    }

    abstract protected function payload(string $input, string $model, int $limit): array;
    abstract protected function headers(string $key): array;
    abstract protected function responseText(array $response): string;

    public function status(): array
    {
        $settings = $this->settings();
        $enabled = (bool) ($settings["enabled"] ?? false);
        $valid = $this->validSettings($settings);
        $transportReady = is_callable($this->transport) || extension_loaded("curl");
        $ready = $enabled && $valid && $transportReady;

        return [
            "schema" => "fnlla.runtime_ai.provider_status.v1",
            "driver" => static::DRIVER,
            "enabled" => $enabled,
            "provider_ready" => $ready,
            "external_calls" => $ready,
            "endpoint_allowed" => true,
            "endpoint_host" => parse_url(static::ENDPOINT, PHP_URL_HOST),
            "model" => $valid ? (string) $settings["model"] : "",
            "token_configured" => (string) ($settings["api_key"] ?? "") !== "",
            "integration_state" => !$enabled ? "available_opt_in" : ($ready ? "configured" : "misconfigured"),
            "reason" => !$enabled ? "Disabled; no external requests." : ($ready
                ? "Configured locally; live provider access has not been tested."
                : "A valid model, API key and the PHP cURL extension are required."),
            "learning_enabled" => false,
            "context_forwarded" => false,
        ];
    }

    public function answer(string $input, array $context = []): array
    {
        if (!(bool) config("ai.runtime.enabled", true) || !($this->status()["provider_ready"] ?? false)) {
            throw new RuntimeException("AI provider is disabled or not configured.");
        }

        $input = trim($input);
        $maxInput = max(32, min(32000, (int) config("ai.runtime.max_input_chars", 2000)));
        if ($input === "" || strlen($input) > $maxInput * 4 || preg_match('//u', $input) !== 1
            || preg_match_all('/./us', $input) > $maxInput) {
            throw new RuntimeException("AI input must be non-empty UTF-8 text within the configured character limit.");
        }

        $settings = $this->settings();
        $model = (string) $settings["model"];
        $limit = max(64, min(8192, (int) ($settings["max_output_tokens"] ?? 1024)));
        $timeout = max(1, min(60, (int) ($settings["timeout_seconds"] ?? 30)));
        $started = hrtime(true);

        // Context may contain diagnostics or credentials. Only the explicit input crosses this boundary.
        $payload = $this->payload($input, $model, $limit);
        $headers = array_merge(["Content-Type: application/json", "Accept: application/json", "User-Agent: FNLLA-AI/2.2"], $this->headers((string) $settings["api_key"]));
        try {
            $response = is_callable($this->transport)
                ? ($this->transport)(static::ENDPOINT, $payload, $headers, $timeout)
                : $this->post($payload, $headers, $timeout);
        } catch (Throwable) {
            // Transport errors can include credentials, URLs or upstream bodies. Do not propagate them.
            throw new RuntimeException("AI provider connection failed; check connectivity and server-side configuration.");
        }

        $status = (int) ($response["status"] ?? 0);
        if ($status < 200 || $status >= 300) {
            throw new RuntimeException($status === 429
                ? "AI provider rate or spending limit reached; no automatic retry was made."
                : "AI provider rejected the request (HTTP " . ($status >= 100 && $status <= 599 ? $status : 0) . ").");
        }
        $body = $response["body"] ?? "";
        if (!is_string($body) || strlen($body) > self::RESPONSE_LIMIT) {
            throw new RuntimeException("AI provider response exceeded the size limit.");
        }
        $decoded = json_decode($body, true, 64);
        if (!is_array($decoded)) {
            throw new RuntimeException("AI provider returned invalid JSON.");
        }
        $answer = trim($this->responseText($decoded));
        if ($answer === "" || preg_match_all('/./us', $answer) > 64000) {
            throw new RuntimeException("AI provider returned no complete text answer within the size limit.");
        }
        $usage = is_array($decoded["usage"] ?? null) ? $decoded["usage"] : [];
        $inputTokens = $this->tokenCount($usage, "input_tokens");
        $outputTokens = $this->tokenCount($usage, "output_tokens");
        // Claude Platform accounts for cached input separately, including automatic caching.
        if (static::DRIVER === "anthropic" && $inputTokens !== null) {
            $inputTokens += ($this->tokenCount($usage, "cache_creation_input_tokens") ?? 0)
                + ($this->tokenCount($usage, "cache_read_input_tokens") ?? 0);
        }

        return [
            "schema" => "fnlla.runtime_ai.answer.v1",
            "driver" => static::DRIVER,
            "input" => $input,
            "answer" => $answer,
            "confidence" => 0,
            "intent" => static::DRIVER . ".text",
            "title" => static::DRIVER === "openai" ? "OpenAI API" : "Anthropic API",
            "actions" => [],
            "sources" => [],
            "context" => [],
            "usage" => ["input_tokens" => $inputTokens, "output_tokens" => $outputTokens,
                "total_tokens" => $inputTokens !== null && $outputTokens !== null ? $inputTokens + $outputTokens : null],
            "estimated_cost_gbp" => null,
            "latency_ms" => round((hrtime(true) - $started) / 1000000, 2),
            "provider" => ["model" => $model, "external_calls" => true, "grounded" => false,
                "confidence_measured" => false, "context_forwarded" => false],
        ];
    }

    private function settings(): array
    {
        return (array) config("ai.runtime." . static::DRIVER, []);
    }

    private function validSettings(array $settings): bool
    {
        return preg_match('/\A[A-Za-z0-9][A-Za-z0-9._:\/-]{0,159}\z/D', (string) ($settings["model"] ?? "")) === 1
            && preg_match('/\A[\x21-\x7E]{8,512}\z/D', (string) ($settings["api_key"] ?? "")) === 1;
    }

    private function tokenCount(array $usage, string $key): ?int
    {
        return is_int($usage[$key] ?? null) && $usage[$key] >= 0 && $usage[$key] <= 100000000 ? $usage[$key] : null;
    }

    private function post(array $payload, array $headers, int $timeout): array
    {
        $handle = curl_init(static::ENDPOINT);
        if ($handle === false) {
            throw new RuntimeException("AI transport unavailable.");
        }
        $body = "";
        try {
            curl_setopt_array($handle, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($payload, JSON_THROW_ON_ERROR),
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_CONNECTTIMEOUT => min(10, $timeout),
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$body): int {
                    if (strlen($body) + strlen($chunk) > self::RESPONSE_LIMIT) {
                        return 0;
                    }
                    $body .= $chunk;
                    return strlen($chunk);
                },
            ]);
            if (curl_exec($handle) === false) {
                throw new RuntimeException("AI transport failed.");
            }
            return ["status" => (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE), "body" => $body];
        } finally {
            curl_close($handle);
        }
    }
}
