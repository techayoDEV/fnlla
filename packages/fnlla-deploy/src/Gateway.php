<?php

declare(strict_types=1);

namespace Fnlla\Deploy;

/** Route every request through this gateway; never expose the deployment root. */
final class Gateway
{
    public static function run(string $root): void
    {
        try {
            $root = realpath($root) ?: throw new \RuntimeException("Deployment root is missing.");
            $store = new ReleaseStore($root);
            $id = $store->state()["current"] ?? null;
            if (!is_string($id)) { throw new \RuntimeException("No active release."); }
            $release = $store->release($id);
            $raw = (string) parse_url((string) ($_SERVER["REQUEST_URI"] ?? "/"), PHP_URL_PATH);
            $path = rawurldecode($raw);
            if (preg_match('~[\\\\:\x00-\x1f%]|(?:^|/)\.~', $path) === 1) { self::error(404); return; }
            if (str_starts_with($path, "/_fnlla/releases/")) {
                if (preg_match('~^/_fnlla/releases/([A-Za-z0-9][A-Za-z0-9_-]{0,63})/(.+)$~D', $path, $match) !== 1) {
                    self::error(404); return;
                }
                $assetRelease = $store->release($match[1]);
                if (!is_file($assetRelease . "/.fnlla-release.json")) { self::error(404); return; }
                self::asset($assetRelease . "/public", $match[2], true);
                return;
            }
            if (str_starts_with($path, "/uploads/")) {
                self::asset($root . "/shared/public", ltrim($path, "/"), false, true);
                return;
            }
            // Unversioned static URLs remain valid, but are never cached as immutable.
            if ($path !== "/" && is_file($release . "/public/" . ltrim($path, "/")) && $path !== "/index.php") {
                self::asset($release . "/public", ltrim($path, "/"), false);
                return;
            }
            if (!is_file($root . "/shared/.env")) { throw new \RuntimeException("Provision shared/.env before serving traffic."); }
            define("FNLLA_ENV_PATH", $root . "/shared/.env");
            define("FNLLA_STORAGE_ROOT", $root . "/shared/storage");
            define("FNLLA_CACHE_ROOT", $root . "/shared/release-cache/" . $id);
            define("FNLLA_SHARED_PUBLIC_ROOT", $root . "/shared/public");
            define("FNLLA_RELEASE_ASSET_URL", "/_fnlla/releases/" . $id);
            $_SERVER["SCRIPT_FILENAME"] = $release . "/public/index.php";
            $_SERVER["SCRIPT_NAME"] = "/index.php";
            require $release . "/public/index.php";
        } catch (\Throwable $exception) {
            error_log("FNLLA deployment gateway failed: " . get_class($exception));
            self::error(503);
        }
    }

    private static function asset(string $root, string $relative, bool $immutable, bool $untrusted = false): void
    {
        $base = realpath($root);
        $path = realpath($root . "/" . $relative);
        $expected = str_replace("\\", "/", (string) $base . "/" . $relative);
        if ($base === false || $path === false || !is_file($path)
            || strcasecmp(str_replace("\\", "/", $path), $expected) !== 0) {
            self::error(404); return;
        }
        $types = ["css" => "text/css", "js" => "text/javascript", "png" => "image/png", "jpg" => "image/jpeg",
            "jpeg" => "image/jpeg", "webp" => "image/webp", "gif" => "image/gif", "svg" => "image/svg+xml",
            "ico" => "image/x-icon", "woff" => "font/woff", "woff2" => "font/woff2", "json" => "application/json",
            "webmanifest" => "application/manifest+json", "txt" => "text/plain", "pdf" => "application/pdf"];
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (!isset($types[$extension])) { self::error(404); return; }
        if (!in_array($_SERVER["REQUEST_METHOD"] ?? "GET", ["GET", "HEAD"], true)) {
            header("Allow: GET, HEAD"); self::error(405); return;
        }
        header("Content-Type: " . $types[$extension]);
        if ($untrusted && !in_array($extension, ["png", "jpg", "jpeg", "webp", "gif", "ico"], true)) {
            header("Content-Disposition: attachment");
            header("Content-Security-Policy: sandbox; default-src 'none'");
        }
        header("X-Content-Type-Options: nosniff");
        header("Cache-Control: " . ($immutable ? "public, max-age=31536000, immutable" : "no-cache"));
        header("Content-Length: " . filesize($path));
        if (($_SERVER["REQUEST_METHOD"] ?? "GET") !== "HEAD") { readfile($path); }
    }

    private static function error(int $status): void
    {
        http_response_code($status);
        header("Content-Type: text/plain; charset=UTF-8");
        header("Cache-Control: no-store");
        if ($status === 503) { header("Retry-After: 30"); }
        if (($_SERVER["REQUEST_METHOD"] ?? "GET") !== "HEAD") { echo "Request unavailable."; }
    }
}
