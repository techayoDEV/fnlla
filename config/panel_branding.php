<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA CONFIGURATION FILE
File: config\panel_branding.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

Purpose:
- Defines optional white-label branding for private developer and customer
  review surfaces. Public application branding remains in config/app.php.
*/

return [
    "white_label_enabled" => (bool) env("PANEL_BRAND_WHITE_LABEL_ENABLED", false),
    "name" => trim((string) env("PANEL_BRAND_NAME", "")),
    "tagline" => trim((string) env("PANEL_BRAND_TAGLINE", "")),
    "logo" => trim((string) env("PANEL_BRAND_LOGO", "auto")),
    "url" => trim((string) env("PANEL_BRAND_URL", "")),
    "copyright" => trim((string) env("PANEL_BRAND_COPYRIGHT", "")),
];
