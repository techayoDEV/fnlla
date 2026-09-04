<?php

declare(strict_types=1);

$customerPanelTitle = "Heatmap";
$customerPanelLead = "Aggregate public-website click and scroll signals grouped into customer-safe zones.";
$report = is_array($heatmapReport ?? null) ? (array) $heatmapReport : [];
$summary = (array) ($report["summary"] ?? []);
$charts = (array) ($report["charts"] ?? []);
$grid = (array) ($charts["top_page_click_grid"] ?? []);
$gridRows = (array) ($grid["rows"] ?? []);
$renderBars = static function (array $items, string $empty): void { ?>
        <?php if ($items === []): ?>
        <p class="content-text mb-0"><?= h($empty) ?></p>
        <?php else: ?>
        <div class="customer-bars">
          <?php foreach ($items as $item): ?>
          <?php
              $label = (string) ($item["label"] ?? "");
              $count = (string) ($item["count"] ?? 0);
              $percent = max(2, (int) ($item["percent"] ?? 0));
              $tooltip = trim($label . ": " . $count . " / " . $percent . "%");
          ?>
          <div class="customer-bar-row" data-fnlla-tooltip="<?= h($tooltip) ?>" data-fnlla-tooltip-position="top" aria-label="<?= h($tooltip) ?>" tabindex="0">
            <div><strong><?= h((string) ($item["label"] ?? "")) ?></strong><span><?= h((string) ($item["count"] ?? 0)) ?></span></div>
            <i aria-hidden="true"><b style="width: <?= h((string) $percent) ?>%"></b></i>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
<?php };
require __DIR__ . "/panel-header.php";
?>

      <section class="customer-overview-grid" aria-label="Heatmap metrics">
        <article class="customer-card">
          <p class="feature-kicker">Behavior events</p>
          <h2><?= h((string) ($summary["behavior_events"] ?? 0)) ?></h2>
          <p>Public-site clicks, scrolls and page behavior grouped as aggregate signals.</p>
        </article>
        <article class="customer-card">
          <p class="feature-kicker">Clicks</p>
          <h2><?= h((string) ($summary["click_events"] ?? 0)) ?></h2>
          <p>Stored as public-page zones, not raw cursor trails.</p>
        </article>
        <article class="customer-card">
          <p class="feature-kicker">Top page</p>
          <h2><?= h((string) ($summary["top_page"] ?? "/")) ?></h2>
          <p><?= h((string) ($summary["pages_seen"] ?? 0)) ?> pages have behavior data.</p>
        </article>
      </section>

      <section class="customer-chart-grid" aria-label="Heatmap charts">
        <article class="customer-card">
          <h2>Public click zones</h2>
          <div class="customer-heatmap-grid" role="img" aria-label="Aggregate public-page click-zone intensity">
            <?php foreach ($gridRows as $row): ?>
            <?php foreach ((array) $row as $cell): ?>
            <?php $intensity = max(0, min(100, (int) ($cell["intensity"] ?? 0))); ?>
            <span style="opacity: <?= h((string) max(0.12, $intensity / 100)) ?>" data-fnlla-tooltip="<?= h((string) ($cell["tooltip"] ?? (($cell["zone"] ?? "zone") . ": " . ($cell["count"] ?? 0)))) ?>" data-fnlla-tooltip-position="top" aria-label="<?= h((string) ($cell["tooltip"] ?? (($cell["zone"] ?? "zone") . ": " . ($cell["count"] ?? 0)))) ?>" tabindex="0"></span>
            <?php endforeach; ?>
            <?php endforeach; ?>
          </div>
        </article>
        <article class="customer-card">
          <h2>Scroll depth</h2>
          <?php $renderBars((array) ($charts["top_page_scroll_depth"] ?? []), "No scroll-depth data has been recorded yet."); ?>
        </article>
      </section>

<?php require __DIR__ . "/panel-footer.php"; ?>
