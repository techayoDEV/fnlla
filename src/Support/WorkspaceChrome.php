<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA SUPPORT SOURCE
File: src\Support\WorkspaceChrome.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

Purpose:
- Provides small neutral data builders for server-rendered workspace and
  dashboard chrome without pulling downstream business modules into FNLLA.
*/

namespace Fnlla\Php\Support;

final class WorkspaceChrome
{
    public static function navItem(string $key, string $label, string $href, string $activeKey = "", array $meta = []): array
    {
        return array_merge([
            "key" => trim($key),
            "label" => trim($label),
            "href" => trim($href),
            "active" => trim($key) !== "" && trim($key) === trim($activeKey),
        ], $meta);
    }

    public static function statusCard(string $key, string $label, string|int $value, string $tone = "neutral", array $meta = []): array
    {
        return array_merge([
            "key" => trim($key),
            "label" => trim($label),
            "value" => (string) $value,
            "tone" => self::tone($tone),
        ], $meta);
    }

    public static function identity(string $name, string $email = "", string $avatar = "", array $meta = []): array
    {
        $name = trim($name);
        $email = trim($email);
        $avatar = trim($avatar);

        if ($avatar === "") {
            $avatar = self::initials($name !== "" ? $name : $email);
        }

        return array_merge([
            "name" => $name,
            "email" => $email,
            "avatar" => $avatar,
        ], $meta);
    }

    private static function tone(string $tone): string
    {
        $tone = strtolower(trim($tone));

        return in_array($tone, ["neutral", "success", "warning", "danger", "info"], true) ? $tone : "neutral";
    }

    private static function initials(string $value): string
    {
        $value = trim(preg_replace('/[^A-Za-z0-9\s]+/', " ", $value) ?? "");

        if ($value === "") {
            return "FN";
        }

        $parts = preg_split('/\s+/', $value) ?: [];
        $letters = "";

        foreach (array_slice($parts, 0, 2) as $part) {
            $letters .= strtoupper(substr($part, 0, 1));
        }

        return $letters !== "" ? $letters : "FN";
    }
}
