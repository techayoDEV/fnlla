<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA MAINTENANCE SOURCE
File: src\Maintenance\CustomerAccessManager.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

Purpose:
- Provides a private customer-facing access channel for project progress,
  preview, analytics and heatmap summaries without exposing developer controls.
*/

namespace Fnlla\Php\Maintenance;

use Fnlla\Php\Cache\RateLimiter;
use Fnlla\Php\Http\Request;
use Fnlla\Php\Session\SessionStore;
use Fnlla\Php\Support\Logger;

final class CustomerAccessManager
{
    private const PERMISSION_LABELS = [
        "kanban" => "Project Kanban",
        "analytics" => "Analytics",
        "heatmap" => "Heatmap",
        "preview" => "Public preview",
    ];

    public function __construct(
        private SessionStore $session,
        private RateLimiter $limiter
    ) {
    }

    public function enabled(): bool
    {
        return (bool) config("customer_access.enabled", true);
    }

    public function configured(): bool
    {
        return $this->accounts() !== [];
    }

    public function path(): string
    {
        $path = $this->normalisePath((string) config("customer_access.path", "/client"));

        return $this->pathAllowed($path) ? $path : "/client";
    }

    public function normalisePath(string $path): string
    {
        $path = strtolower(trim($path));
        $path = "/" . trim($path, "/ \t\n\r\0\x0B");
        $path = (string) preg_replace('/\/+/', "/", $path);

        return $path === "/" ? "/client" : $path;
    }

    public function pathAllowed(string $path): bool
    {
        $path = $this->normalisePath($path);

        if (preg_match('/^\/[a-z0-9][a-z0-9-]{2,48}(?:\/[a-z0-9][a-z0-9-]{2,48})?$/', $path) !== 1) {
            return false;
        }

        $firstSegment = explode("/", trim($path, "/"))[0] ?? "";

        return !in_array($firstSegment, [
            "api",
            "assets",
            "contact",
            "developer",
            "docs",
            "fnlla",
            "health",
            "maintenance",
            "privacy",
            "services",
            "terms",
            "vendor",
        ], true);
    }

    public function unlock(Request $request, string $email, string $password): array
    {
        if (!$this->enabled() || !$this->configured()) {
            return [
                "success" => false,
                "error" => "Customer access is not configured for this project yet.",
                "retry_after" => 0,
            ];
        }

        $email = $this->normaliseEmail($email);
        $rateLimitKey = $this->rateLimitKey($request, $email);
        $blockedUntil = (int) cache()->get($this->blockKey($rateLimitKey), 0);
        $maxAttempts = max(1, (int) config("customer_access.max_attempts", 5));
        $lockoutSeconds = max(1, (int) config("customer_access.lockout_minutes", 15)) * 60;
        $attemptWindowSeconds = max(1, (int) config("customer_access.attempt_window_minutes", 15)) * 60;

        if ($blockedUntil > time()) {
            $retryAfter = max(0, $blockedUntil - time());

            return [
                "success" => false,
                "error" => "Too many customer access attempts. " . $this->formatRetryAfter($retryAfter),
                "retry_after" => $retryAfter,
            ];
        }

        $account = $this->matchingAccount($email, $password);

        if ($account === null) {
            $attempts = $this->limiter->hit($rateLimitKey, $attemptWindowSeconds);

            if ($attempts >= $maxAttempts) {
                cache()->put($this->blockKey($rateLimitKey), time() + $lockoutSeconds, $lockoutSeconds);
                $this->limiter->clear($rateLimitKey);
                Logger::write("warning", "Customer access locked out", [
                    "event" => "customer_access_lockout",
                    "email" => $email,
                    "ip" => $request->ip(),
                ]);

                return [
                    "success" => false,
                    "error" => "Too many customer access attempts. " . $this->formatRetryAfter($lockoutSeconds),
                    "retry_after" => $lockoutSeconds,
                ];
            }

            Logger::write("warning", "Customer access denied", [
                "event" => "customer_access_denied",
                "email" => $email,
                "ip" => $request->ip(),
            ]);

            return [
                "success" => false,
                "error" => $email === ""
                    ? "Enter your customer email and password."
                    : "Incorrect customer credentials. Please try again.",
                "retry_after" => max(0, $this->limiter->availableIn($rateLimitKey)),
            ];
        }

        $this->limiter->clear($rateLimitKey);
        cache()->forget($this->blockKey($rateLimitKey));
        $this->grantAccess($account);
        Logger::write("notice", "Customer session unlocked", [
            "event" => "customer_session_unlocked",
            "email" => $account["email"],
            "ip" => $request->ip(),
        ]);

        return [
            "success" => true,
            "error" => "",
            "retry_after" => 0,
        ];
    }

