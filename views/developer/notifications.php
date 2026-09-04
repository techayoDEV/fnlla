<?php

declare(strict_types=1);

$developerPanelTitle = "Notifications";
$developerPanelLead = "Actionable Developer Panel alerts shared by the dashboard bell and release surfaces.";
$report = is_array($notificationsReport ?? null) ? (array) $notificationsReport : [];
$items = array_values((array) ($report["items"] ?? []));
$archivedItems = array_values((array) ($report["archived_items"] ?? []));
$actionRoute = (string) ($developerLinks["notifications_action"] ?? route("developer.panel.notifications.action"));
$severityCounts = ["critical" => 0, "warning" => 0, "success" => 0, "info" => 0];

foreach ($items as $item) {
    $severity = strtolower((string) ($item["severity"] ?? "info"));
    $severityCounts[$severity] = ($severityCounts[$severity] ?? 0) + 1;
}

$formatNotificationTime = static function (string $value): string {
    $value = trim($value);

    if ($value === "") {
        return "Not recorded";
    }

    try {
        return (new \DateTimeImmutable($value))->setTimezone(new \DateTimeZone("UTC"))->format("d M Y, H:i") . " UTC";
    } catch (\Throwable) {
        return $value;
    }
};

require __DIR__ . "/panel-header.php";
?>

        <section class="developer-dashboard-section" aria-label="Developer notifications">
          <div class="developer-panel-intro">
            <div class="developer-panel-intro-copy">
              <p class="feature-kicker">Notification center</p>
              <h2 class="developer-dashboard-section-title">Prioritized alert list for release work.</h2>
              <p class="content-text mb-0">The header bell and dashboard drawer use this same queue. This page keeps the full review, read and archive workflow in one place.</p>
            </div>
            <div class="developer-panel-intro-actions">
              <a class="btn btn-outline btn-sm" href="<?= h((string) ($developerLinks["release_readiness"] ?? route("developer.panel.release_readiness"))) ?>">Readiness</a>
              <a class="btn btn-outline btn-sm" href="<?= h((string) ($developerLinks["framework_updates"] ?? route("developer.panel.framework_updates"))) ?>">Updates</a>
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

          <div class="developer-notification-list">
            <?php foreach ($items as $item): ?>
            <?php
                $severity = strtolower((string) ($item["severity"] ?? "info"));
                $title = trim((string) ($item["title"] ?? ""));
                $text = trim((string) ($item["text"] ?? ""));
                $key = trim((string) ($item["key"] ?? "fnlla-notification"));
                $acknowledgedAt = trim((string) ($item["acknowledged_at"] ?? ""));
                $createdAt = (string) (($item["time"] ?? "") ?: (($item["created_at_utc"] ?? "") ?: ($item["updated_at_utc"] ?? "")));
                $acknowledgedBy = trim((string) ($item["acknowledged_by"] ?? ""));
                $source = \Fnlla\Php\Support\DeveloperNotificationCenter::sourceFor((array) $item);
                $reviewHref = \Fnlla\Php\Support\DeveloperNotificationCenter::routeFor((array) $item, $developerLinks);
                $statusLabel = $acknowledgedAt !== "" || $severity === "success" ? "Read" : (string) ($item["action"] ?? "Review");
            ?>
            <article class="developer-notification-row developer-notification-row-<?= h($severity) ?>">
              <span class="developer-notification-marker" aria-hidden="true"></span>
              <div class="developer-notification-row-main">
                <div class="developer-notification-row-head">
                  <span class="developer-dashboard-status <?= $severity === "success" ? "is-active" : "is-neutral" ?>"><?= h(strtoupper($severity)) ?></span>
                  <strong><?= h($title !== "" ? $title : $source . " notification") ?></strong>
                </div>
                <p class="content-text"><?= h($text !== "" ? $text : "No extra detail was provided by this alert source.") ?></p>
                <div class="developer-notification-meta">
                  <span>Source: <?= h($source) ?></span>
                  <span>Status: <?= h($statusLabel) ?></span>
                  <span>Generated: <?= h($formatNotificationTime($createdAt)) ?></span>
                  <?php if ($acknowledgedAt !== ""): ?>
                  <span>Read: <?= h($formatNotificationTime($acknowledgedAt)) ?><?= $acknowledgedBy !== "" ? " by " . h($acknowledgedBy) : "" ?></span>
                  <?php endif; ?>
                  <span>Key: <?= h($key) ?></span>
                </div>
              </div>
              <div class="developer-notification-row-actions">
                <?php if ($severity !== "success"): ?>
                <form action="<?= h($actionRoute) ?>" method="post">
                  <?= csrf_field() ?>
                  <input type="hidden" name="developer_notification_key" value="<?= h($key) ?>">
                  <input type="hidden" name="developer_notification_action" value="review">
                  <input type="hidden" name="developer_notification_redirect" value="<?= h($reviewHref) ?>">
                  <button class="btn btn-outline btn-sm" type="submit">Review</button>
                </form>
                <?php else: ?>
                <a class="btn btn-outline btn-sm" href="<?= h($reviewHref) ?>">Review</a>
                <?php endif; ?>
                <?php if ($acknowledgedAt === "" && $severity !== "success"): ?>
                <form action="<?= h($actionRoute) ?>" method="post">
                  <?= csrf_field() ?>
                  <input type="hidden" name="developer_notification_key" value="<?= h($key) ?>">
                  <input type="hidden" name="developer_notification_action" value="acknowledge">
                  <button class="btn btn-outline btn-sm" type="submit">Mark read</button>
                </form>
                <?php endif; ?>
                <?php if ($severity !== "success"): ?>
                <form action="<?= h($actionRoute) ?>" method="post">
                  <?= csrf_field() ?>
                  <input type="hidden" name="developer_notification_key" value="<?= h($key) ?>">
                  <input type="hidden" name="developer_notification_action" value="archive">
                  <button class="btn btn-ghost btn-sm" type="submit">Archive</button>
                </form>
                <?php endif; ?>
              </div>
            </article>
            <?php endforeach; ?>
            <?php if ($items === []): ?>
            <article class="developer-notification-row developer-notification-row-success">
              <span class="developer-notification-marker" aria-hidden="true"></span>
              <div class="developer-notification-row-main">
                <div class="developer-notification-row-head">
                  <span class="developer-dashboard-status is-active">CLEAR</span>
                  <strong>No open notifications</strong>
                </div>
                <p class="content-text">The Developer Panel did not detect any action items for this project.</p>
                <div class="developer-notification-meta">
                  <span>Source: Developer Panel</span>
                  <span>Status: Clear</span>
                </div>
              </div>
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
          <div class="developer-notification-list developer-notification-list-archived">
            <?php foreach ($archivedItems as $item): ?>
            <?php
                $key = trim((string) ($item["key"] ?? "fnlla-notification"));
                $severity = strtolower((string) ($item["severity"] ?? "info"));
                $archivedBy = trim((string) ($item["archived_by"] ?? ""));
            ?>
            <article class="developer-notification-row developer-notification-row-<?= h($severity) ?>">
              <span class="developer-notification-marker" aria-hidden="true"></span>
              <div class="developer-notification-row-main">
                <div class="developer-notification-row-head">
                  <span class="developer-dashboard-status is-neutral">ARCHIVED</span>
                  <strong><?= h((string) ($item["title"] ?? "Notification")) ?></strong>
                </div>
                <div class="developer-notification-meta">
                  <span>Source: <?= h(\Fnlla\Php\Support\DeveloperNotificationCenter::sourceFor((array) $item)) ?></span>
                  <span>Archived: <?= h($formatNotificationTime((string) ($item["archived_at"] ?? ""))) ?><?= $archivedBy !== "" ? " by " . h($archivedBy) : "" ?></span>
                  <span>Key: <?= h($key) ?></span>
                </div>
              </div>
              <div class="developer-notification-row-actions">
                <form action="<?= h($actionRoute) ?>" method="post">
                  <?= csrf_field() ?>
                  <input type="hidden" name="developer_notification_key" value="<?= h($key) ?>">
                  <input type="hidden" name="developer_notification_action" value="restore">
                  <button class="btn btn-outline btn-sm" type="submit">Restore</button>
                </form>
              </div>
            </article>
            <?php endforeach; ?>
          </div>
        </section>
        <?php endif; ?>

<?php require __DIR__ . "/panel-footer.php"; ?>
