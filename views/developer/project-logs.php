<?php

declare(strict_types=1);

$developerPanelTitle = "Project Logs";
$developerPanelLead = "Human-readable project activity, developer changes and internal delivery history.";
$report = is_array($projectLogReport ?? null) ? $projectLogReport : [];
$items = array_values((array) ($report["items"] ?? []));
$categories = (array) ($report["categories"] ?? []);
$latestTime = (string) ($report["latest_time"] ?? "");
$formatDate = static function (string $time): string {
    if ($time === "") {
        return "No timestamp";
    }

    try {
        return (new DateTimeImmutable($time))->setTimezone(new DateTimeZone(date_default_timezone_get()))->format("d M Y");
    } catch (Throwable) {
        return "No timestamp";
    }
};
$formatTime = static function (string $time): string {
    if ($time === "") {
        return "Time not recorded";
    }

    try {
        return (new DateTimeImmutable($time))->setTimezone(new DateTimeZone(date_default_timezone_get()))->format("H:i");
    } catch (Throwable) {
        return "Time not recorded";
    }
};
$categoryKey = static fn (string $category): string => strtolower((string) preg_replace('/[^a-z0-9]+/', "-", strtolower($category)));
$grouped = [];

foreach ($items as $item) {
    if (!is_array($item)) {
        continue;
    }

    $grouped[$formatDate((string) ($item["time"] ?? ""))][] = $item;
}

require __DIR__ . "/panel-header.php";
?>

        <section class="developer-dashboard-section" aria-label="Project log overview">
          <div class="developer-panel-intro">
            <div class="developer-panel-intro-copy">
              <p class="feature-kicker">Project logs</p>
              <h2 class="developer-dashboard-section-title">Readable timeline of project changes, developer work and operational actions.</h2>
              <p class="content-text mb-0">This page uses the same activity source as the audit exports, but groups the record into practical daily change history for handover and release review.</p>
            </div>
            <div class="developer-panel-intro-actions">
              <a class="btn btn-outline btn-sm" href="<?= h((string) ($developerLinks["operations"] ?? route("developer.panel.operations"))) ?>">Operations</a>
              <a class="btn btn-outline btn-sm" href="<?= h((string) ($developerLinks["audit_export"] ?? route("developer.panel.audit_export"))) ?>">Export JSON</a>
              <a class="btn btn-outline btn-sm" href="<?= h((string) ($developerLinks["audit_export_csv"] ?? route("developer.panel.audit_export_csv"))) ?>">Export CSV</a>
            </div>
          </div>

          <div class="developer-project-log-summary">
            <article class="developer-dashboard-status-card">
              <div class="developer-dashboard-card-head">
                <strong>Tracked events</strong>
                <span class="developer-dashboard-ok">LIVE</span>
              </div>
              <h3><?= h((string) ($report["total"] ?? count($items))) ?></h3>
              <p>Recent developer-panel events kept for project review.</p>
            </article>
            <article class="developer-dashboard-status-card">
              <div class="developer-dashboard-card-head">
                <strong>Today</strong>
              </div>
              <h3><?= h((string) ($report["today"] ?? 0)) ?></h3>
              <p>Changes recorded since midnight UTC.</p>
            </article>
            <article class="developer-dashboard-status-card">
              <div class="developer-dashboard-card-head">
                <strong>Last actor</strong>
              </div>
              <h3><?= h((string) ($report["last_actor"] ?? "No activity yet")) ?></h3>
              <p><?= h($latestTime !== "" ? "Latest entry at " . $formatDate($latestTime) . " " . $formatTime($latestTime) : "No latest entry recorded.") ?></p>
            </article>
            <article class="developer-dashboard-status-card">
              <div class="developer-dashboard-card-head">
                <strong>Categories</strong>
              </div>
              <?php if ($categories === []): ?>
              <p>No categories have been recorded yet.</p>
              <?php else: ?>
              <div class="developer-project-log-category-list">
                <?php foreach ($categories as $label => $count): ?>
                <span><?= h((string) $label) ?> <b><?= h((string) $count) ?></b></span>
                <?php endforeach; ?>
              </div>
              <?php endif; ?>
            </article>
          </div>
        </section>

        <section class="developer-dashboard-section" aria-label="Project activity timeline">
          <div class="developer-dashboard-section-head">
            <h2 class="developer-dashboard-section-title">Activity timeline</h2>
            <span class="developer-dashboard-refresh">Latest 120 events</span>
          </div>

          <?php if ($grouped === []): ?>
          <article class="developer-project-log-empty">
            <p class="feature-kicker">No project logs yet</p>
            <h3>Activity will appear here after developer-panel changes are made.</h3>
            <p class="content-text mb-0">Project setup updates, workspace changes, access changes, notifications and framework operations are recorded automatically.</p>
          </article>
          <?php else: ?>
          <div class="developer-project-log-timeline">
            <?php foreach ($grouped as $day => $events): ?>
            <section class="developer-project-log-day" aria-label="<?= h((string) $day) ?>">
              <div class="developer-project-log-day-head">
                <span><?= h((string) $day) ?></span>
                <em><?= h((string) count($events)) ?> events</em>
              </div>
              <?php foreach ($events as $event): ?>
              <?php
                $developer = (array) ($event["developer"] ?? []);
                $category = (string) ($event["category"] ?? "Project");
                $action = (string) ($event["action"] ?? "activity");
                $eventTime = (string) ($event["time"] ?? "");
                $actor = (string) (($developer["name"] ?? "") ?: ($developer["email"] ?? "") ?: "Developer");
                $email = (string) ($developer["email"] ?? "");
                $hash = (string) ($event["event_hash"] ?? "");
                $requestId = (string) ($event["request_id"] ?? "");
              ?>
              <article class="developer-project-log-row is-<?= h($categoryKey($category)) ?>">
                <span class="developer-project-log-marker" aria-hidden="true"><?= h(strtoupper(substr($category, 0, 2))) ?></span>
                <div class="developer-project-log-main">
                  <div class="developer-project-log-row-head">
                    <strong><?= h((string) ($event["title"] ?? "Developer change")) ?></strong>
                    <span><?= h($category) ?></span>
                  </div>
                  <p><?= h((string) ($event["text"] ?? "No description was recorded.")) ?></p>
                  <div class="developer-project-log-facts">
                    <span><b>By</b> <?= h($actor) ?></span>
                    <?php if ($email !== ""): ?>
                    <span><b>Email</b> <?= h($email) ?></span>
                    <?php endif; ?>
                    <span><b>Action</b> <?= h($action) ?></span>
                    <?php if ($requestId !== ""): ?>
                    <span><b>Request</b> <?= h($requestId) ?></span>
                    <?php endif; ?>
                    <?php if ($hash !== ""): ?>
                    <span><b>Chain</b> <?= h(substr($hash, 0, 12)) ?></span>
                    <?php endif; ?>
                  </div>
                </div>
                <time datetime="<?= h($eventTime) ?>"><?= h($formatTime($eventTime)) ?></time>
              </article>
              <?php endforeach; ?>
            </section>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </section>

<?php require __DIR__ . "/panel-footer.php"; ?>
