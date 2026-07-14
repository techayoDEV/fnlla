<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA CONFIGURATION FILE
File: config\client_preview.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

Purpose:
- Defines the optional branded private client preview surface that can replace
  the standard maintenance access screen for downstream projects.
*/

return [
    "enabled" => (bool) env("CLIENT_PREVIEW_ENABLED", false),
    "login_disabled" => (bool) env("CLIENT_PREVIEW_LOGIN_DISABLED", false),
    "kicker" => trim((string) env("CLIENT_PREVIEW_KICKER", "Private Client Preview")),
    "title" => trim((string) env("CLIENT_PREVIEW_TITLE", "Your project is being restored")),
    "last_updated_label" => trim((string) env("CLIENT_PREVIEW_LAST_UPDATED_LABEL", "Last updated")),
    "last_updated_value" => trim((string) env("CLIENT_PREVIEW_LAST_UPDATED_VALUE", "")),
    "show_last_updated" => (bool) env("CLIENT_PREVIEW_SHOW_LAST_UPDATED", true),
    "status_title" => trim((string) env("CLIENT_PREVIEW_STATUS_TITLE", "Infrastructure operational, restoration in progress")),
    "status_body" => trim((string) env("CLIENT_PREVIEW_STATUS_BODY", "Our new infrastructure is now fully operational, and we are synchronising your project and restoring access.")),
    "countdown_label" => trim((string) env("CLIENT_PREVIEW_COUNTDOWN_LABEL", "Full Access Restoration in")),
    "restore_at" => trim((string) env("CLIENT_PREVIEW_RESTORE_AT", "")),
    "started_at" => trim((string) env("CLIENT_PREVIEW_STARTED_AT", "")),
    "progress_enabled" => (bool) env("CLIENT_PREVIEW_PROGRESS_ENABLED", true),
    "progress_label" => trim((string) env("CLIENT_PREVIEW_PROGRESS_LABEL", "Restoration progress")),
    "message" => trim((string) env("CLIENT_PREVIEW_MESSAGE", "We sincerely apologise for the recent delay caused by our infrastructure migration and appreciate your patience while we complete the final restoration process.")),
    "support_heading" => trim((string) env("CLIENT_PREVIEW_SUPPORT_HEADING", "Need assistance?")),
    "support_email" => trim((string) env("CLIENT_PREVIEW_SUPPORT_EMAIL", "")),
    "unlock_button_label" => trim((string) env("CLIENT_PREVIEW_UNLOCK_BUTTON_LABEL", "Unlock preview")),
    "locked_notice" => trim((string) env("CLIENT_PREVIEW_LOCKED_NOTICE", "Preview unlock is temporarily unavailable while the restoration is completed.")),
];
