<?php

declare(strict_types=1);

$developerPanelTitle = "Setup Checklist";
$developerPanelLead = "Readiness checklist for identity, private access, preview mode and public visibility.";
$checklist = is_array($projectSetupChecklist ?? null) ? (array) $projectSetupChecklist : [];
$checklistItems = array_values((array) ($checklist["items"] ?? []));
$summary = (array) ($checklist["summary"] ?? []);
$readyCount = (int) ($checklist["ready_count"] ?? 0);
$totalCount = max(1, (int) ($checklist["total_count"] ?? count($checklistItems)));
$readyPercent = max(0, min(100, (int) round(($readyCount / $totalCount) * 100)));
require __DIR__ . "/panel-header.php";
?>

        <section class="developer-dashboard-section" id="developer-setup-checklist" aria-label="Project setup checklist">
          <div class="developer-panel-intro">
            <div class="developer-panel-intro-copy">
              <p class="feature-kicker">Project setup</p>
              <h2 class="developer-dashboard-section-title">Confirm the project is named, reachable, private where needed and safe to hand over.</h2>
              <p class="content-text mb-0">This checklist reads current environment and developer-panel state. It does not save anything by itself.</p>
            </div>
            <div class="developer-panel-intro-actions">
              <a class="btn btn-outline btn-sm" href="<?= h((string) ($developerLinks["identity"] ?? route("developer.panel.project_identity"))) ?>">Identity</a>
              <a class="btn btn-outline btn-sm" href="<?= h((string) ($developerLinks["project_settings"] ?? route("developer.panel.project_settings"))) ?>">Preview</a>
            </div>
          </div>

          <div class="developer-dashboard-status-grid">
            <article class="developer-dashboard-status-card developer-setup-progress-card">
              <div class="developer-dashboard-card-head"><strong>Setup progress</strong><span class="developer-dashboard-ok"><?= h((string) $readyPercent) ?>%</span></div>
              <h3><?= h((string) $readyCount) ?> of <?= h((string) $totalCount) ?> ready</h3>
              <div class="developer-setup-progress" aria-label="Setup progress">
                <span style="width: <?= h((string) $readyPercent) ?>%;"></span>
              </div>
              <p>Review items can ship locally, but should be checked before client handover.</p>
            </article>
            <article class="developer-dashboard-status-card">
              <div class="developer-dashboard-card-head"><strong>Ready</strong><span class="developer-dashboard-ok"><?= h((string) ($summary["ready"] ?? 0)) ?></span></div>
              <h3>Configured</h3>
              <p>Core setup items already have usable values.</p>
            </article>
            <article class="developer-dashboard-status-card">
              <div class="developer-dashboard-card-head"><strong>Review</strong><span class="developer-dashboard-ok"><?= h((string) (($summary["review"] ?? 0) + ($summary["attention"] ?? 0))) ?></span></div>
              <h3>Needs a decision</h3>
              <p>These are usually security, preview or visibility choices.</p>
            </article>
            <article class="developer-dashboard-status-card">
              <div class="developer-dashboard-card-head"><strong>Optional</strong><span class="developer-dashboard-ok"><?= h((string) ($summary["optional"] ?? 0)) ?></span></div>
              <h3>Can stay empty</h3>
              <p>Optional project details should stay blank unless they help this client build.</p>
            </article>
          </div>

          <div class="developer-setup-checklist-grid">
            <?php foreach ($checklistItems as $item): ?>
            <?php
            $status = (string) ($item["status"] ?? "review");
            $statusClass = in_array($status, ["ready", "review", "attention", "optional"], true) ? $status : "review";
            ?>
            <article class="developer-setup-check-card is-<?= h($statusClass) ?>">
              <div class="developer-dashboard-card-head">
                <strong><?= h((string) ($item["label"] ?? "Setup item")) ?></strong>
                <span class="developer-dashboard-ok"><?= h((string) ($item["status_label"] ?? ucfirst($statusClass))) ?></span>
              </div>
              <p><?= h((string) ($item["text"] ?? "")) ?></p>
              <?php if ((string) ($item["href"] ?? "") !== ""): ?>
              <a class="btn btn-outline btn-sm" href="<?= h((string) $item["href"]) ?>">Open setting</a>
              <?php endif; ?>
            </article>
            <?php endforeach; ?>
          </div>
        </section>

<?php require __DIR__ . "/panel-footer.php"; ?>
