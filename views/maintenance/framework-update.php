<?php

declare(strict_types=1);

$pageState = is_array($frameworkUpdatePageState ?? null) ? $frameworkUpdatePageState : [];
$lock = is_array($frameworkUpdateLock ?? null) ? $frameworkUpdateLock : [];
$report = is_array($frameworkUpdateReport ?? null) ? $frameworkUpdateReport : null;
$applicationMeta = (array) ($lock["framework_base"]["application"] ?? []);
$frameworkMeta = (array) ($lock["framework_base"]["framework"] ?? []);
$uiMeta = (array) ($lock["framework_base"]["ui_runtime"] ?? []);
$lockUsesUnifiedVersion = trim((string) ($frameworkMeta["version"] ?? "")) !== ""
    && trim((string) ($frameworkMeta["version"] ?? "")) === trim((string) ($uiMeta["version"] ?? ""));
$managedFiles = (array) ($lock["framework_base"]["managed_files"] ?? []);
$sourcePathValue = (string) ($frameworkUpdateSourcePath ?? "");
$sourceDetection = is_array($frameworkUpdateSourceDetection ?? null) ? $frameworkUpdateSourceDetection : [];
$cachedRelease = is_array($frameworkUpdateCachedRelease ?? null) ? $frameworkUpdateCachedRelease : [];
$detectedSourcePath = (string) ($sourceDetection["resolved_path"] ?? "");
$detectedSourceOrigin = (string) ($sourceDetection["origin"] ?? "manual input required");
$releaseTagValue = trim((string) old("release_tag", ""));
$cachedReleaseTag = trim((string) ($cachedRelease["tag"] ?? ""));
$cachedReleaseVersion = trim((string) ($cachedRelease["version"] ?? ""));
$cachedReleaseNotes = trim((string) ($cachedRelease["notes"] ?? ""));
$reportMode = trim((string) ($report["mode"] ?? ""));
$reportUsesGitHub = in_array($reportMode, ["github-check", "github-apply"], true);
$reportIsApply = in_array($reportMode, ["apply", "github-apply"], true);
$reportUpdates = (array) ($report["updates"] ?? []);
$reportConflicts = (array) ($report["conflicts"] ?? []);
$reportLocalOnlyChanges = (array) ($report["local_only_changes"] ?? []);
$reportHeadlineTitle = trim((string) ($report["headline_title"] ?? ""));
$reportHeadlineText = trim((string) ($report["headline_text"] ?? ""));
$reportVersionTransition = trim((string) ($report["version_transition_summary"] ?? ""));
$reportRecommendedApplyMode = trim((string) ($report["recommended_apply_mode"] ?? ""));
$reportApplyActionAvailable = ($report["apply_action_available"] ?? false) === true;
$reportCanApplyFromUi = ($report["can_apply_from_ui"] ?? false) === true;
$reportUpdateReady = ($report["update_ready"] ?? false) === true;
$reportRequiresManualReview = ($report["requires_manual_review"] ?? false) === true;
$reportSourcePath = trim((string) ($report["source_root"] ?? $report["source_path"] ?? $sourcePathValue));
$reportReleaseTag = trim((string) (($report["github_release"]["tag"] ?? ($report["release_tag"] ?? ""))));
$updateActionLabel = static function (array $update): string {
    $label = trim((string) ($update["label"] ?? ""));

    if ($label !== "") {
        return $label;
    }

    return match ((string) ($update["action"] ?? "update")) {
        "add" => "Automatic add ready",
        "remove" => "Automatic removal ready",
        "sync" => "Formatting-only sync ready",
        default => "Automatic update ready",
    };
};
?>
<section class="section">
  <div class="container">
    <div class="grid grid-4 gap-md">
      <article class="feature-card">
        <p class="feature-kicker">Current base</p>
        <h2 class="content-title mb-xs"><?= h((string) ($frameworkMeta["version"] ?? "unknown")) ?></h2>
        <p class="content-text mb-0">FNLLA for <?= h((string) ($applicationMeta["name"] ?? config("app.name"))) ?>.</p>
      </article>
      <article class="feature-card">
        <p class="feature-kicker">Integrated UI surface</p>
        <h2 class="content-title mb-xs"><?= $lockUsesUnifiedVersion ? "Synced" : h((string) ($uiMeta["version"] ?? "unknown")) ?></h2>
        <p class="content-text mb-0"><?= $lockUsesUnifiedVersion ? "The built-in UI surface currently shares the same FNLLA version contract." : "Legacy lock metadata still records a separate built-in UI surface version." ?></p>
      </article>
      <article class="feature-card">
        <p class="feature-kicker">Managed files</p>
        <h2 class="content-title mb-xs"><?= h((string) count($managedFiles)) ?></h2>
        <p class="content-text mb-0">Framework-managed files tracked inside <code>.fnlla/framework-lock.json</code>.</p>
      </article>
      <article class="feature-card">
        <p class="feature-kicker">GitHub release channel</p>
        <h2 class="content-title mb-xs"><?= ($pageState["github_enabled"] ?? false) ? ($cachedReleaseTag !== "" ? h($cachedReleaseTag) : "Ready") : "Disabled" ?></h2>
        <p class="content-text mb-0">
          <?php if (($pageState["github_enabled"] ?? false) !== true): ?>
          Enable <code>FRAMEWORK_UPDATE_GITHUB_ENABLED</code> to fetch published releases directly from GitHub.
          <?php elseif ($cachedReleaseTag !== ""): ?>
          Cached release <?= h($cachedReleaseVersion !== "" ? $cachedReleaseVersion : $cachedReleaseTag) ?> available for reuse.
          <?php else: ?>
          Ready to fetch the latest published FNLLA release on demand.
          <?php endif; ?>
        </p>
      </article>
    </div>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="grid gap-md framework-update-control-stack">
      <aside class="contact-card contact-summary-card" aria-label="Framework update controls summary">
        <p class="contact-kicker">Update posture</p>
        <h2 class="contact-card-title">Run checks first, keep the source explicit and apply only after the report stays boring.</h2>
        <p class="contact-text"><?= h((string) ($pageState["message"] ?? "")) ?></p>
        <ul class="contact-list framework-update-summary-list">
          <li>Browser UI enabled: <strong><?= ($pageState["enabled"] ?? false) ? "Yes" : "No" ?></strong></li>
          <li>Local-only mode: <strong><?= ($pageState["local_only"] ?? false) ? "Yes" : "No" ?></strong></li>
          <li>Apply allowed from UI: <strong><?= ($pageState["can_apply"] ?? false) ? "Yes" : "No" ?></strong></li>
          <li>GitHub release channel: <strong><?= ($pageState["github_enabled"] ?? false) ? "Enabled" : "Disabled" ?></strong></li>
          <li>Current request is local: <strong><?= ($pageState["is_local_request"] ?? false) ? "Yes" : "No" ?></strong></li>
          <li>Detected source path: <strong><?= $detectedSourcePath !== "" ? "Yes" : "No" ?></strong></li>
        </ul>
        <div class="form-message framework-update-surface-note" role="status">
          <h3 class="form-message-title">What this page protects</h3>
          <p class="form-message-text mb-0">FNLLA can fetch the latest public release, cache it under <code>storage/framework/updates/</code>, export a clean baseline and separate safe framework changes from files that still need a human merge.</p>
        </div>
      </aside>

      <article class="cta-card contact-form-card">
        <form class="form contact-form" action="<?= h(route("maintenance.framework_update.run")) ?>" method="post" novalidate data-framework-update-form>
          <?= csrf_field() ?>
          <div class="grid grid-2 gap-md framework-update-channel-grid">
            <section class="feature-card framework-update-channel-card" aria-label="GitHub release channel controls">
              <div class="framework-update-card-header">
                <div>
                  <p class="contact-kicker">Recommended workflow</p>
                  <h2 class="contact-card-title">GitHub release channel</h2>
                </div>
                <span class="framework-update-badge">Preferred for starters</span>
              </div>
              <p class="contact-text">Use this when the application should fetch the latest published FNLLA release, cache it locally and prepare a browser-readable drift report before any apply step.</p>

              <div class="grid gap-md mb-3">
                <article class="feature-card">
                  <p class="feature-kicker">Latest cached release</p>
                  <h3 class="content-title mb-xs"><?= $cachedReleaseTag !== "" ? h($cachedReleaseTag) : "Not fetched yet" ?></h3>
                  <p class="content-text mb-0"><?= $cachedReleaseTag !== "" ? "Cache path: " . h((string) ($cachedRelease["cache_path"] ?? "storage/framework/updates/fnlla")) : "Run a GitHub check to cache the latest release baseline locally." ?></p>
                </article>
                <article class="feature-card">
                  <p class="feature-kicker">Release notes preview</p>
                  <p class="content-text mb-0"><?= $cachedReleaseNotes !== "" ? nl2br(h($cachedReleaseNotes)) : "Release notes and update highlights appear here after the first GitHub-backed check." ?></p>
                </article>
              </div>

              <div class="form-group">
                <label class="label" for="framework-update-release-tag">Optional release tag override</label>
                <input class="input" id="framework-update-release-tag" name="release_tag" type="text" placeholder="Leave blank for the latest release, or enter a specific tag such as v1.0.x" value="<?= h($releaseTagValue) ?>" <?= ($pageState["can_run"] ?? false) ? "" : "disabled" ?>>
                <p class="help-text">Leave this blank for the latest published release. Use a tag only when you need to verify or apply a specific published FNLLA version.</p>
              </div>

              <div class="grid grid-2 gap-md framework-update-actions-grid">
                <button class="btn btn-outline" type="submit" name="mode" value="github-check" data-framework-update-progress-mode="github-check" <?= (($pageState["can_run"] ?? false) && ($pageState["github_enabled"] ?? false)) ? "" : "disabled" ?>>Check GitHub update</button>
                <button class="btn btn-primary" type="submit" name="mode" value="github-apply" data-framework-update-progress-mode="github-apply" <?= (($pageState["can_apply"] ?? false) && ($pageState["github_enabled"] ?? false)) ? "" : "disabled" ?>>Apply GitHub update</button>
              </div>
            </section>

            <section class="feature-card framework-update-channel-card" aria-label="Local maintained repository controls">
              <div class="framework-update-card-header">
                <div>
                  <p class="contact-kicker">Advanced override</p>
                  <h2 class="contact-card-title">Local maintained repository</h2>
                </div>
                <span class="framework-update-badge framework-update-badge-muted">Maintainer checkout</span>
              </div>
              <p class="contact-text">Use this path-based workflow when you need to compare against a local maintainer checkout and confirm whether a newer maintained update is already waiting in that repository.</p>

              <div class="form-group">
                <label class="label" for="framework-update-source">Maintained FNLLA source repository</label>
                <input class="input" id="framework-update-source" name="source_path" type="text" placeholder="Leave blank to use an auto-detected sibling fnlla repo, or enter C:\path\to\fnlla" value="<?= h($sourcePathValue) ?>" <?= ($pageState["can_run"] ?? false) ? "" : "disabled" ?>>
                <p class="help-text">Leave this blank when the maintained repository sits next to the application. Use a manual path only when the source repository lives elsewhere.</p>
                <?php if ($detectedSourcePath !== ""): ?>
                <p class="help-text mb-0"><strong>Detected now:</strong> <?= h($detectedSourcePath) ?> (<?= h($detectedSourceOrigin) ?>)</p>
                <?php endif; ?>
              </div>

              <div class="grid grid-2 contact-field-grid framework-update-actions-grid">
                <button class="btn btn-outline" type="submit" name="mode" value="check" data-framework-update-progress-mode="check" <?= ($pageState["can_run"] ?? false) ? "" : "disabled" ?>>Check local maintained update</button>
                <button class="btn btn-primary" type="submit" name="mode" value="apply" data-framework-update-progress-mode="apply" <?= ($pageState["can_apply"] ?? false) ? "" : "disabled" ?>>Apply local maintained update</button>
              </div>
            </section>
          </div>

          <div class="form-message" role="status">
            <h3 class="form-message-title">Recommended sequence</h3>
            <ol class="framework-update-sequence mb-0">
              <li>Check the latest GitHub release and let FNLLA cache it locally.</li>
              <li>Review safe changes, conflicts and release notes before touching apply.</li>
              <li>Apply only when the report and post-install checks stay healthy.</li>
            </ol>
          </div>
        </form>
      </article>
    </div>
  </div>
