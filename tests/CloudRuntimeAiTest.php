<?php

declare(strict_types=1);

namespace Fnlla\Php\Tests;

use Fnlla\Php\Ai\AiProviderSettings;
use Fnlla\Php\Ai\AnthropicRuntimeProvider;
use Fnlla\Php\Ai\FionnRuntimeBridge;
use Fnlla\Php\Ai\OpenAiRuntimeProvider;
use Fnlla\Php\Ai\RuntimeAiProviderRegistry;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class CloudRuntimeAiTest extends TestCase
{
    private array $previousConfig;

    protected function setUp(): void
    {
        $this->previousConfig = config();
        config_set("ai.runtime.enabled", true);
        foreach (["openai", "anthropic"] as $driver) {
            config_set("ai.runtime." . $driver, ["enabled" => true, "api_key" => "test-only-not-a-real-key", "model" => "fixture-model", "max_output_tokens" => 1024, "timeout_seconds" => 12]);
        }
    }

    protected function tearDown(): void
    {
        $GLOBALS["fnlla_config"] = $this->previousConfig;
        $GLOBALS["fnlla_php_config"] = $this->previousConfig;
    }

    public function testOpenAiUsesResponsesWithoutStorageOrImplicitContext(): void
    {
        $provider = new OpenAiRuntimeProvider(function ($url, $payload, $headers, $timeout): array {
            self::assertSame("https://api.openai.com/v1/responses", $url);
            self::assertSame(["model" => "fixture-model", "input" => "Explain routing", "max_output_tokens" => 1024, "store" => false, "stream" => false], $payload);
            self::assertContains("Authorization: Bearer test-only-not-a-real-key", $headers);
            self::assertSame(12, $timeout);
            return $this->openAiResponse();
        });
        $answer = $provider->answer("Explain routing", ["environment" => "private-project-data", "api_key" => "private-key"]);
        self::assertSame("First.\nSecond.", $answer["answer"]);
        self::assertSame("OpenAI API", $answer["title"]);
        self::assertSame([], $answer["context"]);
        self::assertSame([], $answer["actions"]);
        self::assertSame([], $answer["sources"]);
        self::assertSame(15, $answer["usage"]["total_tokens"]);
        self::assertNull($answer["estimated_cost_gbp"]);
        self::assertFalse($answer["provider"]["grounded"]);
        self::assertStringNotContainsString("private", json_encode($answer));
    }

    public function testClaudePlatformUsesMessagesAndCountsCachedInput(): void
    {
        $provider = new AnthropicRuntimeProvider(static function ($url, $payload, $headers): array {
            self::assertSame("https://api.anthropic.com/v1/messages", $url);
            self::assertSame([["role" => "user", "content" => "Explain routing"]], $payload["messages"]);
            self::assertSame(1024, $payload["max_tokens"]);
            self::assertContains("anthropic-version: 2023-06-01", $headers);
            self::assertContains("Authorization: Bearer test-only-not-a-real-key", $headers);
            self::assertNotContains("x-api-key: test-only-not-a-real-key", $headers);
            self::assertFalse($payload["stream"]);
            self::assertSame("fixture-model", $payload["model"]);
            return ["status" => 200, "body" => json_encode(["type" => "message", "role" => "assistant", "stop_reason" => "end_turn",
                "content" => [["type" => "thinking", "thinking" => "private reasoning"], ["type" => "text", "text" => "A route maps a request."]],
                "usage" => ["input_tokens" => 10, "output_tokens" => 5, "cache_read_input_tokens" => 4, "cache_creation_input_tokens" => 2]])];
        });
        $answer = $provider->answer("Explain routing");
        self::assertSame("A route maps a request.", $answer["answer"]);
        self::assertSame("Anthropic API", $answer["title"]);
        self::assertSame("anthropic", $answer["driver"]);
        self::assertSame(21, $answer["usage"]["total_tokens"]);
    }

    public function testReadinessNeverCallsProviderOrExposesCredentials(): void
    {
        $provider = new OpenAiRuntimeProvider(static function (): never { throw new RuntimeException("Must not send"); });
        self::assertTrue($provider->status()["provider_ready"]);
        self::assertStringNotContainsString("test-only", json_encode($provider->status()));
        self::assertStringContainsString("not been tested", $provider->status()["reason"]);
        config_set("ai.runtime.openai.enabled", false);
        self::assertFalse($provider->status()["external_calls"]);
        $this->expectExceptionMessage("disabled or not configured");
        $provider->answer("Question");
    }

    public function testGlobalDisableStopsExternalRequests(): void
    {
        config_set("ai.runtime.enabled", false);
        $this->expectExceptionMessage("disabled or not configured");
        (new OpenAiRuntimeProvider(static function (): never { self::fail("Unexpected network request"); }))->answer("Question");
    }

    public function testHeaderInjectionIsRejectedBeforeTransport(): void
    {
        config_set("ai.runtime.openai.api_key", "test-key\r\nX-Injected: yes");
        $provider = new OpenAiRuntimeProvider(static function (): never { self::fail("Unexpected network request"); });
        self::assertFalse($provider->status()["provider_ready"]);
        $this->expectException(RuntimeException::class);
        $provider->answer("Question");
    }

    public function testOversizedInputIsRejectedWithoutTruncation(): void
    {
        config_set("ai.runtime.max_input_chars", 32);
        $this->expectExceptionMessage("character limit");
        (new OpenAiRuntimeProvider(static function (): never { self::fail("Unexpected network request"); }))->answer(str_repeat("a", 33));
    }

    public function testUnknownDriverDoesNotSilentlyUseLocal(): void
    {
        config_set("ai.runtime.driver", "opneai");
        $this->expectExceptionMessage("Unknown runtime AI driver");
        framework_runtime_ai_provider();
    }

    public function testSecurityAuditRequiresConfiguredCloudProviderButNotLocalBundle(): void
    {
        config_set("ai.runtime.driver", "openai");
        config_set("ai.runtime.runtime_path", "resources/nonexistent-test-bundle");
        $checks = array_column((new \Fnlla\Php\Support\SecurityAuditReport())->build()["checks"], null, "id");
        self::assertSame("pass", $checks["runtime_ai_bundle"]["status"]);
        self::assertSame(extension_loaded("curl") ? "pass" : "fail", $checks["runtime_ai_local_driver"]["status"]);
        config_set("ai.runtime.openai.api_key", "");
        $checks = array_column((new \Fnlla\Php\Support\SecurityAuditReport())->build()["checks"], null, "id");
        self::assertSame("fail", $checks["runtime_ai_local_driver"]["status"]);
    }

    public function testTransportErrorsNeverLeakCredentials(): void
    {
        $this->expectExceptionMessage("AI provider connection failed; check connectivity and server-side configuration.");
        (new OpenAiRuntimeProvider(static function (): never { throw new RuntimeException("test-only-not-a-real-key"); }))->answer("Question");
    }

    public function testResponsesRejectRedirectsErrorsInvalidJsonAndOversizedBodies(): void
    {
        foreach ([["status" => 302, "body" => "secret"], ["status" => 401, "body" => "secret"], ["status" => 429, "body" => "secret"],
            ["status" => 200, "body" => "invalid"], ["status" => 200, "body" => str_repeat("a", 1048577)], ["status" => 200, "body" => "[]"]] as $response) {
            try {
                (new OpenAiRuntimeProvider(static fn (): array => $response))->answer("Question");
                self::fail("Invalid response accepted");
            } catch (RuntimeException $exception) {
                self::assertStringNotContainsString("secret", $exception->getMessage());
            }
        }
    }

    public function testIncompleteAndRefusedAnswersAreNotPresentedAsComplete(): void
    {
        foreach (["incomplete", "failed", "queued"] as $status) {
            $response = $this->openAiResponse();
            $body = json_decode($response["body"], true);
            $body["status"] = $status;
            $response["body"] = json_encode($body);
            try {
                (new OpenAiRuntimeProvider(static fn (): array => $response))->answer("Question");
                self::fail("Incomplete answer accepted");
            } catch (RuntimeException $exception) {
                self::assertStringContainsString("no complete text answer", $exception->getMessage());
            }
        }
        $this->expectExceptionMessage("no complete text answer");
        (new AnthropicRuntimeProvider(static fn (): array => ["status" => 200, "body" => json_encode(["type" => "message", "role" => "assistant", "stop_reason" => "max_tokens", "content" => [["type" => "text", "text" => "partial"]]])]))->answer("Question");
    }

    public function testClaudePlatformRejectsEveryNonFinalStopReasonAndRefusalDetails(): void
    {
        foreach (["max_tokens", "tool_use", "pause_turn", "refusal", "model_context_window_exceeded", null] as $reason) {
            $body = ["type" => "message", "role" => "assistant", "stop_reason" => $reason,
                "content" => [["type" => "text", "text" => "Do not present a partial answer"]]];
            $this->assertRejectedClaudeBody($body);
        }
        $this->assertRejectedClaudeBody(["type" => "message", "role" => "assistant", "stop_reason" => "end_turn",
            "stop_details" => ["type" => "refusal"], "content" => [["type" => "text", "text" => "Refused"]]]);
    }

    public function testClaudePlatformAcceptsStopSequenceAndJoinsTextBlocksOnly(): void
    {
        $response = ["status" => 200, "body" => json_encode(["type" => "message", "role" => "assistant",
            "stop_reason" => "stop_sequence", "stop_sequence" => "END", "stop_details" => null,
            "content" => [["type" => "text", "text" => "One"], ["type" => "thinking", "thinking" => "Never expose"],
                ["type" => "text", "text" => "Two"]]])];
        $answer = (new AnthropicRuntimeProvider(static fn (): array => $response))->answer("Question");
        self::assertSame("One\nTwo", $answer["answer"]);
        self::assertNull($answer["usage"]["total_tokens"]);
    }

    public function testOpenAiRejectsRefusalEvenAfterAnOutputTextBlock(): void
    {
        $response = $this->openAiResponse();
        $body = json_decode($response["body"], true);
        $body["output"][] = ["type" => "message", "role" => "assistant",
            "content" => [["type" => "refusal", "refusal" => "Private refusal details"]]];
        $response["body"] = json_encode($body);
        $this->expectExceptionMessage("no complete text answer");
        (new OpenAiRuntimeProvider(static fn (): array => $response))->answer("Question");
    }

    private function assertRejectedClaudeBody(array $body): void
    {
        try {
            (new AnthropicRuntimeProvider(static fn (): array => ["status" => 200, "body" => json_encode($body)]))->answer("Question");
            self::fail("Non-final or refused Claude Platform response was accepted");
        } catch (RuntimeException $exception) {
            self::assertSame("AI provider returned no complete text answer within the size limit.", $exception->getMessage());
        }
    }

    public function testMissingUsageIsUnknownNotFree(): void
    {
        $response = $this->openAiResponse();
        $body = json_decode($response["body"], true);
        unset($body["usage"]);
        $response["body"] = json_encode($body);
        $answer = (new OpenAiRuntimeProvider(static fn (): array => $response))->answer("Question");
        self::assertNull($answer["usage"]["total_tokens"]);
        self::assertNull($answer["estimated_cost_gbp"]);
    }

    public function testSettingsPreserveBlankKeyAndSupportExplicitRemoval(): void
    {
        $input = $this->settingsInput();
        $service = new AiProviderSettings();
        $values = $service->values($input);
        self::assertSame("test-only-not-a-real-key", $values["AI_OPENAI_API_KEY"]);
        $input["ai_openai_remove_key"] = "1";
        self::assertSame("", $service->values($input)["AI_OPENAI_API_KEY"]);
        $service->apply($values);
        self::assertFalse(config("ai.runtime.openai.enabled"));
        self::assertSame("local", config("ai.runtime.driver"));
    }

    public function testSettingsRejectInvalidDriverAndArrayInput(): void
    {
        foreach (["ai_runtime_driver" => "unknown", "ai_openai_api_key" => ["injected"], "ai_openai_model" => "bad\nmodel", "ai_openai_timeout_seconds" => "61"] as $key => $value) {
            try {
                (new AiProviderSettings())->values(array_replace($this->settingsInput(), [$key => $value]));
                self::fail("Invalid settings accepted");
            } catch (RuntimeException $exception) {
                self::assertStringNotContainsString("injected", $exception->getMessage());
            }
        }
    }

    public function testDisabledSelectedCloudProviderCannotBeSaved(): void
    {
        $input = array_replace($this->settingsInput(), ["ai_runtime_driver" => "openai", "ai_runtime_enabled" => "1"]);
        $this->expectExceptionMessage("Enable the selected provider");
        (new AiProviderSettings())->values($input);
    }

    public function testFionnRequiresExplicitAllowlistAndSafeToken(): void
    {
        config_set("ai.runtime.fionn.enabled", true);
        config_set("ai.runtime.fionn.endpoint", "https://fionn.example.com");
        config_set("ai.runtime.fionn.api_token", "token");
        config_set("ai.runtime.fionn.allowed_hosts", []);
        self::assertFalse((new FionnRuntimeBridge())->status()["provider_ready"]);
        config_set("ai.runtime.fionn.allowed_hosts", ["fionn.example.com"]);
        config_set("ai.runtime.fionn.api_token", "token\r\nInjected: value");
        self::assertFalse((new FionnRuntimeBridge())->status()["provider_ready"]);
    }

    public function testFionnKeepsUtf8IntactAndDoesNotCopyPrivateMetadata(): void
    {
        config_set("ai.runtime.max_input_chars", 3);
        config_set("ai.runtime.fionn.enabled", true);
        config_set("ai.runtime.fionn.endpoint", "http://127.0.0.1:8765");
        config_set("ai.runtime.fionn.allowed_hosts", ["127.0.0.1"]);
        $provider = new FionnRuntimeBridge(static function ($url, $payload): array {
            self::assertSame(str_repeat("\u{0105}", 3), $payload["message"]);
            return ["status" => 200, "body" => json_encode(["reply" => "Answer", "privacy" => ["redacted" => true, "session_path" => "private-path"], "grounding" => ["verified" => true, "memory" => ["secret" => "private-value"]]])];
        });
        $answer = $provider->answer(str_repeat("\u{0105}", 4));
        self::assertSame("FIONN AI", $answer["title"]);
        self::assertSame(["redacted" => true], $answer["provider"]["privacy"]);
        self::assertSame(["verified" => true], $answer["provider"]["grounding"]);
        self::assertFalse($answer["provider"]["confidence_measured"]);
        self::assertSame(0, $answer["confidence"]);
        self::assertNull($answer["estimated_cost_gbp"]);
        self::assertStringNotContainsString("private-", json_encode($answer));
    }

    private function settingsInput(): array
    {
        $input = ["ai_runtime_driver" => "local", "ai_runtime_enabled" => "1"];
        foreach (["openai", "anthropic"] as $driver) {
            $input += ["ai_" . $driver . "_model" => "fixture-model", "ai_" . $driver . "_max_output_tokens" => "1024", "ai_" . $driver . "_timeout_seconds" => "30"];
        }
        return $input;
    }

    private function openAiResponse(): array
    {
        return ["status" => 200, "body" => json_encode(["status" => "completed", "output" => [
            ["type" => "reasoning", "summary" => []],
            ["type" => "message", "role" => "assistant", "content" => [["type" => "output_text", "text" => "First."]]],
            ["type" => "message", "role" => "assistant", "content" => [["type" => "output_text", "text" => "Second."]]],
        ], "usage" => ["input_tokens" => 10, "output_tokens" => 5]])];
    }
}
