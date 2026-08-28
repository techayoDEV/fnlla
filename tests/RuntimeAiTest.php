<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA TEST CASE
File: tests\RuntimeAiTest.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

Purpose:
- Validates the local runtime AI service used for project-owned, user-facing
  assistance without external model providers.
*/

namespace Fnlla\Php\Tests;

use Fnlla\Php\Ai\LocalRuntimeAssistant;
use Fnlla\Php\Ai\FionnRuntimeBridge;
use Fnlla\Php\Ai\RuntimeAiProviderInterface;
use Fnlla\Php\Ai\RuntimeAiProviderRegistry;
use Fnlla\Php\Container\Container;
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
    }

    public function testFionnBridgeIsReservedButNotIntegrated(): void
    {
        $bridge = new FionnRuntimeBridge();
        $status = $bridge->status();

        self::assertSame("fionn", $status["driver"]);
        self::assertSame("reserved", $status["integration_state"]);
        self::assertSame(false, $status["provider_ready"]);
        self::assertSame(false, $status["external_calls"]);

        $this->expectException(RuntimeException::class);
        $bridge->answer("hello");
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
        self::assertSame("reserved", $report["providers"]["fionn"]["integration_state"] ?? null);
        self::assertSame(false, $report["providers"]["fionn"]["provider_ready"] ?? null);
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