</section>

<section class="section">
  <div class="container">
    <section class="process-section" aria-label="Framework update workflow">
      <div class="section-header mb-0">
        <p class="process-kicker">Update workflow</p>
        <h2 class="section-title">A professional downstream update flow should stay understandable to any developer who joins the project later.</h2>
        <p class="section-text">The goal is not just to copy files. The goal is to make framework drift visible, explain what is safe and preserve project-owned changes when the starter grows into a real product.</p>
      </div>
      <div class="process-grid">
        <article class="process-step">
          <span class="process-step-number">1</span>
          <h3 class="process-step-title">Resolve the source and export a fresh baseline</h3>
          <p class="process-step-text">FNLLA can fetch the latest published release from GitHub and cache it locally, or it can use a configured source path and an auto-detected sibling <code>fnlla</code> repository before exporting a clean starter baseline for comparison.</p>
        </article>
        <article class="process-step">
          <span class="process-step-number">2</span>
          <h3 class="process-step-title">Separate safe changes from conflicts</h3>
          <p class="process-step-text">Framework-managed files that stayed untouched locally can move automatically. Divergent files are reported as explicit conflicts instead of being overwritten.</p>
        </article>
        <article class="process-step">
          <span class="process-step-number">3</span>
          <h3 class="process-step-title">Verify the application after apply</h3>
          <p class="process-step-text">After a safe apply, FNLLA runs post-install checks for the built-in runtime contract, project tests, lint and version metadata so the update does not end as a blind file copy.</p>
        </article>
      </div>
    </section>
  </div>
