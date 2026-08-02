<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA SUPPORT SOURCE
File: src\Support\ProcessRunner.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Runs maintainer-side operating system processes without shell-string
  interpolation so framework update and export flows keep argument boundaries.
*/

namespace Fnlla\Php\Support;

use RuntimeException;

final class ProcessRunner
{
    /**
     * Run a command as an argv vector and return a normalized result array.
     *
     * FNLLA intentionally keeps this wrapper small. The important contract is
     * that callers pass each argument as its own array item; the runner rejects
     * empty command vectors and null bytes before the process is started.
     *
     * @param string[] $command
     * @return array{exit_code:int, stdout:string, stderr:string, output:string, timed_out:bool}
     */
    public static function run(
        array $command,
        ?string $workingDirectory = null,
        ?int $timeoutSeconds = null,
        ?int $outputLimitBytes = null
    ): array
    {
        self::assertCommandVector($command);

        $timeoutSeconds = $timeoutSeconds ?? max(1, (int) config("process.default_timeout_seconds", 300));
        $outputLimitBytes = $outputLimitBytes ?? max(1024, (int) config("process.output_limit_bytes", 1048576));
        $stdoutPath = self::temporaryOutputPath("stdout");
        $stderrPath = self::temporaryOutputPath("stderr");
        $descriptors = [
            0 => ["pipe", "r"],
            1 => ["file", $stdoutPath, "w"],
            2 => ["file", $stderrPath, "w"],
        ];
        $pipes = [];
        $process = proc_open($command, $descriptors, $pipes, $workingDirectory ?: null);

        if (!is_resource($process)) {
            @unlink($stdoutPath);
            @unlink($stderrPath);
            throw new RuntimeException("Unable to start process: " . self::describe($command));
        }

        fclose($pipes[0]);

        $startedAt = microtime(true);
        $timedOut = false;

        /*
        Child output is written to temporary files instead of live pipes. That
        avoids platform-specific pipe blocking on Windows and keeps timeout
        enforcement independent from how much output the child process writes.
        */
        while (true) {
            $status = proc_get_status($process);

            if (!($status["running"] ?? false)) {
                break;
            }

            if ((microtime(true) - $startedAt) >= $timeoutSeconds) {
                $timedOut = true;
                proc_terminate($process);
                usleep(100000);
                $status = proc_get_status($process);

                if ($status["running"] ?? false) {
                    proc_terminate($process, 9);
                }

                break;
            }

            usleep(10000);
        }

        $exitCode = proc_close($process);
        $stdout = self::readLimitedFile($stdoutPath, $outputLimitBytes, "stdout");
        $stderr = self::readLimitedFile($stderrPath, $outputLimitBytes, "stderr");
        @unlink($stdoutPath);
        @unlink($stderrPath);
        $output = trim($stdout . ($stderr !== "" ? PHP_EOL . $stderr : ""));

        return [
            "exit_code" => $timedOut ? 124 : (is_int($exitCode) ? $exitCode : 1),
            "stdout" => trim($stdout),
            "stderr" => trim($stderr),
            "output" => $timedOut
                ? trim($output . PHP_EOL . "Process timed out after {$timeoutSeconds} seconds.")
                : $output,
            "timed_out" => $timedOut,
        ];
    }

    /**
     * Resolve an executable from PATH without invoking a shell.
     */
    public static function findExecutable(string $name): ?string
    {
        $name = trim($name);

        if ($name === "" || str_contains($name, "\0")) {
            return null;
        }

        if (self::isExecutableFile($name)) {
            return $name;
        }

        $paths = preg_split('/' . preg_quote(PATH_SEPARATOR, '/') . '/', (string) getenv("PATH")) ?: [];
        $extensions = [""];

        if (DIRECTORY_SEPARATOR === "\\") {
            $pathext = preg_split('/;/', (string) getenv("PATHEXT")) ?: [];
            $extensions = array_values(array_unique(array_merge([".exe", ".bat", ".cmd", ""], $pathext)));
        }

        foreach ($paths as $directory) {
            $directory = trim((string) $directory);

            if ($directory === "") {
                continue;
            }

            foreach ($extensions as $extension) {
                $candidate = rtrim($directory, "\\/") . DIRECTORY_SEPARATOR . $name;

                if ($extension !== "" && !str_ends_with(strtolower($candidate), strtolower((string) $extension))) {
                    $candidate .= (string) $extension;
                }

                if (self::isExecutableFile($candidate)) {
                    return $candidate;
                }
            }
        }

        return null;
    }

    /**
     * Build a human-readable command preview for errors and logs only.
     *
     * This is deliberately not used for execution.
     *
     * @param string[] $command
     */
    public static function describe(array $command): string
    {
        return implode(" ", array_map(
            static fn (string $argument): string => preg_match('/\s/', $argument) === 1
                ? '"' . str_replace('"', '\"', $argument) . '"'
                : $argument,
            $command
        ));
    }

    /**
     * @param string[] $command
     */
    private static function assertCommandVector(array $command): void
    {
        if ($command === []) {
            throw new RuntimeException("Process command cannot be empty.");
        }

        foreach ($command as $argument) {
            if (!is_string($argument) || $argument === "") {
                throw new RuntimeException("Process command arguments must be non-empty strings.");
            }

            if (str_contains($argument, "\0")) {
                throw new RuntimeException("Process command arguments cannot contain null bytes.");
            }
        }
    }

    private static function isExecutableFile(string $path): bool
    {
        if (!is_file($path)) {
            return false;
        }

        return DIRECTORY_SEPARATOR === "\\" || is_executable($path);
    }

    private static function temporaryOutputPath(string $label): string
    {
        $path = tempnam(sys_get_temp_dir(), "fnlla-process-" . $label . "-");

        if (!is_string($path) || $path === "") {
            throw new RuntimeException("Unable to create process output buffer.");
        }

        return $path;
    }

    private static function readLimitedFile(string $path, int $limitBytes, string $label): string
    {
        if (!is_file($path)) {
            return "";
        }

        $handle = fopen($path, "rb");

        if (!is_resource($handle)) {
            return "";
        }

        try {
            $contents = fread($handle, $limitBytes + 1);
            $contents = is_string($contents) ? $contents : "";

            if (strlen($contents) > $limitBytes) {
                return substr($contents, 0, $limitBytes) . PHP_EOL . "[{$label} truncated]";
            }

            return $contents;
        } finally {
            fclose($handle);
        }
    }
}
