<?php

declare(strict_types=1);

namespace Fnlla\Php\Support;

final class ProjectProfile
{
    public static function name(?string $root = null): string
    {
        $path = rtrim($root ?? base_path(), "\\/") . "/.fnlla/project-profile";
        $profile = is_file($path) ? trim((string) file_get_contents($path)) : "full";
        if (!in_array($profile, ["full", "plain"], true)) {
            throw new \RuntimeException("Invalid FNLLA project profile.");
        }
        return $profile;
    }

    public static function hasPanel(?string $root = null): bool
    {
        return self::name($root) === "full";
    }

    public static function isPanelFile(string $path): bool
    {
        return str_starts_with($path, "views/developer/") || str_starts_with($path, "views/customer/")
            || str_starts_with($path, "views/maintenance/") || str_starts_with($path, "src/Controllers/Developer")
            || in_array($path, ["routes/maintenance.php", "src/Controllers/CustomerAccessController.php",
                "src/Controllers/FrameworkUpdateController.php", "public/assets/developer-panel.css",
                "public/assets/developer-panel.js", "public/assets/developer-tools.css", "public/assets/debug-toolbar.css"], true);
    }
}
