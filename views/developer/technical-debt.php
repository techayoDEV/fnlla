<?php

declare(strict_types=1);

use Fnlla\Php\Support\TechnicalDebtRegistry;

$developerPanelTitle = "Technical debt";
$developerPanelLead = "";
$debtItems = array_values($debtState["items"]);
$statusFilter = is_string($debtStatusFilter ?? null) ? $debtStatusFilter : "";
if (in_array($statusFilter, TechnicalDebtRegistry::STATUSES, true)) {
    $debtItems = array_values(array_filter($debtItems, static fn (array $item): bool => $item["status"] === $statusFilter));
}
require VIEW_ROOT . "/developer/panel-header.php";
?>
<link rel="stylesheet" href="<?= h(asset("assets/developer-tools.css")) ?>">
<section class="developer-panel-section debt-register" aria-labelledby="debt-title">
  <div class="debt-toolbar">
    <h2 id="debt-title">Register <small><?= count($debtItems) ?></small></h2>
    <form method="get">
      <label>Status <select name="status"><option value="">All</option><?php foreach (TechnicalDebtRegistry::STATUSES as $status): ?><option value="<?= h($status) ?>" <?= $statusFilter === $status ? "selected" : "" ?>><?= h(ucwords(str_replace("_", " ", $status))) ?></option><?php endforeach; ?></select></label>
      <button class="btn btn-outline btn-sm" type="submit">Filter</button>
    </form>
    <?php if ($canManageDebt): ?>
    <form method="post" action="<?= h(route("developer.panel.technical_debt.save")) ?>">
      <?= csrf_field() ?><input type="hidden" name="revision" value="<?= (int) $debtState["revision"] ?>"><input type="hidden" name="action" value="scan">
      <button class="btn btn-outline btn-sm" type="submit">Scan source</button>
    </form>
    <?php endif; ?>
  </div>
  <?php if ($debtItems === []): ?><p>No matching debt items.</p><?php endif; ?>
  <?php foreach ($debtItems as $item): ?>
  <details class="debt-row">
    <summary><strong><?= h($item["title"]) ?></strong><span><?= h($item["priority"]) ?></span><span><?= h(str_replace("_", " ", $item["status"])) ?></span><span><?= h($item["owner"] ?: "Unassigned") ?></span></summary>
    <?php if (($item["source"] ?? "") === "marker"): ?><p>Source marker: <?= ($item["observed"] ?? false) ? "Present at last scan" : "Not present at last scan" ?></p><?php endif; ?>
    <?php if ($canManageDebt): ?>
    <?php require VIEW_ROOT . "/developer/technical-debt-form.php"; ?>
    <?php else: ?><p><?= h($item["notes"]) ?></p><p>Due: <?= h($item["due_date"] ?: "Not set") ?></p><?php endif; ?>
  </details>
  <?php endforeach; ?>
  <?php if ($canManageDebt): ?>
  <details class="debt-row"><summary>Add debt item</summary>
    <?php $item = ["id" => "", "title" => "", "status" => "open", "priority" => "normal", "owner" => "", "notes" => "", "due_date" => ""]; require VIEW_ROOT . "/developer/technical-debt-form.php"; ?>
  </details>
  <?php endif; ?>
  <p>Last scan: <?= h((string) ($debtState["scanned_at"] ?? "Not scanned")) ?></p>
</section>
<?php require VIEW_ROOT . "/developer/panel-footer.php"; ?>
