<?php

declare(strict_types=1);

namespace Fnlla\Php\Support;

use RuntimeException;

final class StarterProfileSelection
{
    /** Stream injection keeps prompts testable without creating projects or blocking CI. */
    public static function resolve(array $arguments, $input = STDIN, $output = STDOUT): array
    {
        $profile = null;
        $positionals = [];
        $interactive = false;
        $nonInteractive = false;
        for ($i = 0; $i < count($arguments); $i++) {
            $argument = (string) $arguments[$i];
            if ($argument === "--interactive") {
                $interactive = true;
            } elseif ($argument === "--no-interaction") {
                $nonInteractive = true;
            } elseif ($argument === "--profile" || str_starts_with($argument, "--profile=")) {
                if ($profile !== null) {
                    throw new RuntimeException("Specify the starter profile only once.");
                }
                $profile = self::normaliseProfile($argument === "--profile" ? (string) ($arguments[++$i] ?? "") : substr($argument, 10));
            } elseif (str_starts_with($argument, "--")) {
                throw new RuntimeException("Unknown project option: " . $argument);
            } else {
                $positionals[] = $argument;
            }
        }
        if ($interactive && $nonInteractive) {
            throw new RuntimeException("Choose --interactive or --no-interaction, not both.");
        }
        if ($profile === null && $positionals !== [] && $interactive) {
            fwrite($output, "FNLLA includes Project Setup, Developer Panel and UI runtime.\nAdvanced installation options:\n  2. FNLLA (fnlla profile, recommended).\n  1. FNLLA Core (core): no panel or UI bundle.\n");
            while ($profile === null) {
                fwrite($output, "Starter [1/2, fnlla/core; default 2; q to cancel]: ");
                $line = fgets($input);
                if ($line === false || in_array(strtolower(trim($line)), ["q", "quit", "cancel"], true)) {
                    throw new RuntimeException("Project creation cancelled; no files were created.");
                }
                $profile = match (strtolower(trim($line))) {
                    "1", "core" => "core",
                    "", "2", "fnlla", "platform" => "fnlla",
                    default => null,
                };
            }
        }
        // Preserve historical automation behavior. Explicit profiles are recommended in CI.
        return ["profile" => $profile ?? "fnlla", "arguments" => $positionals];
    }

    private static function normaliseProfile(string $profile): string
    {
        return match (strtolower(trim($profile))) {
            "core" => "core",
            "fnlla", "platform", "full" => "fnlla",
            default => throw new RuntimeException("Profile must be fnlla or core."),
        };
    }
}
