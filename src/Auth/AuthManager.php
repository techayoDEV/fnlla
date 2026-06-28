<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA PHP AUTHENTICATION SOURCE
File: src\Auth\AuthManager.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA PHP is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA PHP framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Implements authentication, authorization or access-control primitives for the framework.
*/

namespace Fnlla\Php\Auth;

use Fnlla\Php\Hashing\Hasher;
use Fnlla\Php\Session\SessionStore;

final class AuthManager
{
    public function __construct(
        private SessionStore $session,
        private UserProviderInterface $provider,
        private Hasher $hasher
    ) {
    }

    public function check(): bool
    {
        return $this->id() !== null;
    }

    public function guest(): bool
    {
        return !$this->check();
    }

    public function id(): string|int|null
    {
        return $this->session->get((string) config("auth.session_key", "auth.user_id"));
    }

    public function user(): ?array
    {
        $id = $this->id();

        return $id !== null ? $this->provider->findById($id) : null;
    }

    public function attempt(array $credentials): bool
    {
        $user = $this->provider->findByCredentials($credentials);
        $passwordField = (string) config("auth.providers.users.password", "password");

        if ($user === null || !isset($credentials["password"], $user[$passwordField])) {
            return false;
        }

        if (!$this->hasher->check((string) $credentials["password"], (string) $user[$passwordField])) {
            return false;
        }

        $this->login($user);

        return true;
    }

    public function login(array $user): void
    {
        $key = (string) config("auth.providers.users.key", "id");
        $this->session->put((string) config("auth.session_key", "auth.user_id"), $user[$key] ?? null);
        $this->session->regenerate();
    }

    public function logout(): void
    {
        $this->session->forget((string) config("auth.session_key", "auth.user_id"));
        $this->session->regenerate();
    }
}
