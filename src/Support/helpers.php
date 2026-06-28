<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA PHP SUPPORT SOURCE
File: src\Support\helpers.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA PHP is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA PHP framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Implements shared helpers, environment loading, metadata and framework support behavior.
*/

use Fnlla\Php\Container\Container;

function env(string $key, mixed $default = null): mixed
{
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

    if ($value === false || $value === null) {
        return $default;
    }

    if (!is_string($value)) {
        return $value;
    }

    return match (strtolower(trim($value))) {
        "true", "(true)" => true,
        "false", "(false)" => false,
        "null", "(null)" => null,
        "empty", "(empty)" => "",
        default => $value,
    };
}

function config(?string $key = null, mixed $default = null): mixed
{
    $config = $GLOBALS["fnlla_php_config"] ?? [];

    if ($key === null || $key === "") {
        return $config;
    }

    $segments = explode(".", $key);
    $value = $config;

    foreach ($segments as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }

        $value = $value[$segment];
    }

    return $value;
}

function config_set(string $key, mixed $value): void
{
    $config = $GLOBALS["fnlla_php_config"] ?? [];
    $segments = explode(".", $key);
    $cursor = &$config;

    foreach ($segments as $segment) {
        if (!is_array($cursor)) {
            $cursor = [];
        }

        if (!array_key_exists($segment, $cursor) || !is_array($cursor[$segment])) {
            $cursor[$segment] ??= [];
        }

        $cursor = &$cursor[$segment];
    }

    $cursor = $value;
    $GLOBALS["fnlla_php_config"] = $config;
}

function load_config_directory(string $directory): array
{
    $config = [];
    $files = glob(rtrim($directory, "\\/") . DIRECTORY_SEPARATOR . "*.php");

    if ($files === false) {
        return $config;
    }

    sort($files);

    foreach ($files as $file) {
        $key = pathinfo($file, PATHINFO_FILENAME);
        $loaded = require $file;

        if (!is_array($loaded)) {
            throw new RuntimeException("Config file must return an array: " . $file);
        }

        $config[$key] = $loaded;
    }

    return $config;
}

function base_path(string $path = ""): string
{
    return APP_ROOT . ($path !== "" ? DIRECTORY_SEPARATOR . ltrim($path, "\\/") : "");
}

function public_path(string $path = ""): string
{
    return PUBLIC_ROOT . ($path !== "" ? DIRECTORY_SEPARATOR . ltrim($path, "\\/") : "");
}

function storage_path(string $path = ""): string
{
    return APP_ROOT . DIRECTORY_SEPARATOR . "storage" . ($path !== "" ? DIRECTORY_SEPARATOR . ltrim($path, "\\/") : "");
}

function url(string $path = ""): string
{
    $baseUrl = (string) config("app.base_url", "");
    $normalizedPath = "/" . ltrim($path, "/");

    if ($normalizedPath === "/") {
        return $baseUrl !== "" ? $baseUrl . "/" : "/";
    }

    return ($baseUrl !== "" ? $baseUrl : "") . $normalizedPath;
}

function asset(string $path = ""): string
{
    return url($path);
}

function route(string $name, array $parameters = []): string
{
    return app(\Fnlla\Php\Routing\UrlGenerator::class)->route($name, $parameters);
}

