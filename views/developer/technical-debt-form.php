<?php use Fnlla\Php\Support\TechnicalDebtRegistry; ?>
<form class="debt-form" method="post" action="<?= h(route("developer.panel.technical_debt.save")) ?>">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= h($item["id"]) ?>">
  <input type="hidden" name="revision" value="<?= (int) $debtState["revision"] ?>">
  <label class="debt-form-wide">Title<input name="title" required maxlength="160" value="<?= h($item["title"]) ?>"></label>
  <label>Status<select class="select" name="status"><?php foreach (TechnicalDebtRegistry::STATUSES as $status): ?><option value="<?= h($status) ?>" <?= $item["status"] === $status ? "selected" : "" ?>><?= h(ucwords(str_replace("_", " ", $status))) ?></option><?php endforeach; ?></select></label>
  <label>Priority<select class="select" name="priority"><?php foreach (TechnicalDebtRegistry::PRIORITIES as $priority): ?><option value="<?= h($priority) ?>" <?= $item["priority"] === $priority ? "selected" : "" ?>><?= h(ucfirst($priority)) ?></option><?php endforeach; ?></select></label>
  <label>Owner<input name="owner" maxlength="160" value="<?= h($item["owner"]) ?>"></label>
  <label>Due date<input type="date" name="due_date" value="<?= h($item["due_date"]) ?>"></label>
  <label class="debt-form-wide">Notes / acceptance reason<textarea name="notes" maxlength="2000" rows="3"><?= h($item["notes"]) ?></textarea></label>
  <div><button class="btn btn-primary btn-sm" type="submit">Save</button></div>
</form>
