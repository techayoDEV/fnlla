<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA SUPPORT SOURCE
File: src\Support\UpgradeAnalyzer.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

Purpose:
- Builds major-release upgrade readiness reports and safe action plans for
  FNLLA maintainer and downstream project repositories.
*/

namespace Fnlla\Php\Support;

use Fnlla\Php\Cache\CacheStoreInterface;
use Fnlla\Php\Observability\MetricsRecorder;

final class UpgradeAnalyzer
{
    public function report(string $targetVersion = "2.0.0"): array
    {
        $checks = [
            $this->checkRequiredFiles(),
            $this->checkRuntimeResidue(),
            $this->checkBootstrapCaches(),
            $this->checkFrameworkLock(),
            $this->checkMajorDocs(),
            $this->checkCacheSerializer(),
            $this->checkIntegratedAiRuntime(),
            $this->checkAssistantVendorMarkers(),
        ];

        return [
            "schema" => "fnlla.upgrade_report.v1",
            "generated_at_utc" => gmdate(DATE_ATOM),
            "target_version" => $targetVersion,
            "current_version" => $this->readFirstLine(base_path("VERSION")),
            "repository_kind" => is_file(base_path(".fnlla/framework-lock.json")) ? "exported-project" : "maintainer-source",
            "checks" => $checks,
            "summary" => $this->summary($checks),
            "plan" => $this->planFromChecks($checks, $targetVersion),
        ];
    }

    public function write(array $report, string $path): void
    {
        $directory = dirname($path);

        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        file_put_contents($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL, LOCK_EX);
    }

    public function applySafePlan(array $report, bool $dryRun): array
    {
        $actions = [];

        foreach ((array) ($report["plan"]["actions"] ?? []) as $action) {
            $id = (string) ($action["id"] ?? "");
            $safe = (bool) ($action["safe_to_apply"] ?? false);

            if (!$safe) {
                $actions[] = [
                    "id" => $id,
                    "status" => "manual",
                    "detail" => "Action requires manual review.",
                ];
                continue;
            }

            if ($dryRun) {
                $actions[] = [
                    "id" => $id,
                    "status" => "dry-run",
                    "detail" => (string) ($action["detail"] ?? "Would apply safe action."),
                ];
                continue;
            }

            if ($id === "clear-runtime-residue") {
                $this->clearRuntimeResidue();
                $actions[] = [
                    "id" => $id,
                    "status" => "applied",
                    "detail" => "Runtime cache, metrics and warm artefacts cleared.",
                ];
                continue;
            }

            if ($id === "write-upgrade-plan") {
                $this->write($report, framework_upgrade_plan_path());
                $actions[] = [
                    "id" => $id,
                    "status" => "applied",
                    "detail" => "Upgrade plan written to " . framework_upgrade_plan_path(),
                ];
                continue;
            }

            $actions[] = [
                "id" => $id,
                "status" => "skipped",
                "detail" => "No automatic handler is registered for this action.",
            ];
        }

        return [
            "schema" => "fnlla.upgrade_apply.v1",
            "generated_at_utc" => gmdate(DATE_ATOM),
            "dry_run" => $dryRun,
            "actions" => $actions,
        ];
    }

    private function checkRequiredFiles(): array
    {
        $missing = [];

        foreach (["README.md", "VERSION", "MANIFEST.json", "fnlla", "bootstrap/common.php", "public/index.php"] as $file) {
            if (!is_file(base_path($file))) {
                $missing[] = $file;
            }
        }

        return [
            "id" => "required-files",
            "status" => $missing === [] ? "pass" : "fail",
            "detail" => $missing === [] ? "Required release files are present." : "Missing required files: " . implode(", ", $missing),
            "data" => ["missing" => $missing],
        ];
    }

    private function checkRuntimeResidue(): array
    {
        $files = [];

        foreach (["storage/framework/cache", "storage/framework/sessions", "storage/framework/queue", "storage/logs"] as $directory) {
            foreach (glob(base_path($directory) . DIRECTORY_SEPARATOR . "*") ?: [] as $path) {
                if (basename($path) !== ".gitignore") {
                    $files[] = str_replace("\\", "/", substr($path, strlen(base_path()) + 1));
                }
            }
        }

        return [
            "id" => "runtime-residue",
            "status" => $files === [] ? "pass" : "warn",
            "detail" => $files === [] ? "No runtime residue detected." : "Runtime files should be cleared before release.",
            "data" => ["files" => $files],
        ];
    }

    private function checkBootstrapCaches(): array
    {
        $artifacts = array_values(array_filter([
            framework_config_cache_path(),
            framework_route_cache_path(),
            framework_asset_manifest_path(),
            framework_preload_path(),
        ], static fn (string $path): bool => is_file($path)));

        return [
            "id" => "bootstrap-caches",
            "status" => $artifacts === [] ? "pass" : "warn",
            "detail" => $artifacts === [] ? "No generated bootstrap caches are present." : "Generated bootstrap caches should not be committed.",
            "data" => [
                "files" => array_map(static fn (string $path): string => str_replace("\\", "/", substr($path, strlen(base_path()) + 1)), $artifacts),
            ],
        ];
    }

