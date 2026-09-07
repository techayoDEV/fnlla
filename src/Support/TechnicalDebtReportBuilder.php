<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA SUPPORT SOURCE
File: src\Support\TechnicalDebtReportBuilder.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

Purpose:
- Builds the machine-readable technical-debt report used by release checks and
  the self-updating documentation snapshot.
*/

namespace Fnlla\Php\Support;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class TechnicalDebtReportBuilder
{
    public function markerFiles(): array
    {
        return $this->checkDebtMarkers()["data"]["files"];
    }

    public function build(): array
    {
        $checks = is_file(base_path(".fnlla/framework-lock.json"))
            ? [$this->checkDebtMarkers(), $this->checkRuntimeResidue(), $this->checkAiRuntime()]
            : [
            $this->checkDebtMarkers(),
            $this->checkRuntimeResidue(),
            $this->checkDocsHygiene(),
            $this->checkReleaseDocs(),
            $this->checkAiRuntime(),
            $this->checkPublicApiLock(),
            $this->checkModernizationLedger(),
        ];

        return [
            "schema" => "fnlla.technical_debt_report.v1",
            "generated_at_utc" => gmdate(DATE_ATOM),
            "current_version" => $this->readFirstLine(base_path("VERSION")),
            "repository_kind" => is_file(base_path(".fnlla/framework-lock.json")) ? "exported-project" : "maintainer-source",
            "checks" => $checks,
            "summary" => $this->summary($checks),
            "actions" => $this->actions($checks),
        ];
    }

    public function writeJson(array $report, string $path): void
    {
        $directory = dirname($path);

        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        file_put_contents($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL, LOCK_EX);
    }

    public function markdownSnapshot(array $report): string
    {
        $lines = [
            "<!-- FNLLA_TECH_DEBT_REPORT:BEGIN -->",
            "## Self-Checking Debt Snapshot",
            "",
            "Refresh this section with:",
            "",
            "```bash",
            "php fnlla tech-debt:update",
            "php fnlla tech-debt:update --check",
            "```",
            "",
            "The command rebuilds a machine-readable report, rewrites this bounded",
            "snapshot and returns a non-zero exit code when `--check` sees stale",
            "documentation.",
            "",
            "| Check | Status | Detail |",
            "| --- | --- | --- |",
        ];
        $snapshotSummary = ["passed" => 0, "warnings" => 0, "failures" => 0, "info" => 0];

        foreach ((array) ($report["checks"] ?? []) as $check) {
            if (($check["id"] ?? "") === "runtime-residue") {
                $check["status"] = "runtime";
                $check["detail"] = "Runtime data must be excluded from source artifacts, not deleted from a working application.";
            }
            match ((string) ($check["status"] ?? "info")) {
                "pass" => $snapshotSummary["passed"]++,
                "warn" => $snapshotSummary["warnings"]++,
                "fail" => $snapshotSummary["failures"]++,
                default => $snapshotSummary["info"]++,
            };

            $lines[] = sprintf(
                "| `%s` | `%s` | %s |",
                $this->markdownCell((string) ($check["id"] ?? "")),
                $this->markdownCell((string) ($check["status"] ?? "")),
                $this->markdownCell((string) ($check["detail"] ?? ""))
            );
        }

        $lines[] = "";
        $lines[] = "Generated actions:";
        $lines[] = "";

        $actions = array_values(array_filter((array) ($report["actions"] ?? []), static function (mixed $action): bool {
            return !is_string($action) || !str_starts_with($action, "Verify runtime data is excluded");
        }));
        if ($actions === []) {
            $lines[] = "- No generated remediation actions at this point.";
        } else {
            foreach ($actions as $action) {
                $lines[] = "- " . $this->markdownCell((string) $action);
            }
        }

        $lines[] = "";
        $lines[] = sprintf(
            "Snapshot summary: `%s` pass, `%s` warn, `%s` fail, `%s` info.",
            (string) $snapshotSummary["passed"],
            (string) $snapshotSummary["warnings"],
            (string) $snapshotSummary["failures"],
            (string) $snapshotSummary["info"]
        );
        $lines[] = "<!-- FNLLA_TECH_DEBT_REPORT:END -->";

        return implode(PHP_EOL, $lines) . PHP_EOL;
    }

    public function syncMarkdown(string $path, array $report, bool $checkOnly): array
    {
        $current = is_file($path) ? (string) file_get_contents($path) : "";
        $snapshot = $this->markdownSnapshot($report);
        $begin = "<!-- FNLLA_TECH_DEBT_REPORT:BEGIN -->";
        $end = "<!-- FNLLA_TECH_DEBT_REPORT:END -->";

        if (str_contains($current, $begin) && str_contains($current, $end)) {
            $pattern = '/' . preg_quote($begin, '/') . '.*?' . preg_quote($end, '/') . '\R?/s';
            $updated = (string) preg_replace($pattern, $snapshot, $current, 1);
        } else {
            $updated = rtrim($current) . PHP_EOL . PHP_EOL . $snapshot;
        }

        $changed = $updated !== $current;

        if (!$checkOnly && $changed) {
            file_put_contents($path, $updated, LOCK_EX);
        }

        return [
            "path" => $path,
            "changed" => $changed,
            "ok" => !$checkOnly || !$changed,
        ];
    }

