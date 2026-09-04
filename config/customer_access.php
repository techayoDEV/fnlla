<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA CONFIGURATION FILE
File: config\customer_access.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

Purpose:
- Defines the private customer portal used to share selected project delivery
  visibility without exposing the Developer Panel.
*/

return [
    "enabled" => (bool) env("CUSTOMER_ACCESS_ENABLED", true),
    "path" => trim((string) env("CUSTOMER_ACCESS_PATH", "/client")),
    "users" => trim((string) env("CUSTOMER_ACCESS_USERS", "")),
    "invite_ttl_hours" => max(1, (int) env("CUSTOMER_ACCESS_INVITE_TTL_HOURS", 72)),
    "unlock_ttl_minutes" => max(1, (int) env("CUSTOMER_ACCESS_TTL_MINUTES", 240)),
    "absolute_ttl_minutes" => max(1, (int) env("CUSTOMER_ACCESS_ABSOLUTE_TTL_MINUTES", 720)),
    "max_attempts" => max(1, (int) env("CUSTOMER_ACCESS_MAX_ATTEMPTS", 5)),
    "attempt_window_minutes" => max(1, (int) env("CUSTOMER_ACCESS_WINDOW_MINUTES", 15)),
    "lockout_minutes" => max(1, (int) env("CUSTOMER_ACCESS_LOCKOUT_MINUTES", 15)),
    "session_key" => "customer.access_unlocked",
    "unlocked_at_key" => "customer.access_unlocked_at",
    "expires_at_key" => "customer.access_expires_at",
    "credential_fingerprint_key" => "customer.access_credential_fingerprint",
    "identity_key" => "customer.identity",
];