    private function checkFrameworkLock(): array
    {
        $path = base_path(".fnlla/framework-lock.json");

        if (!is_file($path)) {
            return [
                "id" => "framework-lock",
                "status" => "info",
                "detail" => "No downstream framework lock found; this looks like the maintainer source repository.",
                "data" => [],
            ];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return [
            "id" => "framework-lock",
            "status" => is_array($decoded) ? "pass" : "fail",
            "detail" => is_array($decoded) ? "Framework lock is parseable." : "Framework lock is not valid JSON.",
            "data" => is_array($decoded) ? ["framework_version" => $decoded["framework_version"] ?? null] : [],
        ];
    }

    private function checkMajorDocs(): array
    {
        $missing = [];

        foreach (["docs/MIGRATION.md", "CHANGELOG.md", "docs/MAJOR-RELEASE-CHECKLIST.md"] as $file) {
            if (!is_file(base_path($file))) {
                $missing[] = $file;
            }
        }

        return [
            "id" => "major-release-docs",
            "status" => $missing === [] ? "pass" : "warn",
            "detail" => $missing === [] ? "Major-release docs are present." : "Major release should include migration, changelog and checklist docs.",
            "data" => ["missing" => $missing],
        ];
    }

    private function checkCacheSerializer(): array
    {
        $serializer = (string) config("cache.serializer", "json");

        return [
            "id" => "cache-serializer",
            "status" => $serializer === "json" ? "pass" : "warn",
            "detail" => $serializer === "json" ? "JSON cache serializer is the default." : "Consider JSON cache serializer before a public major release.",
            "data" => ["serializer" => $serializer],
        ];
    }

    private function checkIntegratedAiRuntime(): array
    {
        $missing = [];

        foreach ([
            "resources/fnlla-ai-runtime/VERSION",
            "resources/fnlla-ai-runtime/MANIFEST.json",
            "resources/fnlla-ai-runtime/README.md",
            "resources/fnlla-ai-runtime/profile.json",
            "resources/fnlla-ai-runtime/intents/core.json",
            "resources/fnlla-ai-runtime/knowledge/base.json",
        ] as $file) {
            if (!is_file(base_path($file))) {
                $missing[] = $file;
            }
        }

        return [
            "id" => "integrated-ai-runtime",
            "status" => $missing === [] ? "pass" : "fail",
            "detail" => $missing === [] ? "Integrated runtime intelligence bundle is present." : "Runtime intelligence bundle is incomplete.",
            "data" => ["missing" => $missing],
        ];
    }

    private function checkAssistantVendorMarkers(): array
    {
        $matches = [];
        $patterns = array_map(
            static fn (array $parts): string => '/\b' . preg_quote(implode('', $parts), '/') . '\b/i',
            [
                ['Co', 'dex'],
                ['Chat', 'G', 'PT'],
                ['Open', 'A', 'I'],
                ['Clau', 'de'],
                ['Anth', 'ropic'],
                ['Google', ' ', 'Gem', 'ini'],
                ['Gem', 'ini', ' ', 'A', 'I'],
            ]
        );
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(base_path(), \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $item) {
            if (!$item->isFile()) {
                continue;
            }

            $relativePath = str_replace("\\", "/", substr($item->getPathname(), strlen(base_path()) + 1));

            if (preg_match('#^(\.git|dist|storage|vendor)/#', $relativePath) === 1) {
                continue;
            }

            if (preg_match('/\.(png|jpe?g|gif|webp|ico|pdf|zip|phar)$/i', $relativePath) === 1) {
                continue;
            }

            $contents = (string) file_get_contents($item->getPathname());

            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $contents) === 1) {
                    $matches[] = $relativePath;
                    break;
                }
            }
        }

        sort($matches);

        return [
            "id" => "assistant-vendor-markers",
            "status" => $matches === [] ? "pass" : "fail",
            "detail" => $matches === [] ? "No assistant-vendor markers detected in published sources." : "Published sources must not mention assistant-vendor tooling.",
            "data" => ["files" => $matches],
        ];
    }

    private function planFromChecks(array $checks, string $targetVersion): array
    {
        $actions = [
            [
                "id" => "write-upgrade-plan",
                "title" => "Persist machine-readable upgrade plan",
                "detail" => "Write the upgrade report to storage so CI and AI review workflows can consume it.",
                "safe_to_apply" => true,
            ],
        ];

        if ($this->statusFor($checks, "runtime-residue") !== "pass" || $this->statusFor($checks, "bootstrap-caches") !== "pass") {
            $actions[] = [
                "id" => "clear-runtime-residue",
                "title" => "Clear generated runtime artefacts",
                "detail" => "Remove cache/session/queue/log residue before tagging " . $targetVersion . ".",
                "safe_to_apply" => true,
            ];
        }

        $actions[] = [
            "id" => "manual-migration-review",
            "title" => "Review migration notes and public API contract",
            "detail" => "Confirm helper, CLI, config and project-export contracts before tagging a major release.",
            "safe_to_apply" => false,
        ];

        return [
            "target_version" => $targetVersion,
            "actions" => $actions,
        ];
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

    private function clearRuntimeResidue(): void
    {
        foreach ([framework_config_cache_path(), framework_route_cache_path(), framework_asset_manifest_path(), framework_preload_path(), framework_ai_context_path(), framework_ai_review_pack_path(), framework_ai_upgrade_brief_path(), framework_app_map_path()] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        app(CacheStoreInterface::class)->clear();
        app(MetricsRecorder::class)->clear();

        foreach (["storage/framework/cache", "storage/framework/sessions", "storage/framework/queue", "storage/logs"] as $directory) {
            $this->clearRuntimeDirectory(base_path($directory));
        }
    }

    private function clearRuntimeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            $path = $item->getPathname();

            if ($item->getBasename() === ".gitignore") {
                continue;
            }

            $item->isDir() ? rmdir($path) : unlink($path);
        }
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
