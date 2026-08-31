<?php

declare(strict_types=1);

$developerPanelTitle = "Project Workspace";
$developerPanelLead = "Shared Kanban board for project delivery work.";
$board = is_array($workspaceBoard ?? null) ? $workspaceBoard : [];
$columns = (array) ($board["columns"] ?? []);
$priorities = (array) ($board["priorities"] ?? []);
$types = (array) ($board["types"] ?? []);
$colors = (array) ($board["colors"] ?? []);
$columnsWithTasks = (array) ($board["columns_with_tasks"] ?? []);
$allTasks = (array) ($board["tasks"] ?? []);
$currentDeveloper = is_array($developerAccess["current_developer"] ?? null) ? (array) $developerAccess["current_developer"] : [];
$currentEmail = strtolower(trim((string) ($currentDeveloper["email"] ?? "")));
$developerAccounts = array_values(array_filter((array) ($developerAccess["accounts"] ?? []), static fn ($account): bool => is_array($account)));
$participantsByEmail = [];

foreach ($developerAccounts as $account) {
    $email = strtolower(trim((string) ($account["email"] ?? "")));

    if ($email !== "") {
        $participantsByEmail[$email] = (array) $account;
    }
}

if ($currentEmail !== "" && !isset($participantsByEmail[$currentEmail])) {
    $participantsByEmail[$currentEmail] = $currentDeveloper;
}

foreach ($allTasks as $task) {
    $email = strtolower(trim((string) ($task["assignee"] ?? "")));

    if ($email !== "" && !isset($participantsByEmail[$email])) {
        $participantsByEmail[$email] = [
            "email" => $email,
            "name" => $email,
            "avatar" => "",
        ];
    }
}

$participants = array_values($participantsByEmail);
$participantFor = static function (string $email) use ($participantsByEmail): array {
    $email = strtolower(trim($email));

    return $participantsByEmail[$email] ?? [
        "email" => $email,
        "name" => $email !== "" ? $email : "Unassigned",
        "avatar" => "",
    ];
};
$avatarText = static function (array $participant): string {
    $avatar = trim((string) ($participant["avatar"] ?? ""));

    if ($avatar !== "" && filter_var($avatar, FILTER_VALIDATE_URL) === false && !str_starts_with($avatar, "/uploads/developer-avatars/")) {
        return strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $avatar) ?: "D", 0, 2));
    }

    $source = (string) (($participant["name"] ?? "") ?: ($participant["email"] ?? "Developer"));
    $parts = array_values(array_filter(preg_split('/[^A-Za-z0-9]+/', $source) ?: []));

    if (count($parts) >= 2) {
        return strtoupper(substr($parts[0], 0, 1) . substr($parts[1], 0, 1));
    }

    return strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $source) ?: "D", 0, 2));
};
$avatarIsImage = static function (array $participant): bool {
    $avatar = trim((string) ($participant["avatar"] ?? ""));

    return $avatar !== "" && (filter_var($avatar, FILTER_VALIDATE_URL) !== false || str_starts_with($avatar, "/uploads/developer-avatars/"));
};
$checklistToText = static function (array $checklist): string {
    return implode("\n", array_map(static fn (array $item): string => ((bool) ($item["done"] ?? false) ? "[x] " : "[ ] ") . (string) ($item["text"] ?? ""), $checklist));
};
$today = gmdate("Y-m-d");
$totalTasks = count($allTasks);
$urgentTasks = count(array_filter($allTasks, static fn (array $task): bool => (string) ($task["priority"] ?? "") === "urgent"));
$highPriorityTasks = count(array_filter($allTasks, static fn (array $task): bool => in_array((string) ($task["priority"] ?? ""), ["high", "urgent"], true)));
$lowPriorityTasks = count(array_filter($allTasks, static fn (array $task): bool => (string) ($task["priority"] ?? "") === "low"));
$overdueTasks = count(array_filter($allTasks, static fn (array $task): bool => ($task["status"] ?? "") !== "done" && (string) ($task["due_date"] ?? "") !== "" && (string) ($task["due_date"] ?? "") < $today));
$columnAccents = [
    "backlog" => "slate",
    "todo" => "blue",
    "in_progress" => "sky",
    "review" => "indigo",
    "done" => "green",
];

