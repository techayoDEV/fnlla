<?php

declare(strict_types=1);

$developerPanelTitle = "Heatmap";
$developerPanelLead = "First-party click and scroll intelligence inspired by Microsoft Clarity, aggregated inside FNLLA.";
$report = is_array($heatmapReport ?? null) ? (array) $heatmapReport : [];
$summary = (array) ($report["summary"] ?? []);
$charts = (array) ($report["charts"] ?? []);
$privacy = (array) ($report["privacy"] ?? []);
$settings = (array) ($report["settings"] ?? []);
$insights = (array) ($report["insights"] ?? []);
$grid = (array) ($charts["top_page_click_grid"] ?? []);
$gridRows = (array) ($grid["rows"] ?? []);
$topPage = (string) ($grid["page"] ?? ($summary["top_page"] ?? "No page yet"));
$gridMax = max(1, (int) ($grid["max"] ?? 1));
$renderBarList = static function (array $items, string $empty): void { ?>
          <?php if ($items === []): ?>
          <p class="content-text mb-0"><?= h($empty) ?></p>
          <?php else: ?>
          <div class="developer-analytics-bars">
            <?php foreach ($items as $item): ?>
            <div class="developer-analytics-bar-row">
              <div>
                <strong><?= h((string) ($item["label"] ?? "")) ?></strong>
                <span><?= h((string) ($item["count"] ?? 0)) ?></span>
              </div>
              <i aria-hidden="true"><b style="width: <?= h((string) max(2, (int) ($item["percent"] ?? 0))) ?>%"></b></i>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
<?php };
$renderTimeline = static function (array $items, string $empty): void { ?>
          <?php if ($items === []): ?>
          <p class="content-text mb-0"><?= h($empty) ?></p>
          <?php else: ?>
          <div class="developer-analytics-timeline">
            <?php foreach ($items as $item): ?>
            <span title="<?= h((string) ($item["full_label"] ?? $item["label"] ?? "")) ?>">
              <i style="height: <?= h((string) max(4, (int) ($item["percent"] ?? 0))) ?>%"></i>
              <small><?= h((string) ($item["label"] ?? "")) ?></small>
            </span>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
<?php };
$metricCards = [
    ["label" => "Behavior events", "value" => (string) ($summary["behavior_events"] ?? 0), "detail" => "All local heatmap signals", "status" => ($report["enabled"] ?? false) ? "ON" : "OFF", "tone" => "blue"],
    ["label" => "Clicks", "value" => (string) ($summary["click_events"] ?? 0), "detail" => "Aggregated into page zones", "status" => "GRID", "tone" => "green"],
    ["label" => "Scrolls", "value" => (string) ($summary["scroll_events"] ?? 0), "detail" => "25/50/75/100 percent buckets", "status" => "DEPTH", "tone" => "amber"],
    ["label" => "Pages", "value" => (string) ($summary["pages_seen"] ?? 0), "detail" => "Pages with behavior data", "status" => "LOCAL", "tone" => "red"],
];

