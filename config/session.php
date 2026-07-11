<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA CONFIGURATION FILE
File: config\session.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Defines maintained application or framework configuration for the official FNLLA stack.
*/

$environment = framework_detect_environment();
$isDevelopment = $environment === "development";
$sessionLifetimeMinutes = max(1, (int) env("SESSION_LIFETIME_MINUTES", 120));

return [
    "name" => (string) env("SESSION_NAME", "fnlla_session"),
    "lifetime_minutes" => $sessionLifetimeMinutes,
    "cookie_lifetime" => $sessionLifetimeMinutes * 60,
    "path" => (string) env("SESSION_PATH_SCOPE", "/"),
    "domain" => env("SESSION_DOMAIN"),
    "secure" => (bool) env("SESSION_SECURE", !$isDevelopment && app_request_is_secure()),
    "http_only" => (bool) env("SESSION_HTTP_ONLY", true),
    "same_site" => (string) env("SESSION_SAME_SITE", "Lax"),
    "strict_mode" => (bool) env("SESSION_STRICT_MODE", true),
    "use_only_cookies" => (bool) env("SESSION_USE_ONLY_COOKIES", true),
    "rotate_after_minutes" => max(1, (int) env("SESSION_ROTATE_AFTER_MINUTES", 30)),
];
