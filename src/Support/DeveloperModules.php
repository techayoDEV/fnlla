<?php

declare(strict_types=1);

namespace Fnlla\Php\Support;

final class DeveloperModules
{
    public const OPTIONS = [
        "workspace" => "Workspace",
        "analytics" => "Analytics",
        "heatmap" => "Heatmaps with analytics",
        "customer_portal" => "Customer portal",
    ];

    public static function environmentValues(array $input): array
    {
        $values = [];
        foreach (self::OPTIONS as $module => $label) {
            $values["FNLLA_MODULE_" . strtoupper($module)] = ($input["fnlla_module_" . $module] ?? "0") === "1";
        }
        if ($values["FNLLA_MODULE_HEATMAP"]) { $values["FNLLA_MODULE_ANALYTICS"] = true; }
        return $values;
    }

    public static function applyEnvironmentValues(array $values): void
    {
        foreach (self::OPTIONS as $module => $label) {
            $key = "FNLLA_MODULE_" . strtoupper($module);
            if (array_key_exists($key, $values)) { config_set("modules." . $module, $values[$key]); }
        }
    }

    public static function enabled(string $module): bool
    {
        return ProjectProfile::hasPanel()
            && array_key_exists($module, self::OPTIONS)
            && (bool) config("modules." . $module, true);
    }

    public static function forRoute(string $name): array
    {
        $modules = [];
        if (str_starts_with($name, "customer.") || str_contains($name, "customer_account")) {
            $modules[] = "customer_portal";
        }
        foreach (["workspace" => ["workspace", "kanban"], "analytics" => ["analytics"], "heatmap" => ["heatmap"]] as $module => $parts) {
            foreach ($parts as $part) {
                if (str_contains($name, $part)) {
                    $modules[] = $module;
                    break;
                }
            }
        }
        return $modules;
    }
}
