<?php

declare(strict_types=1);

namespace Fnlla\Php\Controllers;

use DateTimeImmutable;
use DateTimeZone;
use Fnlla\Php\Http\Request;
use Fnlla\Php\Http\Response;
use Fnlla\Php\Maintenance\DeveloperAccessManager;
use Fnlla\Php\Maintenance\MaintenanceAccessManager;
use Fnlla\Php\Support\EnvironmentFileManager;
use Fnlla\Php\Support\FrameworkReleaseChannel;
use Fnlla\Php\Support\FrameworkUpdater;
use Fnlla\Php\Support\Logger;
use Fnlla\Php\Support\ProjectLeadership;
use Fnlla\Php\Support\VersionManifest;
use Fnlla\Php\Validation\ValidationException;

abstract class ProjectAccessController extends Controller
{
    protected function clientPreviewState(): array
    {
        $enabled = (bool) config("client_preview.enabled", false);
        $timezone = new DateTimeZone((string) config("app.timezone", "UTC"));
        $restoreAt = $this->parseClientPreviewDateTime((string) config("client_preview.restore_at", ""), $timezone);
        $startedAt = $this->parseClientPreviewDateTime((string) config("client_preview.started_at", ""), $timezone);
        $lastUpdatedValue = trim((string) config("client_preview.last_updated_value", ""));

        if ($lastUpdatedValue === "" && $startedAt instanceof DateTimeImmutable) {
            $lastUpdatedValue = $startedAt->format("j F Y \\a\\t H:i");
        }

        $countdownEnabled = $restoreAt instanceof DateTimeImmutable;
        $progressEnabled = (bool) config("client_preview.progress_enabled", true)
            && $restoreAt instanceof DateTimeImmutable
            && $startedAt instanceof DateTimeImmutable
            && $startedAt->getTimestamp() < $restoreAt->getTimestamp();

        $secondsRemaining = $countdownEnabled ? max(0, $restoreAt->getTimestamp() - time()) : 0;
        $totalSeconds = $progressEnabled ? max(1, $restoreAt->getTimestamp() - $startedAt->getTimestamp()) : 0;
        $elapsedSeconds = $progressEnabled ? max(0, min($totalSeconds, time() - $startedAt->getTimestamp())) : 0;
        $progressPercent = $progressEnabled ? (int) round(($elapsedSeconds / $totalSeconds) * 100) : 0;

        return [
            "active" => $enabled,
            "login_disabled" => (bool) config("client_preview.login_disabled", false),
            "kicker" => (string) config("client_preview.kicker", "Private Client Preview"),
            "title" => (string) config("client_preview.title", "Private client preview is active"),
            "show_last_updated" => (bool) config("client_preview.show_last_updated", true),
            "last_updated_label" => (string) config("client_preview.last_updated_label", "Last updated"),
            "last_updated_value" => $lastUpdatedValue,
            "status_title" => (string) config("client_preview.status_title", ""),
            "status_body" => (string) config("client_preview.status_body", ""),
            "countdown_label" => (string) config("client_preview.countdown_label", "Preview window closes in"),
            "countdown_enabled" => $countdownEnabled,
            "countdown" => $this->formatClientPreviewCountdown($secondsRemaining),
            "restore_at_timestamp" => $restoreAt?->getTimestamp() ?? 0,
            "started_at_timestamp" => $startedAt?->getTimestamp() ?? 0,
            "progress_enabled" => $progressEnabled,
            "progress_label" => (string) config("client_preview.progress_label", "Preview window progress"),
            "progress_percent" => $progressPercent,
            "message" => (string) config("client_preview.message", ""),
            "support_heading" => (string) config("client_preview.support_heading", "Need assistance?"),
            "support_email" => (string) config("client_preview.support_email", ""),
            "unlock_button_label" => (string) config("client_preview.unlock_button_label", "Unlock preview"),
            "locked_notice" => (string) config("client_preview.locked_notice", ""),
        ];
    }


    protected function parseClientPreviewDateTime(string $value, DateTimeZone $timezone): ?DateTimeImmutable
    {
        $trimmed = trim($value);

        if ($trimmed === "") {
            return null;
        }

        try {
            return new DateTimeImmutable($trimmed, $timezone);
        } catch (\Throwable) {
            return null;
        }
    }


    protected function formatClientPreviewCountdown(int $seconds): array
    {
        $safeSeconds = max(0, $seconds);
        $hours = intdiv($safeSeconds, 3600);
        $minutes = intdiv($safeSeconds % 3600, 60);
        $remainingSeconds = $safeSeconds % 60;

        return [
            "hours" => sprintf("%02d", $hours),
            "minutes" => sprintf("%02d", $minutes),
            "seconds" => sprintf("%02d", $remainingSeconds),
        ];
    }

    protected function freshDeveloperOnboardingAvailable(
        Request $request,
        MaintenanceAccessManager $maintenanceAccess,
        DeveloperAccessManager $developerAccess
    ): bool {
        if ($maintenanceAccess->enabled() || $maintenanceAccess->configured() || $developerAccess->configured()) {
            return false;
        }

        $environmentFileManager = app(EnvironmentFileManager::class);
        $setupState = $this->developerAccessSetupState($request, $environmentFileManager, $developerAccess);

        return $setupState["can_setup"] === true && $setupState["needs_setup"] === true;
    }


