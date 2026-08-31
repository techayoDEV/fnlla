<?php

declare(strict_types=1);

$developerPanelTitle = "Notifications";
$developerPanelLead = "Actionable Developer Panel alerts shared by the project readiness surface.";
$report = is_array($notificationsReport ?? null) ? (array) $notificationsReport : [];
$items = (array) ($report["items"] ?? []);
$archivedItems = (array) ($report["archived_items"] ?? []);
$actionRoute = (string) ($developerLinks["notifications_action"] ?? route("developer.panel.notifications.action"));
$severityCounts = ["critical" => 0, "warning" => 0, "success" => 0, "info" => 0];
foreach ($items as $item) {
    $severity = strtolower((string) ($item["severity"] ?? "info"));
    $severityCounts[$severity] = ($severityCounts[$severity] ?? 0) + 1;
}
require __DIR__ . "/panel-header.php";
?>

        <section class="developer-dashboard-section" aria-label="Developer notifications">
          <div class="developer-panel-intro">
            <div class="developer-panel-intro-copy">
              <p class="feature-kicker">Notification center</p>
              <h2 class="developer-dashboard-section-title">Prioritized alerts from access, readiness, analytics and framework checks.</h2>
              <p class="content-text mb-0">The header bell shows the same queue, while this page keeps the full triage view available for release work.</p>
            </div>
            <div class="developer-panel-intro-actions">
              <a class="btn btn-outline btn-sm" href="<?= h((string) ($developerLinks["release_readiness"] ?? route("developer.panel.release_readiness"))) ?>">Readiness</a>
              <a class="btn btn-outline btn-sm" href="<?= h((string) ($developerLinks["operations"] ?? route("developer.panel.operations"))) ?>">Operations</a>
            </div>
          </div>

          <div class="developer-dashboard-status-grid">
            <article class="developer-dashboard-status-card">
              <div class="developer-dashboard-card-head"><strong>Open alerts</strong><span class="developer-dashboard-ok"><?= h((string) ($report["unread_count"] ?? 0)) ?></span></div>
              <h3><?= h((string) ($report["unread_count"] ?? 0)) ?> active</h3>
              <p>Actionable items currently visible in the panel.</p>
            </article>
            <article class="developer-dashboard-status-card">
              <div class="developer-dashboard-card-head"><strong>Critical</strong><span class="developer-dashboard-ok"><?= h((string) ($severityCounts["critical"] ?? 0)) ?></span></div>
              <h3><?= h((string) ($severityCounts["critical"] ?? 0)) ?> blockers</h3>
              <p>Review these before sharing or releasing.</p>
            </article>
            <article class="developer-dashboard-status-card">
              <div class="developer-dashboard-card-head"><strong>Warnings</strong><span class="developer-dashboard-ok"><?= h((string) ($severityCounts["warning"] ?? 0)) ?></span></div>
              <h3><?= h((string) ($severityCounts["warning"] ?? 0)) ?> warnings</h3>
              <p>Usually configuration or readiness follow-up.</p>
            </article>
            <article class="developer-dashboard-status-card">
              <div class="developer-dashboard-card-head"><strong>Archived</strong><span class="developer-dashboard-ok"><?= h((string) ($report["archived_count"] ?? 0)) ?></span></div>
              <h3><?= h((string) ($report["archived_count"] ?? 0)) ?> hidden</h3>
              <p>Restorable items kept out of the active queue.</p>
            </article>
          </div>

          <div class="developer-notification-grid">
            <?php foreach ($items as $item): ?>
            <?php $severity = (string) ($item["severity"] ?? "info"); ?>
            <article class="developer-notification-card developer-notification-card-<?= h($severity) ?>">
              <div class="developer-notification-head">
                <div>
                  <p class="feature-kicker"><?= h($severity) ?></p>
                  <strong><?= h((string) ($item["title"] ?? "Notification")) ?></strong>
                </div>
              </div>
              <p class="content-text"><?= h((string) ($item["text"] ?? "")) ?></p>
              <div class="developer-notification-actions">
                <span class="developer-dashboard-status <?= ((string) ($item["acknowledged_at"] ?? "") !== "" || $severity === "success") ? "is-active" : "is-neutral" ?>"><?= (string) ($item["acknowledged_at"] ?? "") !== "" ? "Read" : h((string) ($item["action"] ?? "Review")) ?></span>
                <small><?= h((string) ($item["key"] ?? "fnlla-notification")) ?></small>
              </div>
              <div class="developer-inline-actions">
                <?php if ((string) ($item["acknowledged_at"] ?? "") === "" && $severity !== "success"): ?>
                <form action="<?= h($actionRoute) ?>" method="post">
                  <?= csrf_field() ?>
                  <input type="hidden" name="developer_notification_key" value="<?= h((string) ($item["key"] ?? "")) ?>">
                  <input type="hidden" name="developer_notification_action" value="acknowledge">
                  <button class="btn btn-outline btn-sm" type="submit">Mark read</button>
                </form>
                <?php endif; ?>
                <form action="<?= h($actionRoute) ?>" method="post">
                  <?= csrf_field() ?>
                  <input type="hidden" name="developer_notification_key" value="<?= h((string) ($item["key"] ?? "")) ?>">
                  <input type="hidden" name="developer_notification_action" value="archive">
                  <button class="btn btn-ghost btn-sm" type="submit">Archive</button>
                </form>
              </div>
            </article>
            <?php endforeach; ?>
            <?php if ($items === []): ?>
            <article class="developer-notification-card">
              <div class="developer-notification-head">
                <div>
                  <p class="feature-kicker">clear</p>
                  <strong>No notifications</strong>
                </div>
              </div>
              <p class="content-text">The Developer Panel did not detect any action items for this project.</p>
            </article>
            <?php endif; ?>
          </div>
        </section>

        <?php if ($archivedItems !== []): ?>
        <section class="developer-dashboard-section" aria-label="Archived developer notifications">
          <div class="developer-dashboard-section-head">
            <h2 class="developer-dashboard-section-title">Archived notifications</h2>
            <span class="developer-dashboard-refresh">Restore if the item needs attention again</span>
          </div>
          <div class="developer-dashboard-glance-table">
            <?php foreach ($archivedItems as $item): ?>
            <div class="developer-dashboard-glance-row">
              <strong><?= h((string) ($item["title"] ?? "Notification")) ?></strong>
              <span><?= h((string) (($item["archived_at"] ?? "") ?: "archived")) ?></span>
              <form action="<?= h($actionRoute) ?>" method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="developer_notification_key" value="<?= h((string) ($item["key"] ?? "")) ?>">
                <input type="hidden" name="developer_notification_action" value="restore">
                <button class="btn btn-outline btn-sm" type="submit">Restore</button>
              </form>
            </div>
            <?php endforeach; ?>
          </div>
        </section>
        <?php endif; ?>

<?php require __DIR__ . "/panel-footer.php"; ?>
