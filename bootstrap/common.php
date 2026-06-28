<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA PHP BOOTSTRAP FILE
File: bootstrap\common.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA PHP is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA PHP framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Bootstraps a framework runtime stage or shared application environment boundary.
*/

use Fnlla\Php\Container\Container;
use Fnlla\Php\Support\Env;
use Fnlla\Php\Support\FnllaUiGuard;
use Fnlla\Php\Support\Logger;
use Fnlla\Php\Support\ServiceProvider;

if (!defined("APP_ROOT")) {
    define("APP_ROOT", dirname(__DIR__));
}

if (!defined("PUBLIC_ROOT")) {
    define("PUBLIC_ROOT", APP_ROOT . DIRECTORY_SEPARATOR . "public");
}

if (!defined("VIEW_ROOT")) {
    define("VIEW_ROOT", APP_ROOT . DIRECTORY_SEPARATOR . "views");
}

$composerAutoload = APP_ROOT . DIRECTORY_SEPARATOR . "vendor" . DIRECTORY_SEPARATOR . "autoload.php";

if (is_file($composerAutoload)) {
    require $composerAutoload;
} else {
    spl_autoload_register(static function (string $class): void {
        $prefix = "Fnlla\\Php\\";

        if (!str_starts_with($class, $prefix)) {
            return;
        }

        $relativeClass = substr($class, strlen($prefix));
        $relativePath = str_replace("\\", DIRECTORY_SEPARATOR, $relativeClass) . ".php";
        $absolutePath = APP_ROOT . DIRECTORY_SEPARATOR . "src" . DIRECTORY_SEPARATOR . $relativePath;

        if (is_file($absolutePath)) {
            require $absolutePath;
        }
    });
}

require APP_ROOT . DIRECTORY_SEPARATOR . "src" . DIRECTORY_SEPARATOR . "Support" . DIRECTORY_SEPARATOR . "helpers.php";

Env::load(base_path(".env"));
$GLOBALS["fnlla_php_config"] = load_config_directory(base_path("config"));

/*
Development guard note:
- FNLLA PHP uses this shared bootstrap point to enforce the official FNLLA UI
  dependency boundary
- the guard is skipped only for specific maintainer repair flows that need to
  fix a broken UI contract from the CLI itself
*/
if (!defined("FNLLA_UI_SKIP_AUTO_GUARD")) {
    FnllaUiGuard::enforce();
}

date_default_timezone_set((string) config("app.timezone", "UTC"));
error_reporting(E_ALL);
ini_set("display_errors", app_debug() ? "1" : "0");
ini_set("display_startup_errors", app_debug() ? "1" : "0");
ini_set("log_errors", "1");
ini_set("error_log", (string) config("app.log_path"));

$logPath = dirname((string) config("app.log_path"));
if (!is_dir($logPath)) {
    mkdir($logPath, 0777, true);
}

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }

    throw new \ErrorException($message, 0, $severity, $file, $line);
});

register_shutdown_function(static function (): void {
    $error = error_get_last();
    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];

    if (!is_array($error) || !in_array($error["type"] ?? 0, $fatalTypes, true)) {
        return;
    }

    Logger::write("critical", "Fatal error", [
        "request_id" => request_id(),
        "type" => $error["type"] ?? null,
        "message" => $error["message"] ?? "Unknown fatal error",
        "file" => $error["file"] ?? null,
        "line" => $error["line"] ?? null,
    ]);
});

$container = new Container();
$GLOBALS["fnlla_php_container"] = $container;
$providers = [];

/* Service providers register first, then boot in a second pass once the container is ready. */
foreach ((array) config("app.providers", []) as $providerClass) {
    /** @var ServiceProvider $provider */
    $provider = new $providerClass($container);
    $provider->register();
    $providers[] = $provider;
}

foreach ($providers as $provider) {
    $provider->boot();
}

return $container;
