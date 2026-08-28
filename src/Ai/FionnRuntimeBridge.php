<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA AI SOURCE
File: src\Ai\FionnRuntimeBridge.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

Purpose:
- Reserves a stable FNLLA-side adapter boundary for a future Fionn AI provider
  without coupling FNLLA to the Fionn codebase or network API.
*/

namespace Fnlla\Php\Ai;

use RuntimeException;

final class FionnRuntimeBridge implements RuntimeAiProviderInterface
{
    public function answer(string $input, array $context = []): array
    {
        throw new RuntimeException("Fionn AI bridge is reserved but not integrated. Keep AI_RUNTIME_DRIVER=local until a reviewed adapter is implemented.");
    }

    public function status(): array
    {
        $config = (array) config("ai.runtime.fionn", []);
        $configuredEnabled = ($config["enabled"] ?? false) === true;
        $endpointConfigured = trim((string) ($config["endpoint"] ?? "")) !== "";

        return [
            "schema" => "fnlla.runtime_ai.provider.fionn.v1",
            "driver" => "fionn",
            "enabled" => false,
            "configured_enabled" => $configuredEnabled,
            "provider_ready" => false,
            "external_calls" => false,
            "integration_state" => "reserved",
            "allowed_operations" => [
                "status_contract_only",
            ],
            "contract" => [
                "expected_capabilities" => [
                    "chat",
                    "reviewed_knowledge",
                    "controlled_learning",
                    "model_status",
                    "eval_summary",
                ],
                "forbidden_until_integrated" => [
                    "network_calls",
                    "training_mutations",
                    "learning_queue_writes",
                    "secret_forwarding",
                ],
                "required_before_enablement" => [
                    "stable_request_response_schema",
                    "authentication_policy",
                    "redaction_policy",
                    "prompt_registry",
                    "eval_fixtures",
                    "cost_token_latency_accounting",
                    "operator_approval_controls",
                ],
            ],
            "endpoint_configured" => $endpointConfigured,
            "timeout_seconds" => max(1, (int) ($config["timeout_seconds"] ?? 10)),
        ];
    }
}
