<?php

declare(strict_types=1);

$customerPanelTitle = "Project overview";
$customerPanelLead = "A customer-safe snapshot of progress, preview access and aggregate project signals.";
$board = is_array($workspaceBoard ?? null) ? (array) $workspaceBoard : [];
$analytics = is_array($analyticsReport ?? null) ? (array) $analyticsReport : [];
$heatmap = is_array($heatmapReport ?? null) ? (array) $heatmapReport : [];
$analyticsSummary = (array) ($analytics["summary"] ?? []);
$heatmapSummary = (array) ($heatmap["summary"] ?? []);
$projectSettings = is_array($projectSettings ?? null) ? (array) $projectSettings : [];
require __DIR__ . "/panel-header.php";
?>

      <section class="customer-overview-grid" aria-label="Project overview cards">
        <?php if ($canSee("kanban")): ?>
        <article class="customer-card">
          <p class="feature-kicker">Project progress</p>
          <h2><?= h((string) ($board["completion_percent"] ?? 0)) ?>% complete</h2>
          <p><?= h((string) ($board["open_tasks_count"] ?? 0)) ?> open tasks, <?= h((string) ($board["done_tasks_count"] ?? 0)) ?> done.</p>
          <a class="btn btn-outline btn-sm" href="<?= h((string) ($customerLinks["kanban"] ?? route("customer.panel.kanban"))) ?>">Open Kanban</a>
        </article>
        <article class="customer-card">
          <p class="feature-kicker">Client-visible work</p>
          <h2><?= h((string) ($board["client_visible_tasks_count"] ?? count((array) ($board["tasks"] ?? [])))) ?> cards</h2>
          <p>Only tasks approved for customer visibility are shown here.</p>
        </article>
        <?php endif; ?>
        <?php if ($canSee("preview")): ?>
        <article class="customer-card">
          <p class="feature-kicker">Preview</p>
          <h2><?= trim((string) ($projectSettings["url"] ?? "")) !== "" ? "Configured" : "Local preview" ?></h2>
          <p><?= trim((string) ($projectSettings["url"] ?? "")) !== "" ? h((string) $projectSettings["url"]) : "Use the supplied private preview route for this build." ?></p>
          <a class="btn btn-outline btn-sm" href="<?= h((string) ($projectSettings["preview_url"] ?? route("home"))) ?>" target="_blank" rel="noopener noreferrer">Open preview</a>
        </article>
        <?php endif; ?>
      </section>

      <section class="customer-overview-grid customer-overview-grid-compact" aria-label="Aggregate signals">
        <?php if ($canSee("analytics")): ?>
        <article class="customer-card">
          <p class="feature-kicker">Analytics</p>
          <h2><?= h((string) ($analyticsSummary["page_views"] ?? 0)) ?> page views</h2>
          <p><?= h((string) ($analyticsSummary["total_requests"] ?? 0)) ?> requests. No raw IP addresses or fingerprints.</p>
        </article>
        <?php endif; ?>
        <?php if ($canSee("heatmap")): ?>
        <article class="customer-card">
          <p class="feature-kicker">Heatmap</p>
          <h2><?= h((string) ($heatmapSummary["behavior_events"] ?? 0)) ?> events</h2>
          <p>Click and scroll data is grouped into aggregate zones.</p>
        </article>
        <?php endif; ?>
        <?php if ($canSee("kanban")): ?>
        <article class="customer-card">
          <p class="feature-kicker">Risks</p>
          <h2><?= h((string) ($board["blocked_tasks_count"] ?? 0)) ?> blocked</h2>
          <p><?= h((string) ($board["due_soon_count"] ?? 0)) ?> tasks are due soon.</p>
        </article>
        <?php endif; ?>
      </section>

<?php require __DIR__ . "/panel-footer.php"; ?>