</section>

<?php if (is_array($report)): ?>
<section class="section">
  <div class="container">
    <section class="feature-section" aria-label="Framework update report">
      <div class="section-header mb-0">
        <p class="feature-kicker">Structured report</p>
        <h2 class="section-title">The last framework update run explains clearly whether the project is already current, blocked by conflicts or ready to update.</h2>
        <p class="section-text">Review the update decision, safe changes, conflicts and post-install outcome before moving on.</p>
      </div>

      <div class="grid grid-3 gap-md mb-lg">
        <article class="feature-card">
          <p class="feature-kicker">Mode</p>
          <h3 class="content-title"><?= h(strtoupper((string) ($report["mode"] ?? "check"))) ?></h3>
          <p class="content-text mb-0">Executed at <?= h((string) ($report["executed_at_utc"] ?? "unknown")) ?></p>
        </article>
        <article class="feature-card">
          <p class="feature-kicker">Source baseline</p>
          <h3 class="content-title"><?= h((string) ($report["source_framework_version"] ?? "unknown")) ?></h3>
          <p class="content-text mb-0">
            <?php if (trim((string) ($report["source_framework_version"] ?? "")) === trim((string) ($report["source_ui_version"] ?? ""))): ?>
            Unified FNLLA version across the integrated UI surface.
            <?php else: ?>
            Integrated UI surface <?= h((string) ($report["source_ui_version"] ?? "unknown")) ?>
            <?php endif; ?>
          </p>
        </article>
        <article class="feature-card">
          <p class="feature-kicker">Summary</p>
          <h3 class="content-title"><?= h((string) count((array) ($report["updates"] ?? []))) ?> safe / <?= h((string) count((array) ($report["conflicts"] ?? []))) ?> conflicts</h3>
          <p class="content-text mb-0">Local-only managed changes preserved: <?= h((string) count((array) ($report["local_only_changes"] ?? []))) ?></p>
        </article>
      </div>

      <?php if ($reportHeadlineTitle !== "" || $reportHeadlineText !== ""): ?>
      <article class="feature-card mb-lg">
        <p class="feature-kicker"><?= $reportIsApply ? "Update result" : "Update decision" ?></p>
        <h3 class="content-title"><?= h($reportHeadlineTitle !== "" ? $reportHeadlineTitle : "Framework update report ready") ?></h3>
        <?php if ($reportHeadlineText !== ""): ?>
        <p class="content-text"><?= h($reportHeadlineText) ?></p>
        <?php endif; ?>
        <?php if ($reportVersionTransition !== ""): ?>
        <p class="content-text"><strong>Detected version shift:</strong> <?= h($reportVersionTransition) ?></p>
        <?php endif; ?>

        <?php if ($reportApplyActionAvailable && $reportRecommendedApplyMode !== ""): ?>
        <form class="form stack gap-md mt-3" action="<?= h(route("maintenance.framework_update.run")) ?>" method="post" novalidate>
          <?= csrf_field() ?>
          <input type="hidden" name="mode" value="<?= h($reportRecommendedApplyMode) ?>">
          <?php if ($reportUsesGitHub && $reportReleaseTag !== ""): ?>
          <input type="hidden" name="release_tag" value="<?= h($reportReleaseTag) ?>">
          <?php elseif (!$reportUsesGitHub && $reportSourcePath !== ""): ?>
          <input type="hidden" name="source_path" value="<?= h($reportSourcePath) ?>">
          <?php endif; ?>
          <div class="d-flex flex-wrap gap-md">
            <button class="btn btn-primary" type="submit" data-framework-update-progress-mode="<?= h($reportRecommendedApplyMode) ?>"><?= $reportUsesGitHub ? "Apply this audited GitHub update" : "Apply this audited local update" ?></button>
          </div>
          <p class="help-text mb-0">FNLLA keeps the update flow automatic for safe framework-managed changes like the ones reviewed above. It pauses only when a real file conflict needs a human merge, then runs the built-in post-install checks after apply.</p>
        </form>
        <?php elseif ($reportUpdateReady && !$reportCanApplyFromUi): ?>
        <p class="content-text mb-0">The update is ready, but browser apply is disabled in this environment. Enable <code>FRAMEWORK_UPDATE_UI_APPLY_ENABLED</code> when you want this page to execute the safe apply flow.</p>
        <?php elseif ($reportRequiresManualReview): ?>
        <p class="content-text mb-0">Apply is intentionally blocked until the conflicts listed below are reviewed and resolved. Once those framework-managed files are aligned again, rerun the check and the apply action will become available here.</p>
        <?php elseif ($reportIsApply): ?>
        <div class="d-flex flex-wrap gap-md">
          <a class="btn btn-outline" href="<?= h(route("maintenance.framework_update")) ?>">Refresh page</a>
        </div>
        <p class="help-text mb-0">Use the refresh after reviewing the report if you want the top version cards to confirm that the newly applied framework base is now active in the maintenance UI.</p>
        <?php endif; ?>
      </article>
      <?php endif; ?>

      <article class="feature-card mb-lg">
        <h3 class="content-title">Resolved source repository</h3>
        <p class="content-text mb-0"><strong>Path:</strong> <?= h((string) ($report["source_root"] ?? $report["source_path"] ?? "unknown")) ?></p>
        <p class="content-text mb-0"><strong>Resolution:</strong> <?= h((string) ($report["source_origin"] ?? "manual source path")) ?></p>
      </article>

      <?php if (is_array($report["github_release"] ?? null) && $report["github_release"] !== []): ?>
      <?php $githubRelease = (array) $report["github_release"]; ?>
      <article class="feature-card mb-lg">
        <h3 class="content-title">GitHub release baseline</h3>
        <div class="grid grid-2 gap-md framework-update-meta-grid">
          <div>
            <p class="content-text mb-0"><strong>Tag:</strong> <?= h((string) ($githubRelease["tag"] ?? "unknown")) ?></p>
            <p class="content-text mb-0"><strong>Current version:</strong> <?= h((string) ($githubRelease["current_version"] ?? "unknown")) ?></p>
            <p class="content-text mb-0"><strong>Published:</strong> <?= h((string) ($githubRelease["published_at_utc"] ?? "unknown")) ?></p>
            <p class="content-text mb-0"><strong>Downloaded now:</strong> <?= ($report["downloaded_now"] ?? false) ? "Yes" : "No (cached release reused)" ?></p>
            <p class="content-text mb-0"><strong>Cache path:</strong> <?= h((string) ($report["download_cache_path"] ?? "unknown")) ?></p>
          </div>
          <div>
            <p class="content-text mb-0"><strong>Update available:</strong> <?= ($githubRelease["has_newer_release"] ?? null) === true ? "Yes" : "No" ?></p>
            <?php if (trim((string) ($githubRelease["html_url"] ?? "")) !== ""): ?>
            <p class="content-text mb-0"><strong>Release page:</strong> <a href="<?= h((string) $githubRelease["html_url"]) ?>" target="_blank" rel="noreferrer"><?= h((string) $githubRelease["html_url"]) ?></a></p>
            <?php endif; ?>
          </div>
        </div>
        <?php if (trim((string) ($githubRelease["notes"] ?? "")) !== ""): ?>
        <div class="form-message mt-3 framework-update-release-notes" role="status">
          <h3 class="form-message-title">What is new in this release</h3>
          <p class="form-message-text mb-0"><?= nl2br(h((string) $githubRelease["notes"])) ?></p>
        </div>
        <?php endif; ?>
        <?php if (trim((string) ($report["release_skip_reason"] ?? "")) !== ""): ?>
        <div class="form-message mt-3" role="status">
          <h3 class="form-message-title">GitHub release decision</h3>
          <p class="form-message-text mb-0"><?= h((string) $report["release_skip_reason"]) ?></p>
        </div>
        <?php endif; ?>
      </article>
      <?php endif; ?>

      <?php if (!empty($reportUpdates)): ?>
      <article class="feature-card mb-lg">
        <h3 class="content-title">Automatic framework changes ready</h3>
        <ul class="contact-list">
          <?php foreach ($reportUpdates as $path => $update): ?>
          <li>
            <strong><?= h($updateActionLabel((array) $update)) ?></strong> <?= h((string) $path) ?>
            <?php if (trim((string) ($update["reason"] ?? "")) !== ""): ?>
            <br><?= h((string) $update["reason"]) ?>
            <?php endif; ?>
          </li>
          <?php endforeach; ?>
        </ul>
      </article>
      <?php endif; ?>

      <?php if (!empty($reportConflicts)): ?>
      <article class="feature-card mb-lg">
        <h3 class="content-title">Conflicts that need manual review</h3>
        <ul class="contact-list">
          <?php foreach ($reportConflicts as $path => $conflict): ?>
          <li>
            <strong><?= h((string) $path) ?></strong> - <?= h((string) ($conflict["reason"] ?? "conflict")) ?>
            <?php if (trim((string) ($conflict["summary"] ?? "")) !== ""): ?>
            <br><?= h((string) $conflict["summary"]) ?>
            <?php endif; ?>
            <?php if (trim((string) ($conflict["next_step"] ?? "")) !== ""): ?>
            <br><strong>Next step:</strong> <?= h((string) $conflict["next_step"]) ?>
            <?php endif; ?>
          </li>
          <?php endforeach; ?>
        </ul>
      </article>
      <?php endif; ?>

      <?php if (!empty($reportLocalOnlyChanges)): ?>
      <article class="feature-card">
        <h3 class="content-title">Local-only managed changes preserved</h3>
        <ul class="contact-list">
          <?php foreach ($reportLocalOnlyChanges as $path => $change): ?>
          <li><strong><?= h((string) $path) ?></strong> - <?= h((string) ($change["reason"] ?? "local change")) ?></li>
          <?php endforeach; ?>
        </ul>
      </article>
      <?php endif; ?>

      <?php if (!empty($report["post_install_checks"])): ?>
      <article class="feature-card mt-4">
        <h3 class="content-title">Post-install checks</h3>
        <ul class="contact-list">
          <?php foreach ((array) $report["post_install_checks"] as $check): ?>
          <?php if (!is_array($check)) { continue; } ?>
          <li>
            <strong><?= h((string) ($check["label"] ?? "Check")) ?></strong>
            - <?= h((string) ($check["status"] ?? "unknown")) ?>
            <?php if (($check["exit_code"] ?? null) !== null): ?>
            (exit <?= h((string) $check["exit_code"]) ?>)
            <?php endif; ?>
          </li>
          <?php endforeach; ?>
        </ul>
      </article>
      <?php endif; ?>
    </section>
  </div>
