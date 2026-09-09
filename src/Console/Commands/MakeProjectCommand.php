<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA CONSOLE SOURCE
File: src\Console\Commands\MakeProjectCommand.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Implements the maintained CLI surface and scheduler-oriented console behaviour.
*/

namespace Fnlla\Php\Console\Commands;

use Fnlla\Php\Console\Command;
use Fnlla\Php\Support\FrameworkIdentity;
use Fnlla\Php\Support\FrameworkLock;
use RuntimeException;

final class MakeProjectCommand extends Command
{
    private string $profile = "full";
    public function name(): string
    {
        return "make:project";
    }

    public function description(): string
    {
        return "Export a clean FNLLA project base into a new project directory.";
    }

    public function handle(array $arguments): int
    {
        if (in_array("--help", $arguments, true) || in_array("-h", $arguments, true)) {
            $this->line("Usage: make:project <target-path> [App Name] [--profile=full|plain] [--packages] [--interactive|--no-interaction]");
            $this->line("Creates the integrated FNLLA starter by default. Use --profile=plain for core only; --interactive opens advanced installation options.");
            return 0;
        }
        try {
            $packages = in_array("--packages", $arguments, true);
            $arguments = array_values(array_filter($arguments, static fn (string $argument): bool => $argument !== "--packages"));
            $selection = \Fnlla\Php\Support\StarterProfileSelection::resolve($arguments);
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());
            return 1;
        }
        $this->profile = $selection["profile"];
        $arguments = $selection["arguments"];
        $targetArgument = trim((string) ($arguments[0] ?? ""));
        $appNameArgument = trim(implode(" ", array_slice($arguments, 1)));

        if ($targetArgument === "") {
            $this->error("Usage: make:project <target-path> [App Name] [--profile=full|plain]");

            return 1;
        }

        $sourceRoot = $this->normalizePath(base_path());
        $targetPath = $this->resolveTargetPath($targetArgument);

        if ($this->pathsEqual($sourceRoot, $targetPath)) {
            $this->error("Target path cannot be the FNLLA source repository itself.");

            return 1;
        }

        if ($this->isChildPath($targetPath, $sourceRoot)) {
            $this->error("Target path must be outside the FNLLA source repository to avoid recursive copies.");

            return 1;
        }

        $appName = $appNameArgument !== "" ? $appNameArgument : $this->guessAppName($targetPath);
        $packageSlug = $this->slugify($appName);

        if ($packageSlug === "") {
            $this->error("Unable to derive a valid project slug from the provided name or path.");

            return 1;
        }

        try {
            $this->prepareTargetDirectory($targetPath);
            if ($this->profile === "plain") {
                (new \Fnlla\Php\Support\PlainProjectExporter())->export($targetPath, $appName, $packageSlug);
                $this->line("Exported plain FNLLA core project to: " . $targetPath);
                $this->line("Copy .env.example to .env, then run composer install, php scripts/test.php and php fnlla route:list.");
                return 0;
            }
            $this->copyProjectTree($sourceRoot, $targetPath);
            $this->customizeExport($targetPath, $appName, $packageSlug);
            if ($packages) {
                (new \Fnlla\Php\Support\CompletePackageExporter())->convert($targetPath);
                $this->line("Composer package preview enabled. Read docs/framework/PACKAGES.md before upgrading.");
            }
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return 1;
        }

        $this->line("Exported FNLLA project base to: " . $targetPath);
        $this->line("Application name: " . $appName);
        $this->line("Project profile: " . $this->profile);
        $this->line("");
        $this->line("Next steps:");
        $this->line("1. Open the new project directory.");
        $this->line("2. Run php fnlla project:claim --product \"Your Product\" --owner \"Owner\" --developer \"Developer\".");
        $this->line("3. Copy .env.example to .env. Use .env.full.example only as the advanced environment reference.");
        $this->line("4. Leave ASSET_URL empty unless browser assets are served from a separate asset domain or CDN.");
        $this->line("5. Review routes/web.php, src/Controllers/PageController.php and views/pages/ and reshape the exported project surface into your real pages.");
        $this->line("6. Run php fnlla project:acceptance --json, php fnlla fnlla-runtime:validate, php scripts/test.php, php scripts/lint.php and php scripts/validate-version-manifest.php.");
        $this->line("7. Windows shortcuts live under scripts/windows; fnlla.cmd stays in root as the CLI launcher.");
        $this->line("8. Before production deployment, run php fnlla optimize:warm; before packaging a clean source release, run php fnlla optimize:clear.");
        $this->line("9. Initialize a separate Git repository for the new website or application.");

