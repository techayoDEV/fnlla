<?php

declare(strict_types=1);

namespace Fnlla\Php\Support;

use RuntimeException;

/** Plain is a positive file inventory, never a full distribution with exclusions. */
final class PlainProjectExporter
{
    public function export(string $target, string $name, string $slug): void
    {
        $manifest = json_decode((string) file_get_contents(base_path("resources/project-templates/v1/core-files.json")), true, 512, JSON_THROW_ON_ERROR);
        if (($manifest["schema"] ?? null) !== "fnlla.core_package.v1") {
            throw new RuntimeException("Invalid core package manifest.");
        }
        foreach ($manifest["files"] as $path) {
            $this->copy(base_path(), $path, $target . "/packages/fnlla-core/" . $path);
        }
        $version = trim((string) (file(base_path("VERSION"), FILE_IGNORE_NEW_LINES)[0] ?? ""));
        if (preg_match('/^\d+\.\d+\.\d+$/D', $version) !== 1) {
            throw new RuntimeException("Invalid core package version.");
        }
        $this->write($target . "/packages/fnlla-core/composer.json", json_encode([
            "name" => "techayodev/fnlla-core", "description" => "FNLLA HTTP and application core without the Developer Panel or UI distribution.",
            "type" => "library", "license" => "MIT", "version" => $version,
            "require" => ["php" => "^8.3"], "autoload" => ["psr-4" => ["Fnlla\\Php\\" => "src/"]],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
        $this->write($target . "/composer.json", json_encode([
            "name" => "project/" . $slug, "description" => $name . " built on FNLLA core", "type" => "project",
            "repositories" => [["type" => "path", "url" => "packages/fnlla-core", "options" => ["symlink" => false]]],
            "require" => ["php" => "^8.3", "techayodev/fnlla-core" => $version],
            "require-dev" => ["phpunit/phpunit" => "^12.5", "phpstan/phpstan" => "^2.1"],
            "autoload" => ["psr-4" => ["App\\" => "app/", "Database\\Seeders\\" => "database/seeders/", "Database\\Factories\\" => "database/factories/"]],
            "scripts" => ["test" => "@php scripts/test.php", "test:unit" => "@php vendor/phpunit/phpunit/phpunit",
                "analyse" => "@php scripts/static-analysis.php", "lint" => "@php scripts/lint.php"],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");

        foreach (["auth", "cache", "cors", "database", "filesystems", "http", "logging", "mail", "queue", "rate_limit", "security", "session"] as $config) {
            $this->copy(base_path(), "config/" . $config . ".php", $target . "/config/" . $config . ".php");
        }
        foreach (["public/index.php", "public/router.php", "public/.htaccess", ".editorconfig", ".gitignore", "LICENSE.md",
            "scripts/lint.php", "scripts/test.php", "scripts/static-analysis.php", "scripts/phpstan-bootstrap.php",
            "tests/phpunit-bootstrap.php", "tests/PHPUnit/Framework/TestCase.php",
            "tests/RequestTest.php", "tests/QueryBuilderSecurityTest.php", "tests/AuthorizationGateTest.php",
            "tests/ValidationTest.php", "tests/ViewSecurityTest.php", "docs/framework/RUNTIME-CONTRACTS.md"] as $path) {
            $this->copy(base_path(), $path, $target . "/" . $path);
        }
        foreach (["README.md", ".env.example", "config/app.php", "bootstrap/common.php", "bootstrap/app.php", "bootstrap/router.php",
            "fnlla", "app/Controllers/HomeController.php", "routes/web.php", "views/layouts/app.php", "views/pages/home.php",
            "views/pages/error.php", "views/pages/not-found.php", "public/assets/app.css", "tests/bootstrap.php", "tests/PlainProjectTest.php", "phpstan.neon"] as $path) {
            $this->copy(base_path("resources/project-templates/v1/plain"), $path, $target . "/" . $path);
        }
        $this->copy(base_path("resources/project-templates/v1"), "database/seeders/DatabaseSeeder.php", $target . "/database/seeders/DatabaseSeeder.php");
        $this->copy(base_path("resources/project-templates/v1"), "phpunit.xml", $target . "/phpunit.xml");
        $this->write($target . "/.fnlla/project-profile", "plain\n");
        $env = (string) file_get_contents($target . "/.env.example");
        $this->write($target . "/.env.example", str_replace("{{APP_NAME}}", str_replace(['"', "\r", "\n"], ["", "", ""], $name), $env));
        foreach (["database/migrations", "storage/app", "storage/logs", "storage/framework/cache", "storage/framework/sessions", "storage/framework/queue"] as $path) {
            $this->write($target . "/" . $path . "/.gitignore", "*\n!.gitignore\n");
        }
    }

    private function copy(string $root, string $relative, string $target): void
    {
        if (!FrameworkLock::isSafeRelativePath($relative)) {
            throw new RuntimeException("Unsafe core export path.");
        }
        $path = $root . "/" . $relative;
        $real = realpath($path);
        $expected = str_replace("\\", "/", $path);
        $resolved = str_replace("\\", "/", (string) $real);
        if ($real === false || !is_file($real) || is_link($path)
            || (PHP_OS_FAMILY === "Windows" ? strcasecmp($resolved, $expected) !== 0 : $resolved !== $expected)) {
            throw new RuntimeException("Core export source missing or aliased: " . $relative);
        }
        $this->write($target, (string) file_get_contents($real));
    }

    private function write(string $path, string $contents): void
    {
        if (!is_dir(dirname($path)) && !mkdir(dirname($path), 0755, true)) {
            throw new RuntimeException("Cannot create plain project directory.");
        }
        if (file_put_contents($path, $contents) !== strlen($contents)) {
            throw new RuntimeException("Cannot write plain project file.");
        }
    }
}
