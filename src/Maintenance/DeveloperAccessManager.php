<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA MAINTENANCE SOURCE
File: src\Maintenance\DeveloperAccessManager.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

Purpose:
- Provides a hidden developer-only access channel for operator surfaces once the
  public operations navigation should no longer stay visible to the client.
*/

namespace Fnlla\Php\Maintenance;

use Fnlla\Php\Cache\RateLimiter;
use Fnlla\Php\Http\Request;
use Fnlla\Php\Session\SessionStore;
use Fnlla\Php\Support\Logger;

final class DeveloperAccessManager
{
    private const PUBLIC_ROLE_LABELS = [
        "lead_developer" => "Lead developer",
        "application_developer" => "Developer",
    ];

    private const ROLE_LABELS = [
        "owner_developer" => "Owner developer",
        "lead_developer" => "Lead developer",
        "application_developer" => "Application developer",
        "operations_engineer" => "Operations engineer",
        "support_developer" => "Support developer",
        "security_reviewer" => "Security reviewer",
        "admin" => "Administrator",
        "developer" => "Developer",
        "operator" => "Operator",
        "client" => "Client reviewer",
    ];

    private const ROLE_CAPABILITIES = [
        "owner_developer" => ["*"],
        "lead_developer" => [
            "panel.view",
            "project.identity.write",
            "preview.manage",
            "service_control.write",
            "developer.accounts.write",
            "developer.profile.write",
            "developer.security.manage",
            "panel.settings.write",
            "workspace.write",
            "operations.view",
            "audit.export",
            "framework.update",
            "policy.view",
        ],
        "application_developer" => [
            "panel.view",
            "developer.profile.write",
            "developer.security.manage",
            "workspace.write",
            "operations.view",
            "policy.view",
        ],
        "operations_engineer" => [
            "panel.view",
            "preview.manage",
            "service_control.write",
            "developer.profile.write",
            "developer.security.manage",
            "workspace.write",
            "operations.view",
            "audit.export",
            "framework.update",
            "policy.view",
        ],
        "support_developer" => [
            "panel.view",
            "developer.profile.write",
            "developer.security.manage",
            "workspace.write",
            "operations.view",
            "policy.view",
        ],
        "security_reviewer" => [
            "panel.view",
            "developer.profile.write",
            "developer.security.manage",
            "operations.view",
            "audit.export",
            "policy.view",
        ],
        "admin" => ["*"],
        "developer" => [
            "panel.view",
            "developer.profile.write",
            "developer.security.manage",
            "workspace.write",
            "operations.view",
            "policy.view",
        ],
        "operator" => [
            "panel.view",
            "preview.manage",
            "service_control.write",
            "developer.profile.write",
            "developer.security.manage",
            "operations.view",
            "policy.view",
        ],
        "client" => [
            "panel.view",
            "developer.profile.write",
            "developer.security.manage",
            "policy.view",
        ],
    ];

    public function __construct(
        private SessionStore $session,
        private RateLimiter $limiter
    ) {
    }

    public function enabled(): bool
    {
        return (bool) config("developer_access.enabled", true);
    }

    public function configured(): bool
    {
        return $this->accounts() !== [];
    }

    public function path(): string
    {
        return "/developer";
    }

    public function operationsNavMode(): string
    {
        if (!$this->configured()) {
            return "hidden";
        }

        $mode = trim((string) config("developer_access.operations_nav_mode", "hidden"));

        return in_array($mode, ["visible", "developer_session_only", "hidden"], true)
            ? $mode
            : "hidden";
    }

    public function operationsNavVisible(bool $maintenanceLocked = false): bool
    {
        return $this->isUnlocked();
    }

    public function canAccessOperations(): bool
    {
        return !$this->configured() || $this->isUnlocked();
    }

