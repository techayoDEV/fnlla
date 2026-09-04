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
use Fnlla\Php\Support\ProcessRunner;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

final class MakeProjectCommand extends Command
{
    private const EXPORT_ROOT_ENTRIES = [
        ".editorconfig",
        ".env.example",
        ".env.full.example",
        ".gitattributes",
        ".gitignore",
        "LICENSE.md",
        "MANIFEST.json",
        "SUPPORT.md",
        "TRADEMARKS.md",
        "VERSION",
        "bootstrap",
        "composer.json",
        "config",
        "database",
        "fnlla",
        "fnlla.cmd",
        "lang",
        "public",
        "resources",
        "routes",
        "scripts",
        "src",
        "storage",
        "tests",
        "update-fnlla-runtime.cmd",
        "views",
    ];

    private const FAST_COPY_DIRECTORIES = [
        "public",
        "public/vendor/fnlla-runtime",
    ];

    private const SKIP_PREFIXES = [
        "docs/",
        "resources/project-templates/",
        "storage/database/",
        "storage/logs/",
        "storage/framework/cache/",
        "storage/framework/queue/",
        "storage/framework/sessions/",
    ];

    private const SKIP_EXACT_PATHS = [
        "docs",
        "database/factories/UserFactory.php",
        "database/migrations/20260627180000_create_users_table.php",
        "database/migrations/20260627200000_add_role_to_users_table.php",
        "resources/project-templates",
        "scripts/apply-techayo-metadata.ps1",
        "scripts/build-docs.php",
        "src/Console/Commands/MakeCommandCommand.php",
        "src/Console/Commands/MakeControllerCommand.php",
        "src/Console/Commands/MakeFactoryCommand.php",
        "src/Console/Commands/MakeMiddlewareCommand.php",
        "src/Console/Commands/MakeMigrationCommand.php",
        "src/Console/Commands/MakeProjectCommand.php",
        "src/Console/Commands/MakeSeederCommand.php",
        "src/Console/Commands/VersionSetCommand.php",
        "src/Controllers/AuthController.php",
        "storage/framework/fnlla-runtime-guard.json",
        "test-fnlla.cmd",
        "lint-fnlla.cmd",
        "tests/ApplicationTest.php",
        "tests/AuthTest.php",
        "tests/EnvironmentConfigTest.php",
        "tests/FnllaRuntimeGuardTest.php",
        "tests/FnllaRuntimeSyncCommandTest.php",
        "tests/FrameworkExtensionsTest.php",
        "tests/FrameworkUpdateCommandTest.php",
        "tests/MakeProjectCommandTest.php",
        "tests/PageMetaTest.php",
        "tests/ReleaseWorkflowTest.php",
        "tests/RequestTest.php",
        "tests/RouterTest.php",
        "tests/ValidationTest.php",
        "views/pages/admin.php",
        "views/pages/dashboard.php",
        "views/pages/login.php",
        "views/pages/platform.php",
    ];

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
        $targetArgument = trim((string) ($arguments[0] ?? ""));
        $appNameArgument = trim(implode(" ", array_slice($arguments, 1)));

        if ($targetArgument === "") {
            $this->error("Usage: make:project <target-path> [App Name]");

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
            $this->copyProjectTree($sourceRoot, $targetPath);
            $this->customizeExport($targetPath, $appName, $packageSlug);
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return 1;
        }

        $this->line("Exported FNLLA project base to: " . $targetPath);
        $this->line("Application name: " . $appName);
        $this->line("");
        $this->line("Next steps:");
        $this->line("1. Open the new project directory.");
        $this->line("2. Run php fnlla project:claim --product \"Your Product\" --owner \"Owner LTD\" --developer \"Developer LTD\".");
        $this->line("3. Copy .env.example to .env. Use .env.full.example only as the advanced environment reference.");
        $this->line("4. Leave ASSET_URL empty unless browser assets are served from a separate asset domain or CDN.");
        $this->line("5. Review routes/web.php, src/Controllers/PageController.php and views/pages/ and reshape the exported project surface into your real pages.");
        $this->line("6. Run php fnlla project:acceptance --json, php fnlla fnlla-runtime:validate, php scripts/test.php, php scripts/lint.php and php scripts/validate-version-manifest.php.");
        $this->line("7. Before production deployment, run php fnlla optimize:warm; before packaging a clean source release, run php fnlla optimize:clear.");
        $this->line("8. Initialize a separate Git repository for the new website or application.");

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
        $iterator = new FilesystemIterator($sourceRoot, FilesystemIterator::SKIP_DOTS);

