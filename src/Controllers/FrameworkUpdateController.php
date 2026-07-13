<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA CONTROLLER SOURCE
File: src\Controllers\FrameworkUpdateController.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Serves the optional framework maintenance page used by downstream
  applications to run local framework-update checks and safe apply flows from
  the browser.
*/

namespace Fnlla\Php\Controllers;

use Fnlla\Php\Http\Request;
use Fnlla\Php\Http\Response;
use Fnlla\Php\Support\FrameworkLock;
use Fnlla\Php\Support\FrameworkReleaseChannel;
use Fnlla\Php\Support\FrameworkUpdater;
use RuntimeException;

final class FrameworkUpdateController extends Controller
{
    public function show(Request $request): Response
    {
        $pageState = $this->pageState($request);
        $lock = $this->safeLoadLock();
        $report = flash("framework_update_report");
        $configuredSourcePath = (string) config("framework_update.source_path", "");
        $sourceDetection = FrameworkUpdater::detectSourceRoot(base_path(), $configuredSourcePath);
        $oldSourcePath = trim((string) old("source_path", ""));
        $cachedRelease = FrameworkReleaseChannel::readCachedReleaseSummary(base_path());
        $sourcePathValue = $oldSourcePath !== ""
            ? $oldSourcePath
            : (string) ($sourceDetection["resolved_path"] ?? $configuredSourcePath);

        return $this->view("maintenance/framework-update", [
            "pageTitle" => "Framework updates",
            "pageTitleSection" => "Maintenance",
            "frameworkUpdatePageState" => $pageState,
            "frameworkUpdateLock" => $lock,
            "frameworkUpdateReport" => is_array($report) ? $report : null,
            "frameworkUpdateSourcePath" => $sourcePathValue,
            "frameworkUpdateSourceDetection" => $sourceDetection,
            "frameworkUpdateCachedRelease" => $cachedRelease,
        ]);
    }