    public function isUnlocked(): bool
    {
        if (!$this->enabled() || !$this->configured()) {
            return false;
        }

        if ($this->session->get($this->sessionKey()) !== true) {
            return false;
        }

        $expiresAt = (int) $this->session->get($this->expiresAtKey(), 0);
        $unlockedAt = (int) $this->session->get($this->unlockedAtKey(), 0);

        if (
            $expiresAt <= time()
            || $unlockedAt <= 0
            || $unlockedAt + $this->absoluteTtlSeconds() <= time()
            || !hash_equals($this->credentialFingerprint(), (string) $this->session->get($this->credentialFingerprintKey(), ""))
        ) {
            $this->lock();

            return false;
        }

        return true;
    }

    public function expiresAt(): int
    {
        if (!$this->isUnlocked()) {
            return 0;
        }

        return (int) $this->session->get($this->expiresAtKey(), 0);
    }

    public function secondsRemaining(): int
    {
        return max(0, $this->expiresAt() - time());
    }

    public function unlock(Request $request, string $password, string $email = "", string $totpCode = ""): array
    {
        if (!$this->enabled() || !$this->configured()) {
            return [
                "success" => false,
                "error" => "Developer access is not configured for this project yet.",
                "retry_after" => 0,
            ];
        }

        $email = $this->normaliseEmail($email);
        $rateLimitKey = $this->rateLimitKey($request, $email);
        $blockedUntil = (int) cache()->get($this->blockKey($rateLimitKey), 0);
        $maxAttempts = max(1, (int) config("developer_access.max_attempts", 5));
        $lockoutSeconds = max(1, (int) config("developer_access.lockout_minutes", 15)) * 60;
        $attemptWindowSeconds = max(1, (int) config("developer_access.attempt_window_minutes", 15)) * 60;

        if ($blockedUntil > time()) {
            $retryAfter = max(0, $blockedUntil - time());

            return [
                "success" => false,
                "error" => "Too many developer access attempts. " . $this->formatRetryAfter($retryAfter),
                "retry_after" => $retryAfter,
            ];
        }

        $account = $this->matchingAccount($email, $password);

        if ($account === null) {
            $attempts = $this->limiter->hit($rateLimitKey, $attemptWindowSeconds);

            if ($attempts >= $maxAttempts) {
                cache()->put($this->blockKey($rateLimitKey), time() + $lockoutSeconds, $lockoutSeconds);
                $this->limiter->clear($rateLimitKey);
                Logger::write("warning", "Developer access locked out", [
                    "event" => "developer_access_lockout",
                    "email" => $email,
                    "ip" => $request->ip(),
                ]);

                return [
                    "success" => false,
                    "error" => "Too many developer access attempts. " . $this->formatRetryAfter($lockoutSeconds),
                    "retry_after" => $lockoutSeconds,
                ];
            }

            Logger::write("warning", "Developer access denied", [
                "event" => "developer_access_denied",
                "email" => $email,
                "ip" => $request->ip(),
            ]);

            return [
                "success" => false,
                "error" => $this->emailRequired() && $email === ""
                    ? "Enter your developer email and password."
                    : "Incorrect developer credentials. Please try again.",
                "retry_after" => max(0, $this->limiter->availableIn($rateLimitKey)),
            ];
        }

        if ($this->totpEnabled($account) && !$this->verifyTotp($account, $totpCode)) {
            $attempts = $this->limiter->hit($rateLimitKey, $attemptWindowSeconds);

            if ($attempts >= $maxAttempts) {
                cache()->put($this->blockKey($rateLimitKey), time() + $lockoutSeconds, $lockoutSeconds);
                $this->limiter->clear($rateLimitKey);
            }

            Logger::write("warning", "Developer two-factor challenge failed", [
                "event" => "developer_access_totp_denied",
                "email" => $account["email"],
                "ip" => $request->ip(),
            ]);

            return [
                "success" => false,
                "error" => "Enter a valid six-digit authenticator code for this developer account.",
                "retry_after" => max(0, $this->limiter->availableIn($rateLimitKey)),
            ];
        }

        $this->limiter->clear($rateLimitKey);
        cache()->forget($this->blockKey($rateLimitKey));
        $this->grantAccess($account);
        Logger::write("notice", "Developer session unlocked", [
            "event" => "developer_session_unlocked",
            "email" => $account["email"],
            "ip" => $request->ip(),
        ]);

        return [
            "success" => true,
            "error" => "",
            "retry_after" => 0,
        ];
    }

