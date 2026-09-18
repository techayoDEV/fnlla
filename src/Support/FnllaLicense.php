<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA SUPPORT SOURCE
File: src\Support\FnllaLicense.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Verifies and stores FNLLA commercial licence tokens issued by fnlla.com.
*/

namespace Fnlla\Php\Support;

use RuntimeException;

final class FnllaLicense
{
    public function verify(string $token): array
    {
        $token = trim($token);
        $prefix = (string) config("license.token_prefix", "fnlla_license_v1");
        $parts = explode(".", $token);

        if (count($parts) !== 3 || $parts[0] !== $prefix) {
            throw new RuntimeException("The licence token format is not recognized.");
        }

        $payloadJson = $this->base64UrlDecode($parts[1]);
        $signature = $this->base64UrlDecode($parts[2]);
        $expected = hash_hmac("sha256", $payloadJson, $this->secret(), true);

        if (!hash_equals($expected, $signature)) {
            throw new RuntimeException("The licence signature is invalid.");
        }

        $payload = json_decode($payloadJson, true);

        if (!is_array($payload)) {
            throw new RuntimeException("The licence payload is invalid.");
        }

        if ((string) ($payload["schema"] ?? "") !== "fnlla.license.v1") {
            throw new RuntimeException("The licence schema is not supported.");
        }

        if ((string) ($payload["issuer"] ?? "") !== (string) config("license.issuer", "fnlla.com")) {
            throw new RuntimeException("The licence issuer is not trusted.");
        }

        $expiresAt = $payload["expires_at_utc"] ?? null;

        if (is_string($expiresAt) && trim($expiresAt) !== "" && strtotime($expiresAt) !== false && strtotime($expiresAt) < time()) {
            throw new RuntimeException("The licence has expired.");
        }

        return [
            "valid" => true,
            "verified_at_utc" => gmdate(DATE_ATOM),
            "token" => $token,
            "payload" => $payload,
        ];
    }

    public function install(string $token): array
    {
        $record = $this->verify($token);
        $path = $this->installedPath();
        $directory = dirname($path);

        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        file_put_contents($path, json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL, LOCK_EX);

        return $record;
    }

    public function current(): ?array
    {
        $path = $this->installedPath();

        if (!is_file($path)) {
            return null;
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : null;
    }

    public function installedPath(): string
    {
        return storage_path((string) config("license.installed_path", "framework/license.json"));
    }

    private function secret(): string
    {
        $secret = trim((string) config("license.secret", ""));

        if ($secret === "") {
            throw new RuntimeException("FNLLA_LICENSE_SECRET is not configured.");
        }

        return $secret;
    }

    private function base64UrlDecode(string $value): string
    {
        $remainder = strlen($value) % 4;

        if ($remainder > 0) {
            $value .= str_repeat("=", 4 - $remainder);
        }

        $decoded = base64_decode(strtr($value, "-_", "+/"), true);

        if (!is_string($decoded)) {
            throw new RuntimeException("The licence token contains invalid base64 data.");
        }

        return $decoded;
    }
}
