<?php

declare(strict_types=1);

define("APP_ROOT", dirname(__DIR__));
if (is_file(APP_ROOT . "/vendor/autoload.php")) {
    require_once APP_ROOT . "/vendor/autoload.php";
} else {
    spl_autoload_register(static function (string $class): void {
        $prefixes = ["Fnlla\\Php\\" => ["src/", "packages/fnlla-core/src/", "packages/fnlla-complete/src/"],
            "App\\" => ["app/"], "Database\\Seeders\\" => ["database/seeders/"], "Database\\Factories\\" => ["database/factories/"]];
        foreach ($prefixes as $prefix => $directories) {
            if (!str_starts_with($class, $prefix)) { continue; }
            foreach ($directories as $directory) {
                $path = APP_ROOT . "/" . $directory . str_replace("\\", "/", substr($class, strlen($prefix))) . ".php";
                if (is_file($path)) { require $path; return; }
            }
        }
    });
    require_once APP_ROOT . "/packages/fnlla-complete/src/Support/optional_helpers.php";
}
define("FNLLA_ENGINE_ROOT", dirname((new ReflectionClass(\Fnlla\Php\Container\Container::class))->getFileName(), 3));
return require FNLLA_ENGINE_ROOT . "/bootstrap/common.php";