    public function grantAccess(?array $developer = null): void
    {
        if (!$this->configured()) {
            return;
        }

        $developer ??= $this->accounts()[0] ?? null;

        if (!is_array($developer)) {
            return;
        }

        $this->session->regenerate();
        $this->session->put($this->sessionKey(), true);
        $this->session->put($this->identityKey(), $this->publicAccount($developer));
        $unlockedAt = time();
        $this->session->put($this->unlockedAtKey(), $unlockedAt);
        $this->session->put($this->expiresAtKey(), min(
            $unlockedAt + $this->unlockTtlSeconds(),
            $unlockedAt + $this->absoluteTtlSeconds()
        ));
        $this->session->put($this->credentialFingerprintKey(), $this->credentialFingerprint());
    }

    public function extendAccess(): bool
    {
        if (!$this->isUnlocked()) {
            return false;
        }

        $unlockedAt = (int) $this->session->get($this->unlockedAtKey(), 0);

        if ($unlockedAt <= 0) {
            return false;
        }

        $this->session->put($this->expiresAtKey(), min(
            time() + $this->unlockTtlSeconds(),
            $unlockedAt + $this->absoluteTtlSeconds()
        ));
        $this->session->put($this->credentialFingerprintKey(), $this->credentialFingerprint());

        return true;
    }

    public function lock(): void
    {
        $this->session->forget($this->sessionKey());
        $this->session->forget($this->unlockedAtKey());
        $this->session->forget($this->expiresAtKey());
        $this->session->forget($this->credentialFingerprintKey());
        $this->session->forget($this->identityKey());
        $this->session->regenerate();
    }

    public function viewState(): array
    {
        return [
            "enabled" => $this->enabled(),
            "configured" => $this->configured(),
            "path" => $this->path(),
            "unlocked" => $this->isUnlocked(),
            "expires_at" => $this->expiresAt(),
            "seconds_remaining" => $this->secondsRemaining(),
            "unlock_ttl_minutes" => max(1, (int) config("developer_access.unlock_ttl_minutes", 120)),
            "absolute_ttl_minutes" => max(1, (int) config("developer_access.absolute_ttl_minutes", 480)),
            "operations_nav_mode" => $this->operationsNavMode(),
            "operations_nav_visible" => $this->operationsNavVisible(),
            "email_required" => $this->emailRequired(),
            "multi_developer_enabled" => count($this->accounts()) > 1,
            "users_count" => count($this->accounts()),
            "current_developer" => $this->currentDeveloper(),
            "accounts" => array_map(fn (array $account): array => $this->publicAccount($account), $this->accounts()),
            "role_options" => $this->roleOptions(),
            "capability_options" => $this->capabilityCatalog(),
            "current_capabilities" => $this->capabilitiesFor($this->currentDeveloper()),
            "security" => $this->securityState($this->currentDeveloper()),
        ];
    }

    public function currentDeveloper(): array
    {
        $identity = $this->session->get($this->identityKey(), []);

        if (is_array($identity) && ($identity["email"] ?? "") !== "") {
            return [
                "email" => (string) ($identity["email"] ?? ""),
                "name" => (string) ($identity["name"] ?? "Developer"),
                "role" => (string) ($identity["role"] ?? "admin"),
                "role_label" => $this->roleLabel((string) ($identity["role"] ?? "admin")),
                "avatar" => (string) ($identity["avatar"] ?? ""),
                "security" => is_array($identity["security"] ?? null) ? $identity["security"] : [],
            ];
        }

        return $this->publicAccount($this->accounts()[0] ?? [
            "email" => "legacy-developer",
            "name" => "Developer",
            "role" => "admin",
            "avatar" => "",
            "password_hash" => "",
        ]);
    }

