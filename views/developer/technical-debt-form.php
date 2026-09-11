<?php use Fnlla\Php\Support\TechnicalDebtRegistry; ?>
<form class="debt-form" method="post" action="<?= h(route("developer.panel.technical_debt.save")) ?>">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= h($item["id"]) ?>">
  <input type="hidden" name="revision" value="<?= (int) $debtState["revision"] ?>">
  <label class="debt-form-wide">Title<input name="title" required maxlength="160" value="<?= h((string) $item["title"]) ?>"></label>
  <label>Status<select class="select" name="status"><?php foreach (TechnicalDebtRegistry::STATUSES as $status): ?><option value="<?= h($status) ?>" <?= $item["status"] === $status ? "selected" : "" ?>><?= h(ucwords(str_replace("_", " ", $status))) ?></option><?php endforeach; ?></select></label>
  <label>Priority<select class="select" name="priority"><?php foreach (TechnicalDebtRegistry::PRIORITIES as $priority): ?><option value="<?= h($priority) ?>" <?= $item["priority"] === $priority ? "selected" : "" ?>><?= h(ucfirst($priority)) ?></option><?php endforeach; ?></select></label>
  <label>Owner<input name="owner" maxlength="160" value="<?= h((string) $item["owner"]) ?>"></label>
  <label>Due date<input type="date" name="due_date" value="<?= h((string) $item["due_date"]) ?>"></label>
  <label>Accepted until<input type="date" name="accepted_until" value="<?= h((string) ($item["accepted_until"] ?? "")) ?>"></label>
  <label>Issue / PR ref<input name="issue_ref" maxlength="240" value="<?= h((string) ($item["issue_ref"] ?? "")) ?>" placeholder="https://github.com/org/repo/issues/123"></label>
  <label>ADR ref<input name="adr_ref" maxlength="240" value="<?= h((string) ($item["adr_ref"] ?? "")) ?>" placeholder="docs/adr/0001-example.md"></label>
  <label>Evidence ref<input name="evidence_ref" maxlength="240" value="<?= h((string) ($item["evidence_ref"] ?? "")) ?>" placeholder="docs/... or https://..."></label>
  <label class="debt-form-wide">Notes / acceptance reason<textarea name="notes" maxlength="2000" rows="4"><?= h((string) $item["notes"]) ?></textarea></label>
  <div class="debt-form-actions"><button class="btn btn-primary btn-sm" type="submit">Save debt item</button></div>
</form>
