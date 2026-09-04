<?php

declare(strict_types=1);

$customerPanelTitle = "Project Kanban";
$customerPanelLead = "Client-visible project tasks in a read-only delivery board.";
$board = is_array($workspaceBoard ?? null) ? (array) $workspaceBoard : [];
$columns = (array) ($board["columns"] ?? []);
$priorities = (array) ($board["priorities"] ?? []);
$types = (array) ($board["types"] ?? []);
$columnsWithTasks = (array) ($board["columns_with_tasks"] ?? []);
$allTasks = (array) ($board["tasks"] ?? []);
$today = gmdate("Y-m-d");
require __DIR__ . "/panel-header.php";
?>

      <section class="customer-kanban-summary" aria-label="Kanban summary">
        <span>All <?= h((string) count($allTasks)) ?></span>
        <span>Open <?= h((string) ($board["open_tasks_count"] ?? 0)) ?></span>
        <span>Done <?= h((string) ($board["done_tasks_count"] ?? 0)) ?></span>
        <span>Blocked <?= h((string) ($board["blocked_tasks_count"] ?? 0)) ?></span>
        <span><?= h((string) ($board["completion_percent"] ?? 0)) ?>% complete</span>
      </section>

      <section class="customer-kanban-board" aria-label="Client-visible project tasks">
        <?php foreach ($columns as $status => $label): ?>
        <?php $tasks = (array) ($columnsWithTasks[$status] ?? []); ?>
        <article class="customer-kanban-column">
          <header>
            <h2><?= h((string) $label) ?></h2>
            <span><?= h((string) count($tasks)) ?></span>
          </header>
          <div class="customer-kanban-list">
            <?php if ($tasks === []): ?>
            <p class="customer-kanban-empty">No customer-visible tasks in this column.</p>
            <?php endif; ?>
            <?php foreach ($tasks as $task): ?>
            <?php
                $checklist = (array) ($task["checklist"] ?? []);
                $checklistDone = count(array_filter($checklist, static fn (array $item): bool => (bool) ($item["done"] ?? false)));
                $checklistTotal = count($checklist);
                $checklistPercent = $checklistTotal > 0 ? (int) round(($checklistDone / max(1, $checklistTotal)) * 100) : 0;
                $attachments = (array) ($task["attachments"] ?? []);
                $taskDueClass = ($task["due_date"] ?? "") !== "" && (string) ($task["due_date"] ?? "") < $today && ($task["status"] ?? "") !== "done" ? " is-overdue" : "";
            ?>
            <article class="customer-kanban-card<?= h($taskDueClass) ?>">
              <div class="customer-kanban-card-meta">
                <span><?= h((string) ($types[$task["type"] ?? "task"] ?? "Task")) ?></span>
                <span><?= h((string) ($priorities[$task["priority"] ?? "normal"] ?? "Normal")) ?></span>
                <?php if (($task["blocked"] ?? false) === true): ?><span>Blocked</span><?php endif; ?>
              </div>
              <h3><?= h((string) ($task["title"] ?? "Task")) ?></h3>
              <?php if (($task["notes"] ?? "") !== ""): ?>
              <p><?= h((string) $task["notes"]) ?></p>
              <?php endif; ?>
              <?php if ($checklistTotal > 0): ?>
              <div class="customer-progress" aria-label="<?= h((string) $checklistDone) ?> of <?= h((string) $checklistTotal) ?> subtasks complete">
                <div><span><?= h((string) $checklistDone) ?>/<?= h((string) $checklistTotal) ?> subtasks</span><small><?= h((string) $checklistPercent) ?>%</small></div>
                <i aria-hidden="true"><b style="width: <?= h((string) $checklistPercent) ?>%"></b></i>
              </div>
              <ul class="customer-subtask-list">
                <?php foreach ($checklist as $item): ?>
                <li class="<?= ($item["done"] ?? false) ? "is-complete" : "" ?>"><?= h((string) ($item["text"] ?? "")) ?></li>
                <?php endforeach; ?>
              </ul>
              <?php endif; ?>
              <footer>
                <span><?= ($task["due_date"] ?? "") !== "" ? "Due " . h((string) $task["due_date"]) : "No due date" ?></span>
                <span><?= ($task["estimate"] ?? "") !== "" ? h((string) $task["estimate"]) : "Estimate pending" ?></span>
              </footer>
              <?php if ($attachments !== []): ?>
              <div class="customer-attachment-list">
                <?php foreach ($attachments as $attachment): ?>
                <a href="<?= h((string) ($attachment["url"] ?? "#")) ?>" target="_blank" rel="noopener noreferrer">
                  <span><?= h(strtoupper(substr(pathinfo((string) (($attachment["original_name"] ?? "") ?: ($attachment["label"] ?? "file")), PATHINFO_EXTENSION) ?: "LINK", 0, 4))) ?></span>
                  <strong><?= h((string) ($attachment["label"] ?? "Attachment")) ?></strong>
                </a>
                <?php endforeach; ?>
              </div>
              <?php endif; ?>
            </article>
            <?php endforeach; ?>
          </div>
        </article>
        <?php endforeach; ?>
      </section>

<?php require __DIR__ . "/panel-footer.php"; ?>
