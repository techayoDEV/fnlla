<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA SUPPORT SOURCE
File: src\Support\ProjectClaimManager.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Claims an exported FNLLA project by writing product ownership, delivery and
  maintenance identity into project-local metadata without copying client logic
  back into the framework.
*/

namespace Fnlla\Php\Support;

use RuntimeException;

final class ProjectClaimManager
{
    public const README_START_MARKER = "<!-- FNLLA_PROJECT_IDENTITY_START -->";
    public const README_END_MARKER = "<!-- FNLLA_PROJECT_IDENTITY_END -->";

    public function claim(array $input, ?string $projectRoot = null): array
    {
        $projectRoot = rtrim($projectRoot ?? base_path(), "\\/");
        $identity = $this->normalizeIdentity($input);

        $this->writeManifest($projectRoot, $identity);
        $this->writeAppConfig($projectRoot, $identity);
        $this->writeEnvExample($projectRoot, $identity);
        $this->writeReadme($projectRoot, $identity);
        $this->syncFrameworkLock($projectRoot, $identity);

        return $identity;
    }

    public function normalizeIdentity(array $input): array
    {
        $productName = $this->required($input, "product");
        $owner = $this->required($input, "owner");
        $developer = $this->required($input, "developer");
        $identifier = $this->identifier((string) ($input["id"] ?? $input["identifier"] ?? ""), $productName);
        $slug = $this->slug((string) ($input["slug"] ?? ""), $productName);
        $maintainer = $this->optional($input, "maintainer", $developer);
        $funder = $this->optional($input, "funder", $owner);
        $client = $this->optional($input, "client", $owner);
        $systemOwner = $this->optional($input, "system_owner", $owner);
        $runtime = $this->optional($input, "runtime", "FNLLA");
        $runtimeCreator = $this->optional($input, "runtime_creator", "TechAyo LTD (techayo.co.uk)");
        $summary = $this->optional($input, "summary", $productName . " built on FNLLA.");

        return [
            "product" => $productName,
            "id" => $identifier,
            "slug" => $slug,
            "summary" => $summary,
            "owner" => $owner,
            "funder" => $funder,
            "client" => $client,
            "system_owner" => $systemOwner,
            "developer" => $developer,
            "maintainer" => $maintainer,
            "runtime" => $runtime,
            "runtime_creator" => $runtimeCreator,
        ];
    }

    private function writeManifest(string $projectRoot, array $identity): void
    {
        file_put_contents(
            $projectRoot . DIRECTORY_SEPARATOR . "MANIFEST.json",
            VersionManifest::encodeManifest(VersionManifest::buildClaimedProjectManifest($identity, $projectRoot))
        );
    }

    private function writeAppConfig(string $projectRoot, array $identity): void
    {
        $path = $projectRoot . DIRECTORY_SEPARATOR . "config" . DIRECTORY_SEPARATOR . "app.php";

        if (!is_file($path)) {
            throw new RuntimeException("config/app.php is missing; cannot claim project identity.");
        }

        $contents = (string) file_get_contents($path);
        $updated = preg_replace_callback(
            '/(["\']name["\']\s*=>\s*)(["\'])(.*?)\2\s*,/',
            static fn (array $matches): string => $matches[1] . var_export($identity["product"], true) . ",",
            $contents,
            1,
            $count
        );

        if (!is_string($updated) || $count !== 1) {
            throw new RuntimeException("Unable to update config/app.php with claimed project name.");
        }

        file_put_contents($path, $updated);
    }

    private function writeEnvExample(string $projectRoot, array $identity): void
    {
        $path = $projectRoot . DIRECTORY_SEPARATOR . ".env.example";

        if (!is_file($path)) {
            throw new RuntimeException(".env.example is missing; cannot write project identity template.");
        }

        $contents = (string) file_get_contents($path);
        $values = [
            "PROJECT_ID" => $identity["id"],
            "PROJECT_NAME" => $identity["product"],
            "PROJECT_OWNER" => $identity["owner"],
            "PROJECT_FUNDER" => $identity["funder"],
            "PROJECT_CLIENT" => $identity["client"],
            "PROJECT_SYSTEM_OWNER" => $identity["system_owner"],
            "PROJECT_DEVELOPER" => $identity["developer"],
            "PROJECT_MAINTAINER" => $identity["maintainer"],
            "PROJECT_RUNTIME" => $identity["runtime"],
            "PROJECT_RUNTIME_CREATOR" => $identity["runtime_creator"],
        ];

        foreach ($values as $key => $value) {
            $line = $key . "=" . $this->serializeEnvValue((string) $value);
            $pattern = '/^' . preg_quote($key, '/') . '=.*$/m';

            if (preg_match($pattern, $contents) === 1) {
                $contents = (string) preg_replace($pattern, $line, $contents, 1);
                continue;
            }

            if (!str_contains($contents, "PROJECT_ID=")) {
                $contents = rtrim($contents) . PHP_EOL . PHP_EOL . "# Project identity" . PHP_EOL;
            }

            $contents = rtrim($contents) . PHP_EOL . $line . PHP_EOL;
        }

        file_put_contents($path, $contents);
    }

