<?php

declare(strict_types=1);

$developerPanelTitle = "My Tasks";
$developerPanelLead = "Private developer tasks, notes, subtasks and attachments that stay outside shared project work.";
$todo = is_array($privateTodo ?? null) ? (array) $privateTodo : [];
$items = array_values((array) ($todo["items"] ?? []));
$today = gmdate("Y-m-d");
$priorityLabels = [
    "low" => "Low",
    "normal" => "Normal",
    "high" => "High",
];
$colorLabels = [
    "blue" => "Blue",
    "slate" => "Slate",
    "sky" => "Sky",
    "indigo" => "Indigo",
    "green" => "Green",
    "red" => "Red",
    "yellow" => "Yellow",
    "orange" => "Orange",
];
$priorityClass = static fn (string $priority): string => array_key_exists($priority, $priorityLabels) ? $priority : "normal";
$colorClass = static fn (string $color): string => array_key_exists($color, $colorLabels) ? $color : "blue";
$formatAttachmentSize = static function (int $bytes): string {
    if ($bytes <= 0) {
        return "";
    }

    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 1) . " MB";
    }

    return max(1, (int) ceil($bytes / 1024)) . " KB";
};
$formatDate = static function (string $date): string {
    if ($date === "") {
        return "Not set";
    }

    $parsed = DateTimeImmutable::createFromFormat("!Y-m-d", $date);

    return $parsed instanceof DateTimeImmutable ? $parsed->format("d M Y") : $date;
};
$formatDateTime = static function (string $time): string {
    if ($time === "") {
        return "Not recorded";
    }

    try {
        return (new DateTimeImmutable($time))
            ->setTimezone(new DateTimeZone(date_default_timezone_get()))
            ->format("d M Y, H:i");
    } catch (Throwable) {
        return $time;
    }
};
$subtaskSummary = static function (array $subtasks): array {
    $total = count($subtasks);
    $done = count(array_filter($subtasks, static fn (array $subtask): bool => (bool) ($subtask["done"] ?? false)));

    return [
        "total" => $total,
        "done" => $done,
        "percent" => $total > 0 ? (int) round(($done / $total) * 100) : 0,
    ];
};
$openItems = [];
$doneItems = [];
foreach ($items as $item) {
    if (!is_array($item)) { continue; }
    if ((bool) ($item["done"] ?? false)) {
        $doneItems[] = $item;
        continue;
    }
    $openItems[] = $item;
}
$priorityRank = ["high" => 0, "normal" => 1, "low" => 2];
$dueRank = static function (array $item) use ($today): int {
    $dueDate = (string) ($item["due_date"] ?? "");
    if ($dueDate === "") { return 2; }
    return $dueDate < $today ? 0 : 1;
};
usort($openItems, static function (array $first, array $second) use ($dueRank, $priorityRank, $priorityClass): int {
    $firstDue = $dueRank($first);
    $secondDue = $dueRank($second);
    if ($firstDue !== $secondDue) { return $firstDue <=> $secondDue; }
    $firstPriority = $priorityRank[$priorityClass((string) ($first["priority"] ?? "normal"))] ?? 1;
    $secondPriority = $priorityRank[$priorityClass((string) ($second["priority"] ?? "normal"))] ?? 1;
    if ($firstPriority !== $secondPriority) { return $firstPriority <=> $secondPriority; }
    return strcmp((string) ($first["due_date"] ?? ""), (string) ($second["due_date"] ?? ""));
});
$orderedItems = array_merge($openItems, $doneItems);
$focusItem = $openItems[0] ?? null;
$owner = (string) ($todo["owner"] ?? "developer");

