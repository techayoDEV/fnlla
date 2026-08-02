<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA CONSOLE SOURCE
File: src\Console\Commands\ReleasePrepareCommand.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

Purpose:
- Runs the release gate and generates supply-chain artefacts.
*/

namespace Fnlla\Php\Console\Commands;

use Fnlla\Php\Cache\CacheStoreInterface;
use Fnlla\Php\Console\Command;
use Fnlla\Php\Observability\MetricsRecorder;
use Fnlla\Php\Support\AiContextBuilder;
use Fnlla\Php\Support\AppMapBuilder;
use Fnlla\Php\Support\ProcessRunner;
use Fnlla\Php\Support\ReleaseArtifactBuilder;
use Fnlla\Php\Support\UpgradeAnalyzer;

final class ReleasePrepareCommand extends Command
{
    public function name(): string
    {
        return "release:prepare";
    }

    public function description(): string
    {
        return "Run release validation and generate SBOM/checksums.";
    }

    public function handle(array $arguments): int
    {
        $json = in_array("--json", $arguments, true);
        $skipTests = in_array("--skip-tests", $arguments, true);
        $major = in_array("--major", $arguments, true);
        $target = $this->optionValue($arguments, "--target") ?? "2.0.0";
        $steps = [];

        if (!$skipTests) {
            foreach ($this->validationCommands($major, $target) as $label => $command) {
                $result = ProcessRunner::run($command, base_path(), 300);
                $steps[] = [
                    "label" => $label,
                    "exit_code" => $result["exit_code"],
                    "ok" => $result["exit_code"] === 0,
                ];

                if ($result["exit_code"] !== 0) {
                    return $this->finish($json, $steps, [], 1, $result["output"]);
                }
            }
        }

        $this->clearRuntimeResidue();

        $builder = $this->container->make(ReleaseArtifactBuilder::class);
        $artifacts = [
            "sbom" => $builder->buildSbom($builder->defaultOutputPath("fnlla-sbom.cdx.json")),
            "checksums" => $builder->buildChecksums($builder->defaultOutputPath("SHA256SUMS")),
        ];

        if ($major) {
            $artifacts["major"] = $this->buildMajorArtifacts($target);
        }

        return $this->finish($json, $steps, $artifacts, 0);
    }

    private function finish(bool $json, array $steps, array $artifacts, int $exitCode, string $error = ""): int
    {
        $payload = [
            "schema" => "fnlla.release_prepare.v1",
            "generated_at_utc" => gmdate(DATE_ATOM),
            "ok" => $exitCode === 0,
            "steps" => $steps,
            "artifacts" => $artifacts,
            "error" => $error,
        ];

        if ($json) {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            return $exitCode;
        }

        foreach ($steps as $step) {
            $this->line(sprintf("[%s] %s", $step["ok"] ? "OK" : "FAIL", $step["label"]));
        }

        $this->printArtifacts($artifacts);

        if ($error !== "") {
            $this->error($error);
        }

        $this->line($exitCode === 0 ? "Release preparation passed." : "Release preparation failed.");

        return $exitCode;
    }

    private function validationCommands(bool $major, string $target): array
    {
        $commands = [
            "tests" => [PHP_BINARY, base_path("scripts/test.php"), "--suite", "all"],
            "lint" => [PHP_BINARY, base_path("scripts/lint.php")],
            "runtime contract" => [PHP_BINARY, base_path("scripts/validate-fnlla-runtime.php")],
            "version manifest" => [PHP_BINARY, base_path("scripts/validate-version-manifest.php")],
            "release metadata" => [PHP_BINARY, base_path("scripts/validate-release-metadata.php")],
            "static analysis" => [PHP_BINARY, base_path("scripts/static-analysis.php")],
        ];

        if ($major) {
            $commands["docs in sync"] = [PHP_BINARY, base_path("scripts/build-docs.php"), "--check"];
            $commands["security posture"] = [PHP_BINARY, base_path("fnlla"), "security:audit", "--json"];
            $commands["upgrade readiness"] = [PHP_BINARY, base_path("fnlla"), "upgrade:check", "--target", $target, "--json"];
            $commands["application map"] = [PHP_BINARY, base_path("fnlla"), "app:map", "--json"];
        }

        return $commands;
    }

    private function printArtifacts(array $artifacts, string $prefix = ""): void
    {
        foreach ($artifacts as $name => $artifact) {
            $label = $prefix !== "" ? $prefix . "." . (string) $name : (string) $name;

            if (is_array($artifact) && isset($artifact["path"])) {
                $this->line(sprintf("Artefact %s: %s", $label, (string) $artifact["path"]));
                continue;
            }

            if (is_array($artifact)) {
                $this->printArtifacts($artifact, $label);
            }
        }
    }

    private function clearRuntimeResidue(): void
    {
        foreach ([framework_config_cache_path(), framework_route_cache_path(), framework_asset_manifest_path(), framework_preload_path()] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        $this->container->make(CacheStoreInterface::class)->clear();
        $this->container->make(MetricsRecorder::class)->clear();

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

    private function buildMajorArtifacts(string $target): array
    {
        $directory = base_path("dist/release/major");

        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $appMapPath = $directory . DIRECTORY_SEPARATOR . "fnlla-app-map.json";
        $upgradePlanPath = $directory . DIRECTORY_SEPARATOR . "fnlla-upgrade-plan.json";
        $aiReviewPackPath = $directory . DIRECTORY_SEPARATOR . "fnlla-ai-review-pack.json";
        $appMap = $this->container->make(AppMapBuilder::class)->build();
        $upgrade = $this->container->make(UpgradeAnalyzer::class)->report($target);
        $contextBuilder = $this->container->make(AiContextBuilder::class);
        $reviewPack = $contextBuilder->redactedCopy([
            "schema" => "fnlla.ai_review_pack.v1",
            "generated_at_utc" => gmdate(DATE_ATOM),
            "privacy" => [
                "mode" => "local-only",
                "external_calls" => false,
                "raw_env_included" => false,
                "source_files_included" => false,
            ],
            "context" => $contextBuilder->build(),
            "app_map" => $appMap,
            "upgrade" => $upgrade,
        ]);

        $this->container->make(AppMapBuilder::class)->writeJson($appMap, $appMapPath);
        $this->container->make(UpgradeAnalyzer::class)->write($upgrade, $upgradePlanPath);
        $contextBuilder->write($reviewPack, $aiReviewPackPath);

        return [
            "app_map" => [
                "path" => $appMapPath,
                "routes" => (int) ($appMap["routes"]["count"] ?? 0),
            ],
            "upgrade_plan" => [
                "path" => $upgradePlanPath,
                "actions" => count((array) ($upgrade["plan"]["actions"] ?? [])),
            ],
            "ai_review_pack" => [
                "path" => $aiReviewPackPath,
                "bytes" => is_file($aiReviewPackPath) ? filesize($aiReviewPackPath) : 0,
            ],
        ];
    }

    private function optionValue(array $arguments, string $name): ?string
    {
        foreach ($arguments as $index => $argument) {
            if ($argument === $name && isset($arguments[$index + 1])) {
                return (string) $arguments[$index + 1];
            }

            if (str_starts_with((string) $argument, $name . "=")) {
                return substr((string) $argument, strlen($name) + 1);
            }
        }

        return null;
    }
}
