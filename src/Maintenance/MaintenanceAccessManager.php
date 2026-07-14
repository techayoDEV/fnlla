<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA MAINTENANCE SOURCE
File: src\Maintenance\MaintenanceAccessManager.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Provides password-protected maintenance access with session unlocks and
  temporary lockouts for repeated failed attempts.
*/

namespace Fnlla\Php\Maintenance;

use Fnlla\Php\Cache\RateLimiter;
use Fnlla\Php\Http\Request;
use Fnlla\Php\Session\SessionStore;

final class MaintenanceAccessManager
{
    public function __construct(
        private SessionStore $session,
        private RateLimiter $limiter
    ) {
    }

    public function enabled(): bool
    {
        return (bool) config("maintenance.enabled", false);
    }

    public function configured(): bool
    {
        return trim($this->configuredPassword()) !== "";
    }

    public function usernameRequired(): bool
    {
        return trim($this->configuredUsername()) !== "";
    }

    public function isUnlocked(): bool
    {
        if (!$this->enabled()) {
            return true;
        }

        if ($this->clientPreviewUnlockDisabled()) {
            if ($this->session->get($this->sessionKey()) === true || (int) $this->session->get($this->expiresAtKey(), 0) > 0) {
                $this->lock();
            }

            return false;
        }

        if ($this->session->get($this->sessionKey()) !== true) {
            return false;
        }

        $expiresAt = (int) $this->session->get($this->expiresAtKey(), 0);

        if ($expiresAt <= time()) {
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

    public function unlock(Request $request, string $password, string $username = ""): array
    {
        if (!$this->enabled()) {
            return [
                "success" => false,
                "error" => "Maintenance mode is currently disabled.",
                "retry_after" => 0,
            ];
        }

        if ($this->clientPreviewUnlockDisabled()) {
            return [
                "success" => false,
                "error" => "Preview unlock is temporarily unavailable while the current client preview notice is active.",
                "retry_after" => 0,
            ];
        }

        if (!$this->configured()) {
            return [
                "success" => false,
                "error" => "Maintenance access is enabled, but no password is configured yet.",
                "retry_after" => 0,
            ];
        }

        $rateLimitKey = $this->rateLimitKey($request);
        $blockedUntil = (int) cache()->get($this->blockKey($rateLimitKey), 0);
        $maxAttempts = max(1, (int) config("maintenance.max_attempts", 5));
        $lockoutSeconds = max(1, (int) config("maintenance.lockout_minutes", 15)) * 60;
        $attemptWindowSeconds = max(1, (int) config("maintenance.attempt_window_minutes", 15)) * 60;

        if ($blockedUntil > time()) {
            $retryAfter = max(0, $blockedUntil - time());

            return [
                "success" => false,
                "error" => "Too many unlock attempts. " . $this->formatRetryAfter($retryAfter),
                "retry_after" => $retryAfter,
            ];
        }

        if (!$this->credentialsMatch($username, $password)) {
            $attempts = $this->limiter->hit($rateLimitKey, $attemptWindowSeconds);

            if ($attempts >= $maxAttempts) {
                cache()->put($this->blockKey($rateLimitKey), time() + $lockoutSeconds, $lockoutSeconds);
                $this->limiter->clear($rateLimitKey);

                return [
                    "success" => false,
                    "error" => "Too many unlock attempts. " . $this->formatRetryAfter($lockoutSeconds),
                    "retry_after" => $lockoutSeconds,
                ];
            }

            return [
                "success" => false,
                "error" => "Incorrect maintenance credentials. Please try again.",
                "retry_after" => max(0, $this->limiter->availableIn($rateLimitKey)),
            ];
        }

        $this->limiter->clear($rateLimitKey);
        cache()->forget($this->blockKey($rateLimitKey));
        $this->session->regenerate();
        $this->session->put($this->sessionKey(), true);
        $this->session->put($this->unlockedAtKey(), time());
        $this->session->put($this->expiresAtKey(), time() + $this->unlockTtlSeconds());

        return [
            "success" => true,
            "error" => "",
            "retry_after" => 0,
        ];
    }

    public function lock(): void
    {
        $this->session->forget($this->sessionKey());
        $this->session->forget($this->unlockedAtKey());
        $this->session->forget($this->expiresAtKey());
        $this->session->regenerate();
    }

    public function viewState(): array
    {
        return [
            "enabled" => $this->enabled(),
            "configured" => $this->configured(),
            "unlocked" => $this->isUnlocked(),
            "username_required" => $this->usernameRequired(),
            "expires_at" => $this->expiresAt(),
            "seconds_remaining" => $this->secondsRemaining(),
            "unlock_ttl_minutes" => max(1, (int) config("maintenance.unlock_ttl_minutes", 10)),
        ];
    }

    private function credentialsMatch(string $username, string $password): bool
    {
        $knownPassword = $this->configuredPassword();

        if ($knownPassword === "") {
            return false;
        }

        if (!$this->safeEquals($knownPassword, trim($password))) {
            return false;
        }

        $knownUsername = $this->configuredUsername();

        if ($knownUsername === "") {
            return true;
        }

        return $this->safeEquals($knownUsername, trim($username));
    }

    private function safeEquals(string $knownValue, string $providedValue): bool
    {
        return $knownValue !== ""
            && $providedValue !== ""
            && hash_equals($knownValue, $providedValue);
    }

    private function rateLimitKey(Request $request): string
    {
        return "maintenance-access:" . sha1($request->ip());
    }

    private function blockKey(string $rateLimitKey): string
    {
        return $rateLimitKey . ":blocked-until";
    }

    private function configuredUsername(): string
    {
        return trim((string) config("maintenance.username", ""));
    }

    private function configuredPassword(): string
    {
        return trim((string) config("maintenance.password", ""));
    }

    private function sessionKey(): string
    {
        return (string) config("maintenance.session_key", "maintenance.access_unlocked");
    }

    private function unlockedAtKey(): string
    {
        return (string) config("maintenance.unlocked_at_key", "maintenance.access_unlocked_at");
    }

    private function expiresAtKey(): string
    {
        return (string) config("maintenance.expires_at_key", "maintenance.access_expires_at");
    }

    private function unlockTtlSeconds(): int
    {
        return max(1, (int) config("maintenance.unlock_ttl_minutes", 10)) * 60;
    }

    private function clientPreviewUnlockDisabled(): bool
    {
        return $this->enabled()
            && (bool) config("client_preview.enabled", false)
            && (bool) config("client_preview.login_disabled", false);
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
