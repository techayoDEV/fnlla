<?php

declare(strict_types=1);

$developerPanelTitle = "Panel Settings";
$developerPanelLead = "Entry route, session window and developer navigation behavior.";
$developerPath = (string) ($developerAccess["path"] ?? "/developer");
$navMode = (string) ($developerAccess["operations_nav_mode"] ?? "hidden");
$sessionMinutes = (int) ($developerAccess["unlock_ttl_minutes"] ?? 120);
$absoluteMinutes = (int) ($developerAccess["absolute_ttl_minutes"] ?? 480);
require __DIR__ . "/panel-header.php";
?>

        <section class="developer-dashboard-section" id="developer-panel-settings" aria-label="Developer panel settings">
          <div class="developer-panel-intro">
            <div class="developer-panel-intro-copy">
              <p class="feature-kicker">Runtime and storage</p>
              <h2 class="developer-dashboard-section-title">Keep the private entry, session window and local storage contract predictable.</h2>
              <p class="content-text mb-0">Public navigation stays client-safe. Developer links appear through the private entry or an active developer session.</p>
            </div>
            <div class="developer-panel-intro-actions">
              <a class="btn btn-outline btn-sm" href="<?= h((string) ($developerLinks["release_readiness"] ?? route("developer.panel.release_readiness"))) ?>">Readiness</a>
              <a class="btn btn-outline btn-sm" href="<?= h((string) ($developerLinks["overview"] ?? route("developer.panel"))) ?>">Dashboard</a>
            </div>
          </div>

          <div class="developer-dashboard-status-grid">
            <article class="developer-dashboard-status-card">
              <div class="developer-dashboard-card-head"><strong>Private entry</strong><span class="developer-dashboard-ok">FIXED</span></div>
              <h3><code><?= h($developerPath) ?></code></h3>
              <p>Locked users see sign-in; unlocked sessions return to the panel.</p>
            </article>
            <article class="developer-dashboard-status-card">
              <div class="developer-dashboard-card-head"><strong>Navigation mode</strong><span class="developer-dashboard-ok"><?= h($navMode === "hidden" ? "FOOTER" : "SESSION") ?></span></div>
              <h3><?= $navMode === "hidden" ? "Private entry visible" : "Session-only menu" ?></h3>
              <p>Client-facing navigation remains clean.</p>
            </article>
            <article class="developer-dashboard-status-card">
              <div class="developer-dashboard-card-head"><strong>Session window</strong><span class="developer-dashboard-ok"><?= h((string) $sessionMinutes) ?>M</span></div>
              <h3><?= h((string) $sessionMinutes) ?> minutes</h3>
              <p>Regular developer unlock duration.</p>
            </article>
            <article class="developer-dashboard-status-card">
              <div class="developer-dashboard-card-head"><strong>Absolute window</strong><span class="developer-dashboard-ok"><?= h((string) $absoluteMinutes) ?>M</span></div>
              <h3><?= h((string) $absoluteMinutes) ?> minutes</h3>
              <p>Maximum lifetime before a fresh sign-in.</p>
            </article>
          </div>

          <div class="developer-panel-form-grid">
            <article class="developer-panel-fieldset-card">
              <p class="feature-kicker">Session settings</p>
              <h2 class="content-title">Save panel settings</h2>
              <form class="form stack gap-md" action="<?= h(route("developer.settings.panel")) ?>" method="post" novalidate>
                <?= csrf_field() ?>
                <div class="form-group">
                  <label class="label" for="developer-operations-nav-mode">Developer navigation mode</label>
                  <select class="select" id="developer-operations-nav-mode" name="developer_operations_nav_mode">
                    <option value="hidden" <?= $navMode === "hidden" ? "selected" : "" ?>>Private entry and unlocked-session menu</option>
                    <option value="developer_session_only" <?= $navMode === "developer_session_only" ? "selected" : "" ?>>Unlocked-session menu only</option>
                  </select>
                  <p class="help-text">Use session-only mode when the public footer should not advertise developer entry.</p>
                </div>
                <div class="developer-modal-form-grid">
                  <div class="form-group">
                    <label class="label" for="developer-access-ttl-minutes">Session window in minutes</label>
                    <input class="input" id="developer-access-ttl-minutes" name="developer_access_ttl_minutes" type="number" min="5" max="240" step="1" value="<?= h((string) $sessionMinutes) ?>" required>
                  </div>
                  <div class="form-group">
                    <label class="label" for="developer-access-absolute-ttl-minutes">Absolute window in minutes</label>
                    <input class="input" id="developer-access-absolute-ttl-minutes" name="developer_access_absolute_ttl_minutes" type="number" min="5" max="720" step="1" value="<?= h((string) $absoluteMinutes) ?>" required>
                  </div>
                </div>
                <button class="btn btn-primary" type="submit">Save panel settings</button>
              </form>
            </article>

            <article class="developer-panel-fieldset-card">
              <p class="feature-kicker">Storage mode</p>
              <h2 class="content-title">Workspace and audit storage</h2>
              <div class="developer-dashboard-glance-table">
                <div class="developer-dashboard-glance-row"><strong>Audit log</strong><span><?= h((string) config("developer_control.activity_log_driver", "file")) ?></span></div>
                <div class="developer-dashboard-glance-row"><strong>Workspace</strong><span><?= h((string) config("developer_workspace.driver", "file")) ?></span></div>
                <div class="developer-dashboard-glance-row"><strong>Install SQL</strong><span><code>php fnlla developer:install-storage --sql</code></span></div>
                <div class="developer-dashboard-glance-row"><strong>Install tables</strong><span><code>php fnlla developer:install-storage</code></span></div>
              </div>
              <div class="developer-panel-status-note">
                <strong>Production note</strong>
                <span>File storage is portable for starter projects. Switch to database-backed storage after credentials, backups and migrations are in place.</span>
              </div>
            </article>
          </div>
        </section>

<?php require __DIR__ . "/panel-footer.php"; ?>
