<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA AI SOURCE
File: src\Ai\LocalRuntimeAssistant.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

Purpose:
- Provides deterministic, project-owned runtime assistance from local
  configuration and approved knowledge records.
*/

namespace Fnlla\Php\Ai;

use RuntimeException;

final class LocalRuntimeAssistant
{
    public function answer(string $input, array $context = []): array
    {
        $config = $this->runtimeConfig();

        if (($config["enabled"] ?? false) !== true) {
            return $this->fallback("", "runtime_disabled", 0, $context);
        }

        if ((string) ($config["driver"] ?? "local") !== "local") {
            throw new RuntimeException("Unsupported runtime AI driver: " . (string) ($config["driver"] ?? ""));
        }

        $input = $this->normaliseInput($input, (int) ($config["max_input_chars"] ?? 2000));
        $tokens = $this->tokens($input);

        if ($tokens === []) {
            return $this->fallback($input, "empty_input", 0, $context);
        }

        $best = null;

        foreach ($this->knowledgeItems($config) as $item) {
            $score = $this->scoreItem($tokens, $item);

            if ($best === null || $score > (int) $best["score"]) {
                $best = [
                    "score" => $score,
                    "item" => $item,
                ];
            }
        }

        $threshold = (int) ($config["confidence_threshold"] ?? 35);

        if ($best === null || (int) $best["score"] < $threshold) {
            return $this->fallback($input, "low_confidence", $best !== null ? (int) $best["score"] : 0, $context);
        }

        $item = (array) $best["item"];

        return [
            "schema" => "fnlla.runtime_ai.answer.v1",
            "driver" => "local",
            "input" => $input,
            "answer" => (string) ($item["answer"] ?? ""),
            "confidence" => min(100, (int) $best["score"]),
            "intent" => (string) ($item["id"] ?? "knowledge"),
            "title" => (string) ($item["title"] ?? ""),
            "actions" => array_values(array_filter((array) ($item["actions"] ?? []), "is_string")),
            "sources" => [(string) ($item["source"] ?? $item["id"] ?? "local")],
            "context" => $this->safeContext($context),
        ];
    }

    public function remember(string $id, array $utterances, string $answer, array $metadata = []): array
    {
        $config = $this->runtimeConfig();

        if (($config["learning_enabled"] ?? false) !== true) {
            throw new RuntimeException("Runtime AI learning is disabled. Enable AI_RUNTIME_LEARNING_ENABLED only for approved project knowledge capture.");
        }

        $id = $this->normaliseId($id);
        $answer = $this->normaliseInput($answer, 5000);
        $utterances = array_values(array_filter(array_map(
            fn (mixed $utterance): string => $this->normaliseInput((string) $utterance, 240),
            $utterances
        ), static fn (string $utterance): bool => $utterance !== ""));

        if ($id === "" || $utterances === [] || $answer === "") {
            throw new RuntimeException("Runtime AI knowledge requires an id, at least one utterance and an answer.");
        }

        $items = $this->learnedItems($config);
        $items[$id] = [
            "id" => $id,
            "title" => (string) ($metadata["title"] ?? $id),
            "utterances" => $utterances,
            "answer" => $answer,
            "actions" => array_values(array_filter((array) ($metadata["actions"] ?? []), "is_string")),
            "updated_at_utc" => gmdate(DATE_ATOM),
        ];

        $path = $this->learningPath($config);
        $directory = dirname($path);

        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        file_put_contents($path, json_encode(array_values($items), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL, LOCK_EX);

        return $items[$id];
    }

    private function runtimeConfig(): array
    {
        $config = config("ai.runtime", []);

        return is_array($config) ? $config : [];
    }

    private function knowledgeItems(array $config): array
    {
        $bundle = $this->runtimeBundle($config);

        return array_merge(
            array_values((array) ($bundle["intents"] ?? [])),
            array_values((array) ($bundle["knowledge"] ?? [])),
            array_values((array) ($config["intents"] ?? [])),
            array_values((array) ($config["knowledge"] ?? [])),
            array_values($this->learnedItems($config))
        );
    }

    private function runtimeBundle(array $config): array
    {
        if (($config["load_integrated_runtime"] ?? true) !== true) {
            return [];
        }

        $directory = $this->runtimeDirectory($config);

        if ($directory === null || !is_dir($directory)) {
            return [];
        }

        return [
            "manifest" => $this->readRuntimeJson($directory . DIRECTORY_SEPARATOR . "MANIFEST.json"),
            "profile" => $this->readRuntimeJson($directory . DIRECTORY_SEPARATOR . "profile.json"),
            "intents" => $this->runtimeRecords($directory . DIRECTORY_SEPARATOR . "intents"),
            "knowledge" => $this->runtimeRecords($directory . DIRECTORY_SEPARATOR . "knowledge"),
        ];
    }

    private function runtimeRecords(string $directory): array
    {
        if (!is_dir($directory)) {
            return [];
        }

        $records = [];

        foreach (glob($directory . DIRECTORY_SEPARATOR . "*.json") ?: [] as $path) {
            $payload = $this->readRuntimeJson($path);
            $items = is_array($payload["items"] ?? null) ? (array) $payload["items"] : [$payload];

            foreach ($items as $item) {
                if (is_array($item) && is_string($item["id"] ?? null)) {
                    $item["source"] = "runtime:" . basename($path);
                    $records[] = $item;
                }
            }
        }

        return $records;
    }

    private function readRuntimeJson(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }

        $payload = json_decode((string) file_get_contents($path), true);

        return is_array($payload) ? $payload : [];
    }

