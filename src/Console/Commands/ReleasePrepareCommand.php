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

use Fnlla\Php\Console\Command;
use Fnlla\Php\Console\Input;
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
        $input = Input::parse($arguments, ["json" => false, "skip-tests" => false, "major" => false, "target" => true]);
        if ($input->option("help", false)) { $this->printHelp(); return 0; }
        $json = $input->option("json", false);
        $skipTests = $input->option("skip-tests", false);
        $major = $input->option("major", false);
        $target = $input->option("target", $this->currentVersionTarget());
        $steps = [];

        // Documentation checks apply to every maintainer release, even with --skip-tests.
        if (!$this->isProject()) {
            foreach ($this->documentationCommands() as $label => $command) {
                $result = ProcessRunner::run($command, base_path(), 300);
                $steps[] = ["label" => $label, "exit_code" => $result["exit_code"], "ok" => $result["exit_code"] === 0];
                if ($result["exit_code"] !== 0) {
                    return $this->finish($json, $steps, [], 1, $result["output"]);
                }
            }
        }

        if (!$skipTests) {
            foreach ($this->validationCommands($major, $target) as $label => $command) {
                $result = ProcessRunner::run($command, base_path(), $label === "tests" ? 1800 : 300);
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

        $builder = $this->container->make(ReleaseArtifactBuilder::class);
        $artifacts = [
            "sbom" => $builder->buildSbom($builder->defaultOutputPath("fnlla-sbom.cdx.json")),
            "checksums" => $builder->buildChecksums($builder->defaultOutputPath("SHA256SUMS")),
        ];
        $artifacts["manifest"] = $builder->buildManifest($builder->defaultOutputPath("fnlla-release-manifest.json"), [
            "sbom" => (string) ($artifacts["sbom"]["path"] ?? ""),
            "checksums" => (string) ($artifacts["checksums"]["path"] ?? ""),
        ]);

        if ($major) {
            $artifacts["major"] = $this->buildMajorArtifacts($target);
        }

        return $this->finish($json, $steps, $artifacts, 0);
    }

    private function finish(bool $json, array $steps, array $artifacts, int $exitCode, string $error = ""): int
    {
        $tested = count(array_filter($steps, static fn (array $step): bool => $step["label"] === "tests" && $step["ok"])) > 0;
        $payload = [
            "schema" => "fnlla.release_prepare.v1",
            "generated_at_utc" => gmdate(DATE_ATOM),
            "ok" => $exitCode === 0,
            "steps" => $steps,
            "artifacts" => $artifacts,
            "validation" => $exitCode !== 0 ? "failed" : ($tested ? "passed" : "skipped"),
            "risk" => $exitCode !== 0 ? "high" : $this->riskScore($steps, $artifacts),
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

        $this->line($exitCode !== 0 ? "Release preparation failed." : ($tested
            ? "Local release validation passed; publication acceptance must be verified separately."
            : "Artifacts prepared; validation skipped. This is not a release-readiness result."));

        return $exitCode;
    }

    private function riskScore(array $steps, array $artifacts): string
    {
        if (count(array_filter($steps, static fn (array $step): bool => !($step["ok"] ?? false))) > 0) {
            return "high";
        }
        if (count(array_filter($steps, static fn (array $step): bool => $step["label"] === "tests" && $step["ok"])) === 0) {
            return "unknown";
        }

        $upgrade = $artifacts["major"]["upgrade_plan"] ?? null;
        if (is_array($upgrade) && (int) ($upgrade["actions"] ?? 0) > 3) {
            return "medium";
        }

        return "low";
    }

    private function validationCommands(bool $major, string $target): array
    {
        $commands = [
            "tests" => $this->isProject()
                ? [PHP_BINARY, base_path("scripts/test.php"), "--suite", "all"]
                : [PHP_BINARY, base_path("vendor/phpunit/phpunit/phpunit"), "--testsuite", "framework", "--fail-on-skipped"],
            "lint" => [PHP_BINARY, base_path("scripts/lint.php")],
            "runtime contract" => [PHP_BINARY, base_path("scripts/validate-fnlla-runtime.php")],
            "version manifest" => [PHP_BINARY, base_path("scripts/validate-version-manifest.php")],
            "release metadata" => [PHP_BINARY, base_path("scripts/validate-release-metadata.php")],
            "static analysis" => [PHP_BINARY, base_path("scripts/static-analysis.php")],
            "technical debt snapshot" => [PHP_BINARY, base_path("fnlla"), "tech-debt:update", "--check"],
        ];

        if ($this->isProject()) {
            unset($commands["release metadata"], $commands["technical debt snapshot"]);
            $commands["project acceptance"] = [PHP_BINARY, base_path("fnlla"), "project:acceptance", "--json"];
            $commands["project configuration"] = [PHP_BINARY, base_path("fnlla"), "config:doctor", "--json"];
        }

        if ($major) {
            $commands["security posture"] = [PHP_BINARY, base_path("fnlla"), "security:audit", "--strict", "--json"];
            $commands["upgrade readiness"] = [PHP_BINARY, base_path("fnlla"), "upgrade:check", "--target", $target, "--json"];
            $commands["application map"] = [PHP_BINARY, base_path("fnlla"), "app:map", "--json"];
        }

        return $commands;
    }

    private function documentationCommands(): array
    {
        return [
            "docs hygiene" => [PHP_BINARY, base_path("scripts/check-docs.php")],
            "modernization ledger" => [PHP_BINARY, base_path("scripts/check-modernization.php")],
        ];
    }

    private function isProject(): bool
    {
        return is_file(base_path(".fnlla/framework-lock.json")) || is_file(base_path(".fnlla/ui-distribution"));
    }

    private function currentVersionTarget(): string
    {
        $contents = is_file(base_path("VERSION")) ? (string) file_get_contents(base_path("VERSION")) : "";
        $version = trim(strtok($contents, "\r\n") ?: "");

        return $version !== "" ? $version : UpgradeAnalyzer::DEFAULT_TARGET_VERSION;
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

    public function usage(): string
    {
        return "release:prepare [--json] [--skip-tests] [--major] [--target=VERSION] [--help]";
    }
}