        foreach ($iterator as $fileInfo) {
            $name = $fileInfo->getFilename();

            if (!$this->shouldExportRootEntry($name)) {
                continue;
            }

            $sourcePath = $fileInfo->getPathname();
            $targetPath = $targetRoot . DIRECTORY_SEPARATOR . $name;

            $this->copyPath($sourcePath, $targetPath);
        }
    }

    private function shouldExportRootEntry(string $name): bool
    {
        return in_array($name, self::EXPORT_ROOT_ENTRIES, true);
    }

    private function copyPath(string $sourcePath, string $targetPath): void
    {
        if (is_dir($sourcePath)) {
            $relativePath = $this->normalizeSeparators(substr($sourcePath, strlen(base_path()) + 1));

            if ($this->copyDirectoryFastIfSupported($sourcePath, $targetPath, $relativePath)) {
                return;
            }

            if (!is_dir($targetPath) && !mkdir($targetPath, 0777, true) && !is_dir($targetPath)) {
                throw new RuntimeException("Unable to create directory during export: " . $targetPath);
            }

            $iterator = new FilesystemIterator($sourcePath, FilesystemIterator::SKIP_DOTS);

            foreach ($iterator as $fileInfo) {
                $name = $fileInfo->getFilename();
                $childSource = $fileInfo->getPathname();
                $childTarget = $targetPath . DIRECTORY_SEPARATOR . $name;
                $relativeSource = $this->normalizeSeparators(substr($childSource, strlen(base_path()) + 1));

                if ($this->shouldSkipRelativeEntry($relativeSource)) {
                    continue;
                }

                $this->copyPath($childSource, $childTarget);
            }

            return;
        }

        if (!copy($sourcePath, $targetPath)) {
            throw new RuntimeException("Unable to copy file during export: " . $sourcePath);
        }
    }

    private function copyDirectoryFastIfSupported(string $sourcePath, string $targetPath, string $relativePath): bool
    {
        if (!$this->canFastCopyDirectory($relativePath)) {
            return false;
        }

        $robocopy = $this->findRobocopy();

        if ($robocopy === null) {
            return false;
        }

        if (!is_dir($targetPath) && !mkdir($targetPath, 0777, true) && !is_dir($targetPath)) {
            throw new RuntimeException("Unable to create directory during export: " . $targetPath);
        }

        $result = ProcessRunner::run([
            $robocopy,
            $sourcePath,
            $targetPath,
            "/E",
            "/NFL",
            "/NDL",
            "/NJH",
            "/NJS",
            "/NP",
        ]);

        if ($result["exit_code"] > 7) {
            throw new RuntimeException("Fast directory copy failed for {$relativePath}: " . $result["output"]);
        }

        return true;
    }

    private function canFastCopyDirectory(string $relativePath): bool
    {
        return in_array($relativePath, self::FAST_COPY_DIRECTORIES, true);
    }

    private function findRobocopy(): ?string
    {
        if (DIRECTORY_SEPARATOR !== "\\" || !function_exists("proc_open")) {
            return null;
        }

        return ProcessRunner::findExecutable("robocopy");
    }

    private function shouldSkipRelativeEntry(string $relativePath): bool
    {
        if (in_array($relativePath, self::SKIP_EXACT_PATHS, true)) {
            return true;
        }

        foreach (self::SKIP_PREFIXES as $prefix) {
            if (str_starts_with($relativePath, $prefix)) {
                return basename($relativePath) !== ".gitignore";
            }
        }

        return false;
    }

    private function customizeExport(string $targetRoot, string $appName, string $packageSlug): void
    {
        $this->sanitizeExportedStorage($targetRoot);
        $this->rewriteAppConfig($targetRoot, $appName);
        $this->rewriteEnvTemplates($targetRoot, $appName, $packageSlug);
        $this->rewriteComposerMetadata($targetRoot, $appName, $packageSlug);
        $this->rewriteProjectReadme($targetRoot, $appName);
        $this->rewriteApplicationSurface($targetRoot, $appName);
        $this->rewriteDatabaseSurface($targetRoot);
        $this->rewriteProjectTests($targetRoot);
        $this->rewriteConsoleLaunchers($targetRoot);
        $this->rewriteProjectLaunchers($targetRoot);
        FrameworkLock::write($targetRoot, base_path(), $appName, $packageSlug);
    }

    private function sanitizeExportedStorage(string $targetRoot): void
    {
        $keepFiles = [
            "storage/database/.gitignore" => "*\n!.gitignore\n",
            "storage/framework/cache/.gitignore" => "# Keep the cache directory in the repository while ignoring runtime cache files.\n*\n!.gitignore\n",
            "storage/framework/queue/.gitignore" => "# Keep the queue directory in the repository while ignoring runtime queue files.\n*\n!.gitignore\n",
            "storage/framework/sessions/.gitignore" => "# Keep the sessions directory in the repository while ignoring runtime session files.\n*\n!.gitignore\n",
            "storage/logs/.gitignore" => "*\n!.gitignore\n",
        ];

        foreach ($keepFiles as $relativePath => $contents) {
            $absolutePath = $targetRoot . DIRECTORY_SEPARATOR . str_replace("/", DIRECTORY_SEPARATOR, $relativePath);
            $directory = dirname($absolutePath);

            if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
                throw new RuntimeException("Unable to create storage directory during export: " . $directory);
            }

            file_put_contents($absolutePath, $contents);
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

        file_put_contents(
            $path,
            json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL
        );
    }

    private function rewriteProjectReadme(string $targetRoot, string $appName): void
    {
        $this->writeProjectTemplate($targetRoot, "README.md", [
            "{{APP_NAME}}" => $appName,
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
            "public/assets/fnlla-logo.png",
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
        ];

        foreach ($legacyLaunchers as $legacyLauncher) {
            if (is_file($legacyLauncher)) {
                unlink($legacyLauncher);
            }
        }

        $this->writeProjectTemplate($targetRoot, "test-project.cmd");
        $this->writeProjectTemplate($targetRoot, "lint-project.cmd");
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

        file_put_contents($targetPath, strtr($contents, $tokens) . PHP_EOL);
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

    private function normalizeSeparators(string $path): string
    {
        return str_replace("\\", "/", $path);
    }

    private function pathsEqual(string $left, string $right): bool
    {
        return strcasecmp(rtrim($left, "\\/"), rtrim($right, "\\/")) === 0;
    }

    private function isChildPath(string $childPath, string $parentPath): bool
    {
        $child = rtrim(strtolower($childPath), "\\/");
        $parent = rtrim(strtolower($parentPath), "\\/");

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
