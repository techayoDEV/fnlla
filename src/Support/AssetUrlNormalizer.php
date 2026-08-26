<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA SUPPORT SOURCE
File: src\Support\AssetUrlNormalizer.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

Purpose:
- Normalizes stored browser asset URLs so downstream projects can move between
  local hosts, production domains and optional asset origins without stale
  absolute image or media URLs.
*/

namespace Fnlla\Php\Support;

final class AssetUrlNormalizer
{
    public function asset(string $value, string $fallback = ""): string
    {
        $normalized = $this->relativePath($value);

        if ($normalized !== "") {
            return asset($normalized);
        }

        $fallback = $this->relativePath($fallback);

        return $fallback !== "" ? asset($fallback) : "";
    }

    public function srcset(string $value): string
    {
        $items = [];

        foreach (explode(",", $value) as $candidate) {
            $candidate = trim($candidate);

            if ($candidate === "") {
                continue;
            }

            $parts = preg_split('/\s+/', $candidate) ?: [];
            $url = (string) array_shift($parts);
            $normalized = $this->asset($url);

            if ($normalized === "") {
                continue;
            }

            $descriptor = trim(implode(" ", $parts));
            $items[] = $descriptor !== "" ? $normalized . " " . $descriptor : $normalized;
        }

        return implode(", ", $items);
    }

    public function relativePath(string $value): string
    {
        $value = trim($value);

        if ($value === "" || str_starts_with($value, "data:") || str_starts_with($value, "mailto:") || str_starts_with($value, "tel:")) {
            return "";
        }

        $path = $this->stripKnownOrigin($value);

        if (preg_match('/^[a-z][a-z0-9+.-]*:\/\//i', $path) === 1) {
            return "";
        }

        $path = parse_url($path, PHP_URL_PATH);
        $path = is_string($path) ? $path : "";
        $path = ltrim(str_replace("\\", "/", $path), "/");

        if ($path === "" || str_contains($path, "..")) {
            return "";
        }

        return $path;
    }

    private function stripKnownOrigin(string $url): string
    {
        $assetBaseUrl = rtrim((string) config("app.asset_url", ""), "/");
        $appBaseUrl = rtrim((string) config("app.base_url", ""), "/");

        foreach (array_filter([$assetBaseUrl, $appBaseUrl]) as $baseUrl) {
            if ($url === $baseUrl) {
                return "";
            }

            if (str_starts_with($url, $baseUrl . "/")) {
                return substr($url, strlen($baseUrl) + 1);
            }
        }

        return $url;
    }
}
