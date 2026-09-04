<?php

declare(strict_types=1);

$customerPanelTitle = "Analytics";
$customerPanelLead = "Aggregate project traffic and performance signals without raw visitor identity.";
$report = is_array($analyticsReport ?? null) ? (array) $analyticsReport : [];
$summary = (array) ($report["summary"] ?? []);
$charts = (array) ($report["charts"] ?? []);
$formatMetric = static fn (mixed $value, string $suffix = ""): string => is_numeric($value) ? rtrim(rtrim((string) round((float) $value, 2), "0"), ".") . $suffix : "0" . $suffix;
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
            <div><strong><?= h($label) ?></strong><span><?= h($count) ?></span></div>
            <i aria-hidden="true"><b style="width: <?= h((string) $percent) ?>%"></b></i>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
<?php };
require __DIR__ . "/panel-header.php";
?>

      <section class="customer-overview-grid" aria-label="Analytics metrics">
        <article class="customer-card">
          <p class="feature-kicker">Page views</p>
          <h2><?= h((string) ($summary["page_views"] ?? 0)) ?></h2>
          <p><?= h((string) ($summary["total_requests"] ?? 0)) ?> total requests.</p>
        </article>
        <article class="customer-card">
          <p class="feature-kicker">Response time</p>
          <h2><?= h($formatMetric($summary["average_response_ms"] ?? 0, "ms")) ?></h2>
          <p>Maximum <?= h($formatMetric($summary["max_response_ms"] ?? 0, "ms")) ?> recorded.</p>
        </article>
        <article class="customer-card">
          <p class="feature-kicker">Errors</p>
          <h2><?= h((string) ($summary["error_requests"] ?? 0)) ?></h2>
          <p><?= h($formatMetric($summary["error_rate"] ?? 0, "%")) ?> aggregate error rate.</p>
        </article>
      </section>

      <section class="customer-chart-grid" aria-label="Analytics charts">
        <article class="customer-card">
          <h2>Top pages</h2>
          <?php $renderBars((array) ($charts["top_routes"] ?? []), "No page-view data has been recorded yet."); ?>
        </article>
        <article class="customer-card">
          <h2>Devices</h2>
          <?php $renderBars((array) ($charts["device_counts"] ?? []), "No device buckets have been recorded yet."); ?>
        </article>
      </section>

<?php require __DIR__ . "/panel-footer.php"; ?>