    public function run(Request $request): Response
    {
        $pageState = $this->pageState($request);

        if ($pageState["can_run"] !== true) {
            flash_set("status", [
                "variant" => "warning",
                "title" => "Framework updates are currently locked",
                "text" => $pageState["message"],
                "toast" => false,
            ]);
            regenerate_csrf_token();

            return $this->redirect(route("maintenance.framework_update"));
        }

        $mode = trim((string) $request->input("mode", "check"));
        $usesGitHub = in_array($mode, ["github-check", "github-apply"], true);
        $configuredSourcePath = (string) config("framework_update.source_path", "");
        $sourceDetection = FrameworkUpdater::detectSourceRoot(base_path(), $configuredSourcePath);
        $sourcePathInput = trim((string) $request->input("source_path", ""));
        $sourcePath = $sourcePathInput !== ""
            ? $sourcePathInput
            : (string) ($sourceDetection["resolved_path"] ?? "");
        $releaseTag = trim((string) $request->input("release_tag", ""));
        flash_set("old", [
            "source_path" => $sourcePath,
            "release_tag" => $releaseTag,
        ]);

        if ($usesGitHub !== true && $sourcePath === "") {
            flash_set("status", [
                "variant" => "warning",
                "title" => "Maintained source repository still needed",
                "text" => "FNLLA could not auto-detect a maintained source repository for this project. Set FRAMEWORK_UPDATE_SOURCE_PATH in .env or enter the path manually below.",
                "toast" => false,
            ]);
            regenerate_csrf_token();

            return $this->redirect(route("maintenance.framework_update"));
        }

        if (!in_array($mode, ["check", "apply", "github-check", "github-apply"], true)) {
            flash_set("status", [
                "variant" => "warning",
                "title" => "Unknown framework update action",
                "text" => "Choose a supported action before rerunning the framework update workflow.",
                "toast" => false,
            ]);
            regenerate_csrf_token();

            return $this->redirect(route("maintenance.framework_update"));
        }

        if (in_array($mode, ["apply", "github-apply"], true) && $pageState["can_apply"] !== true) {
            flash_set("status", [
                "variant" => "warning",
                "title" => "Safe apply is disabled here",
                "text" => "This application currently allows browser-based checks only. Enable apply explicitly in the local environment when you are ready.",
                "toast" => false,
            ]);
            regenerate_csrf_token();

            return $this->redirect(route("maintenance.framework_update"));
        }

        if ($usesGitHub && ((bool) config("framework_update.github_enabled", true)) !== true) {
            flash_set("status", [
                "variant" => "warning",
                "title" => "GitHub release channel is disabled",
                "text" => "Enable FRAMEWORK_UPDATE_GITHUB_ENABLED in the local environment to let this page fetch FNLLA releases directly from GitHub.",
                "toast" => false,
            ]);
            regenerate_csrf_token();

            return $this->redirect(route("maintenance.framework_update"));
        }

        try {
            $report = match ($mode) {
                "apply" => FrameworkUpdater::apply(base_path(), $sourcePath, (string) config("app.name")),
                "github-check" => FrameworkUpdater::checkLatestRelease(base_path(), (string) config("app.name"), $releaseTag !== "" ? $releaseTag : null),
                "github-apply" => FrameworkUpdater::applyLatestRelease(base_path(), (string) config("app.name"), $releaseTag !== "" ? $releaseTag : null),
                default => FrameworkUpdater::check(base_path(), $sourcePath, (string) config("app.name")),
            };

            $report = array_merge($report, [
                "mode" => $mode,
                "executed_at_utc" => gmdate(DATE_ATOM),
                "source_path" => (string) ($report["source_root"] ?? $sourcePath),
                "release_tag" => $releaseTag,
            ]);
            $report = array_merge($report, $this->reportPresentation($mode, $report, $pageState));
            $status = $this->statusPayloadForReport($mode, $report);

            flash_set("framework_update_report", $report);
            flash_set("status", $status);
        } catch (RuntimeException $exception) {
            if (in_array($mode, ["apply", "github-apply"], true) && $exception->getMessage() === FrameworkUpdater::APPLY_CONFLICT_MESSAGE) {
                $report = $mode === "github-apply"
                    ? FrameworkUpdater::checkLatestRelease(base_path(), (string) config("app.name"), $releaseTag !== "" ? $releaseTag : null)
                    : FrameworkUpdater::check(base_path(), $sourcePath, (string) config("app.name"));

                $report = array_merge($report, [
                    "mode" => $mode === "github-apply" ? "github-check" : "check",
                    "executed_at_utc" => gmdate(DATE_ATOM),
                    "source_path" => (string) ($report["source_root"] ?? $sourcePath),
                    "release_tag" => $releaseTag,
                ]);
                $report = array_merge($report, $this->reportPresentation((string) $report["mode"], $report, $pageState));
                flash_set("framework_update_report", $report);
                flash_set("status", [
                    "variant" => "warning",
                    "title" => "Update stopped for manual review",
                    "text" => "FNLLA did not apply the update because one or more framework-managed files need a manual merge first. Review the conflict report below, resolve those files, then rerun the update.",
                    "toast" => false,
                ]);
                regenerate_csrf_token();

                return $this->redirect(route("maintenance.framework_update"));
            }

            flash_set("status", [
                "variant" => "danger",
                "title" => "Framework update could not run",
                "text" => $exception->getMessage(),
                "toast" => false,
            ]);
        }

        regenerate_csrf_token();

        return $this->redirect(route("maintenance.framework_update"));
    }

    private function pageState(Request $request): array
    {
        $enabled = (bool) config("framework_update.ui_enabled", false);
        $localOnly = (bool) config("framework_update.ui_local_only", true);
        $applyEnabled = (bool) config("framework_update.ui_apply_enabled", false);
        $isLocalRequest = in_array($request->ip(), ["127.0.0.1", "::1"], true);
        $isLocalContext = !$localOnly || $isLocalRequest;
        $canRun = $enabled && $isLocalContext;

        $message = match (true) {
            $enabled !== true => "Enable FRAMEWORK_UPDATE_UI_ENABLED in the local environment to run browser-based framework checks.",
            $isLocalContext !== true => "This page is configured for local-only usage. Open it from the same machine as the project runtime.",
            default => "Framework update checks are available from this page.",
        };

        return [
            "enabled" => $enabled,
            "local_only" => $localOnly,
            "apply_enabled" => $applyEnabled,
            "github_enabled" => (bool) config("framework_update.github_enabled", true),
            "is_local_request" => $isLocalRequest,
            "can_run" => $canRun,
            "can_apply" => $canRun && $applyEnabled,
            "message" => $message,
        ];
    }

