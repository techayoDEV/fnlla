<?php

declare(strict_types=1);

namespace Fnlla\Php\Tests;

use Fnlla\Php\Support\StarterProfileSelection;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class StarterProfileSelectionTest extends TestCase
{
    private function resolve(array $arguments, string $answer = ""): array
    {
        $input = fopen("php://memory", "r+");
        $output = fopen("php://memory", "r+");
        try {
            fwrite($input, $answer);
            rewind($input);
            $result = StarterProfileSelection::resolve($arguments, $input, $output);
            rewind($output);
            return $result + ["output" => stream_get_contents($output)];
        } finally {
            fclose($input);
            fclose($output);
        }
    }

    public function testPromptAcceptsProfilesAndRetriesInvalidAnswers(): void
    {
        foreach (["1\n" => "plain", "plain\n" => "plain", "2\n" => "full", "\n" => "full", "wrong\nfull\n" => "full"] as $answer => $profile) {
            $result = $this->resolve(["C:/workspace/project", "Example App", "--interactive"], $answer);
            self::assertSame($profile, $result["profile"]);
            self::assertSame(["C:/workspace/project", "Example App"], $result["arguments"]);
            self::assertStringContainsString("FNLLA (full, recommended)", $result["output"]);
        }
    }

    public function testExplicitProfilesAndAutomationNeverPrompt(): void
    {
        foreach ([["--profile=plain", "target"], ["target", "--profile", "plain"]] as $arguments) {
            $result = $this->resolve($arguments);
            self::assertSame("plain", $result["profile"]);
            self::assertSame("", $result["output"]);
        }
        self::assertSame("full", $this->resolve(["target", "--no-interaction"])["profile"]);
        self::assertSame("", $this->resolve(["target"])["output"]);
        self::assertSame("full", $this->resolve(["target"])["profile"]);
    }

    public function testCancellationAndAmbiguousOptionsFailClosed(): void
    {
        foreach ([[["target", "--interactive"], "q\n"], [["target", "--interactive"], ""],
            [["target", "--interactive", "--no-interaction"], ""], [["target", "--profile=small"], ""],
            [["target", "--profile=full", "--profile=plain"], ""], [["target", "--profil=plain"], ""]] as [$arguments, $input]) {
            try {
                $this->resolve($arguments, $input);
                self::fail("Expected cancellation or invalid options to be rejected.");
            } catch (RuntimeException $error) {
                self::assertTrue($error->getMessage() !== "");
            }
        }
    }
}
