<?php

declare(strict_types=1);

namespace Fnlla\Php\Support;

use RuntimeException;

/** Converts only a freshly generated export, never an existing client project. */
final class CompletePackageExporter
{
    public function convert(string $target): void
    {
        $core = json_decode((string) file_get_contents(base_path("resources/project-templates/v1/core-files.json")), true, 512, JSON_THROW_ON_ERROR);
        $export = json_decode((string) file_get_contents(base_path("resources/project-templates/v1/export-files.json")), true, 512, JSON_THROW_ON_ERROR);
        $version = trim((string) file(base_path("VERSION"))[0]);
        $coreFiles = array_fill_keys($core["files"], true);
        foreach (["fnlla-core", "fnlla-complete"] as $package) {
            $this->write($target . "/packages/" . $package . "/LICENSE.md", (string) file_get_contents(base_path("LICENSE.md")));
        }
        foreach ($export["files"] as $path) {
            // PageController is application-owned. Existing namespace stays compatible.
            if (!str_starts_with($path, "src/") || $path === "src/Controllers/PageController.php" || !is_file($target . "/" . $path)) { continue; }
            $package = isset($coreFiles[$path]) ? "fnlla-core" : "fnlla-complete";
            $destination = $target . "/packages/" . $package . "/" . $path;
            $this->write($destination, (string) file_get_contents($target . "/" . $path));
            if (!unlink($target . "/" . $path)) { throw new RuntimeException("Cannot relocate generated framework source."); }
        }
        // The same positive Core inventory is bundled by both presets.
        foreach ($core["files"] as $path) {
            if (!is_file($target . "/packages/fnlla-core/" . $path)) {
                $this->write($target . "/packages/fnlla-core/" . $path, (string) file_get_contents(base_path($path)));
            }
        }
        foreach (["fnlla-core", "fnlla-complete"] as $package) {
            $metadata = ["name" => "techayodev/" . $package, "description" => "FNLLA " . $package . " runtime package",
                "type" => "library", "license" => "MIT", "version" => $version,
                "require" => ["php" => "^8.3"], "autoload" => ["psr-4" => ["Fnlla\\Php\\" => "src/"]]];
            if ($package === "fnlla-complete") {
                $metadata["require"]["techayodev/fnlla-core"] = $version;
                $metadata["autoload"]["files"] = ["src/Support/optional_helpers.php"];
            }
            $this->writeJson($target . "/packages/" . $package . "/composer.json", $metadata);
        }
        $metadata = json_decode((string) file_get_contents($target . "/composer.json"), true, 512, JSON_THROW_ON_ERROR);
        $metadata["repositories"] = [["type" => "path", "url" => "packages/*", "options" => ["symlink" => false]]];
        $metadata["require"]["techayodev/fnlla-core"] = $version;
        $metadata["require"]["techayodev/fnlla-complete"] = $version;
        $this->writeJson($target . "/composer.json", $metadata);
        $this->write($target . "/bootstrap/common.php", (string) file_get_contents(base_path("resources/project-templates/v1/packages/bootstrap/common.php")));
        $this->write($target . "/phpstan.neon", (string) file_get_contents(base_path("resources/project-templates/v1/packages/phpstan.neon")));
        $this->write($target . "/.fnlla/package-distribution", "core-complete-v1\n");
        $this->write($target . "/docs/framework/PACKAGES.md", (string) file_get_contents(base_path("resources/project-templates/v1/packages/PACKAGES.md")));
        // Keep the legacy lock as migration evidence, never feed package trees to its file updater.
    }

    private function writeJson(string $path, array $value): void
    {
        $this->write($path, json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
    }

    private function write(string $path, string $content): void
    {
        if (!is_dir(dirname($path)) && !mkdir(dirname($path), 0700, true) && !is_dir(dirname($path))) {
            throw new RuntimeException("Cannot create package directory.");
        }
        if (file_put_contents($path, $content) !== strlen($content)) { throw new RuntimeException("Cannot write generated package."); }
    }
}