    private function safeLoadLock(): ?array
    {
        try {
            return FrameworkLock::load(base_path());
        } catch (RuntimeException) {
            return null;
        }
    }

    private function statusPayloadForReport(string $mode, array $report): array
    {
        if (in_array($mode, ["apply", "github-apply"], true)) {
            return [
                "variant" => ($report["post_install_ok"] ?? true) === true ? "success" : "warning",
                "title" => (string) ($report["headline_title"] ?? "Framework update finished"),
                "text" => (string) ($report["headline_text"] ?? $this->applyStatusText($report)),
                "toast" => false,
            ];
        }

        if (($report["requires_manual_review"] ?? false) === true) {
            $variant = "warning";
        } elseif (($report["update_ready"] ?? false) === true) {
            $variant = "success";
        } elseif (($report["update_detected"] ?? false) === true) {
            $variant = "info";
        } else {
            $variant = "info";
        }

        return [
            "variant" => $variant,
            "title" => (string) ($report["headline_title"] ?? "Framework update check finished"),
            "text" => (string) ($report["headline_text"] ?? $this->checkStatusText($mode, $report)),
            "toast" => false,
        ];
    }

    private function applyStatusText(array $report): string
    {
        if (($report["post_install_ok"] ?? true) === true) {
            return "The framework update finished and the built-in post-install checks passed. Review the report, then refresh this page to confirm the maintenance surface is now using the updated framework base.";
        }

        return "The framework update finished, but one or more post-install checks reported follow-up work. Review the report carefully before refreshing the page and treating the application as ready.";
    }

    private function checkStatusText(string $mode, array $report): string
    {
        if (is_string($report["release_skip_reason"] ?? null) && $report["release_skip_reason"] !== "") {
            return (string) $report["release_skip_reason"];
        }

        if (($report["requires_manual_review"] ?? false) === true) {
            return (string) ($report["headline_text"] ?? "FNLLA detected upstream framework changes, but one or more framework-managed files changed both locally and upstream. Review the conflicts before any apply run can continue.");
        }

        if (($report["update_ready"] ?? false) === true) {
            return (string) ($report["headline_text"] ?? "FNLLA detected framework changes and prepared a safe update that can be applied from this page.");
        }

        if ($mode === "github-check") {
            return "The application checked the selected FNLLA release channel, prepared a structured report and confirmed whether a newer GitHub-backed update is ready.";
        }

        return "The application compared its framework base against the maintained source export and prepared a structured report that makes the update decision explicit.";
    }