    private function runtimeDirectory(array $config): ?string
    {
        $relativePath = trim((string) ($config["runtime_path"] ?? "resources/fnlla-ai-runtime"));
        $relativePath = str_replace(["/", "\\"], DIRECTORY_SEPARATOR, $relativePath);
        $relativePath = ltrim($relativePath, DIRECTORY_SEPARATOR);

        if ($relativePath === "" || in_array("..", explode(DIRECTORY_SEPARATOR, $relativePath), true)) {
            throw new RuntimeException("Runtime AI path must be a project-local directory.");
        }

        $path = base_path($relativePath);
        $baseRoot = realpath(base_path()) ?: base_path();
        $targetDirectory = realpath($path) ?: $path;

        if (!str_starts_with($targetDirectory, $baseRoot)) {
            throw new RuntimeException("Runtime AI path must stay inside the project repository.");
        }

        return $path;
    }

    private function learnedItems(array $config): array
    {
        $path = $this->learningPath($config);

        if (!is_file($path)) {
            return [];
        }

        $payload = json_decode((string) file_get_contents($path), true);
        $items = [];

        foreach (is_array($payload) ? $payload : [] as $item) {
            if (!is_array($item) || !is_string($item["id"] ?? null)) {
                continue;
            }

            $items[(string) $item["id"]] = $item;
        }

        return $items;
    }

    private function learningPath(array $config): string
    {
        $relativePath = trim((string) ($config["learning_path"] ?? "framework/ai/runtime-knowledge.json"));
        $relativePath = str_replace(["/", "\\"], DIRECTORY_SEPARATOR, $relativePath);
        $relativePath = ltrim($relativePath, DIRECTORY_SEPARATOR);

        if (in_array("..", explode(DIRECTORY_SEPARATOR, $relativePath), true)) {
            throw new RuntimeException("Runtime AI learning path cannot contain parent-directory segments.");
        }

        $path = storage_path($relativePath);
        $storageRoot = realpath(storage_path()) ?: storage_path();
        $targetDirectory = realpath(dirname($path)) ?: dirname($path);

        if (!str_starts_with($targetDirectory, $storageRoot)) {
            throw new RuntimeException("Runtime AI learning path must stay inside storage.");
        }

        return $path;
    }

    private function scoreItem(array $tokens, array $item): int
    {
        $haystack = implode(" ", array_merge(
            [(string) ($item["id"] ?? ""), (string) ($item["title"] ?? ""), (string) ($item["answer"] ?? "")],
            array_map("strval", (array) ($item["utterances"] ?? [])),
            array_map("strval", (array) ($item["tags"] ?? []))
        ));
        $itemTokens = array_unique($this->tokens($haystack));
        $matches = array_intersect($tokens, $itemTokens);

        if ($itemTokens === []) {
            return 0;
        }

        return (int) round((count($matches) / max(1, count($tokens))) * 100);
    }

    private function fallback(string $input, string $reason, int $confidence, array $context): array
    {
        $config = $this->runtimeConfig();
        $profile = array_merge(
            (array) ($this->runtimeBundle($config)["profile"] ?? []),
            (array) ($config["profile"] ?? [])
        );

        return [
            "schema" => "fnlla.runtime_ai.answer.v1",
            "driver" => "local",
            "input" => $input,
            "answer" => (string) ($profile["fallback"] ?? "I do not know that from the local project knowledge yet."),
            "confidence" => $confidence,
            "intent" => null,
            "title" => "",
            "actions" => [],
            "sources" => [],
            "reason" => $reason,
            "context" => $this->safeContext($context),
        ];
    }

    private function normaliseInput(string $value, int $limit): string
    {
        $value = trim(strip_tags(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]+/', " ", $value) ?? ""));

        return substr($value, 0, max(1, $limit));
    }

    private function normaliseId(string $id): string
    {
        return trim(strtolower(preg_replace('/[^a-zA-Z0-9_.-]+/', "-", $id) ?? ""), "-");
    }

    private function tokens(string $value): array
    {
        $value = strtolower($value);
        preg_match_all('/[a-z0-9][a-z0-9_-]{1,}/', $value, $matches);

        return array_values(array_unique($matches[0] ?? []));
    }

    private function safeContext(array $context): array
    {
        $safe = [];

        foreach ($context as $key => $value) {
            if (!is_scalar($value) && $value !== null) {
                continue;
            }

            $key = $this->normaliseId((string) $key);

            if ($this->looksSensitive($key)) {
                continue;
            }

            if ($key !== "") {
                $safe[$key] = $this->normaliseInput((string) $value, 240);
            }
        }

        return $safe;
    }

    private function looksSensitive(string $key): bool
    {
        return preg_match('/(password|secret|token|cookie|session|credential|key|authorization|csrf)/i', $key) === 1;
    }
}
