<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA SUPPORT SOURCE
File: src\Support\ProjectLeadership.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

Purpose:
- Normalizes optional project and product leadership attribution used by public
  project pages and private Developer Panel surfaces.
*/

namespace Fnlla\Php\Support;

final class ProjectLeadership
{
    public const VISIBILITY_PUBLIC = "public";
    public const VISIBILITY_ADMIN = "admin";
    public const VISIBILITY_DISABLED = "disabled";

    public const STATUS_PENDING = "pending";
    public const STATUS_CONFIRMED = "confirmed";
    public const STATUS_REJECTED = "rejected";

    public function state(string $context = "admin"): array
    {
        $config = (array) config("app.project_leadership", []);
        $visibility = $this->visibility((string) ($config["visibility"] ?? self::VISIBILITY_DISABLED));
        $status = $this->status((string) ($config["status"] ?? self::STATUS_PENDING));
        $profileUrl = trim((string) ($config["profile_url"] ?? ""));
        $profileUrl = filter_var($profileUrl, FILTER_VALIDATE_URL) !== false ? $profileUrl : "";
        $email = strtolower(trim((string) ($config["person_email"] ?? "")));
        $organization = $this->text((string) ($config["organization"] ?? ""), 120);
        $personName = $this->text((string) ($config["person_name"] ?? ""), 120);
        $personRole = $this->text((string) ($config["person_role"] ?? ""), 120);
        $responsibility = $this->text((string) ($config["responsibility"] ?? ""), 240);
        $configured = $visibility !== self::VISIBILITY_DISABLED
            && $organization !== ""
            && $personName !== ""
            && $email !== "";
        $publicVisible = $configured
            && $visibility === self::VISIBILITY_PUBLIC
            && $status === self::STATUS_CONFIRMED;

        return [
            "schema" => "fnlla.project_leadership.v1",
            "context" => $context,
            "heading" => $context === "public" ? "Product leadership" : "System information",
            "organization" => $organization,
            "person_name" => $personName,
            "person_email" => $email,
            "person_role" => $personRole,
            "responsibility" => $responsibility,
            "profile_url" => $profileUrl,
            "visibility" => $visibility,
            "status" => $configured ? $status : self::STATUS_PENDING,
            "confirmed_by" => $this->text((string) ($config["confirmed_by"] ?? ""), 160),
            "confirmed_at" => $this->text((string) ($config["confirmed_at"] ?? ""), 40),
            "configured" => $configured,
            "public_visible" => $publicVisible,
        ];
    }

    public function canConfirm(array $state, array $developer, array $capabilities = []): bool
    {
        if (($state["configured"] ?? false) !== true) {
            return false;
        }

        $developerEmail = strtolower(trim((string) ($developer["email"] ?? "")));
        $leadEmail = strtolower(trim((string) ($state["person_email"] ?? "")));

        if ($leadEmail !== "" && $developerEmail !== "" && hash_equals($leadEmail, $developerEmail)) {
            return true;
        }

        $role = strtolower(trim((string) ($developer["role"] ?? "")));

        return in_array($role, ["owner_developer", "lead_developer", "admin"], true)
            && in_array("project.identity.write", $capabilities, true);
    }

    public function sameIdentity(array $current, array $next): bool
    {
        foreach (["organization", "person_name", "person_email", "person_role", "responsibility", "profile_url"] as $key) {
            if ((string) ($current[$key] ?? "") !== (string) ($next[$key] ?? "")) {
                return false;
            }
        }

        return true;
    }

    public function visibility(string $value): string
    {
        $value = strtolower(trim($value));

        return in_array($value, [self::VISIBILITY_PUBLIC, self::VISIBILITY_ADMIN, self::VISIBILITY_DISABLED], true)
            ? $value
            : self::VISIBILITY_DISABLED;
    }

    public function status(string $value): string
    {
        $value = strtolower(trim($value));

        return in_array($value, [self::STATUS_PENDING, self::STATUS_CONFIRMED, self::STATUS_REJECTED], true)
            ? $value
            : self::STATUS_PENDING;
    }

    private function text(string $value, int $maxLength): string
    {
        $value = trim((string) preg_replace('/\s+/', ' ', $value));

        return strlen($value) <= $maxLength ? $value : substr($value, 0, $maxLength);
    }
}
