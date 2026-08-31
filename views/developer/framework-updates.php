<?php

declare(strict_types=1);

$developerPanelTitle = "Framework Updates";
$developerPanelLead = "Official FNLLA update checks, dry-runs and safe apply controls inside the developer workspace.";
$pageState = is_array($frameworkUpdatePageState ?? null) ? (array) $frameworkUpdatePageState : [];
$lock = is_array($frameworkUpdateLock ?? null) ? (array) $frameworkUpdateLock : [];
$cachedRelease = is_array($frameworkUpdateCachedRelease ?? null) ? (array) $frameworkUpdateCachedRelease : [];
$sourceDetection = is_array($frameworkUpdateSourceDetection ?? null) ? (array) $frameworkUpdateSourceDetection : [];
$frameworkMeta = (array) ($lock["framework_base"]["framework"] ?? []);
$managedFiles = (array) ($lock["framework_base"]["managed_files"] ?? []);
$cachedReleaseTag = trim((string) ($cachedRelease["tag"] ?? ""));
$detectedSourcePath = trim((string) ($sourceDetection["resolved_path"] ?? ""));
require __DIR__ . "/panel-header.php";
?>

        <section class="developer-dashboard-section" aria-label="Framework update command center">
          <div class="developer-panel-intro">
            <div class="developer-panel-intro-copy">
              <p class="feature-kicker">Framework update command center</p>
              <h2 class="developer-dashboard-section-title">Check, dry-run and apply only the safe part of official FNLLA updates.</h2>
              <p class="content-text mb-0">The workflow below keeps the source explicit, validates official release metadata and separates safe framework changes from files that need manual merge review.</p>
            </div>
            <div class="developer-panel-intro-actions">
              <a class="btn btn-outline btn-sm" href="<?= h((string) ($developerLinks["release_readiness"] ?? route("developer.panel.release_readiness"))) ?>">Readiness</a>
              <a class="btn btn-outline btn-sm" href="<?= h((string) ($developerLinks["operations"] ?? route("developer.panel.operations"))) ?>">Operations</a>
            </div>
          </div>

          <div class="developer-dashboard-status-grid">
            <article class="developer-dashboard-status-card">
              <div class="developer-dashboard-card-head"><strong>Current base</strong><span class="developer-dashboard-ok">FNLLA</span></div>
              <h3><?= h((string) ($frameworkMeta["version"] ?? "unknown")) ?></h3>
              <p>Framework version currently recorded in the lock file.</p>
            </article>
            <article class="developer-dashboard-status-card">
              <div class="developer-dashboard-card-head"><strong>Managed files</strong><span class="developer-dashboard-ok"><?= h((string) count($managedFiles)) ?></span></div>
              <h3><?= h((string) count($managedFiles)) ?> files</h3>
              <p>Tracked by <code>.fnlla/framework-lock.json</code>.</p>
            </article>
            <article class="developer-dashboard-status-card">
              <div class="developer-dashboard-card-head"><strong>Release channel</strong><span class="developer-dashboard-ok"><?= ($pageState["github_enabled"] ?? false) ? "ON" : "OFF" ?></span></div>
              <h3><?= $cachedReleaseTag !== "" ? h($cachedReleaseTag) : (($pageState["github_enabled"] ?? false) ? "Ready" : "Disabled") ?></h3>
              <p>Official GitHub channel only.</p>
            </article>
            <article class="developer-dashboard-status-card">
              <div class="developer-dashboard-card-head"><strong>Detected source</strong><span class="developer-dashboard-ok"><?= $detectedSourcePath !== "" ? "YES" : "NO" ?></span></div>
              <h3><?= h($detectedSourcePath !== "" ? "Source path found" : "Manual input") ?></h3>
              <p><?= h((string) ($sourceDetection["origin"] ?? "manual input required")) ?></p>
            </article>
          </div>
        </section>

<?php
require dirname(__DIR__) . "/maintenance/framework-update.php";
require __DIR__ . "/panel-footer.php";
