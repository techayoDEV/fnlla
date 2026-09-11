<?php

declare(strict_types=1);

use Fnlla\Php\Support\TechnicalDebtRegistry;

$developerPanelTitle = "Technical Debt";
$developerPanelLead = "Release triage for known debt, accepted risk and source markers.";
$allDebtItems = array_values((array) ($debtState["items"] ?? []));
$statusFilter = is_string($debtStatusFilter ?? null) ? $debtStatusFilter : "";
$priorityLabels = [
    "critical" => "Critical",
    "high" => "High",
    "normal" => "Normal",
    "low" => "Low",
];
$statusLabels = [
    "open" => "Open",
    "in_progress" => "In progress",
    "accepted" => "Accepted risk",
    "resolved" => "Resolved",
];
$statusCounts = array_fill_keys(TechnicalDebtRegistry::STATUSES, 0);
$priorityCounts = array_fill_keys(TechnicalDebtRegistry::PRIORITIES, 0);
$today = gmdate("Y-m-d");
$overdueCount = 0;
$linkedEvidenceCount = 0;

foreach ($allDebtItems as $debtItem) {
    $status = (string) ($debtItem["status"] ?? "open");
    $priority = (string) ($debtItem["priority"] ?? "normal");
    if (array_key_exists($status, $statusCounts)) {
        $statusCounts[$status]++;
    }
    if (array_key_exists($priority, $priorityCounts)) {
        $priorityCounts[$priority]++;
    }
    $dueDate = (string) ($debtItem["due_date"] ?? "");
    if ($dueDate !== "" && $dueDate < $today && !in_array($status, ["accepted", "resolved"], true)) {
        $overdueCount++;
    }
    if (trim((string) ($debtItem["issue_ref"] ?? "")) !== "" || trim((string) ($debtItem["adr_ref"] ?? "")) !== "" || trim((string) ($debtItem["evidence_ref"] ?? "")) !== "") {
        $linkedEvidenceCount++;
    }
}

$debtItems = $allDebtItems;
if (in_array($statusFilter, TechnicalDebtRegistry::STATUSES, true)) {
    $debtItems = array_values(array_filter($debtItems, static fn (array $item): bool => $item["status"] === $statusFilter));
}

$priorityRank = ["critical" => 0, "high" => 1, "normal" => 2, "low" => 3];
usort($debtItems, static function (array $left, array $right) use ($priorityRank): int {
    $leftStatus = (string) ($left["status"] ?? "open");
    $rightStatus = (string) ($right["status"] ?? "open");
    $leftClosed = in_array($leftStatus, ["accepted", "resolved"], true) ? 1 : 0;
    $rightClosed = in_array($rightStatus, ["accepted", "resolved"], true) ? 1 : 0;
    $closedComparison = $leftClosed <=> $rightClosed;

    if ($closedComparison !== 0) {
        return $closedComparison;
    }

    $priorityComparison = ($priorityRank[(string) ($left["priority"] ?? "normal")] ?? 2) <=> ($priorityRank[(string) ($right["priority"] ?? "normal")] ?? 2);

    if ($priorityComparison !== 0) {
        return $priorityComparison;
    }

    return strcmp((string) ($left["due_date"] ?? "9999-12-31"), (string) ($right["due_date"] ?? "9999-12-31"));
});

$activeDebtCount = (int) (($statusCounts["open"] ?? 0) + ($statusCounts["in_progress"] ?? 0));
$releaseState = $overdueCount > 0 || ($priorityCounts["critical"] ?? 0) > 0 ? "Blocked" : ($activeDebtCount > 0 ? "Review" : "Clear");
$releaseStateClass = strtolower($releaseState);
$nextDebt = $debtItems[0] ?? null;
$renderDebtReference = static function (string $label, string $value): void {
    $value = trim($value);
    if ($value === "") {
        return;
    }
    $isHref = preg_match('/^(https?:\/\/|\/|#)/i', $value) === 1;
    ?>
        <span><?= h($label) ?>: <?php if ($isHref): ?><a href="<?= h($value) ?>"<?= str_starts_with(strtolower($value), "http") ? ' target="_blank" rel="noreferrer"' : "" ?>><?= h($value) ?></a><?php else: ?><code><?= h($value) ?></code><?php endif; ?></span>
<?php };

