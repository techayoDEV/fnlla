<?php

declare(strict_types=1);

$developerPanelTitle = "Project Kanban";
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
$formatAttachmentSize = static function (int $bytes): string {
    if ($bytes <= 0) {
        return "";
    }

    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 1) . " MB";
    }

    return max(1, (int) ceil($bytes / 1024)) . " KB";
};
$formatActivityTime = static function (string $value): string {
    $value = trim($value);

    if ($value === "") {
        return "";
    }

    try {
        return (new \DateTimeImmutable($value))->setTimezone(new \DateTimeZone("UTC"))->format("d M Y, H:i") . " UTC";
    } catch (\Throwable) {
        return $value;
    }
};
$attachmentPreviewFor = static function (array $attachment): array {
    $type = (string) ($attachment["type"] ?? "url");
    $url = trim((string) ($attachment["url"] ?? ""));
    $mimeType = strtolower(trim((string) ($attachment["mime_type"] ?? "")));

    if ($type !== "file" || $url === "") {
        return ["kind" => "none"];
    }

    if (in_array($mimeType, ["image/jpeg", "image/png", "image/webp"], true)) {
        return ["kind" => "image", "url" => $url];
    }

    if ($mimeType === "application/pdf") {
        return ["kind" => "pdf", "url" => $url];
    }

    if (!in_array($mimeType, ["text/plain", "text/csv"], true) || !str_starts_with($url, "/uploads/developer-workspace-attachments/")) {
        return ["kind" => "none"];
    }

    $basePath = realpath(public_path("uploads/developer-workspace-attachments"));
    $filePath = realpath(public_path(ltrim($url, "/")));

    if (!is_string($basePath) || !is_string($filePath) || !str_starts_with($filePath, rtrim($basePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)) {
        return ["kind" => "none"];
    }

    $preview = file_get_contents($filePath, false, null, 0, 2400);

    if (!is_string($preview) || trim($preview) === "") {
        return ["kind" => "none"];
    }

    return ["kind" => "text", "text" => $preview];
};
$today = gmdate("Y-m-d");
$totalTasks = count($allTasks);
$urgentTasks = count(array_filter($allTasks, static fn (array $task): bool => (string) ($task["priority"] ?? "") === "urgent"));
$highPriorityTasks = count(array_filter($allTasks, static fn (array $task): bool => in_array((string) ($task["priority"] ?? ""), ["high", "urgent"], true)));
$lowPriorityTasks = count(array_filter($allTasks, static fn (array $task): bool => (string) ($task["priority"] ?? "") === "low"));
$overdueTasks = count(array_filter($allTasks, static fn (array $task): bool => ($task["status"] ?? "") !== "done" && (string) ($task["due_date"] ?? "") !== "" && (string) ($task["due_date"] ?? "") < $today));
$wipTasks = count(array_filter($allTasks, static fn (array $task): bool => in_array((string) ($task["status"] ?? ""), ["in_progress", "review"], true)));
$columnAccents = [
    "backlog" => "slate",
    "in_progress" => "sky",
    "review" => "indigo",
    "done" => "green",
];
$timelineHorizonDays = 30;
$timelineStart = new \DateTimeImmutable($today . " 00:00:00", new \DateTimeZone("UTC"));
$timelineEnd = $timelineStart->modify("+" . $timelineHorizonDays . " days");
$daysBetween = static fn (\DateTimeImmutable $from, \DateTimeImmutable $to): int => (int) floor(((int) $to->format("U") - (int) $from->format("U")) / 86400);
$timelineTasks = [];

foreach ($allTasks as $task) {
    $dueDate = trim((string) ($task["due_date"] ?? ""));

    if ($dueDate === "") {
        continue;
    }

    try {
        $due = new \DateTimeImmutable($dueDate . " 23:59:59", new \DateTimeZone("UTC"));
        $created = new \DateTimeImmutable((string) (($task["created_at_utc"] ?? "") ?: $today), new \DateTimeZone("UTC"));
    } catch (\Throwable) {
        continue;
    }

    $created = $created < $timelineStart ? $timelineStart : $created;
    $status = (string) ($task["status"] ?? "backlog");
    $priority = (string) ($task["priority"] ?? "normal");
    $daysToDue = $daysBetween($timelineStart, $due);
    $startOffsetDays = max(0, min($timelineHorizonDays, $daysBetween($timelineStart, $created)));
    $spanDays = max(1, $daysBetween($created, $due) + 1);
    $offsetPercent = (int) round(($startOffsetDays / $timelineHorizonDays) * 100);
    $spanPercent = max(6, min(100 - $offsetPercent, (int) round((min($timelineHorizonDays, $spanDays) / $timelineHorizonDays) * 100)));

    if ($due < $timelineStart && $status !== "done") {
        $offsetPercent = 0;
        $spanPercent = 6;
    }

    $timelineTasks[] = [
        "title" => (string) ($task["title"] ?? "Task"),
        "status" => (string) ($columns[$status] ?? ucfirst(str_replace("_", " ", $status))),
        "priority" => (string) ($priorities[$priority] ?? "Normal"),
        "assignee" => (string) (($participantFor(strtolower(trim((string) ($task["assignee"] ?? ""))))["name"] ?? "") ?: ((string) ($task["assignee"] ?? "") ?: "Unassigned")),
        "due" => $dueDate,
        "days_to_due" => $daysToDue,
        "offset" => $offsetPercent,
        "span" => $spanPercent,
        "tone" => $status === "done" ? "done" : ($due < $timelineStart ? "overdue" : (in_array($priority, ["high", "urgent"], true) ? "urgent" : "normal")),
    ];
}

usort($timelineTasks, static fn (array $left, array $right): int => strcmp((string) $left["due"], (string) $right["due"]));
$timelineTasks = array_slice($timelineTasks, 0, 12);

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
              <span class="developer-kanban-avatar" tabindex="0" data-fnlla-tooltip="<?= h($participantName) ?>" data-fnlla-tooltip-position="left" aria-label="<?= h($participantName) ?>">
                <?php if ($avatarIsImage((array) $participant)): ?>
                <img src="<?= h((string) ($participant["avatar"] ?? "")) ?>" alt="">
                <?php else: ?>
                <?= h($avatarText((array) $participant)) ?>
                <?php endif; ?>
              </span>
              <?php endforeach; ?>
              <?php if (count($participants) > 5): ?>
              <span class="developer-kanban-avatar developer-kanban-avatar-more" tabindex="0" data-fnlla-tooltip="<?= h((string) count($participants)) ?> participants" data-fnlla-tooltip-position="left">+<?= h((string) (count($participants) - 5)) ?></span>
              <?php endif; ?>
            </div>
          </div>

          <div class="developer-kanban-toolbar" aria-label="Kanban filters">
            <label class="developer-kanban-search">
              <span class="visually-hidden">Search tasks</span>
              <input class="input" type="search" placeholder="Search tasks..." data-developer-kanban-search>
            </label>
            <div class="developer-kanban-filter-tabs" aria-label="Priority counts">
              <button class="is-active" type="button" data-developer-kanban-filter="all" aria-pressed="true">All <b><?= h((string) $totalTasks) ?></b></button>
              <button type="button" data-developer-kanban-filter="mine" aria-pressed="false">My work <b><?= h((string) ($board["my_tasks_count"] ?? 0)) ?></b></button>
              <button type="button" data-developer-kanban-filter="urgent" aria-pressed="false">Urgent <b><?= h((string) $urgentTasks) ?></b></button>
              <button type="button" data-developer-kanban-filter="high" aria-pressed="false">High <b><?= h((string) $highPriorityTasks) ?></b></button>
              <button type="button" data-developer-kanban-filter="low" aria-pressed="false">Low <b><?= h((string) $lowPriorityTasks) ?></b></button>
              <button type="button" data-developer-kanban-filter="blocked" aria-pressed="false">Blocked <b><?= h((string) ($board["blocked_tasks_count"] ?? 0)) ?></b></button>
            </div>
          </div>

          <div class="developer-kanban-insights" aria-label="Kanban board health">
            <span><strong><?= h((string) $wipTasks) ?></strong> WIP</span>
            <span><strong><?= h((string) ($board["due_soon_count"] ?? 0)) ?></strong> due soon</span>
            <span><strong><?= h((string) $overdueTasks) ?></strong> overdue</span>
            <span><strong><?= h((string) ($board["attachments_count"] ?? 0)) ?></strong> attachments</span>
            <span><strong><?= h((string) ($board["comments_count"] ?? 0)) ?></strong> comments</span>
          </div>

          <section class="developer-kanban-timeline" aria-label="Delivery timeline">
            <div class="developer-kanban-timeline-head">
              <div>
                <p class="feature-kicker">Timeline / Gantt</p>
                <h3>Due-date plan for the next <?= h((string) $timelineHorizonDays) ?> days</h3>
              </div>
              <span><?= h($timelineStart->format("d M")) ?> - <?= h($timelineEnd->format("d M")) ?></span>
            </div>
            <?php if ($timelineTasks === []): ?>
            <p class="content-text mb-0">Add due dates to Kanban cards to build the local delivery timeline.</p>
            <?php else: ?>
            <div class="developer-kanban-timeline-scale" aria-hidden="true">
              <span>Today</span>
              <span>7d</span>
              <span>14d</span>
              <span>21d</span>
              <span>30d</span>
            </div>
            <div class="developer-kanban-timeline-list">
              <?php foreach ($timelineTasks as $timelineTask): ?>
              <?php
                  $daysToDue = (int) ($timelineTask["days_to_due"] ?? 0);
                  $dueLabel = $daysToDue < 0 ? abs($daysToDue) . "d overdue" : ($daysToDue === 0 ? "due today" : "due in " . $daysToDue . "d");
              ?>
              <article class="developer-kanban-timeline-row is-<?= h((string) ($timelineTask["tone"] ?? "normal")) ?>">
                <div>
                  <strong><?= h((string) ($timelineTask["title"] ?? "Task")) ?></strong>
                  <small><?= h((string) ($timelineTask["status"] ?? "")) ?> / <?= h((string) ($timelineTask["priority"] ?? "")) ?> / <?= h((string) ($timelineTask["assignee"] ?? "Unassigned")) ?></small>
                </div>
                <div class="developer-kanban-timeline-track" aria-label="<?= h((string) ($timelineTask["title"] ?? "Task")) ?> <?= h($dueLabel) ?>">
                  <span style="left: <?= h((string) ($timelineTask["offset"] ?? 0)) ?>%; width: <?= h((string) ($timelineTask["span"] ?? 6)) ?>%;"></span>
                </div>
                <em><?= h($dueLabel) ?></em>
              </article>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>
          </section>

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
                    <button class="developer-kanban-modal-close" type="button" data-fnlla-modal-close aria-label="Close task modal"><span aria-hidden="true">x</span></button>
                  </div>
                  <div class="developer-kanban-create-summary developer-kanban-modal-wide">
                    <span>Starts in <?= h((string) $label) ?></span>
                    <small>Details can be edited later from the task modal.</small>
                  </div>
                  <form class="form developer-kanban-modal-form developer-kanban-create-form" action="<?= h(route("developer.workspace.tasks.create")) ?>" method="post" enctype="multipart/form-data" novalidate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="developer_workspace_status" value="<?= h((string) $status) ?>">
                    <div class="developer-kanban-modal-section-heading developer-kanban-modal-wide">Core task</div>
                    <div class="form-group developer-kanban-modal-wide">
                      <label class="label" for="developer-kanban-create-title-input-<?= h((string) $status) ?>">Task title</label>
                      <input class="input" id="developer-kanban-create-title-input-<?= h((string) $status) ?>" name="developer_workspace_title" type="text" maxlength="120" required data-fnlla-modal-initial-focus>
                    </div>
                    <div class="developer-kanban-modal-section-heading developer-kanban-modal-wide">Triage</div>
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
                      <span class="label" id="developer-kanban-create-color-<?= h((string) $status) ?>">Color</span>
                      <div class="developer-kanban-color-picker" role="radiogroup" aria-labelledby="developer-kanban-create-color-<?= h((string) $status) ?>">
                        <?php foreach ($colors as $color => $colorLabel): ?>
                        <label class="developer-kanban-color-option developer-kanban-color-option-<?= h((string) $color) ?>" data-fnlla-tooltip="<?= h((string) $colorLabel) ?>" data-fnlla-tooltip-position="top">
                          <input type="radio" name="developer_workspace_color" value="<?= h((string) $color) ?>" <?= $color === "blue" ? "checked" : "" ?>>
                          <span aria-hidden="true"></span>
                          <em><?= h((string) $colorLabel) ?></em>
                        </label>
                        <?php endforeach; ?>
                      </div>
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
                    <input type="hidden" name="developer_workspace_client_visible" value="0">
                    <label class="developer-workspace-check">
                      <input type="checkbox" name="developer_workspace_client_visible" value="1" checked>
                      <span>Visible to customer</span>
                    </label>
                    <div class="developer-kanban-modal-section-heading developer-kanban-modal-wide">Delivery notes</div>
                    <div class="form-group developer-kanban-modal-wide">
                      <label class="label" for="developer-kanban-create-notes-<?= h((string) $status) ?>">Notes</label>
                      <textarea class="textarea" id="developer-kanban-create-notes-<?= h((string) $status) ?>" name="developer_workspace_notes" rows="3" maxlength="280"></textarea>
                    </div>
                    <div class="form-group developer-kanban-modal-wide">
                      <label class="label" for="developer-kanban-create-checklist-<?= h((string) $status) ?>">Subtasks</label>
                      <textarea class="textarea" id="developer-kanban-create-checklist-<?= h((string) $status) ?>" name="developer_workspace_checklist" rows="4" maxlength="640" placeholder="[ ] Confirm copy&#10;[ ] Run release checks"></textarea>
                    </div>
                    <div class="developer-kanban-modal-section-heading developer-kanban-modal-wide">Reference material</div>
                    <div class="form-group developer-kanban-modal-wide">
                      <label class="label" for="developer-kanban-create-attachment-label-<?= h((string) $status) ?>">Attachment label</label>
                      <input class="input" id="developer-kanban-create-attachment-label-<?= h((string) $status) ?>" name="developer_workspace_attachment_label" type="text" maxlength="100" placeholder="Spec, screenshot, report">
                    </div>
                    <div class="form-group developer-kanban-modal-split">
                      <label class="label" for="developer-kanban-create-attachment-url-<?= h((string) $status) ?>">Attachment URL</label>
                      <input class="input" id="developer-kanban-create-attachment-url-<?= h((string) $status) ?>" name="developer_workspace_attachment_url" type="url" placeholder="https://example.test/spec">
                    </div>
                    <div class="form-group developer-kanban-modal-split">
                      <label class="label" for="developer-kanban-create-attachment-file-<?= h((string) $status) ?>">Upload file</label>
                      <input class="input developer-kanban-file-input" id="developer-kanban-create-attachment-file-<?= h((string) $status) ?>" name="developer_workspace_attachment_file" type="file" accept="image/jpeg,image/png,image/webp,application/pdf,text/plain,text/csv,application/zip,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,.jpg,.jpeg,.png,.webp,.pdf,.txt,.csv,.zip,.doc,.docx,.xls,.xlsx">
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
                    $taskColor = array_key_exists($taskColor, $colors) ? $taskColor : "blue";
                    $taskClientVisible = (bool) ($task["client_visible"] ?? true);
                    $taskId = (string) ($task["id"] ?? "");
                    $taskPosition = (string) ($task["position"] ?? "");
                    $taskAssignee = strtolower(trim((string) ($task["assignee"] ?? "")));
                    $assignee = $participantFor($taskAssignee);
                    $assigneeName = (string) (($assignee["name"] ?? "") ?: ($taskAssignee !== "" ? $taskAssignee : "Unassigned"));
                    $createdBy = (string) (($task["created_by"] ?? "") ?: "developer");
                    $createdParticipant = $participantFor($createdBy);
                    $lastChangedBy = (string) (($task["updated_by"] ?? "") ?: ($task["created_by"] ?? "developer"));
                    $lastChangedParticipant = $participantFor($lastChangedBy);
                    $taskActivity = [
                        [
                            "kind" => "Created",
                            "title" => (string) (($createdParticipant["name"] ?? "") ?: $createdBy),
                            "meta" => $formatActivityTime((string) ($task["created_at_utc"] ?? "")),
                        ],
                        [
                            "kind" => "Updated",
                            "title" => (string) (($lastChangedParticipant["name"] ?? "") ?: $lastChangedBy),
                            "meta" => $formatActivityTime((string) ($task["updated_at_utc"] ?? "")),
                        ],
                    ];

                    foreach ($comments as $comment) {
                        $taskActivity[] = [
                            "kind" => "Comment",
                            "title" => (string) (($comment["text"] ?? "") ?: "Comment added"),
                            "meta" => trim((string) (($comment["author"] ?? "") ?: "developer") . " - " . $formatActivityTime((string) ($comment["created_at_utc"] ?? "")), " -"),
                        ];
                    }

                    foreach ($attachments as $attachment) {
                        $taskActivity[] = [
                            "kind" => "Attachment",
                            "title" => (string) ($attachment["label"] ?? "Attachment"),
                            "meta" => trim((string) (($attachment["added_by"] ?? "") ?: "developer") . " - " . $formatActivityTime((string) ($attachment["created_at_utc"] ?? "")), " -"),
                        ];
                    }

                    $checklistText = $checklistToText($checklist);
                    $modalId = "developer-kanban-task-modal-" . preg_replace('/[^A-Za-z0-9_-]/', "-", $taskId);
                    $taskKey = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $taskId) ?: "TASK", 0, 6));
                    $taskSearch = trim((string) ($task["title"] ?? "") . " " . (string) ($task["notes"] ?? "") . " " . (string) ($types[$taskType] ?? "Task") . " " . (string) ($priorities[$taskPriority] ?? "Normal") . " " . $assigneeName);
                    $taskDueClass = ($task["due_date"] ?? "") !== "" && (string) ($task["due_date"] ?? "") < $today && ($task["status"] ?? "") !== "done" ? " is-overdue" : "";
                ?>
                <article class="developer-kanban-task developer-kanban-task-<?= h($taskColor) ?><?= h($taskDueClass) ?> <?= $taskAssignee === $currentEmail && $currentEmail !== "" ? "is-mine" : "" ?>" draggable="true" data-developer-kanban-task="<?= h($taskId) ?>" data-developer-kanban-position="<?= h($taskPosition) ?>" data-developer-kanban-priority="<?= h($taskPriority) ?>" data-developer-kanban-assignee="<?= h($taskAssignee) ?>" data-developer-kanban-blocked="<?= $taskBlocked ? "true" : "false" ?>" data-developer-kanban-search-text="<?= h(strtolower($taskSearch)) ?>">
                  <div class="developer-kanban-task-topline">
                    <div class="developer-kanban-labels">
                      <span><?= h($taskKey) ?></span>
                      <span><?= h((string) ($types[$taskType] ?? "Task")) ?></span>
                      <?php if ($taskBlocked): ?>
                      <span>Blocked</span>
                      <?php endif; ?>
                      <?php if (!$taskClientVisible): ?>
                      <span>Internal</span>
                      <?php endif; ?>
                    </div>
                    <div class="developer-kanban-task-top-actions">
                      <span class="developer-kanban-task-state"><?= h((string) ($priorities[$taskPriority] ?? "Normal")) ?></span>
                      <details class="developer-kanban-task-menu">
                      <summary aria-label="Task actions" data-fnlla-tooltip="Task actions" data-fnlla-tooltip-position="left"><span aria-hidden="true"></span><span aria-hidden="true"></span><span aria-hidden="true"></span></summary>
                        <div class="developer-kanban-task-menu-panel">
                          <button class="developer-kanban-task-menu-item" type="button" data-fnlla-modal-open="#<?= h($modalId) ?>">Edit</button>
                          <form action="<?= h(route("developer.workspace.tasks.delete")) ?>" method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="developer_workspace_task_id" value="<?= h($taskId) ?>">
                            <button class="developer-kanban-task-menu-item developer-kanban-task-menu-danger" type="submit">Remove</button>
                          </form>
                        </div>
                      </details>
                    </div>
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
                  <?php $checklistPercent = (int) round(($checklistDone / max(1, $checklistTotal)) * 100); ?>
                  <div class="developer-kanban-checklist <?= $checklistDone === $checklistTotal ? "is-complete" : "" ?>" aria-label="<?= h((string) $checklistDone) ?> of <?= h((string) $checklistTotal) ?> checklist items complete">
                    <div class="developer-kanban-checklist-head">
                      <span><?= h((string) $checklistDone) ?>/<?= h((string) $checklistTotal) ?> checklist</span>
                      <small><?= h((string) $checklistPercent) ?>%</small>
                    </div>
                    <div class="developer-kanban-checklist-track" aria-hidden="true">
                      <i style="width: <?= h((string) $checklistPercent) ?>%"></i>
                    </div>
                  </div>
                  <?php endif; ?>
                  <div class="developer-kanban-card-footer">
                    <span class="developer-kanban-assignee">
                      <span class="developer-kanban-avatar developer-kanban-avatar-sm" tabindex="0" data-fnlla-tooltip="<?= h($assigneeName) ?>" data-fnlla-tooltip-position="left">
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
                    <input type="hidden" name="developer_workspace_client_visible" value="<?= $taskClientVisible ? "1" : "0" ?>">
                    <input type="hidden" name="developer_workspace_checklist" value="<?= h($checklistText) ?>">
                    <input type="hidden" name="developer_workspace_status" value="<?= h($status) ?>" data-developer-kanban-status>
                  </form>
                </article>

                <div class="modal developer-kanban-modal" id="<?= h($modalId) ?>" data-fnlla-modal role="dialog" aria-modal="true" aria-labelledby="<?= h($modalId) ?>-title" hidden>
                  <div class="modal-content developer-kanban-modal-content">
                    <div class="developer-kanban-modal-head">
                      <div>
                        <p class="feature-kicker mb-2"><?= h($taskKey) ?> / <?= h((string) ($columns[$status] ?? $status)) ?></p>
                        <h2 class="content-title mb-0" id="<?= h($modalId) ?>-title"><?= h((string) ($task["title"] ?? "Task")) ?></h2>
                      </div>
                      <button class="developer-kanban-modal-close" type="button" data-fnlla-modal-close aria-label="Close task modal"><span aria-hidden="true">x</span></button>
                    </div>
                    <div class="developer-kanban-modal-meta">
                      <span class="developer-kanban-meta-person">
                        <span class="developer-kanban-avatar developer-kanban-avatar-sm" tabindex="0" data-fnlla-tooltip="<?= h($createdBy) ?>" data-fnlla-tooltip-position="left">
                          <?php if ($avatarIsImage($createdParticipant)): ?>
                          <img src="<?= h((string) ($createdParticipant["avatar"] ?? "")) ?>" alt="">
                          <?php else: ?>
                          <?= h($avatarText($createdParticipant)) ?>
                          <?php endif; ?>
                        </span>
                        <span>
                          <small>Created by</small>
                          <b><?= h((string) (($createdParticipant["name"] ?? "") ?: $createdBy)) ?></b>
                        </span>
                      </span>
                      <span class="developer-kanban-meta-person">
                        <span class="developer-kanban-avatar developer-kanban-avatar-sm" tabindex="0" data-fnlla-tooltip="<?= h($lastChangedBy) ?>" data-fnlla-tooltip-position="left">
                          <?php if ($avatarIsImage($lastChangedParticipant)): ?>
                          <img src="<?= h((string) ($lastChangedParticipant["avatar"] ?? "")) ?>" alt="">
                          <?php else: ?>
                          <?= h($avatarText($lastChangedParticipant)) ?>
                          <?php endif; ?>
                        </span>
                        <span>
                          <small>Last changed by</small>
                          <b><?= h((string) (($lastChangedParticipant["name"] ?? "") ?: $lastChangedBy)) ?></b>
                        </span>
                      </span>
                    </div>
                    <details class="developer-kanban-activity">
                      <summary>
                        <span>More / task activity</span>
                        <small><?= h((string) count($taskActivity)) ?> events. Created by <?= h((string) (($createdParticipant["name"] ?? "") ?: $createdBy)) ?>. Last changed by <?= h((string) (($lastChangedParticipant["name"] ?? "") ?: $lastChangedBy)) ?>.</small>
                      </summary>
                      <div class="developer-kanban-activity-list">
                        <?php foreach ($taskActivity as $activity): ?>
                        <p>
                          <strong><?= h((string) ($activity["kind"] ?? "Activity")) ?></strong>
                          <span>
                            <b><?= h((string) ($activity["title"] ?? "")) ?></b>
                            <?php if (($activity["meta"] ?? "") !== ""): ?>
                            <small><?= h((string) ($activity["meta"] ?? "")) ?></small>
                            <?php endif; ?>
                          </span>
                        </p>
                        <?php endforeach; ?>
                      </div>
                    </details>
                    <form class="form developer-kanban-modal-form" action="<?= h(route("developer.workspace.tasks.update")) ?>" method="post" enctype="multipart/form-data">
                      <?= csrf_field() ?>
                      <input type="hidden" name="developer_workspace_task_id" value="<?= h($taskId) ?>">
                      <div class="developer-kanban-triage-strip developer-kanban-modal-wide" aria-label="Task triage summary">
                        <span><small>Status</small><strong><?= h((string) ($columns[$status] ?? $status)) ?></strong></span>
                        <span><small>Priority</small><strong><?= h((string) ($priorities[$taskPriority] ?? "Normal")) ?></strong></span>
                        <span><small>Owner</small><strong><?= h($taskAssignee !== "" ? $assigneeName : "Unassigned") ?></strong></span>
                        <span><small>Due</small><strong><?= ($task["due_date"] ?? "") !== "" ? h((string) $task["due_date"]) : "No date" ?></strong></span>
                      </div>
                      <div class="developer-kanban-modal-section-heading developer-kanban-modal-wide">Core task</div>
                      <div class="form-group developer-kanban-modal-wide">
                        <label class="label" for="developer-kanban-title-<?= h($taskId) ?>">Title</label>
                        <input class="input" id="developer-kanban-title-<?= h($taskId) ?>" name="developer_workspace_title" type="text" maxlength="120" value="<?= h((string) ($task["title"] ?? "")) ?>" required data-fnlla-modal-initial-focus>
                      </div>
                      <div class="developer-kanban-modal-section-heading developer-kanban-modal-wide">Triage</div>
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
                        <span class="label" id="developer-kanban-color-<?= h($taskId) ?>">Color</span>
                        <div class="developer-kanban-color-picker" role="radiogroup" aria-labelledby="developer-kanban-color-<?= h($taskId) ?>">
                          <?php foreach ($colors as $color => $colorLabel): ?>
                          <label class="developer-kanban-color-option developer-kanban-color-option-<?= h((string) $color) ?>" data-fnlla-tooltip="<?= h((string) $colorLabel) ?>" data-fnlla-tooltip-position="top">
                            <input type="radio" name="developer_workspace_color" value="<?= h((string) $color) ?>" <?= $color === $taskColor ? "checked" : "" ?>>
                            <span aria-hidden="true"></span>
                            <em><?= h((string) $colorLabel) ?></em>
                          </label>
                          <?php endforeach; ?>
                        </div>
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
                      <input type="hidden" name="developer_workspace_client_visible" value="0">
                      <label class="developer-workspace-check">
                        <input type="checkbox" name="developer_workspace_client_visible" value="1" <?= $taskClientVisible ? "checked" : "" ?>>
                        <span>Visible to customer</span>
                      </label>
                      <div class="developer-kanban-modal-section-heading developer-kanban-modal-wide">Delivery notes</div>
                      <div class="form-group developer-kanban-modal-wide">
                        <label class="label" for="developer-kanban-notes-<?= h($taskId) ?>">Notes</label>
                        <textarea class="textarea" id="developer-kanban-notes-<?= h($taskId) ?>" name="developer_workspace_notes" rows="3" maxlength="280"><?= h((string) ($task["notes"] ?? "")) ?></textarea>
                      </div>
                      <div class="developer-kanban-modal-section-heading developer-kanban-modal-wide">Subtasks</div>
                      <div class="form-group developer-kanban-modal-wide">
                        <label class="label" for="developer-kanban-subtask-<?= h($taskId) ?>">Add subtask</label>
                        <div class="developer-kanban-subtask-row">
                          <input class="input" id="developer-kanban-subtask-<?= h($taskId) ?>" name="developer_workspace_subtask" type="text" maxlength="120" placeholder="Write a new subtask">
                          <div class="developer-kanban-color-picker developer-kanban-color-picker-compact" role="radiogroup" aria-label="Subtask color">
                            <?php foreach ($colors as $color => $colorLabel): ?>
                            <label class="developer-kanban-color-option developer-kanban-color-option-<?= h((string) $color) ?>" data-fnlla-tooltip="<?= h((string) $colorLabel) ?>" data-fnlla-tooltip-position="top">
                              <input type="radio" name="developer_workspace_subtask_color" value="<?= h((string) $color) ?>" <?= $color === $taskColor ? "checked" : "" ?>>
                              <span aria-hidden="true"></span>
                              <em><?= h((string) $colorLabel) ?></em>
                            </label>
                            <?php endforeach; ?>
                          </div>
                        </div>
                      </div>
                      <input type="hidden" name="developer_workspace_checklist" value="<?= h($checklistText) ?>">
                      <div class="developer-kanban-subtask-list developer-kanban-modal-wide">
                        <div class="developer-kanban-subtask-list-head">
                          <strong>Subtasks</strong>
                          <small><?= h((string) $checklistDone) ?>/<?= h((string) $checklistTotal) ?> complete</small>
                        </div>
                        <?php if ($checklist === []): ?>
                        <p class="content-text mb-0">No subtasks yet.</p>
                        <?php else: ?>
                        <?php foreach ($checklist as $subtaskIndex => $item): ?>
                        <?php
                            $itemColor = (string) ($item["color"] ?? "blue");
                            $itemColor = array_key_exists($itemColor, $colors) ? $itemColor : "blue";
                            $subtaskDone = (bool) ($item["done"] ?? false);
                            $subtaskControlId = "developer-kanban-subtask-done-" . $taskId . "-" . (string) $subtaskIndex;
                        ?>
                        <div class="developer-kanban-subtask-item developer-kanban-subtask-<?= h($itemColor) ?><?= $subtaskDone ? " is-complete" : "" ?>">
                          <label class="developer-kanban-subtask-toggle" for="<?= h($subtaskControlId) ?>" data-fnlla-tooltip="Toggle completed" data-fnlla-tooltip-position="top">
                            <input id="<?= h($subtaskControlId) ?>" type="checkbox" name="developer_workspace_subtasks_done[<?= h((string) $subtaskIndex) ?>]" value="1" <?= $subtaskDone ? "checked" : "" ?>>
                            <span aria-hidden="true"></span>
                          </label>
                          <input class="input developer-kanban-subtask-input" name="developer_workspace_subtasks_text[<?= h((string) $subtaskIndex) ?>]" type="text" maxlength="120" value="<?= h((string) ($item["text"] ?? "")) ?>" aria-label="Subtask text">
                          <div class="developer-kanban-subtask-colors" role="radiogroup" aria-label="Subtask color">
                            <?php foreach ($colors as $color => $colorLabel): ?>
                            <label class="developer-kanban-color-option developer-kanban-color-option-<?= h((string) $color) ?>" data-fnlla-tooltip="<?= h((string) $colorLabel) ?>" data-fnlla-tooltip-position="top">
                              <input type="radio" name="developer_workspace_subtasks_color[<?= h((string) $subtaskIndex) ?>]" value="<?= h((string) $color) ?>" <?= $color === $itemColor ? "checked" : "" ?>>
                              <span aria-hidden="true"></span>
                              <em><?= h((string) $colorLabel) ?></em>
                            </label>
                            <?php endforeach; ?>
                          </div>
                          <button class="developer-kanban-subtask-remove" type="submit" name="developer_workspace_delete_subtask_index" value="<?= h((string) $subtaskIndex) ?>" formnovalidate aria-label="Remove subtask">
                            <span aria-hidden="true"></span>
                          </button>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                      </div>
                      <div class="developer-kanban-modal-section-heading developer-kanban-modal-wide">Comments and attachments</div>
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
                      <div class="form-group developer-kanban-modal-wide">
                        <label class="label" for="developer-kanban-attachment-file-<?= h($taskId) ?>">Attachment file</label>
                        <input class="input developer-kanban-file-input" id="developer-kanban-attachment-file-<?= h($taskId) ?>" name="developer_workspace_attachment_file" type="file" accept="image/jpeg,image/png,image/webp,application/pdf,text/plain,text/csv,application/zip,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,.jpg,.jpeg,.png,.webp,.pdf,.txt,.csv,.zip,.doc,.docx,.xls,.xlsx">
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
                        <div class="developer-kanban-attachment-list">
                        <?php foreach ($attachments as $attachmentIndex => $attachment): ?>
                        <?php
                            $attachmentType = (string) ($attachment["type"] ?? "url");
                            $attachmentSize = $formatAttachmentSize((int) ($attachment["size_bytes"] ?? 0));
                            $attachmentOriginalName = trim((string) ($attachment["original_name"] ?? ""));
                            $attachmentLabel = (string) ($attachment["label"] ?? "Attachment");
                            $attachmentMetaParts = [$attachmentType === "file" ? "File" : "URL"];
                            if ($attachmentSize !== "") {
                                $attachmentMetaParts[] = $attachmentSize;
                            }
                            $attachmentMetaParts[] = (string) (($attachment["added_by"] ?? "") ?: "developer");
                            $attachmentMeta = implode(" - ", $attachmentMetaParts);
                            $attachmentPreview = $attachmentPreviewFor($attachment);
                            $attachmentPreviewKind = (string) ($attachmentPreview["kind"] ?? "none");
                            $attachmentUrl = (string) ($attachment["url"] ?? "");
                            $attachmentPreviewModalId = $modalId . "-attachment-preview-" . (string) $attachmentIndex;
                            $attachmentCanLargePreview = in_array($attachmentPreviewKind, ["image", "pdf", "text"], true);
                        ?>
                        <div class="developer-kanban-attachment-item">
                          <?php if ($attachmentCanLargePreview): ?>
                          <button class="developer-kanban-attachment-row" type="button" data-fnlla-modal-open="#<?= h($attachmentPreviewModalId) ?>" aria-label="Preview attachment <?= h($attachmentLabel) ?>">
                          <?php else: ?>
                          <a class="developer-kanban-attachment-row" href="<?= h($attachmentUrl) ?>" target="_blank" rel="noopener noreferrer">
                          <?php endif; ?>
                            <span class="developer-kanban-attachment-thumb developer-kanban-attachment-thumb-<?= h($attachmentPreviewKind !== "none" ? $attachmentPreviewKind : $attachmentType) ?>" aria-hidden="true">
                              <?php if ($attachmentPreviewKind === "image"): ?>
                              <img src="<?= h((string) ($attachmentPreview["url"] ?? "")) ?>" alt="">
                              <?php elseif ($attachmentPreviewKind === "text"): ?>
                              <span>TXT</span>
                              <?php elseif ($attachmentPreviewKind === "pdf"): ?>
                              <span>PDF</span>
                              <?php else: ?>
                              <span><?= h(strtoupper(substr($attachmentType === "file" && $attachmentOriginalName !== "" ? pathinfo($attachmentOriginalName, PATHINFO_EXTENSION) : $attachmentType, 0, 4)) ?: "LINK") ?></span>
                              <?php endif; ?>
                            </span>
                            <span class="developer-kanban-attachment-copy">
                              <strong><?= h($attachmentLabel) ?></strong>
                              <small><?= h($attachmentMeta) ?></small>
                            </span>
                          <?php if ($attachmentCanLargePreview): ?>
                          </button>
                          <?php else: ?>
                          </a>
                          <?php endif; ?>
                          <?php if ($attachmentCanLargePreview): ?>
                          <div class="modal developer-kanban-modal developer-kanban-attachment-modal" id="<?= h($attachmentPreviewModalId) ?>" data-fnlla-modal role="dialog" aria-modal="true" aria-labelledby="<?= h($attachmentPreviewModalId) ?>-title" hidden>
                            <div class="modal-content developer-kanban-modal-content developer-kanban-attachment-modal-content">
                              <div class="developer-kanban-modal-head">
                                <div>
                                  <p class="feature-kicker mb-2">Attachment preview</p>
                                  <h2 class="content-title mb-0" id="<?= h($attachmentPreviewModalId) ?>-title"><?= h($attachmentLabel) ?></h2>
                                </div>
                                <button class="developer-kanban-modal-close" type="button" data-fnlla-modal-close aria-label="Close attachment preview"><span aria-hidden="true">x</span></button>
                              </div>
                              <div class="developer-kanban-attachment-large-preview">
                                <?php if ($attachmentPreviewKind === "image"): ?>
                                <img src="<?= h((string) ($attachmentPreview["url"] ?? "")) ?>" alt="">
                                <?php elseif ($attachmentPreviewKind === "pdf"): ?>
                                <iframe src="<?= h((string) ($attachmentPreview["url"] ?? "")) ?>" title="Attachment preview"></iframe>
                                <?php elseif ($attachmentPreviewKind === "text"): ?>
                                <pre class="developer-kanban-attachment-preview-text"><?= h((string) ($attachmentPreview["text"] ?? "")) ?></pre>
                                <?php endif; ?>
                              </div>
                              <div class="developer-kanban-modal-actions">
                                <a class="btn btn-outline btn-sm" href="<?= h($attachmentUrl) ?>" target="_blank" rel="noopener noreferrer">Open original</a>
                                <button class="btn btn-ghost btn-sm" type="button" data-fnlla-modal-close>Close</button>
                              </div>
                            </div>
                          </div>
                          <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                        </div>
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

              if (!card || !event.dataTransfer || event.target.closest("button, a, input, select, textarea, summary, details")) {
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

            board.addEventListener("change", function (event) {
              var checkbox = event.target.closest(".developer-kanban-subtask-toggle input");
              var row = checkbox ? checkbox.closest(".developer-kanban-subtask-item") : null;

              if (!row) {
                return;
              }

              row.classList.toggle("is-complete", checkbox.checked);
            });

            var search = document.querySelector("[data-developer-kanban-search]");
            var activeFilter = "all";
            var filterButtons = document.querySelectorAll("[data-developer-kanban-filter]");

            function applyKanbanFilters() {
              var query = search ? search.value.trim().toLowerCase() : "";

              board.querySelectorAll("[data-developer-kanban-task]").forEach(function (card) {
                var haystack = card.getAttribute("data-developer-kanban-search-text") || "";
                var priority = card.getAttribute("data-developer-kanban-priority") || "";
                var assignee = card.getAttribute("data-developer-kanban-assignee") || "";
                var blocked = card.getAttribute("data-developer-kanban-blocked") === "true";
                var matchesSearch = query === "" || haystack.indexOf(query) !== -1;
                var matchesFilter = activeFilter === "all"
                  || (activeFilter === "mine" && assignee === <?= json_encode($currentEmail, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>)
                  || (activeFilter === "urgent" && priority === "urgent")
                  || (activeFilter === "high" && (priority === "high" || priority === "urgent"))
                  || (activeFilter === "low" && priority === "low")
                  || (activeFilter === "blocked" && blocked);

                card.hidden = !matchesSearch || !matchesFilter;
              });
            }

            filterButtons.forEach(function (button) {
              button.addEventListener("click", function () {
                activeFilter = button.getAttribute("data-developer-kanban-filter") || "all";
                filterButtons.forEach(function (item) {
                  var selected = item === button;
                  item.classList.toggle("is-active", selected);
                  item.setAttribute("aria-pressed", String(selected));
                });
                applyKanbanFilters();
              });
            });

            if (search) {
              search.addEventListener("input", applyKanbanFilters);
            }
          })();
        </script>

<?php require __DIR__ . "/panel-footer.php"; ?>