</section>
<?php endif; ?>

<div class="modal" id="framework-update-progress-modal" data-fnlla-modal role="dialog" aria-modal="true" aria-labelledby="framework-update-progress-title" hidden>
  <div class="modal-content">
    <div class="d-flex justify-between items-center mb-3">
      <div>
        <h2 class="content-title mb-0" id="framework-update-progress-title">Framework update in progress</h2>
        <p class="content-text mb-0" data-framework-update-progress-copy>Please keep this page open while FNLLA prepares the update workflow.</p>
      </div>
    </div>
    <div class="progress-field mb-3">
      <div class="progress-meta">
        <span class="progress-label" data-framework-update-progress-label>Preparing maintenance request</span>
        <span class="progress-value" data-framework-update-progress-value>5%</span>
      </div>
      <div class="progress">
        <div class="progress-bar" data-framework-update-progress-bar style="width: 5%"></div>
      </div>
    </div>
    <ul class="progress-steps" data-framework-update-progress-steps aria-label="Framework update progress stages">
      <li class="progress-step is-active">
        <p class="progress-step-label">Preparing the maintenance request.</p>
        <p class="progress-step-meta">The browser is packaging the selected mode and source details before the server-side workflow starts.</p>
      </li>
      <li class="progress-step">
        <p class="progress-step-label">Contacting the selected update source.</p>
        <p class="progress-step-meta">The maintenance flow resolves the GitHub release cache or the maintained local source checkout.</p>
      </li>
      <li class="progress-step">
        <p class="progress-step-label">Building the framework update report.</p>
        <p class="progress-step-meta">FNLLA compares framework-managed files and prepares the structured result for the operator.</p>
      </li>
      <li class="progress-step">
        <p class="progress-step-label">Waiting for the final response.</p>
        <p class="progress-step-meta">The last stage captures any safe apply work plus post-install checks before the report is shown.</p>
      </li>
    </ul>
    <p class="help-text mb-0">This modal is an operator-facing execution guide. The final authoritative outcome still comes from the structured report, release notes and post-install validation summary returned after the request completes.</p>
  </div>