    private function checkDebtMarkers(): array
    {
        $matches = [];

        foreach ($this->sourceFiles() as $relativePath => $absolutePath) {
            $contents = (string) file_get_contents($absolutePath);

            if (preg_match($this->markerPattern(), $contents) === 1) {
                $matches[] = $relativePath;
            }
        }

        sort($matches);

        return [
            "id" => "explicit-debt-markers",
            "status" => $matches === [] ? "pass" : "warn",
            "detail" => $matches === [] ? "No explicit debt markers found in release-facing source files." : "Explicit debt markers need owner, scope and acceptance criteria.",
            "data" => [
                "count" => count($matches),
                "files" => $matches,
            ],
        ];
    }

    private function checkRuntimeResidue(): array
    {
        $files = [];
        $excluded = [
            $this->relativePath(framework_technical_debt_report_path()) => true,
        ];

        foreach (["storage/framework/cache", "storage/framework/sessions", "storage/framework/queue", "storage/logs"] as $directory) {
            $absolute = base_path($directory);

            if (!is_dir($absolute)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($absolute, FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $item) {
                if (!$item->isFile() || $item->getBasename() === ".gitignore") {
                    continue;
                }

                $relativePath = $this->relativePath($item->getPathname());

                if (isset($excluded[$relativePath])) {
                    continue;
                }

                $files[] = $relativePath;
            }
        }

        sort($files);

        return [
            "id" => "runtime-residue",
            "status" => $files === [] ? "pass" : "warn",
            "detail" => $files === [] ? "No runtime cache, session, queue or log residue detected." : "Local runtime data exists; verify source artifact exclusions without deleting application state.",
            "data" => [
                "count" => count($files),
                "files" => array_slice($files, 0, 50),
            ],
        ];
    }

    private function checkDocsHygiene(): array
    {
        $script = base_path("scripts/check-docs.php");

        if (!is_file($script)) {
            return [
                "id" => "documentation-hygiene",
                "status" => "warn",
                "detail" => "Documentation hygiene script is missing.",
                "data" => [],
            ];
        }

        $result = ProcessRunner::run([PHP_BINARY, $script], base_path(), 120);

        return [
            "id" => "documentation-hygiene",
            "status" => $result["exit_code"] === 0 ? "pass" : "warn",
            "detail" => $result["exit_code"] === 0 ? "Documentation hygiene and relative links passed." : "Documentation hygiene failed; run php scripts/check-docs.php.",
            "data" => [
                "exit_code" => $result["exit_code"],
            ],
        ];
    }

    private function checkReleaseDocs(): array
    {
        $required = [
            "README.md",
            "CHANGELOG.md",
            "docs/README.md",
            "docs/AI-CONTEXT.md",
            "docs/DEVELOPER-PANEL.md",
            "docs/ENVIRONMENT.md",
            "docs/PUBLIC-API.md",
            "docs/RELEASE-AND-OPERATIONS.md",
        ];
        $missing = [];

        foreach ($required as $file) {
            if (!is_file(base_path($file))) {
                $missing[] = $file;
            }
        }

        return [
            "id" => "release-documentation",
            "status" => $missing === [] ? "pass" : "fail",
            "detail" => $missing === [] ? "Required release, AI, operations and developer-panel documents are present." : "Required release documentation is missing.",
            "data" => ["missing" => $missing],
        ];
    }

    private function checkAiRuntime(): array
    {
        $required = [
            "config/ai.php",
            "resources/fnlla-ai-runtime/MANIFEST.json",
            "resources/fnlla-ai-runtime/profile.json",
            "resources/fnlla-ai-runtime/intents/core.json",
            "resources/fnlla-ai-runtime/knowledge/base.json",
            "resources/fnlla-ai-runtime/prompts/registry.json",
            "resources/fnlla-ai-runtime/evals/runtime-commands.json",
            "src/Ai/RuntimeAiProviderRegistry.php",
            "src/Ai/LocalRuntimeAssistant.php",
            "src/Ai/FionnRuntimeBridge.php",
        ];
        $missing = [];

        foreach ($required as $file) {
            if (!is_file(base_path($file))) {
                $missing[] = $file;
            }
        }

        return [
            "id" => "ai-product-runtime",
            "status" => $missing === [] ? "pass" : "fail",
            "detail" => $missing === [] ? "Local reference lookup and the opt-in FIONN AI gateway contract are present." : "Runtime AI surface is incomplete.",
            "data" => ["missing" => $missing],
        ];
    }

    private function checkPublicApiLock(): array
    {
        $path = base_path("docs/PUBLIC-API.lock.json");
        $payload = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
        $commands = is_array($payload) ? (array) ($payload["commands"] ?? []) : [];
        $schemas = is_array($payload) ? (array) ($payload["schemas"] ?? []) : [];
        $ok = is_array($payload)
            && in_array("tech-debt:update", $commands, true)
            && in_array("fnlla.technical_debt_report.v1", $schemas, true);

        return [
            "id" => "technical-debt-public-contract",
            "status" => $ok ? "pass" : "warn",
            "detail" => $ok ? "Technical-debt command and schema are present in the public API lock." : "Refresh docs/PUBLIC-API.lock.json with php fnlla api:lock.",
            "data" => [
                "path" => "docs/PUBLIC-API.lock.json",
            ],
        ];
    }

    private function actions(array $checks): array
    {
        $actions = [];
        if ($this->statusFor($checks, "modernization-ledger") === "warn") {
            $actions[] = "Run php scripts/check-modernization.php --require-complete and close outstanding acceptance criteria before claiming modernization is complete.";
        }

        if ($this->statusFor($checks, "explicit-debt-markers") !== "pass") {
            $actions[] = "Replace explicit debt markers with tracked issues or implement the missing work.";
        }

        if ($this->statusFor($checks, "runtime-residue") !== "pass") {
            $actions[] = "Verify runtime data is excluded from the source archive; remove only obsolete generated release artifacts.";
        }

        if (in_array($this->statusFor($checks, "documentation-hygiene"), ["warn", "fail"], true)) {
            $actions[] = "Run php scripts/check-docs.php, fix reported issues and repeat php fnlla tech-debt:update --check.";
        }

        if (in_array($this->statusFor($checks, "technical-debt-public-contract"), ["warn", "fail"], true)) {
            $actions[] = "Run php fnlla api:lock after public CLI or schema changes.";
        }

        return $actions;
    }

    private function checkModernizationLedger(): array
    {
        $path = base_path("resources/modernization-tasks.json");
        $ledger = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        if (($ledger["schema"] ?? null) !== "fnlla.modernization_tasks.v1" || !is_array($ledger["tasks"] ?? null)) {
            throw new \RuntimeException("Invalid modernization ledger.");
        }
        $remaining = array_values(array_filter($ledger["tasks"], static fn (array $task): bool => $task["status"] !== "done"));
        return ["id" => "modernization-ledger", "status" => $remaining === [] ? "pass" : "warn",
            "detail" => count($remaining) . " modernization criteria remain unfinished. See docs/MODERNIZATION-STATUS.md.",
            "data" => ["remaining" => $remaining]];
    }

    private function summary(array $checks): array
    {
        $summary = ["passed" => 0, "warnings" => 0, "failures" => 0, "info" => 0];

        foreach ($checks as $check) {
            match ((string) ($check["status"] ?? "info")) {
                "pass" => $summary["passed"]++,
                "warn" => $summary["warnings"]++,
                "fail" => $summary["failures"]++,
                default => $summary["info"]++,
            };
        }

        return $summary;
    }

    private function statusFor(array $checks, string $id): string
    {
        foreach ($checks as $check) {
            if (($check["id"] ?? "") === $id) {
                return (string) ($check["status"] ?? "info");
            }
        }

        return "info";
    }

    /**
     * @return array<string, string>
     */
    private function sourceFiles(): array
    {
        $files = [];

        foreach (["src", "bootstrap", "config", "routes", "views", "tests", "scripts", "docs", "resources"] as $directory) {
            $absolute = base_path($directory);

            if (!is_dir($absolute)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($absolute, FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $item) {
                if (!$item->isFile() || $item->isLink()) {
                    continue;
                }

                $relativePath = $this->relativePath($item->getPathname());

                if ($relativePath === "docs/DEVELOPER-PANEL.md" || !$this->isTextSource($relativePath)) {
                    continue;
                }

                $files[$relativePath] = $item->getPathname();
            }
        }

        ksort($files);

        return $files;
    }

    private function isTextSource(string $relativePath): bool
    {
        return preg_match('/\.(php|md|json|js|css|neon|dist|cmd|ps1)$/i', $relativePath) === 1;
    }

    private function markdownCell(string $value): string
    {
        return str_replace(["|", "\r", "\n"], ["\\|", " ", " "], $value);
    }

    private function markerPattern(): string
    {
        $words = [
            ["TO", "DO"],
            ["FIX", "ME"],
            ["HA", "CK"],
            ["X", "XX"],
        ];

        return '/(?:^|[\/#*<\s])(' . implode("|", array_map(
            static fn (array $parts): string => preg_quote(implode("", $parts), '/'),
            $words
        )) . ')(?:\s*:|\s+-|\s|$)/im';
    }

    private function relativePath(string $path): string
    {
        return str_replace("\\", "/", substr($path, strlen(base_path()) + 1));
    }

    private function readFirstLine(string $path): ?string
    {
        if (!is_file($path)) {
            return null;
        }

        $contents = trim(strtok((string) file_get_contents($path), "\r\n") ?: "");

        return $contents !== "" ? $contents : null;
    }
}