require __DIR__ . "/panel-header.php";
?>

        <section class="developer-dashboard-section developer-workspace-summary" aria-label="Workspace summary">
          <div class="developer-dashboard-status-grid">
            <article class="developer-dashboard-status-card">
              <div class="developer-dashboard-card-head">
                <strong>Total tasks</strong>
                <span class="developer-dashboard-ok">LIVE</span>
              </div>
              <h3><?= h((string) $totalTasks) ?></h3>
              <p><?= h((string) ($board["open_tasks_count"] ?? 0)) ?> open, <?= h((string) ($board["done_tasks_count"] ?? 0)) ?> done.</p>
            </article>
            <article class="developer-dashboard-status-card">
              <div class="developer-dashboard-card-head">
                <strong>High priority</strong>
              </div>
              <h3><?= h((string) $highPriorityTasks) ?></h3>
              <p><?= h((string) $urgentTasks) ?> urgent tasks. Assigned to me: <?= h((string) ($board["my_tasks_count"] ?? 0)) ?>.</p>
            </article>
            <article class="developer-dashboard-status-card">
              <div class="developer-dashboard-card-head">
                <strong>Low priority</strong>
              </div>
              <h3><?= h((string) $lowPriorityTasks) ?></h3>
              <p>Lower-risk cards that can stay behind release-critical work.</p>
            </article>
            <article class="developer-dashboard-status-card">
              <div class="developer-dashboard-card-head">
                <strong>Overdue</strong>
              </div>
              <h3><?= h((string) $overdueTasks) ?></h3>
              <p><?= h((string) ($board["due_soon_count"] ?? 0)) ?> due soon, <?= h((string) ($board["blocked_tasks_count"] ?? 0)) ?> blocked.</p>
            </article>
          </div>
        </section>

        <section class="developer-dashboard-section" aria-label="Kanban board">
          <div class="developer-dashboard-section-head developer-kanban-board-head">
            <div>
              <h2 class="developer-dashboard-section-title">Project Kanban</h2>
              <span class="developer-dashboard-refresh">Shared workspace / <?= h((string) ($board["completion_percent"] ?? 0)) ?>% complete</span>
            </div>
            <div class="developer-kanban-participants" aria-label="Workspace participants">
              <?php foreach (array_slice($participants, 0, 5) as $participant): ?>
              <?php $participantName = (string) (($participant["name"] ?? "") ?: ($participant["email"] ?? "Developer")); ?>
              <span class="developer-kanban-avatar" title="<?= h($participantName) ?>" aria-label="<?= h($participantName) ?>">
                <?php if ($avatarIsImage((array) $participant)): ?>
                <img src="<?= h((string) ($participant["avatar"] ?? "")) ?>" alt="">
                <?php else: ?>
                <?= h($avatarText((array) $participant)) ?>
                <?php endif; ?>
              </span>
              <?php endforeach; ?>
              <?php if (count($participants) > 5): ?>
              <span class="developer-kanban-avatar developer-kanban-avatar-more">+<?= h((string) (count($participants) - 5)) ?></span>
              <?php endif; ?>
            </div>
          </div>

          <div class="developer-kanban-toolbar" aria-label="Kanban filters">
            <label class="developer-kanban-search">
              <span class="visually-hidden">Search tasks</span>
              <input class="input" type="search" placeholder="Search tasks..." data-developer-kanban-search>
            </label>
            <div class="developer-kanban-filter-tabs" aria-label="Priority counts">
              <span class="is-active">All <b><?= h((string) $totalTasks) ?></b></span>
              <span>Urgent <b><?= h((string) $urgentTasks) ?></b></span>
              <span>High <b><?= h((string) $highPriorityTasks) ?></b></span>
              <span>Low <b><?= h((string) $lowPriorityTasks) ?></b></span>
              <span>Blocked <b><?= h((string) ($board["blocked_tasks_count"] ?? 0)) ?></b></span>
            </div>
          </div>

          <div class="developer-kanban-board" data-developer-kanban>
            <?php foreach ($columns as $status => $label): ?>
            <?php $tasks = (array) ($columnsWithTasks[$status] ?? []); ?>
            <article class="developer-kanban-column developer-kanban-column-<?= h((string) ($columnAccents[$status] ?? "blue")) ?>" data-developer-kanban-column="<?= h((string) $status) ?>">
              <div class="developer-kanban-column-head">
                <div>
                  <h3><?= h((string) $label) ?></h3>
                  <small><?= h((string) count($tasks)) ?> cards</small>
                </div>
                <button class="developer-kanban-column-add" type="button" data-fnlla-modal-open="#developer-kanban-create-<?= h((string) $status) ?>" aria-label="Add card to <?= h((string) $label) ?>">+</button>
              </div>

              <div class="modal developer-kanban-modal" id="developer-kanban-create-<?= h((string) $status) ?>" data-fnlla-modal role="dialog" aria-modal="true" aria-labelledby="developer-kanban-create-title-<?= h((string) $status) ?>" hidden>
                <div class="modal-content developer-kanban-modal-content">
                  <div class="developer-kanban-modal-head">
                    <div>
                      <p class="feature-kicker mb-2"><?= h((string) $label) ?></p>
                      <h2 class="content-title mb-0" id="developer-kanban-create-title-<?= h((string) $status) ?>">Add task</h2>
                    </div>
                    <button class="developer-kanban-modal-close" type="button" data-fnlla-modal-close aria-label="Close task modal">Close</button>
                  </div>
                  <form class="form developer-kanban-modal-form" action="<?= h(route("developer.workspace.tasks.create")) ?>" method="post" novalidate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="developer_workspace_status" value="<?= h((string) $status) ?>">
                    <div class="form-group developer-kanban-modal-wide">
                      <label class="label" for="developer-kanban-create-title-input-<?= h((string) $status) ?>">Task title</label>
                      <input class="input" id="developer-kanban-create-title-input-<?= h((string) $status) ?>" name="developer_workspace_title" type="text" maxlength="120" required data-fnlla-modal-initial-focus>
                    </div>
                    <div class="form-group">
                      <label class="label" for="developer-kanban-create-type-<?= h((string) $status) ?>">Type</label>
                      <select class="select" id="developer-kanban-create-type-<?= h((string) $status) ?>" name="developer_workspace_type">
                        <?php foreach ($types as $type => $typeLabel): ?>
                        <option value="<?= h((string) $type) ?>"><?= h((string) $typeLabel) ?></option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="form-group">
                      <label class="label" for="developer-kanban-create-priority-<?= h((string) $status) ?>">Priority</label>
                      <select class="select" id="developer-kanban-create-priority-<?= h((string) $status) ?>" name="developer_workspace_priority">
                        <?php foreach ($priorities as $priority => $priorityLabel): ?>
                        <option value="<?= h((string) $priority) ?>" <?= $priority === "normal" ? "selected" : "" ?>><?= h((string) $priorityLabel) ?></option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="form-group">
                      <label class="label" for="developer-kanban-create-color-<?= h((string) $status) ?>">Color</label>
                      <select class="select" id="developer-kanban-create-color-<?= h((string) $status) ?>" name="developer_workspace_color">
                        <?php foreach ($colors as $color => $colorLabel): ?>
                        <option value="<?= h((string) $color) ?>"><?= h((string) $colorLabel) ?></option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="form-group">
                      <label class="label" for="developer-kanban-create-assignee-<?= h((string) $status) ?>">Assignee</label>
                      <select class="select" id="developer-kanban-create-assignee-<?= h((string) $status) ?>" name="developer_workspace_assignee">
                        <option value="">Unassigned</option>
                        <?php foreach ($participants as $participant): ?>
                        <?php $email = strtolower(trim((string) ($participant["email"] ?? ""))); ?>
                        <?php if ($email !== ""): ?>
                        <option value="<?= h($email) ?>" <?= $email === $currentEmail ? "selected" : "" ?>><?= h((string) (($participant["name"] ?? "") ?: $email)) ?></option>
                        <?php endif; ?>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="form-group">
                      <label class="label" for="developer-kanban-create-due-<?= h((string) $status) ?>">Due date</label>
                      <input class="input" id="developer-kanban-create-due-<?= h((string) $status) ?>" name="developer_workspace_due_date" type="date">
                    </div>
                    <div class="form-group">
                      <label class="label" for="developer-kanban-create-estimate-<?= h((string) $status) ?>">Estimate</label>
                      <input class="input" id="developer-kanban-create-estimate-<?= h((string) $status) ?>" name="developer_workspace_estimate" type="text" maxlength="24" placeholder="30m">
                    </div>
                    <label class="developer-workspace-check">
                      <input type="checkbox" name="developer_workspace_blocked" value="1">
                      <span>Blocked</span>
                    </label>
                    <div class="form-group developer-kanban-modal-wide">
                      <label class="label" for="developer-kanban-create-notes-<?= h((string) $status) ?>">Notes</label>
                      <textarea class="textarea" id="developer-kanban-create-notes-<?= h((string) $status) ?>" name="developer_workspace_notes" rows="3" maxlength="280"></textarea>
                    </div>
                    <div class="form-group developer-kanban-modal-wide">
                      <label class="label" for="developer-kanban-create-checklist-<?= h((string) $status) ?>">Subtasks</label>
                      <textarea class="textarea" id="developer-kanban-create-checklist-<?= h((string) $status) ?>" name="developer_workspace_checklist" rows="4" maxlength="640" placeholder="[ ] Confirm copy&#10;[ ] Run release checks"></textarea>
                    </div>
                    <div class="developer-kanban-modal-actions developer-kanban-modal-wide">
                      <button class="btn btn-primary" type="submit">Add task</button>
                      <button class="btn btn-ghost" type="button" data-fnlla-modal-close>Cancel</button>
                    </div>
                  </form>
                </div>
              </div>

              <div class="developer-kanban-task-list" data-developer-kanban-list>
                <?php if ($tasks === []): ?>
                <p class="developer-kanban-empty">No tasks in this column.</p>
                <?php else: ?>
                <?php foreach ($tasks as $task): ?>
                <?php
                    $checklist = (array) ($task["checklist"] ?? []);
                    $checklistDone = count(array_filter($checklist, static fn (array $item): bool => (bool) ($item["done"] ?? false)));
                    $checklistTotal = count($checklist);
                    $comments = (array) ($task["comments"] ?? []);
                    $attachments = (array) ($task["attachments"] ?? []);
                    $taskBlocked = (bool) ($task["blocked"] ?? false);
                    $taskType = (string) ($task["type"] ?? "task");
                    $taskPriority = (string) ($task["priority"] ?? "normal");
                    $taskColor = (string) ($task["color"] ?? "blue");
                    $taskId = (string) ($task["id"] ?? "");
                    $taskPosition = (string) ($task["position"] ?? "");
                    $taskAssignee = strtolower(trim((string) ($task["assignee"] ?? "")));
                    $assignee = $participantFor($taskAssignee);
                    $assigneeName = (string) (($assignee["name"] ?? "") ?: ($taskAssignee !== "" ? $taskAssignee : "Unassigned"));
                    $lastChangedBy = (string) (($task["updated_by"] ?? "") ?: ($task["created_by"] ?? "developer"));
                    $lastChangedParticipant = $participantFor($lastChangedBy);
                    $checklistText = $checklistToText($checklist);
                    $modalId = "developer-kanban-task-modal-" . preg_replace('/[^A-Za-z0-9_-]/', "-", $taskId);
                    $taskKey = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $taskId) ?: "TASK", 0, 6));
                    $taskSearch = trim((string) ($task["title"] ?? "") . " " . (string) ($task["notes"] ?? "") . " " . (string) ($types[$taskType] ?? "Task") . " " . (string) ($priorities[$taskPriority] ?? "Normal") . " " . $assigneeName);
                    $taskDueClass = ($task["due_date"] ?? "") !== "" && (string) ($task["due_date"] ?? "") < $today && ($task["status"] ?? "") !== "done" ? " is-overdue" : "";
                ?>
                <article class="developer-kanban-task developer-kanban-task-<?= h($taskColor) ?><?= h($taskDueClass) ?> <?= $taskAssignee === $currentEmail && $currentEmail !== "" ? "is-mine" : "" ?>" draggable="true" tabindex="0" role="button" aria-label="Open task <?= h((string) ($task["title"] ?? "Task")) ?>" data-developer-kanban-task="<?= h($taskId) ?>" data-developer-kanban-position="<?= h($taskPosition) ?>" data-developer-kanban-modal="#<?= h($modalId) ?>" data-developer-kanban-search-text="<?= h(strtolower($taskSearch)) ?>">
                  <div class="developer-kanban-task-topline">
                    <div class="developer-kanban-labels">
                      <span><?= h($taskKey) ?></span>
                      <span><?= h((string) ($types[$taskType] ?? "Task")) ?></span>
                      <?php if ($taskBlocked): ?>
                      <span>Blocked</span>
                      <?php endif; ?>
                    </div>
                    <span class="developer-kanban-task-state"><?= h((string) ($priorities[$taskPriority] ?? "Normal")) ?></span>
                  </div>
                  <div class="developer-kanban-task-head">
                    <strong><?= h((string) ($task["title"] ?? "Task")) ?></strong>
                    <?php if (($task["estimate"] ?? "") !== ""): ?>
                    <span><?= h((string) $task["estimate"]) ?></span>
                    <?php endif; ?>
                  </div>
                  <?php if (($task["notes"] ?? "") !== ""): ?>
                  <p><?= h((string) $task["notes"]) ?></p>
                  <?php endif; ?>
                  <?php if ($checklistTotal > 0): ?>
                  <div class="developer-kanban-checklist">
                    <span><?= h((string) $checklistDone) ?>/<?= h((string) $checklistTotal) ?> checklist</span>
                    <i style="width: <?= h((string) (int) round(($checklistDone / max(1, $checklistTotal)) * 100)) ?>%"></i>
                  </div>
                  <?php endif; ?>
                  <div class="developer-kanban-card-footer">
                    <span class="developer-kanban-assignee">
                      <span class="developer-kanban-avatar developer-kanban-avatar-sm" title="<?= h($assigneeName) ?>">
                        <?php if ($avatarIsImage($assignee)): ?>
                        <img src="<?= h((string) ($assignee["avatar"] ?? "")) ?>" alt="">
                        <?php else: ?>
                        <?= h($avatarText($assignee)) ?>
                        <?php endif; ?>
                      </span>
                      <span><?= h($taskAssignee !== "" ? $assigneeName : "Unassigned") ?></span>
                    </span>
                    <span><?= ($task["due_date"] ?? "") !== "" ? "Due " . h((string) $task["due_date"]) : "No due date" ?></span>
                  </div>
                  <form class="developer-kanban-task-actions" action="<?= h(route("developer.workspace.tasks.update")) ?>" method="post" data-developer-kanban-move-form>
                    <?= csrf_field() ?>
                    <input type="hidden" name="developer_workspace_task_id" value="<?= h($taskId) ?>">
                    <input type="hidden" name="developer_workspace_title" value="<?= h((string) ($task["title"] ?? "")) ?>">
                    <input type="hidden" name="developer_workspace_notes" value="<?= h((string) ($task["notes"] ?? "")) ?>">
                    <input type="hidden" name="developer_workspace_priority" value="<?= h((string) ($task["priority"] ?? "normal")) ?>">
                    <input type="hidden" name="developer_workspace_type" value="<?= h((string) ($task["type"] ?? "task")) ?>">
                    <input type="hidden" name="developer_workspace_color" value="<?= h($taskColor) ?>">
                    <input type="hidden" name="developer_workspace_assignee" value="<?= h($taskAssignee) ?>">
                    <input type="hidden" name="developer_workspace_due_date" value="<?= h((string) ($task["due_date"] ?? "")) ?>">
                    <input type="hidden" name="developer_workspace_estimate" value="<?= h((string) ($task["estimate"] ?? "")) ?>">
                    <input type="hidden" name="developer_workspace_position" value="<?= h($taskPosition) ?>">
                    <input type="hidden" name="developer_workspace_blocked" value="<?= $taskBlocked ? "1" : "0" ?>">
                    <input type="hidden" name="developer_workspace_checklist" value="<?= h($checklistText) ?>">
                    <select class="select" name="developer_workspace_status" aria-label="Move task" data-developer-kanban-status>
                      <?php foreach ($columns as $targetStatus => $targetLabel): ?>
                      <option value="<?= h((string) $targetStatus) ?>" <?= $targetStatus === $status ? "selected" : "" ?>><?= h((string) $targetLabel) ?></option>
                      <?php endforeach; ?>
                    </select>
                    <button class="btn btn-outline btn-sm" type="submit">Move</button>
                  </form>
                </article>

                <div class="modal developer-kanban-modal" id="<?= h($modalId) ?>" data-fnlla-modal role="dialog" aria-modal="true" aria-labelledby="<?= h($modalId) ?>-title" hidden>
                  <div class="modal-content developer-kanban-modal-content">
                    <div class="developer-kanban-modal-head">
                      <div>
                        <p class="feature-kicker mb-2"><?= h($taskKey) ?> / <?= h((string) ($columns[$status] ?? $status)) ?></p>
                        <h2 class="content-title mb-0" id="<?= h($modalId) ?>-title"><?= h((string) ($task["title"] ?? "Task")) ?></h2>
                      </div>
                      <button class="developer-kanban-modal-close" type="button" data-fnlla-modal-close aria-label="Close task modal">Close</button>
                    </div>
                    <div class="developer-kanban-modal-meta">
                      <span class="developer-kanban-assignee">
                        <span class="developer-kanban-avatar developer-kanban-avatar-sm" title="<?= h($assigneeName) ?>">
                          <?php if ($avatarIsImage($assignee)): ?>
                          <img src="<?= h((string) ($assignee["avatar"] ?? "")) ?>" alt="">
                          <?php else: ?>
                          <?= h($avatarText($assignee)) ?>
                          <?php endif; ?>
                        </span>
                        <?= h($taskAssignee !== "" ? $assigneeName : "Unassigned") ?>
                      </span>
                      <span>Changed by <?= h($lastChangedBy) ?></span>
                      <span class="developer-kanban-avatar developer-kanban-avatar-sm" title="<?= h($lastChangedBy) ?>">
                        <?php if ($avatarIsImage($lastChangedParticipant)): ?>
                        <img src="<?= h((string) ($lastChangedParticipant["avatar"] ?? "")) ?>" alt="">
                        <?php else: ?>
                        <?= h($avatarText($lastChangedParticipant)) ?>
                        <?php endif; ?>
                      </span>
                    </div>
                    <form class="form developer-kanban-modal-form" action="<?= h(route("developer.workspace.tasks.update")) ?>" method="post">
                      <?= csrf_field() ?>
                      <input type="hidden" name="developer_workspace_task_id" value="<?= h($taskId) ?>">
                      <div class="form-group developer-kanban-modal-wide">
                        <label class="label" for="developer-kanban-title-<?= h($taskId) ?>">Title</label>
                        <input class="input" id="developer-kanban-title-<?= h($taskId) ?>" name="developer_workspace_title" type="text" maxlength="120" value="<?= h((string) ($task["title"] ?? "")) ?>" required data-fnlla-modal-initial-focus>
                      </div>
                      <div class="form-group">
                        <label class="label" for="developer-kanban-status-<?= h($taskId) ?>">Column</label>
                        <select class="select" id="developer-kanban-status-<?= h($taskId) ?>" name="developer_workspace_status">
                          <?php foreach ($columns as $targetStatus => $targetLabel): ?>
                          <option value="<?= h((string) $targetStatus) ?>" <?= $targetStatus === $status ? "selected" : "" ?>><?= h((string) $targetLabel) ?></option>
                          <?php endforeach; ?>
                        </select>
                      </div>
                      <div class="form-group">
                        <label class="label" for="developer-kanban-type-<?= h($taskId) ?>">Type</label>
                        <select class="select" id="developer-kanban-type-<?= h($taskId) ?>" name="developer_workspace_type">
                          <?php foreach ($types as $type => $typeLabel): ?>
                          <option value="<?= h((string) $type) ?>" <?= $type === $taskType ? "selected" : "" ?>><?= h((string) $typeLabel) ?></option>
                          <?php endforeach; ?>
                        </select>
                      </div>
                      <div class="form-group">
                        <label class="label" for="developer-kanban-priority-<?= h($taskId) ?>">Priority</label>
                        <select class="select" id="developer-kanban-priority-<?= h($taskId) ?>" name="developer_workspace_priority">
                          <?php foreach ($priorities as $priority => $priorityLabel): ?>
                          <option value="<?= h((string) $priority) ?>" <?= $priority === $taskPriority ? "selected" : "" ?>><?= h((string) $priorityLabel) ?></option>
                          <?php endforeach; ?>
                        </select>
                      </div>
                      <div class="form-group">
                        <label class="label" for="developer-kanban-color-<?= h($taskId) ?>">Color</label>
                        <select class="select" id="developer-kanban-color-<?= h($taskId) ?>" name="developer_workspace_color">
                          <?php foreach ($colors as $color => $colorLabel): ?>
                          <option value="<?= h((string) $color) ?>" <?= $color === $taskColor ? "selected" : "" ?>><?= h((string) $colorLabel) ?></option>
                          <?php endforeach; ?>
                        </select>
                      </div>
                      <div class="form-group">
                        <label class="label" for="developer-kanban-assignee-<?= h($taskId) ?>">Assignee</label>
                        <select class="select" id="developer-kanban-assignee-<?= h($taskId) ?>" name="developer_workspace_assignee">
                          <option value="" <?= $taskAssignee === "" ? "selected" : "" ?>>Unassigned</option>
                          <?php foreach ($participants as $participant): ?>
                          <?php $email = strtolower(trim((string) ($participant["email"] ?? ""))); ?>
                          <?php if ($email !== ""): ?>
                          <option value="<?= h($email) ?>" <?= $email === $taskAssignee ? "selected" : "" ?>><?= h((string) (($participant["name"] ?? "") ?: $email)) ?></option>
                          <?php endif; ?>
                          <?php endforeach; ?>
                        </select>
                      </div>
                      <div class="form-group">
                        <label class="label" for="developer-kanban-due-<?= h($taskId) ?>">Due date</label>
                        <input class="input" id="developer-kanban-due-<?= h($taskId) ?>" name="developer_workspace_due_date" type="date" value="<?= h((string) ($task["due_date"] ?? "")) ?>">
                      </div>
                      <div class="form-group">
                        <label class="label" for="developer-kanban-estimate-<?= h($taskId) ?>">Estimate</label>
                        <input class="input" id="developer-kanban-estimate-<?= h($taskId) ?>" name="developer_workspace_estimate" type="text" maxlength="24" value="<?= h((string) ($task["estimate"] ?? "")) ?>">
                      </div>
                      <div class="form-group">
                        <label class="label" for="developer-kanban-position-<?= h($taskId) ?>">Rank</label>
                        <input class="input" id="developer-kanban-position-<?= h($taskId) ?>" name="developer_workspace_position" type="number" step="10" min="0" value="<?= h($taskPosition) ?>">
                      </div>
                      <label class="developer-workspace-check">
                        <input type="checkbox" name="developer_workspace_blocked" value="1" <?= $taskBlocked ? "checked" : "" ?>>
                        <span>Blocked</span>
                      </label>
                      <div class="form-group developer-kanban-modal-wide">
                        <label class="label" for="developer-kanban-notes-<?= h($taskId) ?>">Notes</label>
                        <textarea class="textarea" id="developer-kanban-notes-<?= h($taskId) ?>" name="developer_workspace_notes" rows="3" maxlength="280"><?= h((string) ($task["notes"] ?? "")) ?></textarea>
                      </div>
                      <div class="form-group developer-kanban-modal-wide">
                        <label class="label" for="developer-kanban-subtask-<?= h($taskId) ?>">Add subtask</label>
                        <div class="developer-kanban-subtask-row">
                          <input class="input" id="developer-kanban-subtask-<?= h($taskId) ?>" name="developer_workspace_subtask" type="text" maxlength="120" placeholder="Write a new subtask">
                          <select class="select" name="developer_workspace_subtask_color" aria-label="Subtask color">
                            <?php foreach ($colors as $color => $colorLabel): ?>
                            <option value="<?= h((string) $color) ?>" <?= $color === $taskColor ? "selected" : "" ?>><?= h((string) $colorLabel) ?></option>
                            <?php endforeach; ?>
                          </select>
                        </div>
                      </div>
                      <div class="form-group developer-kanban-modal-wide">
                        <label class="label" for="developer-kanban-checklist-<?= h($taskId) ?>">Subtasks checklist</label>
                        <textarea class="textarea" id="developer-kanban-checklist-<?= h($taskId) ?>" name="developer_workspace_checklist" rows="5" maxlength="640"><?= h($checklistText) ?></textarea>
                      </div>
                      <div class="developer-kanban-subtask-list developer-kanban-modal-wide">
                        <?php if ($checklist === []): ?>
                        <p class="content-text mb-0">No subtasks yet.</p>
                        <?php else: ?>
                        <?php foreach ($checklist as $item): ?>
                        <span class="developer-kanban-subtask developer-kanban-subtask-<?= h((string) ($item["color"] ?? "blue")) ?>">
                          <span><?= (bool) ($item["done"] ?? false) ? "OK" : "+" ?></span>
                          <?= h((string) ($item["text"] ?? "")) ?>
                        </span>
                        <?php endforeach; ?>
                        <?php endif; ?>
                      </div>
                      <div class="form-group developer-kanban-modal-wide">
                        <label class="label" for="developer-kanban-comment-<?= h($taskId) ?>">Add comment</label>
                        <textarea class="textarea" id="developer-kanban-comment-<?= h($taskId) ?>" name="developer_workspace_comment" rows="3" maxlength="360" placeholder="Write a short delivery note"></textarea>
                      </div>
                      <div class="form-group">
                        <label class="label" for="developer-kanban-attachment-label-<?= h($taskId) ?>">Attachment label</label>
                        <input class="input" id="developer-kanban-attachment-label-<?= h($taskId) ?>" name="developer_workspace_attachment_label" type="text" maxlength="100" placeholder="Spec, screenshot, report">
                      </div>
                      <div class="form-group">
                        <label class="label" for="developer-kanban-attachment-url-<?= h($taskId) ?>">Attachment URL</label>
                        <input class="input" id="developer-kanban-attachment-url-<?= h($taskId) ?>" name="developer_workspace_attachment_url" type="url" maxlength="240" placeholder="https://example.test/spec">
                      </div>
                      <div class="developer-kanban-detail-list developer-kanban-modal-wide">
                        <strong>Comments</strong>
                        <?php if ($comments === []): ?>
                        <p class="content-text mb-0">No comments yet.</p>
                        <?php else: ?>
                        <?php foreach ($comments as $comment): ?>
                        <p><?= h((string) ($comment["text"] ?? "")) ?> <small><?= h((string) (($comment["author"] ?? "") ?: "developer")) ?></small></p>
                        <?php endforeach; ?>
                        <?php endif; ?>
                      </div>
                      <div class="developer-kanban-detail-list developer-kanban-modal-wide">
                        <strong>Attachments</strong>
                        <?php if ($attachments === []): ?>
                        <p class="content-text mb-0">No attachments yet.</p>
                        <?php else: ?>
                        <?php foreach ($attachments as $attachment): ?>
                        <p><a href="<?= h((string) ($attachment["url"] ?? "")) ?>" target="_blank" rel="noopener noreferrer"><?= h((string) ($attachment["label"] ?? "Attachment")) ?></a> <small><?= h((string) (($attachment["added_by"] ?? "") ?: "developer")) ?></small></p>
                        <?php endforeach; ?>
                        <?php endif; ?>
                      </div>
                      <div class="developer-kanban-modal-actions developer-kanban-modal-wide">
                        <button class="btn btn-primary" type="submit">Save task</button>
                        <button class="btn btn-ghost" type="button" data-fnlla-modal-close>Cancel</button>
                      </div>
                    </form>
                    <form class="developer-kanban-delete-form" action="<?= h(route("developer.workspace.tasks.delete")) ?>" method="post">
                      <?= csrf_field() ?>
                      <input type="hidden" name="developer_workspace_task_id" value="<?= h($taskId) ?>">
                      <button class="btn btn-ghost btn-sm" type="submit">Remove task</button>
                    </form>
                  </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
              </div>
            </article>
            <?php endforeach; ?>
          </div>
        </section>

        <script>
          (function () {
            var board = document.querySelector("[data-developer-kanban]");

            if (!board) {
              return;
            }

            board.addEventListener("dragstart", function (event) {
              var card = event.target.closest("[data-developer-kanban-task]");

              if (!card || !event.dataTransfer || event.target.closest("button, a, input, select, textarea")) {
                return;
              }

              event.dataTransfer.effectAllowed = "move";
              event.dataTransfer.setData("text/plain", card.getAttribute("data-developer-kanban-task") || "");
              card.classList.add("is-dragging");
            });

            board.addEventListener("dragend", function (event) {
              var card = event.target.closest("[data-developer-kanban-task]");

              if (card) {
                card.classList.remove("is-dragging");
              }

              board.querySelectorAll(".is-drop-target").forEach(function (column) {
                column.classList.remove("is-drop-target");
              });
            });

            board.addEventListener("dragover", function (event) {
              var column = event.target.closest("[data-developer-kanban-column]");

              if (!column) {
                return;
              }

              event.preventDefault();
              column.classList.add("is-drop-target");
            });

            board.addEventListener("dragleave", function (event) {
              var column = event.target.closest("[data-developer-kanban-column]");

              if (column && !column.contains(event.relatedTarget)) {
                column.classList.remove("is-drop-target");
              }
            });

            board.addEventListener("drop", function (event) {
              var column = event.target.closest("[data-developer-kanban-column]");
              var taskId = event.dataTransfer ? event.dataTransfer.getData("text/plain") : "";
              var card = taskId ? board.querySelector("[data-developer-kanban-task='" + taskId + "']") : null;

              if (!column || !card) {
                return;
              }

              event.preventDefault();
              column.classList.remove("is-drop-target");

              var targetStatus = column.getAttribute("data-developer-kanban-column") || "";
              var form = card.querySelector("[data-developer-kanban-move-form]");
              var select = form ? form.querySelector("[data-developer-kanban-status]") : null;

              if (!form || !select || select.value === targetStatus) {
                return;
              }

              select.value = targetStatus;
              form.submit();
            });

            board.addEventListener("click", function (event) {
              var card = event.target.closest("[data-developer-kanban-modal]");

              if (!card || event.target.closest("button, a, input, select, textarea, form")) {
                return;
              }

              var target = card.getAttribute("data-developer-kanban-modal");

              if (target && window.FNLLARUNTIME && typeof window.FNLLARUNTIME.openModal === "function") {
                window.FNLLARUNTIME.openModal(target);
              }
            });

            board.addEventListener("keydown", function (event) {
              if (event.key !== "Enter" && event.key !== " ") {
                return;
              }

              var card = event.target.closest("[data-developer-kanban-modal]");

              if (!card || event.target.closest("button, a, input, select, textarea, form")) {
                return;
              }

              event.preventDefault();

              var target = card.getAttribute("data-developer-kanban-modal");

              if (target && window.FNLLARUNTIME && typeof window.FNLLARUNTIME.openModal === "function") {
                window.FNLLARUNTIME.openModal(target);
              }
            });

            var search = document.querySelector("[data-developer-kanban-search]");

            if (search) {
              search.addEventListener("input", function () {
                var query = search.value.trim().toLowerCase();

                board.querySelectorAll("[data-developer-kanban-task]").forEach(function (card) {
                  var haystack = card.getAttribute("data-developer-kanban-search-text") || "";
                  card.hidden = query !== "" && haystack.indexOf(query) === -1;
                });
              });
            }
          })();
        </script>

<?php require __DIR__ . "/panel-footer.php"; ?>
