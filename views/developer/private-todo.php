<?php

declare(strict_types=1);

$developerPanelTitle = "My To-do";
$developerPanelLead = "Private developer notes and personal tasks outside the shared project Kanban.";
$todo = is_array($privateTodo ?? null) ? (array) $privateTodo : [];
$items = array_values((array) ($todo["items"] ?? []));
$today = gmdate("Y-m-d");
$priorityLabels = [
    "low" => "Low",
    "normal" => "Normal",
    "high" => "High",
];
$priorityClass = static fn (string $priority): string => in_array($priority, ["low", "normal", "high"], true) ? $priority : "normal";

require __DIR__ . "/panel-header.php";
?>

        <section class="developer-dashboard-section" aria-label="Private to-do summary">
          <div class="developer-dashboard-status-grid">
            <article class="developer-dashboard-status-card">
              <div class="developer-dashboard-card-head"><strong>Open</strong><span class="developer-dashboard-ok"><?= h((string) ($todo["open_count"] ?? 0)) ?></span></div>
              <h3><?= h((string) count($items)) ?> personal items</h3>
              <p>Done: <?= h((string) ($todo["done_count"] ?? 0)) ?>.</p>
            </article>
            <article class="developer-dashboard-status-card">
              <div class="developer-dashboard-card-head"><strong>Due soon</strong><span class="developer-dashboard-ok"><?= h((string) ($todo["due_soon_count"] ?? 0)) ?></span></div>
              <p>Items due in the next seven days.</p>
            </article>
            <article class="developer-dashboard-status-card">
              <div class="developer-dashboard-card-head"><strong>Overdue</strong><span class="developer-dashboard-ok"><?= h((string) ($todo["overdue_count"] ?? 0)) ?></span></div>
              <p>Open items past their due date.</p>
            </article>
            <article class="developer-dashboard-status-card">
              <div class="developer-dashboard-card-head"><strong>Notes</strong><span class="developer-dashboard-ok"><?= h((string) ($todo["notes_count"] ?? 0)) ?></span></div>
              <p><?= h((string) ($todo["owner"] ?? "developer")) ?></p>
            </article>
          </div>
        </section>

        <section class="developer-dashboard-section" aria-label="Create private to-do">
          <div class="developer-private-todo-shell">
            <form class="form developer-private-todo-form" action="<?= h(route("developer.private_todo.items.create")) ?>" method="post" novalidate>
              <?= csrf_field() ?>
              <div class="form-group developer-private-todo-title">
                <label class="label" for="developer-private-todo-title">Item</label>
                <input class="input" id="developer-private-todo-title" name="developer_private_todo_title" type="text" maxlength="160" required>
              </div>
              <div class="form-group">
                <label class="label" for="developer-private-todo-priority">Priority</label>
                <select class="select" id="developer-private-todo-priority" name="developer_private_todo_priority">
                  <?php foreach ($priorityLabels as $priority => $label): ?>
                  <option value="<?= h($priority) ?>" <?= $priority === "normal" ? "selected" : "" ?>><?= h($label) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group">
                <label class="label" for="developer-private-todo-due">Due date</label>
                <input class="input" id="developer-private-todo-due" name="developer_private_todo_due_date" type="date">
              </div>
              <div class="form-group developer-private-todo-notes">
                <label class="label" for="developer-private-todo-notes">Notes</label>
                <textarea class="textarea" id="developer-private-todo-notes" name="developer_private_todo_notes" rows="3" maxlength="700"></textarea>
              </div>
              <div class="developer-private-todo-actions">
                <button class="btn btn-primary" type="submit">Add item</button>
              </div>
            </form>

            <div class="developer-private-todo-list" aria-label="Private to-do items">
              <?php if ($items === []): ?>
              <article class="developer-project-log-empty">
                <p class="feature-kicker">No private items</p>
                <h3>Your personal developer list is empty.</h3>
              </article>
              <?php endif; ?>

              <?php foreach ($items as $item): ?>
              <?php
                  $itemId = (string) ($item["id"] ?? "");
                  $done = (bool) ($item["done"] ?? false);
                  $priority = $priorityClass((string) ($item["priority"] ?? "normal"));
                  $dueDate = (string) ($item["due_date"] ?? "");
                  $dueClass = !$done && $dueDate !== "" && $dueDate < $today ? " is-overdue" : "";
              ?>
              <article class="developer-private-todo-item<?= $done ? " is-done" : "" ?><?= h($dueClass) ?>">
                <form action="<?= h(route("developer.private_todo.items.toggle")) ?>" method="post">
                  <?= csrf_field() ?>
                  <input type="hidden" name="developer_private_todo_id" value="<?= h($itemId) ?>">
                  <button class="developer-private-todo-check" type="submit" aria-label="<?= $done ? "Reopen item" : "Mark item done" ?>">
                    <span aria-hidden="true"><?= $done ? "x" : "" ?></span>
                  </button>
                </form>
                <div class="developer-private-todo-copy">
                  <div class="developer-dashboard-card-head">
                    <strong><?= h((string) ($item["title"] ?? "Private item")) ?></strong>
                    <span class="developer-private-todo-priority is-<?= h($priority) ?>"><?= h((string) ($priorityLabels[$priority] ?? "Normal")) ?></span>
                  </div>
                  <?php if (($item["notes"] ?? "") !== ""): ?>
                  <p><?= h((string) ($item["notes"] ?? "")) ?></p>
                  <?php endif; ?>
                  <div class="developer-project-log-facts">
                    <span><b>Status</b> <?= $done ? "Done" : "Open" ?></span>
                    <span><b>Due</b> <?= $dueDate !== "" ? h($dueDate) : "Not set" ?></span>
                    <span><b>Updated</b> <?= h((string) ($item["updated_at_utc"] ?? "")) ?></span>
                  </div>
                </div>
                <form action="<?= h(route("developer.private_todo.items.delete")) ?>" method="post">
                  <?= csrf_field() ?>
                  <input type="hidden" name="developer_private_todo_id" value="<?= h($itemId) ?>">
                  <button class="btn btn-ghost btn-sm" type="submit">Delete</button>
                </form>
              </article>
              <?php endforeach; ?>
            </div>
          </div>
        </section>

<?php require __DIR__ . "/panel-footer.php"; ?>