    public function grantAccess(?array $customer = null): void
    {
        if (!$this->configured()) {
            return;
        }

        $customer ??= $this->accounts()[0] ?? null;

        if (!is_array($customer) || trim((string) ($customer["email"] ?? "")) === "") {
            return;
        }

        $this->session->regenerate();
        $this->session->put($this->sessionKey(), true);
        $this->session->put($this->identityKey(), $this->publicAccount($customer));
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

    public function currentCustomer(): array
    {
        $identity = $this->session->get($this->identityKey(), []);

        if (is_array($identity) && ($identity["email"] ?? "") !== "") {
            return [
                "email" => (string) ($identity["email"] ?? ""),
                "name" => (string) ($identity["name"] ?? "Customer"),
                "company" => (string) ($identity["company"] ?? ""),
                "permissions" => $this->normalisePermissions((array) ($identity["permissions"] ?? [])),
            ];
        }

        return $this->publicAccount($this->accounts()[0] ?? [
            "email" => "",
            "name" => "Customer",
            "company" => "",
            "permissions" => array_keys(self::PERMISSION_LABELS),
            "password_hash" => "",
            "invite" => [],
        ]);
    }

    public function can(string $permission, ?array $customer = null): bool
    {
        $permission = strtolower(trim($permission));

        return in_array($permission, $this->normalisePermissions((array) (($customer ?? $this->currentCustomer())["permissions"] ?? []), false), true);
    }

    public function viewState(): array
    {
        $accounts = $this->accounts();

        return [
            "schema" => "fnlla.customer_access.v1",
            "enabled" => $this->enabled(),
            "configured" => $this->configured(),
            "path" => $this->path(),
            "unlocked" => $this->isUnlocked(),
            "expires_at" => $this->expiresAt(),
            "seconds_remaining" => $this->secondsRemaining(),
            "unlock_ttl_minutes" => max(1, (int) config("customer_access.unlock_ttl_minutes", 240)),
            "absolute_ttl_minutes" => max(1, (int) config("customer_access.absolute_ttl_minutes", 720)),
            "invite_ttl_hours" => max(1, (int) config("customer_access.invite_ttl_hours", 72)),
            "users_count" => count($accounts),
            "pending_invites_count" => count(array_filter($accounts, static fn (array $account): bool => (bool) ($account["invite_pending"] ?? false))),
            "current_customer" => $this->currentCustomer(),
            "accounts" => array_map(fn (array $account): array => $this->publicAccount($account), $accounts),
            "permission_options" => self::PERMISSION_LABELS,
        ];
    }

    public function accounts(): array
    {
        return $this->parseConfiguredAccounts((string) config("customer_access.users", ""));
    }

    public function createInvitation(array $account): array
    {
        $token = bin2hex(random_bytes(32));
        $expiresAt = gmdate(DATE_ATOM, time() + (max(1, (int) config("customer_access.invite_ttl_hours", 72)) * 3600));
        $account["invite"] = [
            "token_hash" => hash("sha256", $token),
            "expires_at_utc" => $expiresAt,
            "created_at_utc" => gmdate(DATE_ATOM),
        ];
        $account["password_hash"] = trim((string) ($account["password_hash"] ?? ""));
        $accounts = $this->upsertAccount($account);
        $savedAccount = $this->accountByEmail($accounts, (string) ($account["email"] ?? "")) ?? $this->publicAccount($account);
        $invitePath = route("customer.invite") . "?token=" . rawurlencode($token);

        return [
            "accounts" => $accounts,
            "account" => $savedAccount,
            "token" => $token,
            "url" => url($invitePath),
            "expires_at_utc" => $expiresAt,
        ];
    }

    public function invitationForToken(string $token): ?array
    {
        $tokenHash = hash("sha256", trim($token));

        foreach ($this->accounts() as $account) {
            $invite = (array) ($account["invite"] ?? []);
            $knownHash = (string) ($invite["token_hash"] ?? "");
            $expiresAt = strtotime((string) ($invite["expires_at_utc"] ?? ""));

            if ($knownHash === "" || !hash_equals($knownHash, $tokenHash)) {
                continue;
            }

            if ($expiresAt === false || $expiresAt <= time()) {
                return null;
            }

            return [
                "account" => $this->publicAccount($account),
                "expires_at_utc" => (string) ($invite["expires_at_utc"] ?? ""),
            ];
        }

        return null;
    }

    public function consumeInvitation(string $token, string $password): array
    {
        $tokenHash = hash("sha256", trim($token));
        $accounts = $this->accounts();

        foreach ($accounts as $index => $account) {
            $invite = (array) ($account["invite"] ?? []);
            $knownHash = (string) ($invite["token_hash"] ?? "");
            $expiresAt = strtotime((string) ($invite["expires_at_utc"] ?? ""));

            if ($knownHash === "" || !hash_equals($knownHash, $tokenHash)) {
                continue;
            }

            if ($expiresAt === false || $expiresAt <= time()) {
                return [
                    "success" => false,
                    "error" => "This customer invitation has expired. Ask the project team for a new invitation.",
                    "accounts" => $accounts,
                    "account" => null,
                ];
            }

            $accounts[$index]["password_hash"] = password_hash($password, PASSWORD_DEFAULT);
            $accounts[$index]["invite"] = [];

            return [
                "success" => true,
                "error" => "",
                "accounts" => $accounts,
                "account" => $this->publicAccount($accounts[$index]),
            ];
        }

        return [
            "success" => false,
            "error" => "This customer invitation link is not valid.",
            "accounts" => $accounts,
            "account" => null,
        ];
    }

    public function upsertAccount(array $account): array
    {
        $email = $this->normaliseEmail((string) ($account["email"] ?? ""));

        if ($email === "") {
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
                "name" => $this->normaliseName((string) ($account["name"] ?? $existing["name"] ?? "Customer")),
                "company" => $this->normaliseOptionalText((string) ($account["company"] ?? $existing["company"] ?? ""), 120),
                "permissions" => $this->normalisePermissions((array) ($account["permissions"] ?? $existing["permissions"] ?? []), false),
                "password_hash" => trim((string) ($account["password_hash"] ?? $existing["password_hash"] ?? "")),
                "invite" => $this->normaliseInvite((array) ($account["invite"] ?? $existing["invite"] ?? [])),
            ];
            $updated = true;
            break;
        }

        if (!$updated) {
            $accounts[] = [
                "email" => $email,
                "name" => $this->normaliseName((string) ($account["name"] ?? "Customer")),
                "company" => $this->normaliseOptionalText((string) ($account["company"] ?? ""), 120),
                "permissions" => $this->normalisePermissions((array) ($account["permissions"] ?? []), false),
                "password_hash" => trim((string) ($account["password_hash"] ?? "")),
                "invite" => $this->normaliseInvite((array) ($account["invite"] ?? [])),
            ];
        }

        return array_values($accounts);
    }

