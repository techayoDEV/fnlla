<?php

declare(strict_types=1);

namespace Fnlla\Php\Ai;

/** Anthropic API adapter; the class and driver names remain compatibility identifiers. */
final class AnthropicRuntimeProvider extends CloudRuntimeProvider
{
    protected const DRIVER = "anthropic";
    protected const ENDPOINT = "https://api.anthropic.com/v1/messages";

    protected function payload(string $input, string $model, int $limit): array
    {
        return ["model" => $model, "messages" => [["role" => "user", "content" => $input]], "max_tokens" => $limit, "stream" => false];
    }

    protected function headers(string $key): array
    {
        return ["Authorization: Bearer " . $key, "anthropic-version: 2023-06-01"];
    }

    protected function responseText(array $response): string
    {
        if (($response["type"] ?? "") !== "message" || ($response["role"] ?? "") !== "assistant"
            || ($response["stop_details"]["type"] ?? "") === "refusal"
            || !in_array($response["stop_reason"] ?? "", ["end_turn", "stop_sequence"], true)) {
            return "";
        }
        $parts = [];
        foreach ((array) ($response["content"] ?? []) as $content) {
            if (is_array($content) && ($content["type"] ?? "") === "text" && is_string($content["text"] ?? null)) {
                $parts[] = $content["text"];
            }
        }
        return implode("\n", $parts);
    }
}