require __DIR__ . "/panel-header.php";
?>

        <section class="developer-dashboard-section" aria-label="Private tasks summary">
          <div class="developer-panel-intro developer-private-todo-intro">
            <div class="developer-panel-intro-copy">
              <p class="feature-kicker">Private work queue</p>
              <h2 class="developer-dashboard-section-title">Personal execution list</h2>
              <p class="content-text mb-0">Use this for your own reminders, release notes, follow-ups and scratch work. Shared project delivery still belongs in Project work.</p>
            </div>
            <div class="developer-panel-intro-actions">
              <span class="developer-dashboard-status is-neutral">Owner: <?= h($owner) ?></span>
              <span class="developer-dashboard-status">Private</span>
            </div>
          </div>
          <div class="developer-dashboard-status-grid developer-private-todo-summary-grid">
            <article class="developer-dashboard-status-card">
              <div class="developer-dashboard-card-head"><strong>Open</strong><span class="developer-dashboard-ok"><?= h((string) ($todo["open_count"] ?? 0)) ?></span></div>
              <h3><?= h((string) count($items)) ?> personal items</h3>
              <p><?= h((string) ($todo["done_count"] ?? 0)) ?> completed and kept for context.</p>
            </article>
            <article class="developer-dashboard-status-card">
              <div class="developer-dashboard-card-head"><strong>Due soon</strong><span class="developer-dashboard-ok"><?= h((string) ($todo["due_soon_count"] ?? 0)) ?></span></div>
              <p><?= h((string) ($todo["overdue_count"] ?? 0)) ?> overdue items need attention first.</p>
            </article>
            <article class="developer-dashboard-status-card">
              <div class="developer-dashboard-card-head"><strong>Subtasks</strong><span class="developer-dashboard-ok"><?= h((string) ($todo["subtasks_count"] ?? 0)) ?></span></div>
              <p><?= h((string) ($todo["completed_subtasks_count"] ?? 0)) ?> subtasks already completed.</p>
            </article>
            <article class="developer-dashboard-status-card">
              <div class="developer-dashboard-card-head"><strong>Attachments</strong><span class="developer-dashboard-ok"><?= h((string) ($todo["attachments_count"] ?? 0)) ?></span></div>
              <p>Files and reference links attached to private items.</p>
            </article>
          </div>
        </section>

        <section class="developer-dashboard-section" id="developer-private-todo-capture" aria-label="Private tasks workbench">
          <div class="developer-private-todo-shell">
            <div class="developer-private-todo-workbench">
              <div class="developer-private-todo-composer developer-private-todo-composer-minimal">
                <div class="developer-private-todo-composer-head">
                  <div>
                    <p class="feature-kicker">Capture</p>
                    <h2 class="developer-dashboard-section-title">Quick add a private task</h2>
                  </div>
                  <span class="developer-dashboard-refresh">Stored per developer</span>
                </div>
                <form class="developer-private-todo-quick-form" action="<?= h(route("developer.private_todo.items.create")) ?>" method="post" novalidate data-developer-ajax>
                  <?= csrf_field() ?>
                  <input type="hidden" name="developer_private_todo_priority" value="normal">
                  <input type="hidden" name="developer_private_todo_color" value="blue">
                  <label class="visually-hidden" for="developer-private-todo-title">Private task title</label>
                  <input class="input" id="developer-private-todo-title" name="developer_private_todo_title" type="text" maxlength="160" placeholder="Type a task and press Enter..." required>
                  <button class="btn btn-primary" type="submit">Add</button>
                </form>
                <p class="developer-private-todo-quick-hint">Details stay inside the created task. Open <strong>Edit details</strong> when a task needs priority, date, subtasks or attachments.</p>
              </div>
            </div>

            <div class="developer-private-todo-list" aria-label="Private task items">
              <div class="developer-dashboard-section-head">
                <h2 class="developer-dashboard-section-title">Execution queue</h2>
                <span class="developer-dashboard-refresh"><?= h((string) count($openItems)) ?> open / <?= h((string) count($doneItems)) ?> done</span>
              </div>
              <?php if ($items === []): ?>
              <article class="developer-project-log-empty">
                <p class="feature-kicker">No private items</p>
                <h3>Your personal developer list is empty.</h3>
              </article>
              <?php endif; ?>

              <?php foreach ($orderedItems as $item): ?>
              <?php
                  $itemId = (string) ($item["id"] ?? "");
                  $done = (bool) ($item["done"] ?? false);
                  $priority = $priorityClass((string) ($item["priority"] ?? "normal"));
                  $color = $colorClass((string) ($item["color"] ?? "blue"));
                  $dueDate = (string) ($item["due_date"] ?? "");
                  $dueClass = !$done && $dueDate !== "" && $dueDate < $today ? " is-overdue" : "";
                  $dueReadable = $formatDate($dueDate);
                  $dueLabel = $dueDate === "" ? "Not set" : ($dueDate < $today && !$done ? "Overdue " . $dueReadable : $dueReadable);
                  $updatedAt = (string) ($item["updated_at_utc"] ?? "");
                  $updatedLabel = $formatDateTime($updatedAt);
                  $subtasks = array_values((array) ($item["subtasks"] ?? []));
                  $attachments = array_values((array) ($item["attachments"] ?? []));
                  $progress = $subtaskSummary($subtasks);
                  $isNextAction = is_array($focusItem) && !$done && (string) ($focusItem["id"] ?? "") === $itemId;
                  $safeItemId = preg_replace('/[^A-Za-z0-9_-]/', "-", $itemId) ?: ("item-" . substr(hash("sha256", $itemId), 0, 8));
                  $editableSubtasks = $subtasks === [] ? [["text" => "", "done" => false]] : $subtasks;
                  $privateTaskConfirmName = trim((string) ($item["title"] ?? ""));
                  $privateTaskConfirmName = $privateTaskConfirmName !== "" ? $privateTaskConfirmName : "Untitled private task";
                  $privateTaskDeleteConfirmTitle = "Delete private task: " . $privateTaskConfirmName;
                  $privateTaskDeleteConfirmMessage = 'Delete "' . $privateTaskConfirmName . '" from your private My Tasks list? This removes its subtasks and attachments from your developer account.';
              ?>
              <article class="developer-private-todo-item is-color-<?= h($color) ?><?= $done ? " is-done" : "" ?><?= h($dueClass) ?><?= $isNextAction ? " is-next-action" : "" ?>">
                <form action="<?= h(route("developer.private_todo.items.toggle")) ?>" method="post" data-developer-ajax>
                  <?= csrf_field() ?>
                  <input type="hidden" name="developer_private_todo_id" value="<?= h($itemId) ?>">
                  <button class="developer-private-todo-check" type="submit" aria-label="<?= $done ? "Reopen item" : "Mark item done" ?>">
                    <span aria-hidden="true"></span>
                  </button>
                </form>
                <div class="developer-private-todo-copy">
                  <div class="developer-dashboard-card-head">
                    <strong><?= h((string) ($item["title"] ?? "Private item")) ?></strong>
                    <span class="developer-private-todo-badges">
                      <?php if ($isNextAction): ?>
                      <span class="developer-dashboard-status">Next action</span>
                      <?php endif; ?>
                      <span class="developer-private-todo-priority is-<?= h($priority) ?>"><?= h((string) ($priorityLabels[$priority] ?? "Normal")) ?></span>
                    </span>
                  </div>
                  <?php if (($item["notes"] ?? "") !== ""): ?>
                  <p><?= h((string) ($item["notes"] ?? "")) ?></p>
                  <?php endif; ?>
                  <?php if ($subtasks !== []): ?>
                  <div class="developer-private-todo-subtasks">
                    <div class="developer-private-todo-progress" aria-label="<?= h((string) $progress["percent"]) ?> percent of subtasks complete">
                      <span><b style="width: <?= h((string) $progress["percent"]) ?>%"></b></span>
                      <small><?= h((string) $progress["done"]) ?>/<?= h((string) $progress["total"]) ?> subtasks</small>
                    </div>
                    <ul class="developer-private-todo-subtask-list">
                      <?php foreach ($subtasks as $subtask): ?>
                      <li class="<?= ((bool) ($subtask["done"] ?? false)) ? "is-done" : "" ?>"><?= h((string) ($subtask["text"] ?? "")) ?></li>
                      <?php endforeach; ?>
                    </ul>
                  </div>
                  <?php endif; ?>
                  <?php if ($attachments !== []): ?>
                  <div class="developer-private-todo-attachment-list">
                    <?php foreach ($attachments as $attachment): ?>
                    <?php
                        $attachmentUrl = (string) ($attachment["url"] ?? "");
                        $attachmentSize = $formatAttachmentSize((int) ($attachment["size_bytes"] ?? 0));
                        $attachmentMeta = trim(((string) ($attachment["type"] ?? "url")) . ($attachmentSize !== "" ? " / " . $attachmentSize : ""));
                    ?>
                    <a href="<?= h($attachmentUrl) ?>" target="_blank" rel="noopener noreferrer">
                      <strong><?= h((string) ($attachment["label"] ?? "Attachment")) ?></strong>
                      <small><?= h($attachmentMeta !== "" ? $attachmentMeta : "Reference") ?></small>
                    </a>
                    <?php endforeach; ?>
                  </div>
                  <?php endif; ?>
                  <div class="developer-private-todo-detail-row">
                    <details class="developer-private-todo-editor">
                      <summary>Edit details</summary>
                      <form class="developer-private-todo-editor-form" action="<?= h(route("developer.private_todo.items.update")) ?>" method="post" enctype="multipart/form-data" novalidate data-developer-ajax>
                        <?= csrf_field() ?>
                        <input type="hidden" name="developer_private_todo_id" value="<?= h($itemId) ?>">
                        <div class="developer-private-todo-editor-grid">
                          <div class="form-group developer-private-todo-editor-title">
                            <label class="label" for="developer-private-todo-title-<?= h($safeItemId) ?>">Task title</label>
                            <input class="input" id="developer-private-todo-title-<?= h($safeItemId) ?>" name="developer_private_todo_title" type="text" maxlength="160" value="<?= h((string) ($item["title"] ?? "")) ?>" required>
                          </div>
                          <fieldset class="developer-private-todo-priority-field">
                            <legend>Priority</legend>
                            <div class="developer-private-todo-priority-picker" role="radiogroup" aria-label="Private task priority">
                              <?php foreach ($priorityLabels as $priorityValue => $label): ?>
                              <label>
                                <input type="radio" name="developer_private_todo_priority" value="<?= h($priorityValue) ?>" <?= $priorityValue === $priority ? "checked" : "" ?>>
                                <span><?= h($label) ?></span>
                              </label>
                              <?php endforeach; ?>
                            </div>
                          </fieldset>
                          <div class="form-group developer-private-todo-due-field">
                            <label class="label" for="developer-private-todo-due-<?= h($safeItemId) ?>">Due date</label>
                            <input class="input" id="developer-private-todo-due-<?= h($safeItemId) ?>" name="developer_private_todo_due_date" type="date" value="<?= h($dueDate) ?>">
                          </div>
                          <fieldset class="developer-private-todo-color-field">
                            <legend>Color</legend>
                            <div class="developer-private-todo-color-picker developer-kanban-color-picker" role="radiogroup" aria-label="Private task color">
                              <?php foreach ($colorLabels as $colorValue => $label): ?>
                              <label class="developer-kanban-color-option developer-kanban-color-option-<?= h($colorValue) ?>" data-fnlla-tooltip="<?= h($label) ?>" data-fnlla-tooltip-position="top">
                                <input type="radio" name="developer_private_todo_color" value="<?= h($colorValue) ?>" <?= $colorValue === $color ? "checked" : "" ?>>
                                <span aria-hidden="true"></span>
                                <em><?= h($label) ?></em>
                              </label>
                              <?php endforeach; ?>
                            </div>
                          </fieldset>
                          <div class="form-group developer-private-todo-notes developer-private-todo-form-wide">
                            <label class="label" for="developer-private-todo-notes-<?= h($safeItemId) ?>">Private note</label>
                            <textarea class="textarea" id="developer-private-todo-notes-<?= h($safeItemId) ?>" name="developer_private_todo_notes" rows="3" maxlength="700" placeholder="Context, decisions, links to check or a short next-action note."><?= h((string) ($item["notes"] ?? "")) ?></textarea>
                          </div>
                          <div class="developer-private-todo-subtasks-field developer-private-todo-form-wide" data-private-todo-subtasks data-private-todo-subtasks-id="developer-private-todo-edit-<?= h($safeItemId) ?>">
                            <div class="developer-private-todo-field-head">
                              <div>
                                <p class="feature-kicker">Subtasks</p>
                                <h3>Task steps</h3>
                              </div>
                              <button class="btn btn-ghost btn-sm" type="button" data-private-todo-subtask-add>Add subtask</button>
                            </div>
                            <div class="developer-private-todo-subtask-builder" data-private-todo-subtask-list>
                              <?php foreach ($editableSubtasks as $subtaskIndex => $subtask): ?>
                              <?php $subtaskId = "developer-private-todo-subtask-" . $safeItemId . "-" . (string) $subtaskIndex; ?>
                              <div class="developer-private-todo-subtask-row">
                                <label class="developer-private-todo-subtask-toggle" for="<?= h($subtaskId) ?>-done">
                                  <input id="<?= h($subtaskId) ?>-done" type="checkbox" name="developer_private_todo_subtasks_done[]" value="<?= h((string) $subtaskIndex) ?>" <?= ((bool) ($subtask["done"] ?? false)) ? "checked" : "" ?>>
                                  <span aria-hidden="true"></span>
                                </label>
                                <label class="visually-hidden" for="<?= h($subtaskId) ?>">Subtask <?= h((string) ($subtaskIndex + 1)) ?></label>
                                <input class="input" id="<?= h($subtaskId) ?>" name="developer_private_todo_subtasks[<?= h((string) $subtaskIndex) ?>]" type="text" maxlength="140" value="<?= h((string) ($subtask["text"] ?? "")) ?>" placeholder="Optional step">
                              </div>
                              <?php endforeach; ?>
                            </div>
                            <small class="form-hint">Delete text to remove a subtask. Checked rows stay completed.</small>
                          </div>
                          <div class="developer-private-todo-attachment-fields developer-private-todo-form-wide">
                            <div class="developer-private-todo-field-head developer-private-todo-attachment-head">
                              <div>
                                <p class="feature-kicker">Reference</p>
                                <h3>Add another attachment</h3>
                              </div>
                              <span class="developer-dashboard-refresh"><?= h((string) count($attachments)) ?> saved</span>
                            </div>
                            <div class="form-group">
                              <label class="label" for="developer-private-todo-attachment-label-<?= h($safeItemId) ?>">Attachment label</label>
                              <input class="input" id="developer-private-todo-attachment-label-<?= h($safeItemId) ?>" name="developer_private_todo_attachment_label" type="text" maxlength="100" placeholder="Screenshot, vendor email, checklist">
                            </div>
                            <div class="form-group">
                              <label class="label" for="developer-private-todo-attachment-url-<?= h($safeItemId) ?>">Attachment URL</label>
                              <input class="input" id="developer-private-todo-attachment-url-<?= h($safeItemId) ?>" name="developer_private_todo_attachment_url" type="url" maxlength="240" placeholder="https://example.test/reference">
                            </div>
                            <div class="form-group">
                              <label class="label" for="developer-private-todo-attachment-file-<?= h($safeItemId) ?>">Attachment file</label>
                              <input class="input developer-kanban-file-input" id="developer-private-todo-attachment-file-<?= h($safeItemId) ?>" name="developer_private_todo_attachment_file" type="file" accept="image/jpeg,image/png,image/webp,application/pdf,text/plain,text/csv,application/zip,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,.jpg,.jpeg,.png,.webp,.pdf,.txt,.csv,.zip,.doc,.docx,.xls,.xlsx">
                            </div>
                          </div>
                          <div class="developer-private-todo-actions developer-private-todo-form-wide">
                            <span class="developer-dashboard-refresh">Saved only in your private developer list</span>
                            <button class="btn btn-primary" type="submit">Save details</button>
                          </div>
                        </div>
                      </form>
                    </details>
                    <form class="developer-private-todo-delete-form" action="<?= h(route("developer.private_todo.items.delete")) ?>" method="post" data-developer-ajax data-developer-confirm="delete" data-developer-confirm-title="<?= h($privateTaskDeleteConfirmTitle) ?>" data-developer-confirm-message="<?= h($privateTaskDeleteConfirmMessage) ?>" data-developer-confirm-action="Delete task">
                      <?= csrf_field() ?>
                      <input type="hidden" name="developer_private_todo_id" value="<?= h($itemId) ?>">
                      <button class="btn btn-ghost btn-sm developer-private-todo-delete" type="submit">Delete</button>
                    </form>
                  </div>
                  <div class="developer-project-log-facts">
                    <span><b>Status</b> <?= $done ? "Done" : "Open" ?></span>
                    <span><b>Due</b> <?= h($dueLabel) ?></span>
                    <span><b>Updated</b> <time datetime="<?= h($updatedAt) ?>"><?= h($updatedLabel) ?></time></span>
                  </div>
                </div>
              </article>
              <?php endforeach; ?>
            </div>
          </div>
        </section>

<?php require __DIR__ . "/panel-footer.php"; ?>
