<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA MAINTAINER SCRIPT
File: scripts/export-fnlla-core-repo.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

Purpose:
- Builds the standalone FNLLA Core repository workspace from the maintained Core
  file manifest.
*/

$sourceRoot = dirname(__DIR__);
$targetRoot = resolve_target($argv[1] ?? dirname($sourceRoot) . DIRECTORY_SEPARATOR . "fnlla-core");
$manifestPath = $sourceRoot . DIRECTORY_SEPARATOR . "resources/project-templates/v1/core-files.json";
$versionPath = $sourceRoot . DIRECTORY_SEPARATOR . "VERSION";

$manifest = json_decode((string) file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
if (($manifest["schema"] ?? null) !== "fnlla.core_package.v1" || !is_array($manifest["files"] ?? null)) {
    fail("Invalid Core package manifest.");
}

$version = trim((string) (file($versionPath, FILE_IGNORE_NEW_LINES)[0] ?? ""));
if (preg_match('/^\d+\.\d+\.\d+$/D', $version) !== 1) {
    fail("Invalid FNLLA version.");
}

if (file_exists($targetRoot)) {
    $entries = array_values(array_diff(scandir($targetRoot) ?: [], [".", ".."]));
    if ($entries !== []) {
        fail("Target directory must be empty: " . $targetRoot);
    }
} elseif (!mkdir($targetRoot, 0777, true) && !is_dir($targetRoot)) {
    fail("Cannot create target directory: " . $targetRoot);
}

foreach ($manifest["files"] as $relativePath) {
    if (!is_string($relativePath) || !safe_relative_path($relativePath)) {
        fail("Unsafe Core path in manifest.");
    }

    $source = $sourceRoot . DIRECTORY_SEPARATOR . str_replace("/", DIRECTORY_SEPARATOR, $relativePath);
    $realSource = realpath($source);
    if ($realSource === false || !is_file($realSource) || is_link($source)) {
        fail("Core source missing or aliased: " . $relativePath);
    }

    $contents = (string) file_get_contents($realSource);
    if ($relativePath === "src/Support/FrameworkIdentity.php") {
        $contents = str_replace(
            [
                'public const PRODUCT_NAME = "FNLLA";',
                'public const PRODUCT_SLUG = "fnlla";',
                'public const REPOSITORY = "techayoDEV/fnlla";',
                'public const REPOSITORY_URL = "https://github.com/techayoDEV/fnlla.git";',
                'public const REPOSITORY_WEB_URL = "https://github.com/techayoDEV/fnlla";',
            ],
            [
                'public const PRODUCT_NAME = "FNLLA Core";',
                'public const PRODUCT_SLUG = "fnlla-core";',
                'public const REPOSITORY = "techayoDEV/fnlla-core";',
                'public const REPOSITORY_URL = "https://github.com/techayoDEV/fnlla-core.git";',
                'public const REPOSITORY_WEB_URL = "https://github.com/techayoDEV/fnlla-core";',
            ],
            $contents
        );
    }

    write_file($targetRoot . DIRECTORY_SEPARATOR . str_replace("/", DIRECTORY_SEPARATOR, $relativePath), $contents);
}

write_file($targetRoot . DIRECTORY_SEPARATOR . "VERSION", $version . PHP_EOL);
write_json($targetRoot . DIRECTORY_SEPARATOR . "composer.json", [
    "name" => "techayodev/fnlla-core",
    "description" => "FNLLA Core PHP framework package without the integrated Developer Panel or FNLLA UI surface.",
    "type" => "library",
    "license" => "MIT",
    "homepage" => "https://fnlla.com",
    "support" => [
        "issues" => "https://github.com/techayoDEV/fnlla-core/issues",
        "source" => "https://github.com/techayoDEV/fnlla-core",
    ],
    "require" => [
        "php" => "^8.3",
    ],
    "autoload" => [
        "psr-4" => [
            "Fnlla\\Php\\" => "src/",
        ],
        "files" => [
            "src/Support/helpers.php",
        ],
    ],
    "scripts" => [
        "test" => "@php scripts/test.php",
        "lint" => "@php scripts/lint.php",
        "analyse" => "@php scripts/static-analysis.php",
    ],
], $version);

write_file($targetRoot . DIRECTORY_SEPARATOR . "README.md", str_replace("{{VERSION}}", $version, <<<'MD'
# FNLLA Core

FNLLA Core is the standalone PHP framework core used by FNLLA. It contains the
runtime primitives, routing, HTTP layer, container, validation, database,
session, cache, mail, queue and core CLI building blocks without the integrated
Developer Panel, Client Portal, FNLLA UI surface or commercial operations layer.

Package: `techayodev/fnlla-core`
Repository: `techayoDEV/fnlla-core`
Version: `{{VERSION}}`

## Validate

```powershell
php scripts/test.php
php scripts/lint.php
php scripts/static-analysis.php
```

This repository is generated from the maintained FNLLA source manifest. Do not
add FNLLA product-panel files here; those belong in `techayoDEV/fnlla`.
MD));

write_file($targetRoot . DIRECTORY_SEPARATOR . ".gitignore", <<<'TXT'
/vendor/
/storage/
/.env
/.env.*
!.env.example
TXT);

write_file($targetRoot . DIRECTORY_SEPARATOR . "scripts/lint.php", <<<'PHP'
<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$errors = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS));