    public function removeAccount(string $email): array
    {
        $email = $this->normaliseEmail($email);

        if ($email === "") {
            return $this->accounts();
        }

        return array_values(array_filter(
            $this->accounts(),
            static fn (array $account): bool => ($account["email"] ?? "") !== $email
        ));
    }

    public function serializeAccounts(array $accounts): string
    {
        $encoded = [];

        foreach ($accounts as $account) {
            $email = $this->normaliseEmail((string) ($account["email"] ?? ""));

            if ($email === "") {
                continue;
            }

            $encoded[] = implode("|", [
                $email,
                $this->encodeAccountSegment($this->normaliseName((string) ($account["name"] ?? "Customer"))),
                $this->encodeAccountSegment($this->normaliseOptionalText((string) ($account["company"] ?? ""), 120)),
                $this->encodeAccountSegment(implode(",", $this->normalisePermissions((array) ($account["permissions"] ?? []), false))),
                trim((string) ($account["password_hash"] ?? "")),
                $this->encodeAccountSegment(json_encode($this->normaliseInvite((array) ($account["invite"] ?? [])), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)),
            ]);
        }

        return implode(";", $encoded);
    }

    public function permissionOptions(): array
    {
        return self::PERMISSION_LABELS;
    }

    private function parseConfiguredAccounts(string $value): array
    {
        $accounts = [];

        foreach (array_filter(array_map("trim", explode(";", $value))) as $entry) {
            $parts = array_pad(array_slice(explode("|", $entry), 0, 6), 6, "");
            $email = $this->normaliseEmail((string) $parts[0]);

            if ($email === "") {
                continue;
            }

            $account = [
                "email" => $email,
                "name" => $this->decodeAccountSegment((string) $parts[1]) ?: "Customer",
                "company" => $this->decodeAccountSegment((string) $parts[2]),
                "permissions" => $this->normalisePermissions(array_filter(array_map("trim", explode(",", $this->decodeAccountSegment((string) $parts[3])))), false),
                "password_hash" => trim((string) $parts[4]),
                "invite" => $this->decodeInvite((string) $parts[5]),
            ];

            $public = $this->publicAccount($account);
            $accounts[] = array_merge($account, [
                "invite_pending" => (bool) ($public["invite_pending"] ?? false),
                "invite_expires_at_utc" => (string) ($public["invite_expires_at_utc"] ?? ""),
            ]);
        }

        return $accounts;
    }