require VIEW_ROOT . "/developer/panel-header.php";
?>
<section class="developer-panel-section debt-register" aria-labelledby="debt-title">
  <div class="debt-command">
    <div>
      <p class="feature-kicker">Release triage</p>
      <h2 id="debt-title" class="developer-dashboard-section-title">Decide what blocks release, what is accepted, and what is done.</h2>
      <p class="content-text mb-0">Source markers, promoted runtime issues and manual notes land in one register.</p>
    </div>
    <div class="debt-command-actions">
      <?php if ($canManageDebt): ?>
      <form method="post" action="<?= h(route("developer.panel.technical_debt.save")) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="revision" value="<?= (int) $debtState["revision"] ?>">
        <input type="hidden" name="action" value="scan">
        <button class="btn btn-outline btn-sm" type="submit">Scan source</button>
      </form>
      <?php endif; ?>
    </div>
  </div>

  <div class="debt-snapshot-grid" aria-label="Technical debt release snapshot">
    <article class="debt-snapshot-card is-<?= h($releaseStateClass) ?>">
      <span>Release state</span>
      <strong><?= h($releaseState) ?></strong>
      <small><?= h((string) $activeDebtCount) ?> active, <?= h((string) $overdueCount) ?> overdue</small>
    </article>
    <article class="debt-snapshot-card">
      <span>Critical / high</span>
      <strong><?= h((string) (($priorityCounts["critical"] ?? 0) + ($priorityCounts["high"] ?? 0))) ?></strong>
      <small><?= h((string) ($priorityCounts["critical"] ?? 0)) ?> critical</small>
    </article>
    <article class="debt-snapshot-card">
      <span>Accepted risk</span>
      <strong><?= h((string) ($statusCounts["accepted"] ?? 0)) ?></strong>
      <small>Requires reason and expiry</small>
    </article>
    <article class="debt-snapshot-card">
      <span>Evidence links</span>
      <strong><?= h((string) $linkedEvidenceCount) ?></strong>
      <small>Issue, PR, ADR or evidence refs</small>
    </article>
  </div>

  <div class="debt-focus-row">
    <article class="debt-focus-card">
      <span>Next decision</span>
      <?php if (is_array($nextDebt)): ?>
      <strong><?= h((string) ($nextDebt["title"] ?? "Debt item")) ?></strong>
      <small><?= h($statusLabels[(string) ($nextDebt["status"] ?? "open")] ?? "Open") ?> / <?= h($priorityLabels[(string) ($nextDebt["priority"] ?? "normal")] ?? "Normal") ?><?= ((string) ($nextDebt["owner"] ?? "")) !== "" ? " / " . h((string) $nextDebt["owner"]) : "" ?></small>
      <?php else: ?>
      <strong>No active debt in this filter</strong>
      <small>Run a scan or add a manual item when a release risk appears.</small>
      <?php endif; ?>
    </article>
    <form class="debt-filter" method="get" aria-label="Technical debt status filter">
      <label for="debt-status-filter">Status</label>
      <select class="select" id="debt-status-filter" name="status">
        <option value="">All statuses</option>
        <?php foreach (TechnicalDebtRegistry::STATUSES as $status): ?>
        <option value="<?= h($status) ?>" <?= $statusFilter === $status ? "selected" : "" ?>><?= h($statusLabels[$status] ?? ucwords(str_replace("_", " ", $status))) ?> (<?= h((string) ($statusCounts[$status] ?? 0)) ?>)</option>
        <?php endforeach; ?>
      </select>
      <button class="btn btn-outline btn-sm" type="submit">Apply</button>
    </form>
  </div>

  <div class="debt-status-tabs" aria-label="Technical debt status counts">
    <a class="<?= $statusFilter === "" ? "is-active" : "" ?>" href="<?= h(route("developer.panel.technical_debt")) ?>">All <span><?= h((string) count($allDebtItems)) ?></span></a>
    <?php foreach (TechnicalDebtRegistry::STATUSES as $status): ?>
    <a class="<?= $statusFilter === $status ? "is-active" : "" ?>" href="<?= h(route("developer.panel.technical_debt")) ?>?status=<?= h($status) ?>"><?= h($statusLabels[$status] ?? $status) ?> <span><?= h((string) ($statusCounts[$status] ?? 0)) ?></span></a>
    <?php endforeach; ?>
  </div>

  <div class="debt-list">
    <?php if ($debtItems === []): ?>
    <article class="debt-empty">
      <strong>No matching debt items</strong>
      <span>Change the status filter or scan the source markers.</span>
    </article>
    <?php endif; ?>
    <?php foreach ($debtItems as $item): ?>
    <?php
    $itemStatus = (string) ($item["status"] ?? "open");
    $itemPriority = (string) ($item["priority"] ?? "normal");
    $itemDue = (string) ($item["due_date"] ?? "");
    $itemAcceptedUntil = (string) ($item["accepted_until"] ?? "");
    $itemOverdue = $itemDue !== "" && $itemDue < $today && !in_array($itemStatus, ["accepted", "resolved"], true);
    ?>
    <details class="debt-row is-<?= h($itemStatus) ?> is-priority-<?= h($itemPriority) ?><?= $itemOverdue ? " is-overdue" : "" ?>">
      <summary>
        <span class="debt-row-title">
          <strong><?= h((string) $item["title"]) ?></strong>
          <small><?= h((string) (($item["source"] ?? "manual") === "marker" ? "Source marker" : (($item["source"] ?? "manual") === "runtime_issue" ? "Runtime issue" : "Manual item"))) ?></small>
        </span>
        <span><?= h($priorityLabels[$itemPriority] ?? ucfirst($itemPriority)) ?></span>
        <span><?= h($statusLabels[$itemStatus] ?? str_replace("_", " ", $itemStatus)) ?></span>
        <span><?= h((string) (($item["owner"] ?? "") !== "" ? $item["owner"] : "Unassigned")) ?></span>
        <span><?= h($itemStatus === "accepted" && $itemAcceptedUntil !== "" ? "Accepted until " . $itemAcceptedUntil : ($itemDue !== "" ? $itemDue : "No date")) ?></span>
      </summary>
      <div class="debt-row-body">
        <?php if (($item["source"] ?? "") === "marker"): ?>
        <p class="debt-source-note">Marker was <?= ($item["observed"] ?? false) ? "present" : "not present" ?> at the last scan.</p>
        <?php endif; ?>
        <div class="developer-notification-meta">
          <?php $renderDebtReference("Issue/PR", (string) ($item["issue_ref"] ?? "")); ?>
          <?php $renderDebtReference("ADR", (string) ($item["adr_ref"] ?? "")); ?>
          <?php $renderDebtReference("Evidence", (string) ($item["evidence_ref"] ?? "")); ?>
          <?php if ($itemAcceptedUntil !== ""): ?>
          <span>Accepted until: <?= h($itemAcceptedUntil) ?></span>
          <?php endif; ?>
        </div>
        <?php if ($canManageDebt): ?>
        <?php require VIEW_ROOT . "/developer/technical-debt-form.php"; ?>
        <?php else: ?>
        <p><?= h((string) ($item["notes"] ?? "")) ?></p>
        <p>Due: <?= h($itemDue !== "" ? $itemDue : "Not set") ?></p>
        <?php endif; ?>
      </div>
    </details>
    <?php endforeach; ?>
  </div>

  <?php if ($canManageDebt): ?>
  <details class="debt-row debt-add-row">
    <summary class="debt-add-summary">Add debt item</summary>
    <div class="debt-row-body">
      <?php $item = ["id" => "", "title" => "", "status" => "open", "priority" => "normal", "owner" => "", "notes" => "", "due_date" => "", "accepted_until" => "", "issue_ref" => "", "adr_ref" => "", "evidence_ref" => ""]; require VIEW_ROOT . "/developer/technical-debt-form.php"; ?>
    </div>
  </details>
  <?php endif; ?>
</section>
<?php require VIEW_ROOT . "/developer/panel-footer.php"; ?>
