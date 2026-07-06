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

function framework_detect_environment(): string
{
    $explicit = $_ENV["APP_ENV"] ?? $_SERVER["APP_ENV"] ?? getenv("APP_ENV");

    if (is_string($explicit) && trim($explicit) !== "") {
        return trim($explicit);
    }

    return is_file(base_path(".env")) ? "production" : "development";
}

function framework_trusted_proxies(): array
{
    $raw = trim((string) env("TRUSTED_PROXIES", ""));

    if ($raw === "") {
        return [];
    }

    $entries = preg_split('/[\s,;]+/', $raw) ?: [];

    return array_values(array_filter(
        array_map(static fn (string $entry): string => trim($entry), $entries),
        static fn (string $entry): bool => $entry !== ""
    ));
}

function framework_remote_addr(array $server): string
{
    return (string) ($server["REMOTE_ADDR"] ?? "0.0.0.0");
}

function framework_request_comes_from_trusted_proxy(array $server): bool
{
    $remoteAddr = framework_remote_addr($server);

    if (filter_var($remoteAddr, FILTER_VALIDATE_IP) === false) {
        return false;
    }

    foreach (framework_trusted_proxies() as $trustedProxy) {
        $normalized = strtolower($trustedProxy);

        if (($normalized === "loopback" || $normalized === "localhost") && in_array($remoteAddr, ["127.0.0.1", "::1"], true)) {
            return true;
        }

        if (str_contains($trustedProxy, "/")) {
            [$network, $prefixLength] = array_pad(explode("/", $trustedProxy, 2), 2, "");
            $networkPacked = inet_pton(trim($network));
            $addressPacked = inet_pton($remoteAddr);
            $prefix = is_numeric($prefixLength) ? (int) $prefixLength : -1;

            if ($networkPacked === false || $addressPacked === false || strlen($networkPacked) !== strlen($addressPacked)) {
                continue;
            }

            $maxBits = strlen($networkPacked) * 8;

            if ($prefix < 0 || $prefix > $maxBits) {
                continue;
            }

            $fullBytes = intdiv($prefix, 8);
            $remainingBits = $prefix % 8;

            if ($fullBytes > 0 && substr($addressPacked, 0, $fullBytes) !== substr($networkPacked, 0, $fullBytes)) {
                continue;
            }

            if ($remainingBits === 0) {
                return true;
            }

            $mask = (0xFF << (8 - $remainingBits)) & 0xFF;
            $addressByte = ord($addressPacked[$fullBytes]);
            $networkByte = ord($networkPacked[$fullBytes]);

            if (($addressByte & $mask) === ($networkByte & $mask)) {
                return true;
            }

            continue;
        }

        if (strcasecmp($remoteAddr, $trustedProxy) === 0) {
            return true;
        }
    }

    return false;
}

function framework_trusted_forwarded_ip(array $server, array $headers = []): ?string
{
    if (!framework_request_comes_from_trusted_proxy($server)) {
        return null;
    }

    $forwardedFor = $headers["x-forwarded-for"] ?? $server["HTTP_X_FORWARDED_FOR"] ?? "";

    if (!is_string($forwardedFor) || trim($forwardedFor) === "") {
        return null;
    }

    foreach (explode(",", $forwardedFor) as $candidate) {
        $candidate = trim($candidate);

        if (filter_var($candidate, FILTER_VALIDATE_IP) !== false) {
            return $candidate;
        }
    }

    return null;
}

function framework_request_ip(array $server, array $headers = []): string
{
    return framework_trusted_forwarded_ip($server, $headers) ?? framework_remote_addr($server);
}

function app_request_is_secure(): bool
{
    if (framework_request_comes_from_trusted_proxy($_SERVER)) {
        $forwardedProto = $_SERVER["HTTP_X_FORWARDED_PROTO"] ?? null;

        if (is_string($forwardedProto) && trim($forwardedProto) !== "") {
            $firstProto = strtolower(trim(explode(",", $forwardedProto)[0] ?? ""));

            if ($firstProto === "https") {
                return true;
            }

            if ($firstProto === "http") {
                return false;
            }
        }

        if (strtolower((string) ($_SERVER["HTTP_X_FORWARDED_SSL"] ?? "")) === "on") {
            return true;
        }
    }

    $https = $_SERVER["HTTPS"] ?? null;

    if (is_string($https) && $https !== "" && strtolower($https) !== "off") {
        return true;
    }

    $requestScheme = strtolower((string) ($_SERVER["REQUEST_SCHEME"] ?? ""));

    if ($requestScheme === "https") {
        return true;
    }

    if ((int) ($_SERVER["SERVER_PORT"] ?? 0) === 443) {
        return true;
    }

    $appUrl = strtolower(trim((string) env("APP_URL", "")));

    return $appUrl !== "" && str_starts_with($appUrl, "https://");
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

function has_local_docs_workspace(): bool
{
    return is_dir(base_path("docs"))
        && is_file(base_path("docs/index.html"));
}

function page_meta(array $overrides = []): array
{
    return \Fnlla\Php\Support\PageMeta::resolve($overrides, (string) config("app.name", "FNLLA PHP"));
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
