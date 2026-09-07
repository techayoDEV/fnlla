<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA TEST CASE
File: tests\RuntimeAiTest.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

Purpose:
- Validates the runtime assistant service used for project-owned, user-facing
  assistance without external model providers.
*/

namespace Fnlla\Php\Tests;

use Fnlla\Php\Ai\LocalRuntimeAssistant;
use Fnlla\Php\Ai\FionnRuntimeBridge;
use Fnlla\Php\Ai\RuntimeAiProviderInterface;
use Fnlla\Php\Ai\RuntimeAiProviderRegistry;
use Fnlla\Php\Container\Container;
use Fnlla\Php\Support\SecurityAuditReport;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class RuntimeAiTest extends TestCase
{
    private array $previousConfig = [];
    private string $learningFile = "";

    protected function setUp(): void
    {
        $this->previousConfig = config();
        $this->learningFile = storage_path("framework/cache/runtime-ai-test-" . bin2hex(random_bytes(4)) . ".json");
    }

    protected function tearDown(): void
    {
        if ($this->learningFile !== "" && is_file($this->learningFile)) {
            unlink($this->learningFile);
        }

        $GLOBALS["fnlla_config"] = $this->previousConfig;
        $GLOBALS["fnlla_php_config"] = $this->previousConfig;
    }

    public function testRuntimeAiAnswersFromConfiguredLocalKnowledge(): void
    {
        config_set("ai.runtime.enabled", true);
        config_set("ai.runtime.confidence_threshold", 20);
        config_set("ai.runtime.knowledge", [
            [
                "id" => "pricing",
                "title" => "Pricing guidance",
                "utterances" => ["price", "pricing", "cost"],
                "answer" => "Pricing is shown on the project pricing page.",
                "actions" => ["route:pricing"],
            ],
        ]);

        $answer = (new LocalRuntimeAssistant())->answer("How much does pricing cost?");

        self::assertSame("local", $answer["driver"]);
        self::assertSame("pricing", $answer["intent"]);
        self::assertSame("Pricing is shown on the project pricing page.", $answer["answer"]);
        self::assertTrue(in_array("route:pricing", (array) $answer["actions"], true));
        self::assertArrayHasKey("usage", $answer);
        self::assertSame(0.0, $answer["estimated_cost_gbp"]);
        self::assertArrayHasKey("latency_ms", $answer);
    }

    public function testLocalRuntimeAiExposesProviderStatus(): void
    {
        config_set("ai.runtime.enabled", true);

        $assistant = new LocalRuntimeAssistant();

        self::assertInstanceOf(RuntimeAiProviderInterface::class, $assistant);

        $status = $assistant->status();

        self::assertSame("local", $status["driver"]);
        self::assertSame(true, $status["provider_ready"]);
        self::assertSame(false, $status["external_calls"]);
        self::assertSame("estimated_cost_gbp", $status["accounting"]["cost_field"] ?? null);
    }

    public function testFionnBridgeIsDisabledByDefault(): void
    {
        $bridge = new FionnRuntimeBridge();
        $status = $bridge->status();

        self::assertSame("fionn", $status["driver"]);
        self::assertSame("available_opt_in", $status["integration_state"]);
        self::assertSame(false, $status["provider_ready"]);
        self::assertSame(false, $status["external_calls"]);

        $this->expectException(RuntimeException::class);
        $bridge->answer("hello");
    }

    public function testFionnBridgeCanCallAllowedLocalChatEndpoint(): void
    {
        config_set("ai.runtime.fionn.enabled", true);
        config_set("ai.runtime.fionn.endpoint", "http://127.0.0.1:8765");
        config_set("ai.runtime.fionn.allowed_hosts", ["127.0.0.1"]);
        config_set("ai.runtime.fionn.timeout_seconds", 2);

        $seen = [];
        $bridge = new FionnRuntimeBridge(static function (string $endpoint, array $payload, array $headers, int $timeout) use (&$seen): array {
            $seen = compact("endpoint", "payload", "headers", "timeout");

            return [
                "status" => 200,
                "body" => json_encode([
                    "reply" => "FIONN AI response.",
                    "reply_source" => "model_only",
                    "reply_mode" => "memory_assisted",
                    "knowledge_mode" => "auto",
                    "privacy" => ["redacted" => false],
                    "session_path" => "C:/private/fionn/session.jsonl",
                    "memory_path" => "C:/private/fionn/memory.jsonl",
                    "queue_path" => "C:/private/fionn/queue.jsonl",
                ], JSON_THROW_ON_ERROR),
            ];
        });

        $answer = $bridge->answer("Hello FIONN AI", [
            "page" => "support",
            "api_token" => "secret",
            "session_cookie" => "private",
        ]);

        self::assertSame("http://127.0.0.1:8765/api/chat", $seen["endpoint"]);
        self::assertSame(2, $seen["timeout"]);
        self::assertSame(false, $seen["payload"]["learning_mode"]);
        self::assertSame("Hello FIONN AI", $seen["payload"]["message"]);
        self::assertArrayHasKey("page", $seen["payload"]["context"]);
        self::assertArrayNotHasKey("api_token", $seen["payload"]["context"]);
        self::assertArrayNotHasKey("session_cookie", $seen["payload"]["context"]);
        self::assertSame("fionn", $answer["driver"]);
        self::assertSame("FIONN AI response.", $answer["answer"]);
        self::assertSame(["fionn:model_only"], $answer["sources"]);
        self::assertArrayNotHasKey("session_path", $answer);
        self::assertArrayNotHasKey("memory_path", $answer);
        self::assertArrayNotHasKey("queue_path", $answer);
    }

    public function testFionnBridgeRejectsInsecureRemoteEndpoint(): void
    {
        config_set("ai.runtime.fionn.enabled", true);
        config_set("ai.runtime.fionn.endpoint", "http://fionn.example.com");
        config_set("ai.runtime.fionn.allowed_hosts", ["fionn.example.com"]);
        config_set("ai.runtime.fionn.api_token", "token");

        $bridge = new FionnRuntimeBridge();
        $status = $bridge->status();

        self::assertSame(false, $status["provider_ready"]);
        self::assertStringContainsString("HTTPS", (string) $status["endpoint_policy_reason"]);

        $this->expectException(RuntimeException::class);
        $bridge->answer("hello");
    }

    public function testFionnBridgeRequiresTokenForRemoteEndpoint(): void
    {
        config_set("ai.runtime.fionn.enabled", true);
        config_set("ai.runtime.fionn.endpoint", "https://fionn.example.com");
        config_set("ai.runtime.fionn.allowed_hosts", ["fionn.example.com"]);
        config_set("ai.runtime.fionn.api_token", "");

        $status = (new FionnRuntimeBridge())->status();

        self::assertSame(false, $status["provider_ready"]);
        self::assertStringContainsString("AI_FIONN_API_TOKEN", (string) $status["endpoint_policy_reason"]);
    }

    public function testFionnBridgeRejectsEndpointWithQueryString(): void
    {
        config_set("ai.runtime.fionn.enabled", true);
        config_set("ai.runtime.fionn.endpoint", "http://127.0.0.1:8765?debug=1");
        config_set("ai.runtime.fionn.allowed_hosts", ["127.0.0.1"]);

        $status = (new FionnRuntimeBridge())->status();

        self::assertSame(false, $status["provider_ready"]);
        self::assertStringContainsString("query strings", (string) $status["endpoint_policy_reason"]);
    }

    public function testRuntimeAiProviderRegistryReportsLocalAndReservedFionn(): void
    {
        config_set("ai.runtime.driver", "local");

        $report = (new RuntimeAiProviderRegistry(new Container()))->report();

        self::assertSame("fnlla.runtime_ai.providers.v1", $report["schema"] ?? null);
        self::assertSame("local", $report["selected_driver"] ?? null);
        self::assertFalse((bool) ($report["external_calls"] ?? true));
        self::assertArrayHasKey("local", $report["providers"]);
        self::assertArrayHasKey("fionn", $report["providers"]);
        self::assertSame(true, $report["providers"]["local"]["provider_ready"] ?? null);
        self::assertSame("available_opt_in", $report["providers"]["fionn"]["integration_state"] ?? null);
        self::assertSame(false, $report["providers"]["fionn"]["provider_ready"] ?? null);
        self::assertSame(["input_tokens", "output_tokens", "total_tokens"], $report["providers"]["fionn"]["accounting"]["token_fields"] ?? null);
    }

    public function testRuntimeAiProviderRegistryReportsReadyFionnWhenPolicyPasses(): void
    {
        config_set("ai.runtime.driver", "fionn");
        config_set("ai.runtime.fionn.enabled", true);
        config_set("ai.runtime.fionn.endpoint", "http://localhost:8765");
        config_set("ai.runtime.fionn.allowed_hosts", ["localhost"]);

        $report = (new RuntimeAiProviderRegistry(new Container()))->report();

        self::assertSame("fionn", $report["selected_driver"] ?? null);
        self::assertTrue((bool) ($report["external_calls"] ?? false));
        self::assertSame("ready", $report["providers"]["fionn"]["integration_state"] ?? null);
        self::assertSame(true, $report["providers"]["fionn"]["provider_ready"] ?? null);
        self::assertSame(["chat"], $report["providers"]["fionn"]["allowed_operations"] ?? null);
    }

    public function testRuntimeAiHelperUsesConfiguredProviderInterface(): void
    {
        config_set("ai.runtime.driver", "fionn");
        config_set("ai.runtime.fionn.enabled", true);
        config_set("ai.runtime.fionn.endpoint", "http://localhost:8765");
        config_set("ai.runtime.fionn.allowed_hosts", ["localhost"]);

        self::assertInstanceOf(RuntimeAiProviderInterface::class, runtime_ai());
        self::assertInstanceOf(FionnRuntimeBridge::class, runtime_ai());
    }

    public function testSecurityAuditAllowsOnlyPolicyApprovedFionnDriver(): void
    {
        config_set("app.environment", "production");
        config_set("ai.runtime.enabled", true);
        config_set("ai.runtime.driver", "fionn");
        config_set("ai.runtime.fionn.enabled", true);
        config_set("ai.runtime.fionn.endpoint", "https://fionn.example.com");
        config_set("ai.runtime.fionn.allowed_hosts", ["fionn.example.com"]);
        config_set("ai.runtime.fionn.api_token", "token");

        $checks = array_column((new SecurityAuditReport())->build()["checks"], null, "id");

        self::assertSame("pass", $checks["runtime_ai_local_driver"]["status"] ?? null);

        config_set("ai.runtime.fionn.api_token", "");

        $checks = array_column((new SecurityAuditReport())->build()["checks"], null, "id");

        self::assertSame("fail", $checks["runtime_ai_local_driver"]["status"] ?? null);
    }

    public function testRuntimeAiProviderRegistryBlocksUnapprovedProviders(): void
    {
        config_set("ai.runtime.providers.remote_vendor", [
            "class" => LocalRuntimeAssistant::class,
            "external_calls" => true,
        ]);

        $status = (new RuntimeAiProviderRegistry(new Container()))->status("remote_vendor");

        self::assertSame("blocked", $status["integration_state"] ?? null);
        self::assertSame(false, $status["provider_ready"] ?? null);
        self::assertStringContainsString("Unknown runtime AI driver", (string) ($status["reason"] ?? ""));
        self::assertSame("latency_ms", $status["accounting"]["latency_field"] ?? null);
    }

    public function testRuntimeAiPromptRegistryAndEvalFixturesAreVersionedLocalData(): void
    {
        $version = trim((string) strtok((string) file_get_contents(base_path("VERSION")), "\r\n"));
        $runtimeVersion = trim((string) file_get_contents(base_path("resources/fnlla-ai-runtime/VERSION")));
        $manifest = json_decode((string) file_get_contents(base_path("resources/fnlla-ai-runtime/MANIFEST.json")), true);
        $registry = json_decode((string) file_get_contents(base_path("resources/fnlla-ai-runtime/prompts/registry.json")), true);
        $evals = json_decode((string) file_get_contents(base_path("resources/fnlla-ai-runtime/evals/runtime-commands.json")), true);

        self::assertSame($version, $runtimeVersion);
        self::assertSame($version, $manifest["product"]["version"] ?? null);
        self::assertSame("fnlla.ai_prompt_registry.v1", $registry["schema"] ?? null);
        self::assertSame($version, $registry["version"] ?? null);
        self::assertSame("fnlla.ai_eval_fixture.v1", $evals["schema"] ?? null);
        self::assertSame($version, $evals["version"] ?? null);
        self::assertTrue(in_array("review.release-risk", array_column((array) ($registry["prompts"] ?? []), "id"), true));
        self::assertTrue(in_array("ai-ask-release-readiness", array_column((array) ($evals["fixtures"] ?? []), "id"), true));
        self::assertStringContainsString("prompts/registry.json", (string) file_get_contents(base_path("resources/fnlla-ai-runtime/MANIFEST.json")));
        self::assertStringContainsString("evals/runtime-commands.json", (string) file_get_contents(base_path("resources/fnlla-ai-runtime/MANIFEST.json")));
    }

    public function testRuntimeAiLoadsIntegratedRuntimeBundle(): void
    {
        config_set("ai.runtime.enabled", true);
        config_set("ai.runtime.confidence_threshold", 20);
        config_set("ai.runtime.intents", []);
        config_set("ai.runtime.knowledge", []);

        $answer = (new LocalRuntimeAssistant())->answer("I need contact support help");

        self::assertSame("contact_support", $answer["intent"]);
        self::assertStringContainsString("contact form", $answer["answer"]);
        self::assertStringContainsString("runtime:", (string) ($answer["sources"][0] ?? ""));
    }

    public function testRuntimeAiRejectsRuntimePathTraversal(): void
    {
        config_set("ai.runtime.enabled", true);
        config_set("ai.runtime.runtime_path", "../runtime");

        $this->expectException(RuntimeException::class);
        (new LocalRuntimeAssistant())->answer("support help");
    }

    public function testRuntimeAiLearningRequiresExplicitEnablement(): void
    {
        config_set("ai.runtime.learning_enabled", false);

        $this->expectException(RuntimeException::class);
        (new LocalRuntimeAssistant())->remember("returns", ["return policy"], "Returns are reviewed by support.");
    }

    public function testRuntimeAiCanPersistApprovedLearningInsideStorage(): void
    {
        config_set("ai.runtime.enabled", true);
        config_set("ai.runtime.learning_enabled", true);
        config_set("ai.runtime.learning_path", str_replace(storage_path() . DIRECTORY_SEPARATOR, "", $this->learningFile));
        config_set("ai.runtime.confidence_threshold", 20);

        $assistant = new LocalRuntimeAssistant();
        $assistant->remember("returns", ["return policy", "refund"], "Returns are reviewed by support.", [
            "actions" => ["route:support"],
        ]);

        self::assertFileExists($this->learningFile);

        $answer = $assistant->answer("refund policy");
        self::assertSame("returns", $answer["intent"]);
        self::assertSame("Returns are reviewed by support.", $answer["answer"]);
    }

    public function testRuntimeAiDropsSensitiveContextKeys(): void
    {
        config_set("ai.runtime.enabled", true);
        config_set("ai.runtime.confidence_threshold", 20);
        config_set("ai.runtime.knowledge", [
            [
                "id" => "support",
                "utterances" => ["support", "help"],
                "answer" => "Support can help.",
            ],
        ]);

        $answer = (new LocalRuntimeAssistant())->answer("support help", [
            "page" => "support",
            "api_token" => "secret",
            "session_cookie" => "private",
        ]);

        self::assertArrayHasKey("page", $answer["context"]);
        self::assertArrayNotHasKey("api_token", $answer["context"]);
        self::assertArrayNotHasKey("session_cookie", $answer["context"]);
    }

    public function testRuntimeAiRejectsLearningPathTraversal(): void
    {
        config_set("ai.runtime.learning_enabled", true);
        config_set("ai.runtime.learning_path", "../outside.json");

        $this->expectException(RuntimeException::class);
        (new LocalRuntimeAssistant())->remember("escape", ["escape"], "No.");
    }
}