</div>

<script>
  window.addEventListener("DOMContentLoaded", function () {
    var form = document.querySelector("[data-framework-update-form]");
    var progressModal = document.querySelector("#framework-update-progress-modal");
    var progressBar = document.querySelector("[data-framework-update-progress-bar]");
    var progressLabel = document.querySelector("[data-framework-update-progress-label]");
    var progressValue = document.querySelector("[data-framework-update-progress-value]");
    var progressCopy = document.querySelector("[data-framework-update-progress-copy]");
    var progressList = document.querySelector("[data-framework-update-progress-steps]");
    var activeSubmitter = null;
    var timerId = null;

    if (!form || !progressModal || !progressBar || !progressLabel || !progressValue || !progressCopy || !progressList) {
      return;
    }

    var progressDefinitions = {
      "github-check": {
        copy: "FNLLA is checking the latest published GitHub release, updating the local cache and preparing a drift report.",
        steps: [
          {
            label: "Checking the latest published GitHub release metadata.",
            meta: "Reads the release channel and confirms whether a newer framework baseline is available for this project."
          },
          {
            label: "Downloading or reusing the cached FNLLA release source.",
            meta: "Prepares a local release snapshot so repeat checks stay fast and deterministic."
          },
          {
            label: "Exporting a fresh project baseline from the cached release.",
            meta: "Creates a clean framework reference that matches the published starter contract."
          },
          {
            label: "Comparing framework-managed files against the current application.",
            meta: "Builds the operator report with release notes, drift details and actionable follow-up."
          }
        ]
      },
      "github-apply": {
        copy: "FNLLA is applying the cached GitHub-backed update and then running post-install validation checks.",
        steps: [
          {
            label: "Checking the latest published GitHub release metadata.",
            meta: "Confirms the release source and verifies that the cached baseline is still the correct target."
          },
          {
            label: "Downloading or reusing the cached FNLLA release source.",
            meta: "Prepares the same release snapshot used by the report so the apply run stays auditable."
          },
          {
            label: "Applying safe framework-managed changes from the cached release.",
            meta: "Updates only the framework-owned surfaces that the safe-apply contract allows."
          },
          {
            label: "Running post-install checks for contract, tests, lint and version metadata.",
            meta: "Collects the validation outcome that confirms whether the project stayed healthy after the update."
          }
        ]
      },
      "check": {
        copy: "FNLLA is comparing this application against the selected maintained source repository.",
        steps: [
          {
            label: "Resolving the maintained local source repository.",
            meta: "Finds the local maintainer checkout or the explicit path provided by the operator."
          },
          {
            label: "Exporting a fresh project baseline from that source.",
            meta: "Creates a clean application reference from the maintained framework source."
          },
          {
            label: "Comparing framework-managed files against the current application.",
            meta: "Detects starter drift without touching project-owned business logic."
          },
          {
            label: "Preparing the structured drift report.",
            meta: "Formats the findings so teams can review changes before deciding whether to apply them."
          }
        ]
      },
      "apply": {
        copy: "FNLLA is applying safe changes from the selected maintained source repository and then running post-install checks.",
        steps: [
          {
            label: "Resolving the maintained local source repository.",
            meta: "Locks the update source before any file changes are considered."
          },
          {
            label: "Exporting a fresh project baseline from that source.",
            meta: "Builds the clean reference used to decide which framework-managed files are safe to update."
          },
          {
            label: "Applying safe framework-managed changes.",
            meta: "Updates approved framework surfaces while leaving project-owned customization in place."
          },
          {
            label: "Running post-install checks for contract, tests, lint and version metadata.",
            meta: "Validates the updated project so operators can trust the final result."
          }
        ]
      }
    };

    form.querySelectorAll("button[type='submit']").forEach(function (button) {
      button.addEventListener("click", function () {
        activeSubmitter = button;
      });
    });

    form.addEventListener("submit", function (event) {
      var submitter = event.submitter || activeSubmitter;
      var mode = submitter ? submitter.getAttribute("data-framework-update-progress-mode") : "check";
      var definition = progressDefinitions[mode] || progressDefinitions.check;
      var steps = definition.steps.slice();
      var progressStops = [12, 38, 68, 92];
      var stepIndex = 0;

      progressCopy.textContent = definition.copy;
      progressList.innerHTML = "";

      steps.forEach(function (step, index) {
        var item = document.createElement("li");
        var label = document.createElement("p");
        var meta = document.createElement("p");

        item.className = "progress-step" + (index === 0 ? " is-active" : "");
        label.className = "progress-step-label";
        label.textContent = step.label;
        meta.className = "progress-step-meta";
        meta.textContent = step.meta;
        item.appendChild(label);
        item.appendChild(meta);
        progressList.appendChild(item);
      });

      var stepItems = progressList.querySelectorAll(".progress-step");

      var applyProgressState = function (index) {
        var percent = progressStops[Math.min(index, progressStops.length - 1)];
        var step = steps[Math.min(index, steps.length - 1)];

        progressBar.style.width = percent + "%";
        progressValue.textContent = percent + "%";
        progressLabel.textContent = step.label;

        stepItems.forEach(function (item, itemIndex) {
          item.classList.remove("is-active", "is-complete");

          if (itemIndex < index) {
            item.classList.add("is-complete");
            return;
          }

          if (itemIndex === index) {
            item.classList.add("is-active");
          }
        });
      };

      applyProgressState(stepIndex);

      if (window.FNLLARUNTIME && typeof window.FNLLARUNTIME.showModal === "function") {
        window.FNLLARUNTIME.showModal("#framework-update-progress-modal");
      } else {
        progressModal.hidden = false;
        progressModal.classList.add("is-open");
      }

      if (timerId) {
        window.clearInterval(timerId);
      }

      timerId = window.setInterval(function () {
        if (stepIndex >= steps.length - 1) {
          window.clearInterval(timerId);
          return;
        }

        stepIndex += 1;
        applyProgressState(stepIndex);
      }, 1400);
    });
  });
</script>