    private function writeReadme(string $projectRoot, array $identity): void
    {
        $path = $projectRoot . DIRECTORY_SEPARATOR . "README.md";

        if (!is_file($path)) {
            throw new RuntimeException("README.md is missing; cannot write project identity notes.");
        }

        $contents = (string) file_get_contents($path);
        $block = $this->readmeIdentityBlock($identity);

        if (str_contains($contents, self::README_START_MARKER) && str_contains($contents, self::README_END_MARKER)) {
            $updated = preg_replace_callback(
                '/' . preg_quote(self::README_START_MARKER, '/') . '.*?' . preg_quote(self::README_END_MARKER, '/') . '/s',
                static fn (): string => $block,
                $contents,
                1,
                $count
            );

            if (!is_string($updated) || $count !== 1) {
                throw new RuntimeException("Unable to update README.md project identity block.");
            }

            file_put_contents($path, rtrim($updated) . PHP_EOL);
            return;
        }

        $updated = preg_replace('/^(# .+?\R)/', '$1' . PHP_EOL . $block . PHP_EOL, $contents, 1, $count);

        if (!is_string($updated) || $count !== 1) {
            $updated = $block . PHP_EOL . PHP_EOL . $contents;
        }

        file_put_contents($path, rtrim($updated) . PHP_EOL);
    }

    private function syncFrameworkLock(string $projectRoot, array $identity): void
    {
        $path = FrameworkLock::path($projectRoot);

        if (!is_file($path)) {
            return;
        }

        $lock = FrameworkLock::load($projectRoot);
        $configPath = $projectRoot . DIRECTORY_SEPARATOR . "config" . DIRECTORY_SEPARATOR . "app.php";

        $lock["framework_base"]["application"]["name"] = $identity["product"];
        $lock["framework_base"]["application"]["package_slug"] = $identity["slug"];

        if (is_file($configPath)) {
            $hash = hash_file("sha256", $configPath);

            if (is_string($hash) && $hash !== "") {
                $lock["framework_base"]["managed_files"]["config/app.php"] = $hash;
            }
        }

        file_put_contents($path, VersionManifest::encodeManifest($lock));
    }

    private function readmeIdentityBlock(array $identity): string
    {
        return self::README_START_MARKER . PHP_EOL
            . "## Project Identity" . PHP_EOL . PHP_EOL
            . "- Product/project identifier: `" . $identity["id"] . "`." . PHP_EOL
            . "- Product: " . $identity["product"] . "." . PHP_EOL
            . "- Owner, funder, client and website/system owner: " . $identity["owner"] . "." . PHP_EOL
            . "- Developer and implementation provider: " . $identity["developer"] . "." . PHP_EOL
            . "- Maintenance and update provider: " . $identity["maintainer"] . "." . PHP_EOL
            . "- Framework/runtime: `" . $identity["runtime"] . "`." . PHP_EOL
            . "- Framework/runtime creator: " . $identity["runtime_creator"] . "." . PHP_EOL
            . self::README_END_MARKER;
    }

    private function required(array $input, string $key): string
    {
        $value = trim((string) ($input[$key] ?? ""));

        if ($value === "") {
            throw new RuntimeException("Missing required project claim value: " . $key);
        }

        $this->rejectUnsafeText($value, $key);

        return $value;
    }

    private function optional(array $input, string $key, string $default): string
    {
        $value = trim((string) ($input[$key] ?? ""));
        $value = $value !== "" ? $value : $default;
        $this->rejectUnsafeText($value, $key);

        return $value;
    }

    private function identifier(string $value, string $productName): string
    {
        $value = trim($value) !== "" ? trim($value) : strtoupper(str_replace("-", "_", $this->slug("", $productName)));
        $value = strtoupper((string) preg_replace('/[^A-Z0-9_]+/', "_", $value));
        $value = trim($value, "_");

        if ($value === "") {
            throw new RuntimeException("Unable to derive a valid project identifier.");
        }

        return $value;
    }

    private function slug(string $value, string $productName): string
    {
        $value = strtolower(trim($value) !== "" ? trim($value) : $productName);
        $value = preg_replace('/[^a-z0-9]+/', "-", $value);

        return trim((string) $value, "-");
    }

    private function serializeEnvValue(string $value): string
    {
        $this->rejectUnsafeText($value, "environment value");

        if ($value === "" || preg_match('/^[A-Za-z0-9_.:\\/@-]+$/', $value) === 1) {
            return $value;
        }

        return '"' . str_replace('"', '\"', $value) . '"';
    }

    private function rejectUnsafeText(string $value, string $key): void
    {
        if (str_contains($value, "\r") || str_contains($value, "\n") || str_contains($value, "\0")) {
            throw new RuntimeException("Project claim value cannot contain line breaks or null bytes: " . $key);
        }
    }
}