foreach ($iterator as $fileInfo) {
    if (!$fileInfo->isFile() || $fileInfo->getExtension() !== "php") {
        continue;
    }

    $path = $fileInfo->getPathname();
    if (str_contains($path, DIRECTORY_SEPARATOR . "vendor" . DIRECTORY_SEPARATOR)) {
        continue;
    }

    passthru(escapeshellarg(PHP_BINARY) . " -l " . escapeshellarg($path), $exitCode);
    if ($exitCode !== 0) {
        $errors[] = $path;
    }
}

if ($errors !== []) {
    fwrite(STDERR, "Lint failed for " . count($errors) . " file(s)." . PHP_EOL);
    exit(1);
}

fwrite(STDOUT, "Lint passed." . PHP_EOL);
PHP);

write_file($targetRoot . DIRECTORY_SEPARATOR . "scripts/static-analysis.php", <<<'PHP'
<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$errors = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . "/src", RecursiveDirectoryIterator::SKIP_DOTS));

foreach ($iterator as $fileInfo) {
    if (!$fileInfo->isFile() || $fileInfo->getExtension() !== "php") {
        continue;
    }

    $contents = (string) file_get_contents($fileInfo->getPathname());
    if (!str_contains($contents, "declare(strict_types=1);")) {
        $errors[] = $fileInfo->getPathname() . " is missing strict_types.";
    }
    if (preg_match('/\b(var_dump|print_r|dd)\s*\(/', $contents) === 1) {
        $errors[] = $fileInfo->getPathname() . " contains debug output helpers.";
    }
}

if ($errors !== []) {
    fwrite(STDERR, implode(PHP_EOL, $errors) . PHP_EOL);
    exit(1);
}

fwrite(STDOUT, "Static analysis baseline passed." . PHP_EOL);
PHP);

write_file($targetRoot . DIRECTORY_SEPARATOR . "scripts/test.php", <<<'PHP'
<?php

declare(strict_types=1);

require __DIR__ . "/../tests/CorePackageSmokeTest.php";
PHP);

write_file($targetRoot . DIRECTORY_SEPARATOR . "tests/CorePackageSmokeTest.php", <<<'PHP'
<?php

declare(strict_types=1);

$root = dirname(__DIR__);

spl_autoload_register(static function (string $class) use ($root): void {
    $prefix = "Fnlla\\Php\\";
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $path = $root . "/src/" . str_replace("\\", "/", substr($class, strlen($prefix))) . ".php";
    if (is_file($path)) {
        require $path;
    }
});

require_once $root . "/src/Support/helpers.php";

$classes = [
    Fnlla\Php\Container\Container::class,
    Fnlla\Php\Console\Application::class,
    Fnlla\Php\Http\Request::class,
    Fnlla\Php\Http\Response::class,
    Fnlla\Php\Routing\Router::class,
    Fnlla\Php\Validation\Validator::class,
    Fnlla\Php\View\View::class,
    Fnlla\Php\Support\FrameworkIdentity::class,
];

foreach ($classes as $class) {
    if (!class_exists($class)) {
        fwrite(STDERR, "Missing class: " . $class . PHP_EOL);
        exit(1);
    }
}

$container = new Fnlla\Php\Container\Container();
$container->singleton(stdClass::class, static fn (): stdClass => (object) ["ok" => true]);
if ($container->make(stdClass::class) !== $container->make(stdClass::class)) {
    fwrite(STDERR, "Container singleton contract failed." . PHP_EOL);
    exit(1);
}

$validated = Fnlla\Php\Validation\Validator::make(
    ["email" => "developer@example.test"],
    ["email" => "required|email"]
)->validate();

if (($validated["email"] ?? null) !== "developer@example.test") {
    fwrite(STDERR, "Validator contract failed." . PHP_EOL);
    exit(1);
}

if (Fnlla\Php\Support\FrameworkIdentity::REPOSITORY !== "techayoDEV/fnlla-core") {
    fwrite(STDERR, "Core repository identity was not rewritten." . PHP_EOL);
    exit(1);
}

fwrite(STDOUT, "FNLLA Core package smoke test passed." . PHP_EOL);
PHP);

fwrite(STDOUT, "FNLLA Core repository exported to: " . $targetRoot . PHP_EOL);

function resolve_target(string $path): string
{
    if (preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1 || str_starts_with($path, "\\\\") || str_starts_with($path, "/")) {
        return rtrim(str_replace(["/", "\\"], DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);
    }

    return rtrim(getcwd() . DIRECTORY_SEPARATOR . str_replace(["/", "\\"], DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);
}

function safe_relative_path(string $path): bool
{
    return preg_match('~^(?:[A-Za-z0-9_.-]+/)*[A-Za-z0-9_.-]+$~D', $path) === 1
        && !in_array("..", explode("/", $path), true)
        && !str_starts_with($path, ".git/")
        && !str_starts_with($path, "storage/");
}

function write_json(string $path, array $payload, string $version): void
{
    $payload["version"] = $version;
    write_file($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL);
}

function write_file(string $path, string $contents): void
{
    $directory = dirname($path);
    if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
        fail("Cannot create directory: " . $directory);
    }

    if (file_put_contents($path, $contents) !== strlen($contents)) {
        fail("Cannot write file: " . $path);
    }
}

function fail(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}
