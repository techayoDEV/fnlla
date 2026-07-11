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

            flash_set("framework_update_report", array_merge($report, [
                "mode" => $mode,
                "executed_at_utc" => gmdate(DATE_ATOM),
                "source_path" => (string) ($report["source_root"] ?? $sourcePath),
                "release_tag" => $releaseTag,
            ]));
            flash_set("status", [
                "variant" => $this->statusVariantForReport($mode, $report),
                "title" => in_array($mode, ["apply", "github-apply"], true) ? "Safe framework update finished" : "Framework update check finished",
                "text" => in_array($mode, ["apply", "github-apply"], true)
                    ? $this->applyStatusText($report)
                    : $this->checkStatusText($mode, $report),
                "toast" => false,
            ]);
        } catch (RuntimeException $exception) {
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

    private function statusVariantForReport(string $mode, array $report): string
    {
        if (in_array($mode, ["apply", "github-apply"], true)) {
            return ($report["post_install_ok"] ?? true) === true ? "success" : "warning";
        }

        return ($report["conflicts"] ?? []) === [] ? "success" : "info";
    }

    private function applyStatusText(array $report): string
    {
        if (($report["post_install_ok"] ?? true) === true) {
            return "The framework update was applied and the built-in post-install checks passed. Review the report below before treating the application as ready.";
        }

        return "The framework update was applied, but one or more post-install checks reported follow-up work. Review the report below before treating the application as ready.";
    }

    private function checkStatusText(string $mode, array $report): string
    {
        if (is_string($report["release_skip_reason"] ?? null) && $report["release_skip_reason"] !== "") {
            return (string) $report["release_skip_reason"];
        }

        if ($mode === "github-check") {
            return "The application checked the latest published FNLLA release from GitHub, cached the source baseline locally and prepared a structured drift report.";
        }

        return "The application compared its framework base against the maintained source export and prepared a structured drift report.";
    }
}
