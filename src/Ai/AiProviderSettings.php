<?php

declare(strict_types=1);

namespace Fnlla\Php\Ai;

use RuntimeException;

/** Validates panel input without persisting, echoing or contacting a provider. */
final class AiProviderSettings
{
    public function values(array $input): array
    {
        $driver = $this->string($input, "ai_runtime_driver");
        if (!in_array($driver, RuntimeAiProviderRegistry::ALLOWED_DRIVERS, true)) {
            throw new RuntimeException("Select a supported AI provider.");
        }
        $values = ["AI_RUNTIME_ENABLED" => $this->string($input, "ai_runtime_enabled") === "1", "AI_RUNTIME_DRIVER" => $driver];
        foreach (["openai", "anthropic"] as $provider) {
            $field = "ai_" . $provider . "_";
            $prefix = strtoupper($field);
            $enabled = $this->string($input, $field . "enabled") === "1";
            $model = $this->string($input, $field . "model");
            $key = $this->string($input, $field . "api_key");
            $remove = $this->string($input, $field . "remove_key") === "1";
            if ($remove && $key !== "") {
                throw new RuntimeException("Choose either replacing or removing a provider key.");
            }
            $key = $remove ? "" : ($key !== "" ? $key : (string) config("ai.runtime." . $provider . ".api_key", ""));
            if (($model !== "" && preg_match('/\A[A-Za-z0-9][A-Za-z0-9._:\/-]{0,159}\z/D', $model) !== 1)
                || ($key !== "" && preg_match('/\A[\x21-\x7E]{8,512}\z/D', $key) !== 1)) {
                throw new RuntimeException("Provider model or API key has an invalid format.");
            }
            $limit = filter_var($this->string($input, $field . "max_output_tokens"), FILTER_VALIDATE_INT);
            $timeout = filter_var($this->string($input, $field . "timeout_seconds"), FILTER_VALIDATE_INT);
            if ($limit === false || $limit < 64 || $limit > 8192 || $timeout === false || $timeout < 1 || $timeout > 60) {
                throw new RuntimeException("Output limit must be 64-8192 tokens and timeout 1-60 seconds.");
            }
            if ($enabled && ($key === "" || $model === "" || !extension_loaded("curl"))) {
                throw new RuntimeException("Enabling a cloud provider requires a model, API key and PHP cURL.");
            }
            if ($values["AI_RUNTIME_ENABLED"] && $driver === $provider && !$enabled) {
                throw new RuntimeException("Enable the selected provider or select Local.");
            }
            $values += [$prefix . "ENABLED" => $enabled, $prefix . "MODEL" => $model,
                $prefix . "API_KEY" => $key, $prefix . "MAX_OUTPUT_TOKENS" => $limit, $prefix . "TIMEOUT_SECONDS" => $timeout];
        }
        if ($values["AI_RUNTIME_ENABLED"] && $driver === "fionn" && !((new FionnRuntimeBridge())->status()["provider_ready"] ?? false)) {
            throw new RuntimeException("Configure and enable the separate FIONN AI bridge before selecting it.");
        }
        return $values;
    }

    public function apply(array $values): void
    {
        config_set("ai.runtime.enabled", $values["AI_RUNTIME_ENABLED"]);
        config_set("ai.runtime.driver", $values["AI_RUNTIME_DRIVER"]);
        foreach (["openai", "anthropic"] as $provider) {
            foreach (["enabled", "model", "api_key", "max_output_tokens", "timeout_seconds"] as $field) {
                config_set("ai.runtime." . $provider . "." . $field, $values[strtoupper("AI_" . $provider . "_" . $field)]);
            }
        }
    }

    private function string(array $input, string $key): string
    {
        if (!is_string($input[$key] ?? "")) {
            throw new RuntimeException("AI settings must contain scalar text values.");
        }
        return trim($input[$key] ?? "");
    }
}