    private function matchingAccount(string $email, string $password): ?array
    {
        if ($email === "" || trim($password) === "") {
            return null;
        }

        foreach ($this->accounts() as $account) {
            $hash = trim((string) ($account["password_hash"] ?? ""));

            if (($account["email"] ?? "") !== $email || $hash === "") {
                continue;
            }

            if (password_verify(trim($password), $hash)) {
                return $account;
            }
        }

        return null;
    }

    private function accountByEmail(array $accounts, string $email): ?array
    {
        $email = $this->normaliseEmail($email);

        foreach ($accounts as $account) {
            if (($account["email"] ?? "") === $email) {
                return $account;
            }
        }

        return null;
    }

    private function publicAccount(array $account): array
    {
        $invite = $this->normaliseInvite((array) ($account["invite"] ?? []));
        $expiresAt = strtotime((string) ($invite["expires_at_utc"] ?? ""));

        return [
            "email" => (string) ($account["email"] ?? ""),
            "name" => (string) ($account["name"] ?? "Customer"),
            "company" => (string) ($account["company"] ?? ""),
            "permissions" => $this->normalisePermissions((array) ($account["permissions"] ?? []), false),
            "password_configured" => trim((string) ($account["password_hash"] ?? "")) !== "",
            "invite_pending" => (string) ($invite["token_hash"] ?? "") !== "" && ($expiresAt !== false && $expiresAt > time()),
            "invite_expires_at_utc" => (string) ($invite["expires_at_utc"] ?? ""),
            "invite_created_at_utc" => (string) ($invite["created_at_utc"] ?? ""),
        ];
    }

    private function normalisePermissions(array $permissions, bool $defaultToAll = false): array
    {
        $normalised = [];

        foreach ($permissions as $permission) {
            $permission = strtolower(trim((string) $permission));

            if (array_key_exists($permission, self::PERMISSION_LABELS)) {
                $normalised[] = $permission;
            }
        }

        $normalised = array_values(array_unique($normalised));

        return $normalised !== [] || !$defaultToAll ? $normalised : array_keys(self::PERMISSION_LABELS);
    }

    private function normaliseInvite(array $invite): array
    {
        $tokenHash = strtolower(trim((string) ($invite["token_hash"] ?? "")));

        if ($tokenHash === "" || preg_match('/^[a-f0-9]{64}$/', $tokenHash) !== 1) {
            return [];
        }

        return [
            "token_hash" => $tokenHash,
            "expires_at_utc" => $this->normaliseOptionalText((string) ($invite["expires_at_utc"] ?? ""), 80),
            "created_at_utc" => $this->normaliseOptionalText((string) ($invite["created_at_utc"] ?? ""), 80),
        ];
    }

    private function decodeInvite(string $value): array
    {
        $decoded = json_decode($this->decodeAccountSegment($value), true);

        return is_array($decoded) ? $this->normaliseInvite($decoded) : [];
    }

    private function normaliseEmail(string $email): string
    {
        $email = strtolower(trim($email));

        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false ? $email : "";
    }

    private function normaliseName(string $name): string
    {
        $name = trim((string) preg_replace('/\s+/', ' ', $name));

        return $name !== "" ? substr($name, 0, 100) : "Customer";
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

    private function credentialFingerprint(): string
    {
        return hash("sha256", json_encode($this->accounts(), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    private function rateLimitKey(Request $request, string $email = ""): string
    {
        return "customer-access:" . sha1($request->ip() . "|" . $email);
    }

    private function blockKey(string $rateLimitKey): string
    {
        return $rateLimitKey . ":blocked-until";
    }

    private function sessionKey(): string
    {
        return (string) config("customer_access.session_key", "customer.access_unlocked");
    }

    private function unlockedAtKey(): string
    {
        return (string) config("customer_access.unlocked_at_key", "customer.access_unlocked_at");
    }

    private function expiresAtKey(): string
    {
        return (string) config("customer_access.expires_at_key", "customer.access_expires_at");
    }

    private function credentialFingerprintKey(): string
    {
        return (string) config("customer_access.credential_fingerprint_key", "customer.access_credential_fingerprint");
    }

    private function identityKey(): string
    {
        return (string) config("customer_access.identity_key", "customer.identity");
    }

    private function unlockTtlSeconds(): int
    {
        return max(1, (int) config("customer_access.unlock_ttl_minutes", 240)) * 60;
    }

    private function absoluteTtlSeconds(): int
    {
        return max(1, (int) config("customer_access.absolute_ttl_minutes", 720)) * 60;
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
