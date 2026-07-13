<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA BOOTSTRAP FILE
File: bootstrap\app.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Bootstraps a framework runtime stage or shared application environment boundary.
*/

use Fnlla\Php\Application;
use Fnlla\Php\Exceptions\ExceptionHandler;
use Fnlla\Php\Session\SessionStore;

$container = require __DIR__ . DIRECTORY_SEPARATOR . "common.php";

$sessionConfig = config("session", []);
$sessionPath = (string) config("app.session_path");
if (!is_dir($sessionPath)) {
    mkdir($sessionPath, 0777, true);
}

session_save_path($sessionPath);
session_name((string) ($sessionConfig["name"] ?? "fnlla_session"));
ini_set("session.use_strict_mode", !empty($sessionConfig["strict_mode"]) ? "1" : "0");
ini_set("session.use_only_cookies", !empty($sessionConfig["use_only_cookies"]) ? "1" : "0");
ini_set("session.cookie_httponly", !empty($sessionConfig["http_only"]) ? "1" : "0");
ini_set("session.cookie_secure", !empty($sessionConfig["secure"]) ? "1" : "0");
ini_set("session.gc_maxlifetime", (string) ($sessionConfig["cookie_lifetime"] ?? 7200));
session_set_cookie_params([
    "lifetime" => (int) ($sessionConfig["cookie_lifetime"] ?? 7200),
    "path" => (string) ($sessionConfig["path"] ?? "/"),
    "domain" => is_string($sessionConfig["domain"] ?? null) ? (string) $sessionConfig["domain"] : "",
    "secure" => !empty($sessionConfig["secure"]),
    "httponly" => !empty($sessionConfig["http_only"]),
    "samesite" => (string) ($sessionConfig["same_site"] ?? "Lax"),
]);
session_start();

if (!isset($_SESSION["_meta"]) || !is_array($_SESSION["_meta"])) {
    session_regenerate_id(true);
    $_SESSION["_meta"] = [
        "started_at" => time(),
        "last_regenerated_at" => time(),
    ];
} else {
    $rotationWindow = max(1, (int) ($sessionConfig["rotate_after_minutes"] ?? 30)) * 60;
    $lastRegeneratedAt = (int) ($_SESSION["_meta"]["last_regenerated_at"] ?? 0);

    if ($lastRegeneratedAt <= 0 || (time() - $lastRegeneratedAt) >= $rotationWindow) {
        session_regenerate_id(true);
        $_SESSION["_meta"]["last_regenerated_at"] = time();
    }
}

$_SESSION["_flash_old"] = is_array($_SESSION["_flash"] ?? null) ? $_SESSION["_flash"] : [];
$_SESSION["_flash"] = [];

$container->instance(SessionStore::class, new SessionStore());
$router = require APP_ROOT . DIRECTORY_SEPARATOR . "bootstrap" . DIRECTORY_SEPARATOR . "router.php";

$application = new Application(
    $router,
    $container,
    $container->make(ExceptionHandler::class)
);
$application->middleware(["cors", "maintenance"]);

return $application;