        return 0;
    }

    private function prepareTargetDirectory(string $targetPath): void
    {
        if (is_dir($targetPath)) {
            $entries = scandir($targetPath);

            if ($entries === false) {
                throw new RuntimeException("Unable to inspect target directory: " . $targetPath);
            }

            $visibleEntries = array_values(array_diff($entries, [".", ".."]));

            if ($visibleEntries !== []) {
                throw new RuntimeException("Target directory must be empty: " . $targetPath);
            }

            return;
        }

        if (file_exists($targetPath) && !is_dir($targetPath)) {
            throw new RuntimeException("Target path already exists and is not a directory: " . $targetPath);
        }

        if (!mkdir($targetPath, 0777, true) && !is_dir($targetPath)) {
            throw new RuntimeException("Unable to create target directory: " . $targetPath);
        }
    }

    private function copyProjectTree(string $sourceRoot, string $targetRoot): void
    {
        $manifest = json_decode((string) file_get_contents(base_path("resources/project-templates/v1/export-files.json")), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($manifest) || ($manifest["schema"] ?? "") !== "fnlla.project_export.v1" || !is_array($manifest["files"] ?? null)) {
            throw new RuntimeException("Invalid project export manifest.");
        }

        // Validate every source before copying. Directory traversal never defines the export.
        $sources = [];
        foreach ($manifest["files"] as $relativePath) {
            if (!is_string($relativePath) || preg_match('~^(?:[A-Za-z0-9_.-]+/)*[A-Za-z0-9_.-]+$~D', $relativePath) !== 1
                || in_array("..", explode("/", $relativePath), true) || str_starts_with($relativePath, "storage/")
                || str_starts_with($relativePath, "public/uploads/") || str_starts_with($relativePath, ".git/")
                || (str_starts_with($relativePath, ".env.") && !in_array($relativePath, [".env.example", ".env.full.example"], true))
                || $relativePath === ".env") {
                throw new RuntimeException("Unsafe project export path.");
            }

            $sourcePath = $sourceRoot . DIRECTORY_SEPARATOR . str_replace("/", DIRECTORY_SEPARATOR, $relativePath);
            $resolved = realpath($sourcePath);
            if ($resolved === false || !is_file($resolved) || !$this->isChildPath($this->normalizePath($resolved), $sourceRoot)
                || !$this->pathsEqual($this->normalizePath($resolved), $this->normalizePath($sourcePath))) {
                throw new RuntimeException("Project export source is missing or outside the repository: " . $relativePath);
            }
            $sources[$relativePath] = $resolved;
        }

        foreach ($sources as $relativePath => $sourcePath) {
            $targetPath = $targetRoot . DIRECTORY_SEPARATOR . str_replace("/", DIRECTORY_SEPARATOR, $relativePath);
            $directory = dirname($targetPath);
            if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
                throw new RuntimeException("Unable to create project export directory: " . $directory);
            }
            if (!copy($sourcePath, $targetPath)) {
                throw new RuntimeException("Unable to export: " . $relativePath);
            }
        }
    }

    private function customizeExport(string $targetRoot, string $appName, string $packageSlug): void
    {
        $this->sanitizeExportedStorage($targetRoot);
        $this->writeProjectTemplate($targetRoot, ".fnlla/ui-distribution");
        $this->rewriteAppConfig($targetRoot, $appName);
        $this->writeProjectTemplate($targetRoot, ".env.example");
        $this->rewriteEnvTemplates($targetRoot, $appName, $packageSlug);
        $this->rewriteComposerMetadata($targetRoot, $appName, $packageSlug);
        $this->rewriteProjectReadme($targetRoot, $appName);
        $this->rewriteApplicationSurface($targetRoot, $appName);
        $this->rewriteDatabaseSurface($targetRoot);
        $this->rewriteProjectTests($targetRoot);
        $this->rewriteConsoleLaunchers($targetRoot);
        $this->rewriteProjectLaunchers($targetRoot);
        file_put_contents($targetRoot . "/.fnlla/project-profile", $this->profile . "\n");
        $this->organizeProjectFiles($targetRoot);
        FrameworkLock::write($targetRoot, base_path(), $appName, $packageSlug);
    }

    private function organizeProjectFiles(string $targetRoot): void
    {
        $directory = $targetRoot . "/docs/framework";
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new RuntimeException("Cannot create framework documentation directory.");
        }
        foreach (["SUPPORT.md", "TRADEMARKS.md"] as $file) {
            if (is_file($targetRoot . "/" . $file) && !rename($targetRoot . "/" . $file, $directory . "/" . $file)) {
                throw new RuntimeException("Cannot relocate framework reference: " . $file);
            }
        }
        $manifestPath = $targetRoot . "/MANIFEST.json";
        $manifest = json_decode((string) file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
        $manifest["release"]["state_files"] = array_map(
            static fn (string $path): string => in_array($path, ["SUPPORT.md", "TRADEMARKS.md"], true) ? "docs/framework/" . $path : $path,
            $manifest["release"]["state_files"]
        );
        file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
        $migration = "20260829120000_create_developer_panel_storage_tables.php";
        if (is_file($targetRoot . "/database/migrations/" . $migration)) {
            $optional = $targetRoot . "/database/optional/developer-panel";
            if (!is_dir($optional)) {
                mkdir($optional, 0755, true);
            }
            if (!rename($targetRoot . "/database/migrations/" . $migration, $optional . "/" . $migration)) {
                throw new RuntimeException("Cannot isolate optional Developer Panel migration.");
            }
        }
    }

    private function sanitizeExportedStorage(string $targetRoot): void
    {
        $keepFiles = [
            "storage/.gitignore" => "# Runtime data is private, including files created by future modules.\n*\n!*/\n!.gitignore\n",
            "storage/app/.gitignore" => "*\n!.gitignore\n",
            "storage/uploads/.gitignore" => "*\n!.gitignore\n",
            "public/uploads/.gitignore" => "*\n!.gitignore\n",
            "storage/database/.gitignore" => "*\n!.gitignore\n",
            "storage/framework/cache/.gitignore" => "# Keep the cache directory in the repository while ignoring runtime cache files.\n*\n!.gitignore\n",
            "storage/framework/queue/.gitignore" => "# Keep the queue directory in the repository while ignoring runtime queue files.\n*\n!.gitignore\n",
            "storage/framework/sessions/.gitignore" => "# Keep the sessions directory in the repository while ignoring runtime session files.\n*\n!.gitignore\n",
            "storage/logs/.gitignore" => "*\n!.gitignore\n",
        ];

        foreach ($keepFiles as $relativePath => $contents) {
            $absolutePath = $targetRoot . DIRECTORY_SEPARATOR . str_replace("/", DIRECTORY_SEPARATOR, $relativePath);
            $directory = dirname($absolutePath);

            if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
                throw new RuntimeException("Unable to create storage directory during export: " . $directory);
            }

            if (file_put_contents($absolutePath, $contents) === false) {
                throw new RuntimeException("Unable to protect exported storage: " . $relativePath);
            }
        }
    }

    private function rewriteAppConfig(string $targetRoot, string $appName): void
    {
        $path = $targetRoot . DIRECTORY_SEPARATOR . "config" . DIRECTORY_SEPARATOR . "app.php";
        $contents = (string) file_get_contents($path);
        $replacement = "'name' => (string) env(\"APP_NAME\", " . var_export($appName, true) . "),";
        $updated = preg_replace('/^\s*["\']name["\']\s*=>\s*.*,\s*$/m', "    " . $replacement, $contents, 1, $count);

        if (!is_string($updated) || $count !== 1) {
            throw new RuntimeException("Unable to update config/app.php for exported project.");
        }

        file_put_contents($path, $updated);
    }

    private function rewriteEnvTemplates(string $targetRoot, string $appName, string $packageSlug): void
    {
        $projectIdentifier = strtoupper((string) preg_replace('/[^A-Z0-9]+/', "_", strtoupper($packageSlug)));
        $projectIdentifier = trim($projectIdentifier, "_");
        $projectIdentifier = $projectIdentifier !== "" ? $projectIdentifier : "FNLLA_PROJECT";

        foreach ([".env.example", ".env.full.example"] as $filename) {
            $path = $targetRoot . DIRECTORY_SEPARATOR . $filename;

            if (!is_file($path)) {
                continue;
            }

            $contents = (string) file_get_contents($path);
            $values = [
                "APP_NAME" => $appName,
                "APP_URL" => "",
                "PROJECT_ID" => $projectIdentifier,
                "PROJECT_NAME" => $appName,
                "PROJECT_RUNTIME" => FrameworkIdentity::PRODUCT_NAME,
                "PROJECT_RUNTIME_CREATOR" => FrameworkIdentity::DEFAULT_RUNTIME_CREATOR,
                "MAIL_FROM_ADDRESS" => "no-reply@example.com",
                "MAIL_FROM_NAME" => $appName,
                "CONTACT_NOTIFICATION_EMAIL" => "team@example.com",
                "DEVELOPER_ACCESS_ENABLED" => "true",
            ];

            foreach ($values as $key => $value) {
                $line = $key . "=" . $this->serializeEnvValue((string) $value);
                $pattern = '/^' . preg_quote($key, '/') . '=.*$/m';

                if (preg_match($pattern, $contents) === 1) {
                    $contents = (string) preg_replace($pattern, $line, $contents, 1);
                }
            }

            file_put_contents($path, rtrim($contents) . PHP_EOL);
        }
    }

    private function rewriteComposerMetadata(string $targetRoot, string $appName, string $packageSlug): void
    {
        $path = $targetRoot . DIRECTORY_SEPARATOR . "composer.json";
        $decoded = json_decode((string) file_get_contents($path), true);

        if (!is_array($decoded)) {
            throw new RuntimeException("Unable to decode composer.json for exported project.");
        }

        $decoded["name"] = "project/" . $packageSlug;
        $decoded["description"] = $appName . " built on FNLLA and its integrated UI surface.";
        unset($decoded["autoload-dev"], $decoded["require-dev"]);
        $decoded["autoload"]["psr-4"]["App\\"] = "app/";
        $decoded["suggest"]["phpstan/phpstan"] = "Optional deeper static analysis. The exported starter runs a dependency-light baseline without it.";
        $decoded["suggest"]["phpunit/phpunit"] = "Optional full PHPUnit runner. The exported starter ships a dependency-light local smoke-test harness.";
        $decoded["scripts"] = [
            "console" => "@php fnlla",
            "test" => "@php scripts/test.php",
            "analyse" => "@php scripts/static-analysis.php",
            "doctor" => "@php fnlla doctor",
            "app:map" => "@php fnlla app:map",
            "security" => "@php fnlla security:audit",
            "lint" => [
                "@php scripts/lint.php",
                "@php scripts/validate-fnlla-runtime.php",
                "@php scripts/validate-version-manifest.php",
            ],
        ];

        file_put_contents(
            $path,
            json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL
        );
    }

    private function rewriteProjectReadme(string $targetRoot, string $appName): void
    {
        $this->writeProjectTemplate($targetRoot, "README.md", [
            "{{APP_NAME}}" => $appName,
            "{{FNLLA_VERSION}}" => trim((string) strtok((string) file_get_contents(base_path("VERSION")), "\r\n")),
        ]);
    }

    private function rewriteApplicationSurface(string $targetRoot, string $appName): void
    {
        $this->synchronizeProjectSurfaceFiles($targetRoot, [
            "routes/web.php",
            "src/Controllers/HomeController.php",
            "src/Controllers/PageController.php",
            "views/layouts/app.php",
            "views/pages/home.php",
            "views/pages/about.php",
            "views/pages/services.php",
            "views/pages/contact.php",
            "views/pages/legal.php",
            "views/partials/page-hero.php",
            "public/assets/app.css",
        ]);

        $legacyProjectLaunchView = $targetRoot . DIRECTORY_SEPARATOR . "views" . DIRECTORY_SEPARATOR . "pages" . DIRECTORY_SEPARATOR . "project-launch.php";

        if (is_file($legacyProjectLaunchView)) {
            unlink($legacyProjectLaunchView);
        }
    }

    private function synchronizeProjectSurfaceFiles(string $targetRoot, array $relativePaths): void
    {
        foreach ($relativePaths as $relativePath) {
            if (!is_string($relativePath) || $relativePath === "") {
                continue;
            }

            $sourcePath = base_path($relativePath);

            if (!is_file($sourcePath)) {
                throw new RuntimeException("Project surface source file is missing: " . $sourcePath);
            }

            $targetPath = $targetRoot . DIRECTORY_SEPARATOR . str_replace("/", DIRECTORY_SEPARATOR, $relativePath);
            $targetDirectory = dirname($targetPath);

            if (!is_dir($targetDirectory) && !mkdir($targetDirectory, 0777, true) && !is_dir($targetDirectory)) {
                throw new RuntimeException("Unable to prepare export directory: " . $targetDirectory);
            }

            $contents = file_get_contents($sourcePath);

            if ($contents === false) {
                throw new RuntimeException("Unable to read project surface source file: " . $sourcePath);
            }

            file_put_contents($targetPath, $contents);
        }
    }

    private function rewriteDatabaseSurface(string $targetRoot): void
    {
        $this->writeProjectTemplate($targetRoot, "database/seeders/DatabaseSeeder.php");
    }

    private function rewriteProjectTests(string $targetRoot): void
    {
        $this->writeProjectTemplate($targetRoot, "tests/BootstrapAutoloadTest.php");
        $this->writeProjectTemplate($targetRoot, "tests/ProjectTest.php");
        $this->writeProjectTemplate($targetRoot, "phpunit.xml");
        $this->writeProjectTemplate($targetRoot, "phpstan.neon");
    }

    private function rewriteConsoleLaunchers(string $targetRoot): void
    {
        $this->writeProjectTemplate($targetRoot, "fnlla");
        $this->writeProjectTemplate($targetRoot, "fnlla.cmd");
    }

    private function rewriteProjectLaunchers(string $targetRoot): void
    {
        $legacyLaunchers = [
            $targetRoot . DIRECTORY_SEPARATOR . "test-fnlla.cmd",
            $targetRoot . DIRECTORY_SEPARATOR . "lint-fnlla.cmd",
            $targetRoot . DIRECTORY_SEPARATOR . "test-project.cmd",
            $targetRoot . DIRECTORY_SEPARATOR . "lint-project.cmd",
            $targetRoot . DIRECTORY_SEPARATOR . "update-fnlla-runtime.cmd",
        ];

        foreach ($legacyLaunchers as $legacyLauncher) {
            if (is_file($legacyLauncher)) {
                unlink($legacyLauncher);
            }
        }

        $this->writeProjectTemplate($targetRoot, "scripts/windows/test-project.cmd");
        $this->writeProjectTemplate($targetRoot, "scripts/windows/lint-project.cmd");
    }

    private function writeProjectTemplate(string $targetRoot, string $relativePath, array $tokens = []): void
    {
        $templatePath = base_path("resources/project-templates/v1/" . $relativePath);

        if (!is_file($templatePath)) {
            throw new RuntimeException("Project export template is missing: " . $relativePath);
        }

        $targetPath = $targetRoot . DIRECTORY_SEPARATOR . str_replace("/", DIRECTORY_SEPARATOR, $relativePath);
        $targetDirectory = dirname($targetPath);

        if (!is_dir($targetDirectory) && !mkdir($targetDirectory, 0777, true) && !is_dir($targetDirectory)) {
            throw new RuntimeException("Unable to create project template target directory: " . $targetDirectory);
        }

        $contents = file_get_contents($templatePath);

        if (!is_string($contents)) {
            throw new RuntimeException("Unable to read project export template: " . $relativePath);
        }

        // Templates own their line endings; host-specific suffixes make exports differ.
        if (file_put_contents($targetPath, strtr($contents, $tokens)) === false) {
            throw new RuntimeException("Unable to write project export template: " . $relativePath);
        }
    }

    private function resolveTargetPath(string $targetArgument): string
    {
        if (preg_match('/^[A-Za-z]:[\\\\\\/]/', $targetArgument) === 1 || str_starts_with($targetArgument, "\\\\") || str_starts_with($targetArgument, "/")) {
            return $this->normalizePath($targetArgument);
        }

        return $this->normalizePath((string) getcwd() . DIRECTORY_SEPARATOR . $targetArgument);
    }

    private function normalizePath(string $path): string
    {
        $path = str_replace(["/", "\\"], DIRECTORY_SEPARATOR, $path);
        $segments = [];
        $prefix = "";

        if (preg_match('/^[A-Za-z]:/', $path) === 1) {
            $prefix = strtoupper(substr($path, 0, 2));
            $path = substr($path, 2);
        } elseif (str_starts_with($path, DIRECTORY_SEPARATOR . DIRECTORY_SEPARATOR)) {
            $prefix = DIRECTORY_SEPARATOR . DIRECTORY_SEPARATOR;
            $path = substr($path, 2);
        }

        $isAbsolute = str_starts_with($path, DIRECTORY_SEPARATOR);
        $parts = preg_split('/[\\\\\\/]+/', $path) ?: [];

        foreach ($parts as $part) {
            if ($part === "" || $part === ".") {
                continue;
            }

            if ($part === "..") {
                if ($segments !== [] && end($segments) !== "..") {
                    array_pop($segments);
                } elseif (!$isAbsolute) {
                    $segments[] = $part;
                }

                continue;
            }

            $segments[] = $part;
        }

        $normalized = implode(DIRECTORY_SEPARATOR, $segments);

        if ($prefix !== "") {
            return $prefix . DIRECTORY_SEPARATOR . $normalized;
        }

        return ($isAbsolute ? DIRECTORY_SEPARATOR : "") . $normalized;
    }

    private function pathsEqual(string $left, string $right): bool
    {
        $left = rtrim($left, "\\/");
        $right = rtrim($right, "\\/");
        return DIRECTORY_SEPARATOR === "\\" ? strcasecmp($left, $right) === 0 : $left === $right;
    }

    private function isChildPath(string $childPath, string $parentPath): bool
    {
        $child = rtrim($childPath, "\\/");
        $parent = rtrim($parentPath, "\\/");
        if (DIRECTORY_SEPARATOR === "\\") {
            $child = strtolower($child);
            $parent = strtolower($parent);
        }

        return str_starts_with($child, $parent . DIRECTORY_SEPARATOR);
    }

    private function guessAppName(string $targetPath): string
    {
        $basename = basename(rtrim($targetPath, "\\/"));
        $basename = preg_replace('/[-_]+/', " ", $basename);

        return ucwords(trim((string) $basename));
    }

    private function slugify(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', "-", $value);

        return trim((string) $value, "-");
    }

    private function serializeEnvValue(string $value): string
    {
        if ($value === "" || preg_match('/^[A-Za-z0-9_.:\\/@-]+$/', $value) === 1) {
            return $value;
        }

        return '"' . str_replace('"', '\"', $value) . '"';
    }
}