require __DIR__ . "/panel-header.php";
?>

        <section class="developer-dashboard-section" aria-label="Heatmap summary">
          <div class="developer-analytics-commandbar">
            <div>
              <p class="feature-kicker">Heatmap command center</p>
              <h2 class="developer-dashboard-section-title">FNLLA Heatmap</h2>
              <p class="content-text mb-0">Click zones, scroll depth, page popularity and device mix are stored as aggregate project data after analytics consent.</p>
            </div>
            <div class="developer-analytics-commandbar-panel">
              <strong>Privacy contract <span class="developer-info-tip" tabindex="0" aria-label="No recordings, no text capture, no IP addresses and no raw user agents are stored.">i<span>No session replay, cursor trail, keystroke, raw IP, raw user-agent or fingerprint is stored.</span></span></strong>
              <span><?= h((string) ($privacy["mode"] ?? "first-party aggregate heatmap")) ?></span>
              <span>Endpoint <?= h(route("fnlla.analytics.event")) ?></span>
            </div>
          </div>

          <div class="developer-analytics-metric-grid">
            <?php foreach ($metricCards as $card): ?>
            <article class="developer-analytics-metric-card is-<?= h((string) $card["tone"]) ?>">
              <div class="developer-dashboard-card-head">
                <strong><?= h((string) $card["label"]) ?></strong>
                <span class="developer-dashboard-ok"><?= h((string) $card["status"]) ?></span>
              </div>
              <h3><?= h((string) $card["value"]) ?></h3>
              <p><?= h((string) $card["detail"]) ?></p>
            </article>
            <?php endforeach; ?>
          </div>
        </section>

        <section class="developer-dashboard-section" aria-label="Click intensity map">
          <div class="developer-dashboard-section-head">
            <h2 class="developer-dashboard-section-title">Click intensity</h2>
            <span class="developer-dashboard-refresh"><?= h($topPage) ?></span>
          </div>
          <div class="developer-heatmap-workbench">
            <article class="developer-dashboard-card developer-dashboard-card-wide">
              <p class="feature-kicker">Top page click map</p>
              <?php if ($gridRows === []): ?>
              <p class="content-text mb-0">No click heatmap events have been recorded yet.</p>
              <?php else: ?>
              <div class="developer-heatmap-grid" style="--heatmap-columns: <?= h((string) max(1, (int) ($settings["grid_columns"] ?? 5))) ?>;" aria-label="Click heatmap grid for top page">
                <?php foreach ($gridRows as $row): ?>
                  <?php foreach ((array) $row as $cell): ?>
                  <?php $count = (int) ($cell["count"] ?? 0); $intensity = max(0.08, min(1, $count / $gridMax)); ?>
                  <span style="--heatmap-intensity: <?= h((string) $intensity) ?>;" title="<?= h((string) ($cell["zone"] ?? "")) ?>: <?= h((string) $count) ?>">
                    <?= h((string) $count) ?>
                  </span>
                  <?php endforeach; ?>
                <?php endforeach; ?>
              </div>
              <?php endif; ?>
            </article>
            <article class="developer-dashboard-card">
              <p class="feature-kicker">Scroll depth</p>
              <?php $renderBarList((array) ($charts["top_page_scroll_depth"] ?? []), "No scroll-depth data has been recorded for the top page yet."); ?>
            </article>
          </div>
        </section>

        <section class="developer-dashboard-section" aria-label="Heatmap breakdowns">
          <div class="developer-analytics-workbench">
            <article class="developer-dashboard-card">
              <p class="feature-kicker">Top pages</p>
              <?php $renderBarList((array) ($charts["pages"] ?? []), "No pages have been recorded yet."); ?>
            </article>
            <article class="developer-dashboard-card">
              <p class="feature-kicker">Click elements</p>
              <?php $renderBarList((array) ($charts["click_elements"] ?? []), "No element-level click buckets have been recorded yet."); ?>
            </article>
            <article class="developer-dashboard-card">
              <p class="feature-kicker">Devices</p>
              <?php $renderBarList((array) ($charts["devices"] ?? []), "No device buckets have been recorded yet."); ?>
            </article>
            <article class="developer-dashboard-card">
              <p class="feature-kicker">Behavior events</p>
              <?php $renderBarList((array) ($charts["events"] ?? []), "No behavior events have been recorded yet."); ?>
            </article>
          </div>
        </section>

        <section class="developer-dashboard-section" aria-label="Heatmap trend and settings">
          <div class="developer-analytics-detail-grid">
            <article class="developer-dashboard-card">
              <p class="feature-kicker">Last 14 days</p>
              <?php $renderTimeline((array) ($charts["daily_behavior_events"] ?? []), "No daily behavior history has been recorded yet."); ?>
            </article>
            <article class="developer-dashboard-card">
              <p class="feature-kicker">Runtime settings</p>
              <div class="developer-dashboard-glance-table">
                <div class="developer-dashboard-glance-row"><strong>Sample</strong><span><?= h((string) ($settings["sample_rate"] ?? 100)) ?>%</span></div>
                <div class="developer-dashboard-glance-row"><strong>Grid</strong><span><?= h((string) ($settings["grid_columns"] ?? 5)) ?> x <?= h((string) ($settings["grid_rows"] ?? 5)) ?></span></div>
                <div class="developer-dashboard-glance-row"><strong>Storage</strong><span><?= h((string) ($settings["storage_path"] ?? "storage/framework/metrics.json")) ?></span></div>
              </div>
            </article>
          </div>
        </section>

        <section class="developer-dashboard-section" aria-label="Heatmap settings">
          <div class="developer-dashboard-section-head">
            <h2 class="developer-dashboard-section-title">Heatmap settings <span class="developer-info-tip" tabindex="0" aria-label="Sampling limits how many behavior events are stored.">i<span>Sampling controls event volume. Grid size controls how click positions are grouped before storage.</span></span></h2>
            <span class="developer-dashboard-refresh">Saved to project .env</span>
          </div>
          <form class="form developer-analytics-settings-form" action="<?= h(route("developer.panel.heatmap.settings")) ?>" method="post">
            <?= csrf_field() ?>
            <label class="developer-analytics-toggle">
              <input type="hidden" name="observability_heatmap_enabled" value="0">
              <input type="checkbox" name="observability_heatmap_enabled" value="1" <?= ($report["enabled"] ?? true) ? "checked" : "" ?>>
              <span><strong>First-party heatmap</strong><small>Collect aggregate click zones and scroll-depth buckets after analytics consent.</small></span>
            </label>
            <div class="form-group">
              <label class="label" for="observability-heatmap-sample">Sample rate</label>
              <input class="input" id="observability-heatmap-sample" name="observability_heatmap_sample_rate" type="number" min="1" max="100" value="<?= h((string) ($settings["sample_rate"] ?? 100)) ?>">
            </div>
            <div class="form-group">
              <label class="label" for="observability-heatmap-columns">Grid columns</label>
              <input class="input" id="observability-heatmap-columns" name="observability_heatmap_grid_columns" type="number" min="1" max="12" value="<?= h((string) ($settings["grid_columns"] ?? 5)) ?>">
            </div>
            <div class="form-group">
              <label class="label" for="observability-heatmap-rows">Grid rows</label>
              <input class="input" id="observability-heatmap-rows" name="observability_heatmap_grid_rows" type="number" min="1" max="12" value="<?= h((string) ($settings["grid_rows"] ?? 5)) ?>">
            </div>
            <button class="btn btn-primary" type="submit">Save heatmap settings</button>
          </form>
        </section>

        <section class="developer-dashboard-section" aria-label="Heatmap privacy model">
          <div class="developer-dashboard-overview-grid">
            <article class="developer-dashboard-card developer-dashboard-card-wide">
              <p class="feature-kicker">Insights</p>
              <ul class="developer-analytics-insights">
                <?php foreach ($insights as $insight): ?>
                <li><?= h((string) $insight) ?></li>
                <?php endforeach; ?>
              </ul>
            </article>
            <article class="developer-dashboard-card">
              <p class="feature-kicker">Data boundary</p>
              <p class="content-text mb-0">The recorder keeps aggregate counts only. It is a FNLLA-owned alternative to Clarity-style heatmaps, not a session replay recorder.</p>
              <p class="developer-dashboard-status is-active">No external calls by default</p>
            </article>
          </div>
        </section>

<?php require __DIR__ . "/panel-footer.php"; ?>
