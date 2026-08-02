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
        "enabled" => (bool) env("AI_RUNTIME_ENABLED", false),
        "driver" => (string) env("AI_RUNTIME_DRIVER", "local"),
        "runtime_path" => (string) env("AI_RUNTIME_PATH", "resources/fnlla-ai-runtime"),
        "load_integrated_runtime" => (bool) env("AI_RUNTIME_LOAD_INTEGRATED", true),
        "learning_enabled" => (bool) env("AI_RUNTIME_LEARNING_ENABLED", false),
        "learning_path" => (string) env("AI_RUNTIME_LEARNING_PATH", "framework/ai/runtime-knowledge.json"),
        "max_input_chars" => max(32, (int) env("AI_RUNTIME_MAX_INPUT_CHARS", 2000)),
        "confidence_threshold" => max(0, min(100, (int) env("AI_RUNTIME_CONFIDENCE_THRESHOLD", 35))),
        "profile" => [
            "name" => (string) env("AI_RUNTIME_NAME", "FNLLA Assistant"),
            "direction" => (string) env("AI_RUNTIME_DIRECTION", "Answer from the configured local project knowledge only."),
            "fallback" => (string) env("AI_RUNTIME_FALLBACK", "I do not know that from the local project knowledge yet."),
        ],
        "intents" => [],
        "knowledge" => [],
    ],
];