function h(string|int|float|null $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

function app_environment(): string
{
    return (string) config("app.environment", "production");
}

function app_debug(): bool
{
    return (bool) config("app.debug", false);
}

function request_id(): string
{
    $current = $_SERVER["FNLLA_REQUEST_ID"] ?? $_SERVER["HTTP_X_REQUEST_ID"] ?? null;

    if (is_string($current) && $current !== "") {
        $_SERVER["FNLLA_REQUEST_ID"] = $current;

        return $current;
    }

    $generated = bin2hex(random_bytes(16));
    $_SERVER["FNLLA_REQUEST_ID"] = $generated;

    return $generated;
}

function app(?string $abstract = null, array $parameters = []): mixed
{
    $container = $GLOBALS["fnlla_php_container"] ?? null;

    if (!$container instanceof Container) {
        throw new RuntimeException("Application container has not been bootstrapped.");
    }

    if ($abstract === null) {
        return $container;
    }

    return $container->make($abstract, $parameters);
}

function current_path(): string
{
    $requestUri = $_SERVER["REQUEST_URI"] ?? "/";
    $path = parse_url($requestUri, PHP_URL_PATH) ?: "/";
    $normalized = "/" . trim($path, "/");

    return $normalized === "/" ? "/" : rtrim($normalized, "/");
}

function is_current_path(string $path): bool
{
    $normalized = "/" . trim($path, "/");
    $normalized = $normalized === "/" ? "/" : rtrim($normalized, "/");

    return current_path() === $normalized;
}

function flash(string $key, mixed $default = null): mixed
{
    $flash = $_SESSION["_flash_old"] ?? [];

    return $flash[$key] ?? $default;
}

function flash_set(string $key, mixed $value): void
{
    if (!isset($_SESSION["_flash"]) || !is_array($_SESSION["_flash"])) {
        $_SESSION["_flash"] = [];
    }

    $_SESSION["_flash"][$key] = $value;
}

function old(string $key, mixed $default = ""): mixed
{
    $old = flash("old", []);

    return is_array($old) ? ($old[$key] ?? $default) : $default;
}

function errors(): array
{
    $errors = flash("errors", []);

    return is_array($errors) ? $errors : [];
}

function error_for(string $field): ?string
{
    $allErrors = errors();

    return isset($allErrors[$field]) ? (string) $allErrors[$field] : null;
}

function csrf_token(): string
{
    $token = $_SESSION["_csrf_token"] ?? null;
    $issuedAt = (int) ($_SESSION["_csrf_token_issued_at"] ?? 0);
    $rotationWindow = max(1, (int) config("security.csrf.rotate_after_minutes", 120)) * 60;

    if (!is_string($token) || $token === "" || ($issuedAt > 0 && (time() - $issuedAt) >= $rotationWindow)) {
        return regenerate_csrf_token();
    }

    return $token;
}

function regenerate_csrf_token(): string
{
    $_SESSION["_csrf_token"] = bin2hex(random_bytes(32));
    $_SESSION["_csrf_token_issued_at"] = time();

    return $_SESSION["_csrf_token"];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . h(csrf_token()) . '">';
}

function verify_csrf_token(?string $token): bool
{
    if (!is_string($token) || $token === "") {
        return false;
    }

    $knownToken = csrf_token();

    return is_string($knownToken) && hash_equals($knownToken, $token);
}

function auth(): \Fnlla\Php\Auth\AuthManager
{
    return app(\Fnlla\Php\Auth\AuthManager::class);
}

function session_store(): \Fnlla\Php\Session\SessionStore
{
    return app(\Fnlla\Php\Session\SessionStore::class);
}

function gate(): \Fnlla\Php\Auth\Authorization\Gate
{
    return app(\Fnlla\Php\Auth\Authorization\Gate::class);
}

function cache(): \Fnlla\Php\Cache\CacheStoreInterface
{
    return app(\Fnlla\Php\Cache\CacheStoreInterface::class);
}

function storage(?string $disk = null): \Fnlla\Php\Filesystem\FilesystemAdapter
{
    return app(\Fnlla\Php\Filesystem\StorageManager::class)->disk($disk);
}

function hasher(): \Fnlla\Php\Hashing\Hasher
{
    return app(\Fnlla\Php\Hashing\Hasher::class);
}

function event(object|string $event, array $payload = []): array
{
    return app(\Fnlla\Php\Events\Dispatcher::class)->dispatch($event, $payload);
}

function translator(): \Fnlla\Php\Localization\Translator
{
    return app(\Fnlla\Php\Localization\Translator::class);
}

function __(string $key, array $replace = [], ?string $locale = null): string
{
    return translator()->get($key, $replace, $locale);
}

function mailer(): \Fnlla\Php\Mail\Mailer
{
    return app(\Fnlla\Php\Mail\Mailer::class);
}

function queue(): \Fnlla\Php\Queue\QueueManager
{
    return app(\Fnlla\Php\Queue\QueueManager::class);
}
