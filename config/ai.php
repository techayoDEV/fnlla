<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA CONFIGURATION FILE
File: config\ai.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

Purpose:
- Defines local runtime intelligence settings for applications that need
  user-facing guidance without depending on an external model provider.
*/

return [
    "runtime" => [
        "enabled" => (bool) env("AI_RUNTIME_ENABLED", true),
        "driver" => (string) env("AI_RUNTIME_DRIVER", "local"),
        "runtime_path" => (string) env("AI_RUNTIME_PATH", "resources/fnlla-ai-runtime"),
        "load_integrated_runtime" => (bool) env("AI_RUNTIME_LOAD_INTEGRATED", true),
        "learning_enabled" => (bool) env("AI_RUNTIME_LEARNING_ENABLED", false),
        "learning_path" => (string) env("AI_RUNTIME_LEARNING_PATH", "framework/ai/runtime-knowledge.json"),
        "prompt_registry_path" => (string) env("AI_RUNTIME_PROMPT_REGISTRY_PATH", "resources/fnlla-ai-runtime/prompts/registry.json"),
        "evals_path" => (string) env("AI_RUNTIME_EVALS_PATH", "resources/fnlla-ai-runtime/evals"),
        "max_input_chars" => max(32, (int) env("AI_RUNTIME_MAX_INPUT_CHARS", 2000)),
        "confidence_threshold" => max(0, min(100, (int) env("AI_RUNTIME_CONFIDENCE_THRESHOLD", 35))),
        "profile" => [
            "name" => (string) env("AI_RUNTIME_NAME", "FNLLA Assistant"),
            "direction" => (string) env("AI_RUNTIME_DIRECTION", "Answer from the configured local project knowledge only."),
            "fallback" => (string) env("AI_RUNTIME_FALLBACK", "I do not know that from the local project knowledge yet."),
        ],
        "providers" => [
            "local" => [
                "class" => \Fnlla\Php\Ai\LocalRuntimeAssistant::class,
                "external_calls" => false,
            ],
            "fionn" => [
                "class" => \Fnlla\Php\Ai\FionnRuntimeBridge::class,
                "external_calls" => false,
                "enabled" => (bool) env("AI_FIONN_BRIDGE_ENABLED", false),
                "integration_state" => "available_opt_in",
            ],
        ],
        "fionn" => [
            "enabled" => (bool) env("AI_FIONN_BRIDGE_ENABLED", false),
            "endpoint" => (string) env("AI_FIONN_ENDPOINT", ""),
            "chat_path" => (string) env("AI_FIONN_CHAT_PATH", "/api/chat"),
            "api_token" => (string) env("AI_FIONN_API_TOKEN", ""),
            "allowed_hosts" => array_values(array_filter(array_map("trim", explode(",", (string) env("AI_FIONN_ALLOWED_HOSTS", "127.0.0.1,localhost"))))),
            "allow_insecure_localhost" => (bool) env("AI_FIONN_ALLOW_INSECURE_LOCALHOST", true),
            "timeout_seconds" => max(1, (int) env("AI_FIONN_TIMEOUT_SECONDS", 10)),
            "max_context_chars" => max(0, (int) env("AI_FIONN_MAX_CONTEXT_CHARS", 2000)),
            "reply_mode" => (string) env("AI_FIONN_REPLY_MODE", "memory_assisted"),
            "knowledge_mode" => (string) env("AI_FIONN_KNOWLEDGE_MODE", "auto"),
        ],
        "intents" => [],
        "knowledge" => [],
    ],
];