    public function accounts(): array
    {
        $accounts = $this->parseConfiguredAccounts((string) config("developer_access.users", ""));

        if ($accounts !== []) {
            return $accounts;
        }

        $password = $this->configuredPassword();

        if ($password === "") {
            return [];
        }

        return [[
            "email" => $this->normaliseEmail((string) config("developer_access.email", "")),
            "name" => "Developer",
            "role" => "admin",
            "password_hash" => $password,
        ]];
    }

    public function serializeAccounts(array $accounts): string
    {
        $encoded = [];

        foreach ($accounts as $account) {
            $email = $this->normaliseEmail((string) ($account["email"] ?? ""));
            $hash = trim((string) ($account["password_hash"] ?? ""));

            if ($email === "" || $hash === "") {
                continue;
            }

            $encoded[] = implode("|", [
                $email,
                $this->encodeAccountSegment((string) ($account["name"] ?? "Developer")),
                $this->encodeAccountSegment((string) ($account["role"] ?? "admin")),
                $this->encodeAccountSegment((string) ($account["avatar"] ?? "")),
                $hash,
                $this->encodeAccountSegment(json_encode($this->normaliseSecurity((array) ($account["security"] ?? [])), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)),
            ]);
        }

        return implode(";", $encoded);
    }

    public function upsertAccount(array $account): array
    {
        $email = $this->normaliseEmail((string) ($account["email"] ?? ""));
        $hash = trim((string) ($account["password_hash"] ?? ""));

        if ($email === "" || $hash === "") {
            return $this->accounts();
        }

        $accounts = $this->accounts();
        $updated = false;

        foreach ($accounts as $index => $existing) {
            if (($existing["email"] ?? "") !== $email) {
                continue;
            }

            $accounts[$index] = [
                "email" => $email,
                "name" => $this->normaliseName((string) ($account["name"] ?? $existing["name"] ?? "Developer")),
                "role" => $this->normaliseRole((string) ($account["role"] ?? $existing["role"] ?? "admin")),
                "avatar" => $this->normaliseAvatar((string) ($account["avatar"] ?? $existing["avatar"] ?? "")),
                "security" => $this->normaliseSecurity((array) ($account["security"] ?? $existing["security"] ?? [])),
                "password_hash" => $hash,
            ];
            $updated = true;
            break;
        }

        if (!$updated) {
            $accounts[] = [
                "email" => $email,
                "name" => $this->normaliseName((string) ($account["name"] ?? "Developer")),
                "role" => $this->normaliseRole((string) ($account["role"] ?? "admin")),
                "avatar" => $this->normaliseAvatar((string) ($account["avatar"] ?? "")),
                "security" => $this->normaliseSecurity((array) ($account["security"] ?? [])),
                "password_hash" => $hash,
            ];
        }

        return $this->ensureLeadDeveloper($accounts, $this->normaliseRole((string) ($account["role"] ?? "")) === "lead_developer" ? $email : "");
    }

    public function updateAccountProfile(string $email, array $profile): array
    {
        $email = $this->normaliseEmail($email);

        if ($email === "") {
            return $this->accounts();
        }

        $accounts = $this->accounts();

        foreach ($accounts as $index => $existing) {
            if (($existing["email"] ?? "") !== $email) {
                continue;
            }

            $accounts[$index] = [
                "email" => $email,
                "name" => $this->normaliseName((string) ($profile["name"] ?? $existing["name"] ?? "Developer")),
                "role" => $this->normaliseRole((string) ($existing["role"] ?? "application_developer")),
                "avatar" => $this->normaliseAvatar((string) ($profile["avatar"] ?? $existing["avatar"] ?? "")),
                "security" => $this->normaliseSecurity((array) ($existing["security"] ?? [])),
                "password_hash" => (string) ($existing["password_hash"] ?? ""),
            ];

            return $accounts;
        }

        return $accounts;
    }

