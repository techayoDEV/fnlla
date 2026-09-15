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
$repoTemplateRoot = $sourceRoot . DIRECTORY_SEPARATOR . "resources/project-templates/v1/core-repo";
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
    "description" => "Open PHP framework core for FNLLA applications.",
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
]);

copy_core_repo_templates($repoTemplateRoot, $targetRoot, $version);
copy_branding_assets($sourceRoot, $targetRoot);

write_file($targetRoot . DIRECTORY_SEPARATOR . ".gitignore", <<<'TXT'
/vendor/
/storage/
/.env
/.env.*
!.env.example
TXT);

if (!is_file($targetRoot . DIRECTORY_SEPARATOR . "scripts/lint.php")) {
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
}

if (!is_file($targetRoot . DIRECTORY_SEPARATOR . "scripts/static-analysis.php")) {
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
}

if (!is_file($targetRoot . DIRECTORY_SEPARATOR . "scripts/test.php")) {
    write_file($targetRoot . DIRECTORY_SEPARATOR . "scripts/test.php", <<<'PHP'
<?php

declare(strict_types=1);

require __DIR__ . "/../tests/CorePackageSmokeTest.php";
PHP);
}

if (!is_file($targetRoot . DIRECTORY_SEPARATOR . "tests/CorePackageSmokeTest.php")) {
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

use Fnlla\Php\Container\Container;
use Fnlla\Php\Database\QueryBuilder;
use Fnlla\Php\Http\Request;
use Fnlla\Php\Http\Response;
use Fnlla\Php\Routing\Router;
use Fnlla\Php\Support\FrameworkIdentity;
use Fnlla\Php\Validation\ValidationException;
use Fnlla\Php\Validation\Validator;

$GLOBALS["fnlla_config"] = [
    "app" => [
        "base_url" => "",
    ],
    "http" => [
        "security_headers" => [],
    ],
];

$classes = [
    Container::class,
    Fnlla\Php\Console\Application::class,
    Request::class,
    Response::class,
    Router::class,
    Validator::class,
    Fnlla\Php\View\View::class,
    FrameworkIdentity::class,
];

foreach ($classes as $class) {
    if (!class_exists($class)) {
        fwrite(STDERR, "Missing class: " . $class . PHP_EOL);
        exit(1);
    }
}

$container = new Container();
$GLOBALS["fnlla_container"] = $container;
$container->singleton(stdClass::class, static fn (): stdClass => (object) ["ok" => true]);
assert_true($container->make(stdClass::class) === $container->make(stdClass::class), "Container singleton contract failed.");

$validated = Validator::make(
    ["email" => "developer@example.test"],
    ["email" => "required|email"]
)->validate();
assert_same("developer@example.test", $validated["email"] ?? null, "Validator contract failed.");

expect_exception(ValidationException::class, static fn (): array => Validator::make(
    ["email" => "not-an-email"],
    ["email" => "required|email"]
)->validate(), "Validator should reject invalid email.");

$request = Request::capture(
    '{"name":"Core"}',
    [
        "REQUEST_METHOD" => "POST",
        "REQUEST_URI" => "/payload?debug=1",
        "CONTENT_TYPE" => "application/json",
        "CONTENT_LENGTH" => "15",
        "HTTP_X_REQUEST_ID" => "core-request-1",
    ]
);
assert_same("POST", $request->method(), "JSON request method mismatch.");
assert_same("/payload", $request->path(), "JSON request path mismatch.");
assert_same("Core", $request->json("name"), "JSON request body mismatch.");
assert_same("core-request-1", $request->requestId(), "Request ID mismatch.");

$router = new Router($container);
$router->get("/projects/{slug}", static fn (Request $request, string $slug): Response => Response::json([
    "slug" => $slug,
    "route_param" => $request->input("slug"),
]))->name("projects.show");

$routeResponse = $router->dispatch(Request::capture("", [
    "REQUEST_METHOD" => "GET",
    "REQUEST_URI" => "/projects/core",
]));
assert_true($routeResponse instanceof Response, "Router did not return a response.");
assert_same(200, $routeResponse->status(), "Route response status mismatch.");
$payload = json_decode($routeResponse->body(), true, 512, JSON_THROW_ON_ERROR);
assert_same("core", $payload["slug"] ?? null, "Route parameter mismatch.");
assert_same("core", $payload["route_param"] ?? null, "Route input mismatch.");

$optionsResponse = $router->dispatch(Request::capture("", [
    "REQUEST_METHOD" => "OPTIONS",
    "REQUEST_URI" => "/projects/core",
]));
assert_true($optionsResponse instanceof Response, "OPTIONS did not return a response.");
assert_same(204, $optionsResponse->status(), "OPTIONS response status mismatch.");
assert_true(str_contains((string) ($optionsResponse->headers()["Allow"] ?? ""), "GET"), "OPTIONS Allow header missing GET.");

expect_exception(RuntimeException::class, static fn (): Response => Response::text("bad")->withHeader("X-Test", "bad\r\nheader"), "Response should reject unsafe header values.");

if (in_array("sqlite", PDO::getAvailableDrivers(), true)) {
    $pdo = new PDO("sqlite::memory:");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec("CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT, deleted_at TEXT NULL)");
    $builder = new QueryBuilder($pdo, "users");
    assert_true($builder->insert(["email" => "core@example.test", "deleted_at" => null]), "Query builder insert failed.");
    $row = (new QueryBuilder($pdo, "users"))->where("email", "core@example.test")->whereNull("deleted_at")->first();
    assert_same("core@example.test", $row["email"] ?? null, "Query builder select failed.");
    expect_exception(RuntimeException::class, static fn (): QueryBuilder => new QueryBuilder($pdo, "users; DROP TABLE users"), "Query builder should reject unsafe table names.");
}

assert_same("techayoDEV/fnlla-core", FrameworkIdentity::REPOSITORY, "Core repository identity was not rewritten.");
assert_same("FNLLA Core", FrameworkIdentity::PRODUCT_NAME, "Core product identity was not rewritten.");

fwrite(STDOUT, "FNLLA Core package smoke test passed." . PHP_EOL);

function assert_true(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

function assert_same(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, $message . " Expected " . var_export($expected, true) . ", got " . var_export($actual, true) . "." . PHP_EOL);
        exit(1);
    }
}