    private function reportPresentation(string $mode, array $report, array $pageState): array
    {
        $isApplyMode = in_array($mode, ["apply", "github-apply"], true);
        $updates = count((array) ($report["updates"] ?? []));
        $conflicts = count((array) ($report["conflicts"] ?? []));
        $localOnly = count((array) ($report["local_only_changes"] ?? []));
        $usesGitHub = in_array($mode, ["github-check", "github-apply"], true);
        $versionsDiffer = $this->versionsDiffer(
            (string) ($report["current_framework_version"] ?? ""),
            (string) ($report["source_framework_version"] ?? "")
        );
        $runtimeDiffers = $this->versionsDiffer(
            (string) ($report["current_ui_version"] ?? ""),
            (string) ($report["source_ui_version"] ?? "")
        );
        $versionTransition = $this->versionTransitionSummary($report);
        $githubRelease = is_array($report["github_release"] ?? null) ? (array) $report["github_release"] : [];
        $githubNewer = ($githubRelease["has_newer_release"] ?? null) === true;
        $updateDetected = $updates > 0 || $conflicts > 0 || $githubNewer || $versionsDiffer || $runtimeDiffers;
        $requiresManualReview = !$isApplyMode && $conflicts > 0;
        $updateReady = !$isApplyMode && $updates > 0 && $conflicts === 0;
        $recommendedApplyMode = match ($mode) {
            "check" => "apply",
            "github-check" => "github-apply",
            default => "",
        };
        $applyActionAvailable = $updateReady && (($pageState["can_apply"] ?? false) === true) && $recommendedApplyMode !== "";

        if ($isApplyMode) {
            $headlineTitle = ($report["post_install_ok"] ?? true) === true
                ? "Framework update completed"
                : "Framework update completed with follow-up work";
            $headlineText = $this->applyStatusText($report);
        } elseif (is_string($report["release_skip_reason"] ?? null) && $report["release_skip_reason"] !== "" && !$updateDetected) {
            $headlineTitle = "Framework base already up to date";
            $headlineText = (string) $report["release_skip_reason"];
        } elseif ($requiresManualReview) {
            $headlineTitle = $versionTransition !== ""
                ? "Newer framework base detected, but manual review is required"
                : "Framework changes detected, but manual review is required";
            $headlineText = $versionTransition !== ""
                ? "FNLLA keeps the update path automatic for safe changes, but this run found a real file collision ({$versionTransition}) in {$conflicts} framework-managed file(s). Review those conflicts before applying the update."
                : "FNLLA keeps the update path automatic for safe changes, but this run found {$conflicts} framework-managed file(s) changed both locally and upstream. Review those conflicts before applying the update.";
        } elseif ($updateReady) {
            $headlineTitle = $versionTransition !== ""
                ? "Update is ready to apply"
                : "Framework changes are ready to apply";
            $headlineText = $versionTransition !== ""
                ? "FNLLA detected an upstream framework shift ({$versionTransition}) and prepared the automatic safe portion of the update. No manual merge is needed for these files, so you can apply the update directly from this page."
                : "FNLLA detected framework-managed source changes and prepared the automatic safe portion of the update. No manual merge is needed for these files, so you can apply the update directly from this page.";
        } elseif ($localOnly > 0) {
            $headlineTitle = "Framework base is already aligned";
            $headlineText = "No upstream framework drift was detected. {$localOnly} framework-managed file(s) still differ locally, but the maintained source kept the same baseline, so no apply run is needed.";
        } else {
            $headlineTitle = $usesGitHub
                ? "No newer GitHub update is waiting"
                : "Framework base is already aligned";
            $headlineText = $usesGitHub
                ? "FNLLA checked the selected GitHub release channel and did not find a newer published framework base that should be applied here."
                : "The current project already matches the selected maintained framework source.";
        }

        return [
            "headline_title" => $headlineTitle,
            "headline_text" => $headlineText,
            "update_detected" => $updateDetected,
            "update_ready" => $updateReady,
            "requires_manual_review" => $requiresManualReview,
            "recommended_apply_mode" => $recommendedApplyMode,
            "apply_action_available" => $applyActionAvailable,
            "can_apply_from_ui" => ($pageState["can_apply"] ?? false) === true,
            "version_transition_summary" => $versionTransition,
        ];
    }

    private function versionsDiffer(string $currentVersion, string $sourceVersion): bool
    {
        $currentVersion = trim($currentVersion);
        $sourceVersion = trim($sourceVersion);

        return $currentVersion !== ""
            && $sourceVersion !== ""
            && $currentVersion !== "unknown"
            && $sourceVersion !== "unknown"
            && $currentVersion !== $sourceVersion;
    }

    private function versionTransitionSummary(array $report): string
    {
        $segments = [];
        $currentFrameworkVersion = trim((string) ($report["current_framework_version"] ?? ""));
        $sourceFrameworkVersion = trim((string) ($report["source_framework_version"] ?? ""));
        $currentUiVersion = trim((string) ($report["current_ui_version"] ?? ""));
        $sourceUiVersion = trim((string) ($report["source_ui_version"] ?? ""));

        if ($this->versionsDiffer($currentFrameworkVersion, $sourceFrameworkVersion)) {
            $segments[] = "FNLLA {$currentFrameworkVersion} -> {$sourceFrameworkVersion}";
        }

        if ($this->versionsDiffer($currentUiVersion, $sourceUiVersion)) {
            $segments[] = "Runtime {$currentUiVersion} -> {$sourceUiVersion}";
        }

        return implode(", ", $segments);
    }
}