    public function removeAccount(string $email): array
    {
        $email = $this->normaliseEmail($email);

        if ($email === "") {
            return $this->accounts();
        }

        return $this->ensureLeadDeveloper(array_values(array_filter(
            $this->accounts(),
            static fn (array $account): bool => ($account["email"] ?? "") !== $email
        )));
    }

    public function roleOptions(): array
    {
        return self::PUBLIC_ROLE_LABELS;
    }

    public function updateAccountSecurity(string $email, array $security): array
    {
        $email = $this->normaliseEmail($email);
        $accounts = $this->accounts();

        foreach ($accounts as $index => $existing) {
            if (($existing["email"] ?? "") !== $email) {
                continue;
            }

            $accounts[$index]["security"] = $this->normaliseSecurity(array_merge((array) ($existing["security"] ?? []), $security));

            return $accounts;
        }

        return $accounts;
    }

    public function securityState(array $developer): array
    {
        $account = $this->accountByEmail((string) ($developer["email"] ?? "")) ?? $developer;
        $security = $this->normaliseSecurity((array) ($account["security"] ?? []));
        $secret = (string) ($security["totp_secret"] ?? "");

        return [
            "schema" => "fnlla.developer_security.v1",
            "totp_configured" => $secret !== "",
            "totp_enabled" => (bool) ($security["totp_enabled"] ?? false),
            "totp_secret" => $secret,
            "totp_uri" => $secret !== "" ? $this->totpProvisioningUri($account, $secret) : "",
            "passkey_ready" => trim((string) ($security["passkey_label"] ?? "")) !== "",
            "passkey_label" => (string) ($security["passkey_label"] ?? ""),
            "passkey_adapter" => (string) ($security["passkey_adapter"] ?? "disabled"),
        ];
    }