function expect_exception(string $class, callable $callback, string $message): void
{
    try {
        $callback();
    } catch (Throwable $exception) {
        if ($exception instanceof $class) {
            return;
        }

        fwrite(STDERR, $message . " Unexpected exception: " . get_class($exception) . " " . $exception->getMessage() . PHP_EOL);
        exit(1);
    }

    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}
PHP);
}

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

function copy_core_repo_templates(string $templateRoot, string $targetRoot, string $version): void
{
    $realTemplateRoot = realpath($templateRoot);
    if ($realTemplateRoot === false || !is_dir($realTemplateRoot)) {
        fail("Core repository template directory missing: " . $templateRoot);
    }

    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($realTemplateRoot, RecursiveDirectoryIterator::SKIP_DOTS));
    foreach ($iterator as $fileInfo) {
        if (!$fileInfo->isFile() || $fileInfo->isLink()) {
            continue;
        }

        $sourcePath = $fileInfo->getPathname();
        $relativePath = str_replace(DIRECTORY_SEPARATOR, "/", substr($sourcePath, strlen($realTemplateRoot) + 1));
        if (!safe_relative_path($relativePath)) {
            fail("Unsafe Core repository template path: " . $relativePath);
        }

        $contents = str_replace("{{VERSION}}", $version, (string) file_get_contents($sourcePath));
        write_file($targetRoot . DIRECTORY_SEPARATOR . str_replace("/", DIRECTORY_SEPARATOR, $relativePath), $contents);
    }
}

function copy_branding_assets(string $sourceRoot, string $targetRoot): void
{
    foreach ([
        "branding/assets/logo/favicon.svg",
        "branding/assets/logo/lockup.svg",
        "branding/assets/logo/monogram.svg",
        "branding/assets/logo/wordmark.svg",
    ] as $relativePath) {
        copy_source_file($sourceRoot, $targetRoot, $relativePath);
    }

    $outlineRoot = $sourceRoot . DIRECTORY_SEPARATOR . "branding/assets/logo/outline";
    $outlineFiles = glob($outlineRoot . DIRECTORY_SEPARATOR . "*.svg");
    if ($outlineFiles === false || $outlineFiles === []) {
        fail("FNLLA outline logo assets are missing.");
    }

    foreach ($outlineFiles as $sourcePath) {
        copy_source_file($sourceRoot, $targetRoot, "branding/assets/logo/outline/" . basename($sourcePath));
    }
}

function copy_source_file(string $sourceRoot, string $targetRoot, string $relativePath): void
{
    if (!safe_relative_path($relativePath)) {
        fail("Unsafe source copy path: " . $relativePath);
    }

    $source = $sourceRoot . DIRECTORY_SEPARATOR . str_replace("/", DIRECTORY_SEPARATOR, $relativePath);
    $realSource = realpath($source);
    if ($realSource === false || !is_file($realSource) || is_link($source)) {
        fail("Source file missing or aliased: " . $relativePath);
    }

    write_file(
        $targetRoot . DIRECTORY_SEPARATOR . str_replace("/", DIRECTORY_SEPARATOR, $relativePath),
        (string) file_get_contents($realSource)
    );
}

function write_json(string $path, array $payload): void
{
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
