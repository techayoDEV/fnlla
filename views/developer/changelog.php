<?php

declare(strict_types=1);

$developerPanelTitle = "Project Changelog";
$developerPanelLead = "Project-specific change history made through the Developer Panel and local operations.";
$report = is_array($projectChangelogReport ?? null) ? $projectChangelogReport : [];
$items = array_values((array) ($report["items"] ?? []));
$categories = (array) ($report["categories"] ?? []);
$latestTime = (string) ($report["latest_time"] ?? "");
$latestHash = (string) ($report["latest_hash"] ?? "");
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

        <section class="developer-dashboard-section" aria-label="Project changelog overview">
          <div class="developer-panel-intro">
            <div class="developer-panel-intro-copy">
              <p class="feature-kicker">Project changelog</p>
              <h2 class="developer-dashboard-section-title">Shared project changes every developer should see before continuing work.</h2>
              <p class="content-text mb-0">This is the project changelog for the application built on FNLLA. It is generated from Developer Panel activity and local operational changes, not from FNLLA framework release notes.</p>
            </div>
            <div class="developer-panel-intro-actions">
              <a class="btn btn-outline btn-sm" href="<?= h((string) ($developerLinks["project_logs"] ?? route("developer.panel.project_logs"))) ?>">Project logs</a>
              <a class="btn btn-outline btn-sm" href="<?= h((string) ($developerLinks["operations"] ?? route("developer.panel.operations"))) ?>">Operations</a>
            </div>
          </div>

          <div class="developer-project-log-summary developer-project-changelog-summary">
            <article class="developer-dashboard-status-card">
              <div class="developer-dashboard-card-head">
                <strong>Changelog entries</strong>
                <span class="developer-dashboard-ok">PROJECT</span>
              </div>
              <h3><?= h((string) ($report["total"] ?? count($items))) ?></h3>
              <p>Shared project changes visible to every developer session.</p>
            </article>
            <article class="developer-dashboard-status-card">
              <div class="developer-dashboard-card-head">
                <strong>Today</strong>
              </div>
              <h3><?= h((string) ($report["today"] ?? 0)) ?></h3>
              <p>Project changes recorded since midnight UTC.</p>
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
                <strong>Change areas</strong>
              </div>
              <?php if ($categories === []): ?>
              <p>No project change categories have been recorded yet.</p>
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

        <section class="developer-dashboard-section" id="manual-changelog-entry" aria-label="Add manual project changelog entry">
          <div class="developer-dashboard-section-head">
            <div>
              <p class="feature-kicker">Manual changelog entry</p>
              <h2 class="developer-dashboard-section-title">Add a project-facing change that did not come from an automated panel action.</h2>
            </div>
            <a class="btn btn-outline btn-sm" href="<?= h((string) ($developerLinks["project_changelog"] ?? route("developer.panel.changelog"))) ?>#project-changelog-timeline">Refresh timeline</a>
          </div>
          <form class="developer-changelog-manual-form developer-panel-fieldset-card" action="<?= h(route("developer.panel.changelog.store")) ?>" method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="developer_changelog_seen_hash" value="<?= h($latestHash) ?>">
            <div class="developer-panel-form-grid is-stacked">
              <div class="form-group">
                <label for="developer-changelog-title">Title</label>
                <input class="input" id="developer-changelog-title" name="developer_changelog_title" type="text" maxlength="120" value="<?= h((string) old("developer_changelog_title")) ?>" placeholder="Public copy updated after client review" required>
                <?php if (error_for("title") !== null): ?><p class="help-text form-error"><?= h((string) error_for("title")) ?></p><?php endif; ?>
              </div>
              <div class="form-group">
                <label for="developer-changelog-text">Summary</label>
                <textarea class="textarea" id="developer-changelog-text" name="developer_changelog_text" rows="3" maxlength="240" placeholder="Shortly explain what changed and why every developer should see it." required><?= h((string) old("developer_changelog_text")) ?></textarea>
                <?php if (error_for("text") !== null): ?><p class="help-text form-error"><?= h((string) error_for("text")) ?></p><?php endif; ?>
              </div>
            </div>
            <div class="developer-changelog-manual-actions">
              <p class="content-text mb-0">Manual entries are appended to the shared activity log. They do not edit or replace entries created by another developer session.</p>
              <button class="btn btn-primary btn-sm" type="submit">Add changelog entry</button>
            </div>
          </form>
        </section>

        <section class="developer-dashboard-section" id="project-changelog-timeline" aria-label="Project changelog timeline">
          <div class="developer-dashboard-section-head">
            <h2 class="developer-dashboard-section-title">Project change timeline</h2>
            <span class="developer-dashboard-refresh">Latest 120 project events</span>
          </div>

          <?php if ($grouped === []): ?>
          <article class="developer-project-log-empty">
            <p class="feature-kicker">No project changelog entries yet</p>
            <h3>Project changes will appear after developers use the panel.</h3>
            <p class="content-text mb-0">Identity, workspace, access, preview, analytics, heatmap, service-control and integration changes are recorded as shared project history.</p>
          </article>
          <?php else: ?>
          <div class="developer-project-log-timeline developer-project-changelog-timeline">
            <?php foreach ($grouped as $day => $events): ?>
            <section class="developer-project-log-day" aria-label="<?= h((string) $day) ?>">
              <div class="developer-project-log-day-head">
                <span><?= h((string) $day) ?></span>
                <em><?= h((string) count($events)) ?> entries</em>
              </div>
              <?php foreach ($events as $event): ?>
              <?php
                $developer = (array) ($event["developer"] ?? []);
                $category = (string) ($event["category"] ?? "Project");
                $action = (string) ($event["action"] ?? "activity");
                $eventTime = (string) ($event["time"] ?? "");
                $actor = (string) (($developer["name"] ?? "") ?: ($developer["email"] ?? "") ?: "Developer");
                $hash = (string) ($event["event_hash"] ?? "");
              ?>
              <article class="developer-project-log-row is-<?= h($categoryKey($category)) ?>">
                <span class="developer-project-log-marker" aria-hidden="true"><?= h(strtoupper(substr($category, 0, 2))) ?></span>
                <div class="developer-project-log-main">
                  <div class="developer-project-log-row-head">
                    <strong><?= h((string) ($event["title"] ?? "Project change")) ?></strong>
                    <span><?= h($category) ?></span>
                  </div>
                  <p><?= h((string) ($event["text"] ?? "No description was recorded.")) ?></p>
                  <div class="developer-project-log-facts">
                    <span><b>By</b> <?= h($actor) ?></span>
                    <span><b>Action</b> <?= h($action) ?></span>
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