    public function newTotpSecret(): string
    {
        $alphabet = "ABCDEFGHIJKLMNOPQRSTUVWXYZ234567";
        $secret = "";

        for ($i = 0; $i < 32; $i++) {
            $secret .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $secret;
    }

    public function verifyTotpForEmail(string $email, string $code): bool
    {
        $account = $this->accountByEmail($email);

        return is_array($account) && $this->verifyTotp($account, $code);
    }

    public function capabilitiesFor(array|string $developerOrRole): array
    {
        $role = is_array($developerOrRole)
            ? (string) ($developerOrRole["role"] ?? "application_developer")
            : $developerOrRole;
        $role = $this->normaliseRole($role);
        $capabilities = self::ROLE_CAPABILITIES[$role] ?? self::ROLE_CAPABILITIES["application_developer"];

        if (in_array("*", $capabilities, true)) {
            return array_keys($this->capabilityCatalog());
        }

        return array_values(array_intersect($capabilities, array_keys($this->capabilityCatalog())));
    }

    public function can(string $capability, ?array $developer = null): bool
    {
        return in_array($capability, $this->capabilitiesFor($developer ?? $this->currentDeveloper()), true);
    }

    public function capabilityCatalog(): array
    {
        return [
            "panel.view" => "Open the private developer panel.",
            "project.identity.write" => "Change project name, public URL and browser-title slogan.",
            "preview.manage" => "Change maintenance and client-preview credentials.",
            "service_control.write" => "Disable or reopen the public project surface.",
            "developer.accounts.write" => "Create, update and rotate named developer accounts.",
            "developer.profile.write" => "Update the current developer profile, password and avatar.",
            "developer.security.manage" => "Enable TOTP, register passkey-adapter metadata and manage own developer security.",
            "panel.settings.write" => "Change developer-panel runtime and navigation settings.",
            "workspace.write" => "Create, move and remove technical workspace tasks.",
            "operations.view" => "View privacy-light analytics, health, release readiness and integrations.",
            "audit.export" => "Export developer-panel audit events.",
            "framework.update" => "Run framework update checks and apply audited framework updates.",
            "policy.view" => "View the framework-managed versus project-owned boundary.",
        ];
    }

    private function matchingAccount(string $email, string $password): ?array
    {
        if (trim($password) === "") {
            return null;
        }

        foreach ($this->accounts() as $account) {
            $accountEmail = (string) ($account["email"] ?? "");

            if ($this->emailRequired() && ($email === "" || !hash_equals($accountEmail, $email))) {
                continue;
            }

            if (!$this->emailRequired() && $email !== "" && $accountEmail !== "" && !hash_equals($accountEmail, $email)) {
                continue;
            }

            if ($this->safeEquals((string) ($account["password_hash"] ?? ""), trim($password))) {
                return $account;
            }
        }

        return null;
    }

    private function parseConfiguredAccounts(string $value): array
    {
        $accounts = [];

        foreach (array_filter(array_map("trim", explode(";", $value))) as $entry) {
            $rawParts = explode("|", $entry);
            $parts = count($rawParts) >= 5
                ? array_pad(array_slice($rawParts, 0, 6), 6, "")
                : array_pad(array_slice($rawParts, 0, 4), 6, "");
            $email = $this->normaliseEmail((string) $parts[0]);
            $hash = trim((string) (count($rawParts) >= 5 ? $parts[4] : $parts[3]));

            if ($email === "" || $hash === "") {
                continue;
            }

            $accounts[] = [
                "email" => $email,
                "name" => $this->decodeAccountSegment((string) $parts[1]) ?: "Developer",
                "role" => $this->normaliseRole($this->decodeAccountSegment((string) $parts[2]) ?: "admin"),
                "avatar" => count($rawParts) >= 5 ? $this->normaliseAvatar($this->decodeAccountSegment((string) $parts[3])) : "",
                "security" => count($rawParts) >= 6 ? $this->decodeSecurity((string) $parts[5]) : [],
                "password_hash" => $hash,
            ];
        }

        return $accounts;
    }

    private function emailRequired(): bool
    {
        $accounts = $this->accounts();

        return count($accounts) > 1 || (string) ($accounts[0]["email"] ?? "") !== "";
    }

    private function configuredPassword(): string
    {
        return trim((string) config("developer_access.password_hash", "")) !== ""
            ? trim((string) config("developer_access.password_hash", ""))
            : trim((string) config("developer_access.password", ""));
    }

    private function safeEquals(string $knownValue, string $providedValue): bool
    {
        if ($knownValue === "" || $providedValue === "") {
            return false;
        }

        if ((int) (password_get_info($knownValue)["algo"] ?? 0) > 0) {
            return password_verify($providedValue, $knownValue);
        }

        return hash_equals($knownValue, $providedValue);
    }

    private function accountByEmail(string $email): ?array
    {
        $email = $this->normaliseEmail($email);

        if ($email === "") {
            return null;
        }

        foreach ($this->accounts() as $account) {
            if (($account["email"] ?? "") === $email) {
                return $account;
            }
        }

        return null;
    }

    private function totpEnabled(array $account): bool
    {
        $security = $this->normaliseSecurity((array) ($account["security"] ?? []));

        return (bool) ($security["totp_enabled"] ?? false) && (string) ($security["totp_secret"] ?? "") !== "";
    }

    private function verifyTotp(array $account, string $code): bool
    {
        $security = $this->normaliseSecurity((array) ($account["security"] ?? []));
        $secret = (string) ($security["totp_secret"] ?? "");
        $code = preg_replace('/\D+/', "", $code) ?? "";

        if ($secret === "" || strlen($code) !== 6) {
            return false;
        }

        $counter = (int) floor(time() / 30);

        for ($offset = -1; $offset <= 1; $offset++) {
            if (hash_equals($this->totpCode($secret, $counter + $offset), $code)) {
                return true;
            }
        }

        return false;
    }

    private function totpCode(string $secret, int $counter): string
    {
        $key = $this->base32Decode($secret);
        $time = pack("N*", 0) . pack("N*", $counter);
        $hash = hash_hmac("sha1", $time, $key, true);
        $offset = ord(substr($hash, -1)) & 0x0F;
        $binary = ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF);

        return str_pad((string) ($binary % 1000000), 6, "0", STR_PAD_LEFT);
    }

