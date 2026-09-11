<?php

declare(strict_types=1);

namespace Fnlla\Php\Support;

use Fnlla\Php\Ai\FionnRuntimeBridge;

final class DeveloperIntegrationRegistry
{
    public function all(): array
    {
        $fionn = (new FionnRuntimeBridge())->status();
        $remoteControl = (new TechAyoRemoteControlPlugin())->manifest();

        return [
            [
                "key" => "ai_provider_contract",
                "name" => "AI provider contract",
                "adapter" => "FIONN AI adapter",
                "status" => (string) ($fionn["integration_state"] ?? "available_opt_in"),
                "description" => "Neutral server-side AI provider slot. FIONN, OpenAI API and Anthropic API remain optional project choices.",
                "boundary" => "No remote model call is made unless an approved provider policy enables it and credentials are configured.",
                "consent_event" => "server-side policy",
                "external_calls" => (bool) ($fionn["external_calls"] ?? false),
                "optional" => true,
                "enabled_field" => "ai_fionn_enabled",
                "enabled" => (bool) config("ai.runtime.fionn.enabled", false),
                "settings_modal" => "developer-integration-fionn-settings",
            ],
            [
                "key" => "api_hook_contract",
                "name" => "API hook contract",
                "adapter" => "Generic API hooks",
                "status" => $this->integrationStatus((bool) config("integrations.api_hooks.enabled", false), (string) config("integrations.api_hooks.endpoint", "")),
                "description" => "Project-specific outbound webhook contract for consent updates or operational events.",
                "boundary" => "No webhook request is made unless the endpoint is configured and the adapter is enabled.",
                "consent_event" => "fnlla:cookies-updated",
                "external_calls" => (bool) config("integrations.api_hooks.enabled", false),
                "optional" => true,
                "enabled_field" => "fnlla_integration_api_hooks_enabled",
                "enabled" => (bool) config("integrations.api_hooks.enabled", false),
                "settings_modal" => "developer-integration-api-hooks-settings",
                "settings" => [
                    "endpoint" => $this->redactUrl((string) config("integrations.api_hooks.endpoint", "")),
                ],
            ],
            [
                "key" => "remote_control_contract",
                "name" => "Remote control contract",
                "adapter" => "TechAyo adapter",
                "status" => (string) ($remoteControl["status"] ?? "disabled"),
                "description" => "Optional control-plane contract for project status and operational toggles.",
                "boundary" => "No central-control request is made unless the remote contract is enabled and configured.",
                "consent_event" => "server-side policy",
                "external_calls" => (bool) ($remoteControl["enabled"] ?? false),
                "optional" => true,
                "enabled_field" => "developer_control_remote_enabled",
                "enabled" => (bool) ($remoteControl["enabled"] ?? false),
                "settings_modal" => "developer-integration-remote-control-settings",
                "contract" => $remoteControl,
            ],
        ];
    }

    private function integrationStatus(bool $enabled, string $requiredValue): string
    {
        if (!$enabled) {
            return "disabled";
        }

        return trim($requiredValue) !== "" ? "configured" : "needs configuration";
    }

    private function redactUrl(string $url): string
    {
        if (trim($url) === "") {
            return "";
        }

        $parts = parse_url($url);

        if (!is_array($parts)) {
            return "";
        }

        $scheme = (string) ($parts["scheme"] ?? "");
        $host = (string) ($parts["host"] ?? "");
        $path = (string) ($parts["path"] ?? "");

        return $scheme !== "" && $host !== "" ? $scheme . "://" . $host . $path : "";
    }
}
