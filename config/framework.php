<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA CONFIGURATION FILE
File: config\framework.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Exposes official FNLLA framework identity separately from downstream project
  branding, domains and customer-facing ownership.
*/

use Fnlla\Php\Support\FrameworkIdentity;

return [
    "name" => FrameworkIdentity::PRODUCT_NAME,
    "slug" => FrameworkIdentity::PRODUCT_SLUG,
    "official_domain" => trim((string) env("FNLLA_OFFICIAL_DOMAIN", FrameworkIdentity::OFFICIAL_DOMAIN)),
    "official_url" => rtrim((string) env("FNLLA_OFFICIAL_URL", FrameworkIdentity::OFFICIAL_URL), "/"),
    "support_email" => trim((string) env("FNLLA_SUPPORT_EMAIL", FrameworkIdentity::SUPPORT_EMAIL)),
    "mail_from_address" => trim((string) env("FNLLA_MAIL_FROM_ADDRESS", FrameworkIdentity::MAIL_FROM_ADDRESS)),
    "maintainer_name" => FrameworkIdentity::MAINTAINER_NAME,
    "maintainer_legal" => FrameworkIdentity::MAINTAINER_LEGAL,
    "maintainer_url" => rtrim((string) env("FNLLA_MAINTAINER_URL", FrameworkIdentity::MAINTAINER_URL), "/"),
    "origin" => FrameworkIdentity::ORIGIN,
    "repository" => FrameworkIdentity::REPOSITORY,
    "repository_url" => FrameworkIdentity::REPOSITORY_URL,
    "repository_web_url" => FrameworkIdentity::REPOSITORY_WEB_URL,
    /*
    Brand contract:
    - These values describe the FNLLA framework identity, not the public brand
      of a downstream application.
    - Framework chrome may use these assets in private developer and
      maintenance surfaces.
    - Public project pages should continue to use `config/app.php` branding so
      client projects are not forced to display FNLLA marks.
    */
    "brand" => [
        "version" => "3.0.0",
        "message" => "Build from blueprint.",
        "colors" => [
            "blue" => "#2563EB",
            "blue_dark" => "#1D4ED8",
            "blue_light" => "#DBEAFE",
            "blue_tint" => "#EFF6FF",
            "navy" => "#0B1220",
            "ink" => "#111827",
            "text" => "#374151",
            "muted" => "#6B7280",
            "border" => "#E5E7EB",
            "background" => "#FAFAFA",
            "white" => "#FFFFFF",
            "success" => "#16A34A",
            "danger" => "#EF4444",
        ],
        "fonts" => [
            "brand" => "Space Grotesk",
            "mono" => "JetBrains Mono",
            "fallback" => "Inter, system-ui, -apple-system, BlinkMacSystemFont, Segoe UI, sans-serif",
        ],
        "assets" => [
            "lockup" => "assets/brand/fnlla/fnlla-recommended-lockup-outline-v3.svg",
            "monogram" => "assets/brand/fnlla/fnlla-monogram-outline-v3.svg",
            "blueprint_pattern" => "assets/brand/fnlla/fnlla-blueprint-pattern.svg",
            "favicon" => "assets/brand/fnlla/favicon.svg",
            "apple_touch_icon" => "assets/brand/fnlla/apple-touch-icon.png",
            "open_graph" => "assets/brand/fnlla/fnlla-open-graph-v3-1200x630.png",
            "webmanifest" => "assets/brand/fnlla/site.webmanifest",
        ],
    ],
];