    private function base32Decode(string $secret): string
    {
        $alphabet = "ABCDEFGHIJKLMNOPQRSTUVWXYZ234567";
        $secret = strtoupper(preg_replace('/[^A-Z2-7]/', "", $secret) ?? "");
        $bits = "";

        foreach (str_split($secret) as $char) {
            $value = strpos($alphabet, $char);

            if ($value === false) {
                continue;
            }

            $bits .= str_pad(decbin($value), 5, "0", STR_PAD_LEFT);
        }

        $bytes = "";

        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $bytes .= chr(bindec($byte));
            }
        }

        return $bytes;
    }

    private function totpProvisioningUri(array $account, string $secret): string
    {
        $issuer = trim((string) config("developer_access.totp_issuer", config("app.name", "FNLLA")));
        $label = rawurlencode($issuer . ":" . (string) ($account["email"] ?? "developer"));

        return "otpauth://totp/{$label}?secret={$secret}&issuer=" . rawurlencode($issuer) . "&algorithm=SHA1&digits=6&period=30";
    }

    private function normaliseSecurity(array $security): array
    {
        $secret = strtoupper(preg_replace('/[^A-Z2-7]/', "", (string) ($security["totp_secret"] ?? "")) ?? "");
        $adapter = (string) ($security["passkey_adapter"] ?? "disabled");
        $adapter = in_array($adapter, ["disabled", "external"], true) ? $adapter : "disabled";

        return [
            "totp_secret" => substr($secret, 0, 64),
            "totp_enabled" => (bool) ($security["totp_enabled"] ?? false),
            "passkey_label" => $adapter === "external" ? $this->normaliseOptionalText((string) ($security["passkey_label"] ?? ""), 100) : "",
            "passkey_adapter" => $adapter,
        ];
    }

    private function publicSecurity(array $security): array
    {
        $security = $this->normaliseSecurity($security);

        return [
            "totp_configured" => (string) ($security["totp_secret"] ?? "") !== "",
            "totp_enabled" => (bool) ($security["totp_enabled"] ?? false),
            "passkey_ready" => trim((string) ($security["passkey_label"] ?? "")) !== "",
            "passkey_label" => (string) ($security["passkey_label"] ?? ""),
            "passkey_adapter" => (string) ($security["passkey_adapter"] ?? "disabled"),
        ];
    }

    private function decodeSecurity(string $value): array
    {
        $decoded = json_decode($this->decodeAccountSegment($value), true);

        return is_array($decoded) ? $this->normaliseSecurity($decoded) : [];
    }

    private function rateLimitKey(Request $request, string $email = ""): string
    {
        return "developer-access:" . sha1($request->ip() . "|" . $email);
    }

    private function blockKey(string $rateLimitKey): string
    {
        return $rateLimitKey . ":blocked-until";
    }

    private function sessionKey(): string
    {
        return (string) config("developer_access.session_key", "developer.access_unlocked");
    }

    private function unlockedAtKey(): string
    {
        return (string) config("developer_access.unlocked_at_key", "developer.access_unlocked_at");
    }

    private function expiresAtKey(): string
    {
        return (string) config("developer_access.expires_at_key", "developer.access_expires_at");
    }

    private function credentialFingerprintKey(): string
    {
        return (string) config("developer_access.credential_fingerprint_key", "developer.access_credential_fingerprint");
    }

    private function identityKey(): string
    {
        return (string) config("developer_access.identity_key", "developer.identity");
    }

    private function credentialFingerprint(): string
    {
        return hash("sha256", json_encode($this->accounts(), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    private function unlockTtlSeconds(): int
    {
        return max(1, (int) config("developer_access.unlock_ttl_minutes", 120)) * 60;
    }

    private function absoluteTtlSeconds(): int
    {
        return max(1, (int) config("developer_access.absolute_ttl_minutes", 480)) * 60;
    }

    private function publicAccount(array $account): array
    {
        return [
            "email" => (string) ($account["email"] ?? ""),
            "name" => (string) ($account["name"] ?? "Developer"),
            "role" => (string) ($account["role"] ?? "admin"),
            "role_label" => $this->roleLabel((string) ($account["role"] ?? "admin")),
            "avatar" => (string) ($account["avatar"] ?? ""),
            "security" => $this->publicSecurity((array) ($account["security"] ?? [])),
        ];
    }

    private function normaliseEmail(string $email): string
    {
        return strtolower(trim($email));
    }

    private function normaliseName(string $name): string
    {
        $name = trim((string) preg_replace('/\s+/', ' ', $name));

        return $name !== "" ? substr($name, 0, 100) : "Developer";
    }

    private function normaliseRole(string $role): string
    {
        $role = strtolower(trim($role));
        $role = str_replace([" ", "-"], "_", $role);

        return array_key_exists($role, self::ROLE_LABELS) ? $role : "application_developer";
    }

    private function roleLabel(string $role): string
    {
        $role = $this->normaliseRole($role);

        if (in_array($role, ["owner_developer", "admin"], true)) {
            return self::PUBLIC_ROLE_LABELS["lead_developer"];
        }

        if (in_array($role, ["developer", "support_developer", "client"], true)) {
            return self::PUBLIC_ROLE_LABELS["application_developer"];
        }

        return self::ROLE_LABELS[$role] ?? "Developer";
    }

    private function ensureLeadDeveloper(array $accounts, string $preferredLeadEmail = ""): array
    {
        $accounts = array_values(array_filter($accounts, static fn ($account): bool => is_array($account)));
        $preferredLeadEmail = $this->normaliseEmail($preferredLeadEmail);

        if ($accounts === []) {
            return [];
        }

        if ($preferredLeadEmail !== "") {
            foreach ($accounts as $index => $account) {
                $accounts[$index]["role"] = $this->normaliseEmail((string) ($account["email"] ?? "")) === $preferredLeadEmail
                    ? "lead_developer"
                    : "application_developer";
            }

            return $accounts;
        }

        foreach ($accounts as $account) {
            if ($this->normaliseRole((string) ($account["role"] ?? "")) === "lead_developer") {
                return $accounts;
            }
        }

        $accounts[0]["role"] = "lead_developer";

        return $accounts;
    }

    private function normaliseAvatar(string $avatar): string
    {
        $avatar = trim((string) preg_replace('/\s+/', ' ', $avatar));

        if ($avatar === "") {
            return "";
        }

        if (filter_var($avatar, FILTER_VALIDATE_URL) !== false) {
            $scheme = strtolower((string) parse_url($avatar, PHP_URL_SCHEME));

            return in_array($scheme, ["https", "http"], true) ? substr($avatar, 0, 2048) : "";
        }

        if (str_starts_with($avatar, "/uploads/developer-avatars/")) {
            return substr($avatar, 0, 2048);
        }

        return substr($avatar, 0, 16);
    }

    private function normaliseOptionalText(string $value, int $maxLength): string
    {
        $value = trim((string) preg_replace('/\s+/', ' ', $value));

        return strlen($value) <= $maxLength ? $value : substr($value, 0, $maxLength);
    }

    private function encodeAccountSegment(string $value): string
    {
        $value = trim((string) preg_replace('/\s+/', ' ', $value));

        return rtrim(strtr(base64_encode($value), "+/", "-_"), "=");
    }

    private function decodeAccountSegment(string $value): string
    {
        $decoded = base64_decode(strtr($value, "-_", "+/"), true);

        return is_string($decoded) ? trim((string) preg_replace('/\s+/', ' ', $decoded)) : "";
    }

    private function formatRetryAfter(int $retryAfter): string
    {
        $minutes = (int) ceil(max(1, $retryAfter) / 60);

        if ($minutes <= 1) {
            return "Please wait about 1 minute and try again.";
        }

        return "Please wait about {$minutes} minutes and try again.";
    }
}
