<?php

declare(strict_types=1);

namespace Fnlla\Php\Ai;

final class OpenAiRuntimeProvider extends CloudRuntimeProvider
{
    protected const DRIVER = "openai";
    protected const ENDPOINT = "https://api.openai.com/v1/responses";

    protected function payload(string $input, string $model, int $limit): array
    {
        return ["model" => $model, "input" => $input, "max_output_tokens" => $limit, "store" => false, "stream" => false];
    }

    protected function headers(string $key): array
    {
        return ["Authorization: Bearer " . $key];
    }

    protected function responseText(array $response): string
    {
        if (($response["status"] ?? "") !== "completed") {
            return "";
        }
        $parts = [];
        foreach ((array) ($response["output"] ?? []) as $item) {
            if (!is_array($item) || ($item["type"] ?? "") !== "message" || ($item["role"] ?? "") !== "assistant") {
                continue;
            }
            foreach ((array) ($item["content"] ?? []) as $content) {
                if (is_array($content) && ($content["type"] ?? "") === "refusal") {
                    return "";
                }
                if (is_array($content) && ($content["type"] ?? "") === "output_text" && is_string($content["text"] ?? null)) {
                    $parts[] = $content["text"];
                }
            }
        }
        return implode("\n", $parts);
    }
}