    protected function maintenanceSetupState(
        Request $request,
        EnvironmentFileManager $environmentFileManager,
        MaintenanceAccessManager $maintenanceAccess,
        DeveloperAccessManager $developerAccess
    ): array {
        $setupEnabled = (bool) config("maintenance.setup_ui_enabled", app_environment() !== "production");
        $localOnly = (bool) config("maintenance.setup_ui_local_only", true);
        $isLocalRequest = in_array($request->ip(), ["127.0.0.1", "::1"], true);
        $isLocalContext = !$localOnly || $isLocalRequest;
        $envWritable = $environmentFileManager->isWritable();
        $canSetup = $setupEnabled && $isLocalContext && $envWritable;
        $needsSetup = !$maintenanceAccess->configured() || !$maintenanceAccess->enabled();

        $message = match (true) {
            $setupEnabled !== true => "Browser-based maintenance setup is disabled in this environment.",
            $isLocalContext !== true => "Browser-based maintenance setup is local-only. Open this page from the same machine as the project runtime.",
            $envWritable !== true => "The project .env file is not writable. Make .env or the project directory writable before configuring maintenance here.",
            default => "This project setup flow can save maintenance credentials directly into the project environment file.",
        };

        return [
            "enabled" => $setupEnabled,
            "local_only" => $localOnly,
            "is_local_request" => $isLocalRequest,
            "can_setup" => $canSetup,
            "needs_setup" => $needsSetup,
            "show_setup" => $needsSetup && $canSetup,
            "env_exists" => $environmentFileManager->envExists(),
            "env_writable" => $envWritable,
            "developer_access_configured" => $developerAccess->configured(),
            "message" => $message,
        ];
    }


    protected function projectSetupState(): array
    {
        return [
            "name" => (string) config("app.name", "FNLLA Project"),
            "tagline" => (string) config("app.tagline", ""),
            "url" => (string) config("app.base_url", ""),
        ];
    }


    protected function developerSetupRedirectTarget(MaintenanceAccessManager $maintenanceAccess): string
    {
        if (!$maintenanceAccess->enabled() && !$maintenanceAccess->configured()) {
            return route("home") . "#developer-panel-setup";
        }

        return route("maintenance.home") . "#developer-panel-setup";
    }


    protected function normalizeProjectName(string $value): string
    {
        return trim((string) preg_replace('/\s+/', ' ', $value));
    }


    protected function developerAccessSetupState(
        Request $request,
        EnvironmentFileManager $environmentFileManager,
        DeveloperAccessManager $developerAccess
    ): array {
        $setupEnabled = (bool) config("developer_access.setup_ui_enabled", app_environment() !== "production");
        $localOnly = (bool) config("developer_access.setup_ui_local_only", true);
        $isLocalRequest = in_array($request->ip(), ["127.0.0.1", "::1"], true);
        $isLocalContext = !$localOnly || $isLocalRequest;
        $envWritable = $environmentFileManager->isWritable();
        $canSetup = $setupEnabled && $isLocalContext && $envWritable;
        $needsSetup = !$developerAccess->configured();

        $message = match (true) {
            $setupEnabled !== true => "Browser-based developer panel setup is disabled in this environment.",
            $isLocalContext !== true => "Browser-based developer panel setup is local-only. Open this page from the same machine as the project runtime.",
            $envWritable !== true => "The project .env file is not writable. Make .env or the project directory writable before activating the developer panel here.",
            default => "This project can create developer access directly from the maintenance surface.",
        };

        return [
            "enabled" => $setupEnabled,
            "local_only" => $localOnly,
            "is_local_request" => $isLocalRequest,
            "can_setup" => $canSetup,
            "needs_setup" => $needsSetup,
            "show_setup" => $needsSetup && $canSetup,
            "env_exists" => $environmentFileManager->envExists(),
            "env_writable" => $envWritable,
            "message" => $message,
        ];
    }


    protected function sanitizeMaintenanceRedirectTarget(string $target): string
    {
        $target = trim($target);

        if ($target === "" || !str_starts_with($target, "/") || str_starts_with($target, "//") || str_contains($target, "\\")) {
            return "";
        }

        $parts = parse_url($target);

        if ($parts === false) {
            return "";
        }

        $path = (string) ($parts["path"] ?? "");

        if ($path === "" || !str_starts_with($path, "/")) {
            return "";
        }

        $query = isset($parts["query"]) && $parts["query"] !== "" ? "?" . $parts["query"] : "";

        return $path . $query;
    }


    protected function maintenanceRedirectUrl(string $redirectTarget, string $fragment = ""): string
    {
        $base = route("maintenance.home");

        if ($redirectTarget !== "") {
            $base .= "?redirect=" . rawurlencode($redirectTarget);
        }

        return $base . $fragment;
    }


    protected function maintenanceLockedAccessFragment(MaintenanceAccessManager $maintenanceAccess): string
    {
        $clientPreviewState = $this->clientPreviewState();

        return $clientPreviewState["active"] && $maintenanceAccess->configured()
            ? "#client-preview-access"
            : "#maintenance-access";
    }


    protected function notFoundResponse(): Response
    {
        return $this->view("pages/not-found", [
            "pageTitle" => "Not Found",
        ], 404);
    }


    protected function view(string $template, array $data = [], int $status = 200, ?string $layout = "layouts/developer"): Response
    {
        return parent::view($template, $data, $status, $layout);
    }

}
