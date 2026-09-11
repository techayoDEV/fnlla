<?php

declare(strict_types=1);

$developerAccess ??= [
    "configured" => false,
    "path" => "/developer",
    "unlocked" => false,
    "expires_at" => 0,
    "seconds_remaining" => 0,
    "unlock_ttl_minutes" => 120,
    "absolute_ttl_minutes" => 480,
    "operations_nav_mode" => "hidden",
    "current_developer" => [
        "email" => "",
        "name" => "Developer",
        "role" => "admin",
        "role_label" => "Lead developer",
        "avatar" => "",
    ],
];

$maintenanceAccess ??= [
    "enabled" => false,
    "configured" => false,
    "unlocked" => true,
    "username_required" => false,
];

$developerControl ??= [
    "disabled" => false,
    "remote_enabled" => false,
    "source" => "none",
];

$developerLinks ??= [];
$developerNotice ??= null;
$developerPanelActive ??= "overview";
$developerPanelTitle ??= "Dashboard";
$developerPanelLead ??= "Private operational workspace for the project team.";
$currentDeveloper = is_array($developerAccess["current_developer"] ?? null) ? (array) $developerAccess["current_developer"] : [];
$currentPath = current_path();
$expiresAt = (int) ($developerAccess["expires_at"] ?? 0);
$remaining = max(0, (int) ($developerAccess["seconds_remaining"] ?? 0));
$initialCountdown = sprintf("%02d:%02d", intdiv($remaining, 3600), intdiv($remaining % 3600, 60));
$ttlMinutes = max(1, (int) ($developerAccess["unlock_ttl_minutes"] ?? 120));
$sessionProgress = max(0, min(100, (int) round(($remaining / max(1, $ttlMinutes * 60)) * 100)));
$developerAvatar = trim((string) ($currentDeveloper["avatar"] ?? ""));
$developerAvatarIsUrl = $developerAvatar !== "" && (filter_var($developerAvatar, FILTER_VALIDATE_URL) !== false || str_starts_with($developerAvatar, "/uploads/developer-avatars/"));
$developerAvatarName = (string) preg_replace('/[^A-Za-z0-9]/', '', (string) ($currentDeveloper["name"] ?? "Developer"));
$developerAvatarMark = $developerAvatar !== "" && !$developerAvatarIsUrl
    ? strtoupper(substr($developerAvatar, 0, 2))
    : strtoupper(substr($developerAvatarName !== "" ? $developerAvatarName : "D", 0, 2));
$developerDisplayName = trim((string) ($currentDeveloper["name"] ?? "Developer"));
$developerNameParts = array_values(array_filter(preg_split('/\s+/', $developerDisplayName) ?: []));
$developerMenuLabel = $developerDisplayName !== "" ? $developerDisplayName : "Developer";

if (count($developerNameParts) >= 2) {
    $developerMenuLabel = $developerNameParts[0] . " " . strtoupper(substr($developerNameParts[count($developerNameParts) - 1], 0, 1)) . ".";
} elseif (strlen($developerMenuLabel) > 18) {
    $developerMenuLabel = substr($developerMenuLabel, 0, 17) . ".";
}

$developerFrameworkVersionLines = is_file(base_path("VERSION")) ? file(base_path("VERSION"), FILE_IGNORE_NEW_LINES) : [];
$developerFrameworkVersionLines = is_array($developerFrameworkVersionLines) ? $developerFrameworkVersionLines : [];
$developerFrameworkVersion = trim((string) ($developerFrameworkVersionLines[0] ?? config("app.framework_version", "unknown")));
$developerFrameworkVersion = $developerFrameworkVersion !== "" ? $developerFrameworkVersion : "unknown";
$frameworkOfficialUrl = rtrim((string) config("framework.official_url", "https://fnlla.com"), "/");
$frameworkMaintainerUrl = rtrim((string) config("framework.maintainer_url", "https://techayo.co.uk"), "/");
$dashboardNavigationItem = ["label" => "Dashboard", "href" => (string) ($developerLinks["overview"] ?? route("developer.panel"))];
$panelNavigationGroups = \Fnlla\Php\Support\DeveloperNavigation::groups(
    (array) ($developerAccess["current_capabilities"] ?? []), static fn (string $name): string => route($name)
);
$headerNotifications = is_array($developerHeaderNotifications ?? null) ? (array) $developerHeaderNotifications : [];
$headerNotificationItems = array_values((array) ($headerNotifications["items"] ?? []));
$headerNotificationCount = max(0, (int) ($headerNotifications["unread_count"] ?? 0));
$headerReviewQueue = is_array($developerReviewQueue ?? null) ? (array) $developerReviewQueue : [];
$headerReviewItems = array_values((array) ($headerReviewQueue["items"] ?? []));
$headerReviewCount = max(0, (int) ($headerReviewQueue["total_count"] ?? count($headerReviewItems)));
$headerNotificationActionRoute = (string) ($developerLinks["notifications_action"] ?? route("developer.panel.notifications.action"));
$developerPanelBasePath = rtrim((string) ($developerAccess["path"] ?? "/developer"), "/") . "/panel";
$headerNotificationReturnPath = $currentPath === $developerPanelBasePath || str_starts_with($currentPath, $developerPanelBasePath . "/")
    ? $currentPath
    : (string) ($developerLinks["overview"] ?? route("developer.panel"));
$headerPrivateTodo = is_array($developerHeaderPrivateTodo ?? null) ? (array) $developerHeaderPrivateTodo : [];
$headerPrivateTodoItems = array_values((array) ($headerPrivateTodo["items"] ?? []));
$headerPrivateTodoOpenItems = array_values(array_filter($headerPrivateTodoItems, static fn ($item): bool => is_array($item) && !((bool) ($item["done"] ?? false))));
$headerPrivateTodoCount = max(0, (int) ($headerPrivateTodo["open_count"] ?? count($headerPrivateTodoOpenItems)));
$headerPrivateTodoPriorityLabels = [
    "low" => "Low",
    "normal" => "Normal",
    "high" => "High",
];
$developerFrameworkWordmark = framework_brand_asset("wordmark");
$reviewHref = static function (array $item) use ($developerLinks): string {
    $href = trim((string) ($item["href"] ?? ""));
    if ($href !== "") {
        return $href;
    }

    return \Fnlla\Php\Support\DeveloperNotificationCenter::routeFor($item, $developerLinks);
};
$headerRuntime = is_array($developerDashboard["runtime_environment"] ?? null) ? (array) $developerDashboard["runtime_environment"] : [];
$headerRuntimeMode = (string) ($headerRuntime["mode"] ?? (app_environment() === "production" ? "production" : "development"));
$headerRuntimeReady = (bool) ($headerRuntime["production_ready"] ?? false);
$headerDebugEnabled = (bool) ($headerRuntime["debug_enabled"] ?? app_debug());
$headerRuntimeState = $headerRuntimeMode === "production"
    ? ($headerRuntimeReady ? "Ready" : "Review")
    : ($headerDebugEnabled ? "Debug on" : "Debug off");
$developerCommandItems = (new \Fnlla\Php\Support\DeveloperCommandRegistry())->items(
    $developerLinks,
    (array) ($developerAccess["current_capabilities"] ?? []),
    $panelNavigationGroups
);
?>
<section class="developer-workspace" aria-label="Developer workspace">
  <script src="<?= h(asset("assets/developer-panel.js")) ?>" defer></script>
  <header class="developer-workspace-header">
    <button class="developer-mobile-menu" type="button" data-panel-menu aria-controls="developer-panel-navigation" aria-expanded="false" aria-label="Navigation" title="Navigation">
      <svg width="20" height="20" aria-hidden="true"><use href="<?= h(asset("vendor/fnlla-runtime/assets/icons/sprite.svg")) ?>#menu"></use></svg>
    </button>
    <div class="navbar-brand developer-workspace-brand developer-workspace-framework-brand">
      <a class="developer-workspace-brand-home" href="<?= h((string) ($developerLinks["overview"] ?? route("developer.panel"))) ?>" aria-label="FNLLA Developer Panel">
        <?php if ($developerFrameworkWordmark !== null): ?>
        <img class="developer-workspace-framework-wordmark" src="<?= h($developerFrameworkWordmark) ?>" alt="FNLLA" width="1841" height="590" decoding="async">
        <?php else: ?>
        <span class="developer-workspace-framework-wordmark-fallback">FNLLA</span>
        <?php endif; ?>
        <span class="developer-workspace-framework-meta" aria-hidden="true">
          Developer workspace
        </span>
      </a>
    </div>
    <span class="developer-workspace-header-divider" aria-hidden="true"></span>
    <a class="developer-runtime-header-badge" href="<?= h((string) ($developerLinks["identity"] ?? route("developer.panel.project_identity"))) ?>#runtime-environment" data-fnlla-tooltip="Runtime environment" data-fnlla-tooltip-position="bottom">
      <span><?= h(strtoupper($headerRuntimeMode)) ?></span>
      <strong><?= h($headerRuntimeState) ?></strong>
    </a>
    <div class="developer-workspace-actions">
      <div class="developer-header-tools" aria-label="Developer quick tools">
        <button class="developer-header-tool-button" type="button" data-developer-command-open aria-controls="developer-command-palette" aria-expanded="false" aria-label="Open command palette">
          <span class="developer-header-tool-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" focusable="false">
              <circle cx="11" cy="11" r="7"></circle>
              <path d="m20 20-3.5-3.5"></path>
            </svg>
          </span>
        </button>
        <?php if (\Fnlla\Php\Support\DeveloperModules::enabled("workspace")): ?>
        <div class="dropdown developer-header-tool-dropdown developer-header-todo-dropdown" data-fnlla-dropdown>
          <button class="developer-header-tool-button" type="button" data-fnlla-dropdown-toggle aria-label="Open private to-do list">
            <span class="developer-header-tool-icon" aria-hidden="true">
              <svg viewBox="0 0 24 24" focusable="false"><use href="<?= h(asset("vendor/fnlla-runtime/assets/icons/sprite.svg")) ?>#circle-check"></use></svg>
            </span>
            <?php if ($headerPrivateTodoCount > 0): ?>
            <span class="developer-header-tool-badge"><?= h((string) min(99, $headerPrivateTodoCount)) ?></span>
            <?php endif; ?>
          </button>
          <div class="dropdown-menu developer-header-tool-menu developer-header-todo-menu" role="menu">
            <div class="developer-header-tool-menu-head">
              <p class="project-dropdown-heading">My to-do</p>
              <strong><?= h((string) $headerPrivateTodoCount) ?></strong>
            </div>
            <form class="developer-header-todo-form" action="<?= h(route("developer.private_todo.items.create")) ?>" method="post" role="none" data-developer-ajax>
              <?= csrf_field() ?>
              <input type="hidden" name="developer_private_todo_priority" value="normal">
              <input type="hidden" name="developer_private_todo_color" value="blue">
              <label class="visually-hidden" for="developer-header-todo-title">New private to-do</label>
              <input class="input" id="developer-header-todo-title" name="developer_private_todo_title" type="text" maxlength="160" placeholder="Add private task..." required>
              <button class="btn btn-primary btn-sm" type="submit">Add</button>
            </form>
            <div class="developer-header-tool-list developer-header-todo-list">
              <?php if ($headerPrivateTodoOpenItems === []): ?>
              <p class="developer-header-tool-empty">No private tasks open.</p>
              <?php endif; ?>
              <?php foreach (array_slice($headerPrivateTodoOpenItems, 0, 5) as $item): ?>
              <?php
                  $priority = (string) ($item["priority"] ?? "normal");
                  $priority = array_key_exists($priority, $headerPrivateTodoPriorityLabels) ? $priority : "normal";
                  $dueDate = trim((string) ($item["due_date"] ?? ""));
                  $color = preg_replace('/[^a-z0-9_-]/', '', (string) ($item["color"] ?? "blue")) ?: "blue";
              ?>
              <a class="developer-header-tool-item developer-header-todo-item is-color-<?= h($color) ?>" role="menuitem" href="<?= h((string) ($developerLinks["private_todo"] ?? route("developer.panel.private_todo"))) ?>">
                <span>
                  <strong><?= h((string) ($item["title"] ?? "Private task")) ?></strong>
                  <small><?= h((string) $headerPrivateTodoPriorityLabels[$priority]) ?><?= $dueDate !== "" ? " / Due " . h($dueDate) : "" ?></small>
                </span>
                <em><?= h($dueDate !== "" ? $dueDate : "open") ?></em>
              </a>
              <?php endforeach; ?>
            </div>
            <a class="developer-header-tool-footer" role="menuitem" href="<?= h((string) ($developerLinks["private_todo"] ?? route("developer.panel.private_todo"))) ?>">Open full to-do</a>
          </div>
        </div>
        <?php endif; ?>
        <div class="dropdown developer-header-tool-dropdown" data-fnlla-dropdown>
          <button class="developer-header-tool-button" type="button" data-fnlla-dropdown-toggle aria-label="Open review queue">
            <span class="developer-header-tool-icon" aria-hidden="true">
              <svg viewBox="0 0 24 24" focusable="false">
                <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"></path>
                <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
              </svg>
            </span>
            <?php if ($headerReviewCount > 0): ?>
            <span class="developer-header-tool-badge"><?= h((string) min(99, $headerReviewCount)) ?></span>
            <?php endif; ?>
          </button>
          <div class="dropdown-menu developer-header-tool-menu" role="menu">
            <div class="developer-header-tool-menu-head">
              <p class="project-dropdown-heading">Review queue</p>
              <strong><?= h((string) $headerReviewCount) ?></strong>
            </div>
            <div class="developer-header-tool-list">
              <?php if ($headerReviewItems === []): ?>
              <p class="developer-header-tool-empty">No review items right now.</p>
              <?php endif; ?>
              <?php foreach (array_slice($headerReviewItems, 0, 5) as $item): ?>
              <?php
                  $headerReviewItem = (array) $item;
                  $headerReviewHref = $reviewHref($headerReviewItem);
                  $headerReviewTitle = trim((string) ($headerReviewItem["title"] ?? "Review item"));
                  $headerReviewText = trim((string) ($headerReviewItem["text"] ?? ""));
                  $headerReviewOwner = trim((string) ($headerReviewItem["owner"] ?? "Lead developer"));
                  $headerReviewSeverity = strtolower(trim((string) ($headerReviewItem["severity"] ?? "info")));
                  $headerReviewSeverity = in_array($headerReviewSeverity, ["critical", "warning", "info", "success"], true) ? $headerReviewSeverity : "info";
                  $headerNotificationKey = trim((string) (($headerReviewItem["notification_key"] ?? "") ?: ($headerReviewItem["key"] ?? "")));
                  $headerNotificationAcknowledged = trim((string) ($headerReviewItem["acknowledged_at"] ?? "")) !== "";
                  $headerNotificationActionable = $headerNotificationKey !== "" && $headerReviewSeverity !== "success";
              ?>
              <?php if ($headerNotificationActionable): ?>
              <div class="developer-header-tool-item developer-header-notification-item developer-header-notification-item-<?= h($headerReviewSeverity) ?>" role="group" aria-label="<?= h($headerReviewTitle) ?>">
                <span class="developer-header-tool-copy">
                  <strong><?= h($headerReviewTitle) ?></strong>
                  <small><?= h($headerReviewOwner) ?> / <?= h($headerReviewText) ?></small>
                </span>
                <em><?= h($headerReviewSeverity) ?></em>
                <div class="developer-header-notification-actions">
                  <form action="<?= h($headerNotificationActionRoute) ?>" method="post" data-developer-ajax>
                    <?= csrf_field() ?>
                    <input type="hidden" name="developer_notification_key" value="<?= h($headerNotificationKey) ?>">
                    <input type="hidden" name="developer_notification_action" value="review">
                    <input type="hidden" name="developer_notification_redirect" value="<?= h($headerReviewHref) ?>">
                    <button class="developer-header-notification-action" type="submit">Review</button>
                  </form>
                  <?php if (!$headerNotificationAcknowledged): ?>
                  <form action="<?= h($headerNotificationActionRoute) ?>" method="post" data-developer-ajax>
                    <?= csrf_field() ?>
                    <input type="hidden" name="developer_notification_key" value="<?= h($headerNotificationKey) ?>">
                    <input type="hidden" name="developer_notification_action" value="acknowledge">
                    <input type="hidden" name="developer_notification_redirect" value="<?= h($headerNotificationReturnPath) ?>">
                    <button class="developer-header-notification-action" type="submit">Read</button>
                  </form>
                  <?php endif; ?>
                  <form action="<?= h($headerNotificationActionRoute) ?>" method="post" data-developer-ajax>
                    <?= csrf_field() ?>
                    <input type="hidden" name="developer_notification_key" value="<?= h($headerNotificationKey) ?>">
                    <input type="hidden" name="developer_notification_action" value="archive">
                    <input type="hidden" name="developer_notification_redirect" value="<?= h($headerNotificationReturnPath) ?>">
                    <button class="developer-header-notification-dismiss" type="submit" aria-label="Archive notification <?= h($headerReviewTitle) ?>">x</button>
                  </form>
                </div>
              </div>
              <?php else: ?>
              <a class="developer-header-tool-item" role="menuitem" href="<?= h($reviewHref((array) $item)) ?>">
                <span>
                  <strong><?= h((string) ($item["title"] ?? "Review item")) ?></strong>
                  <small><?= h((string) ($item["owner"] ?? "Lead developer")) ?> / <?= h((string) ($item["text"] ?? "")) ?></small>
                </span>
                <em><?= h((string) ($item["severity"] ?? "info")) ?></em>
              </a>
              <?php endif; ?>
              <?php endforeach; ?>
            </div>
            <a class="developer-header-tool-footer" role="menuitem" href="<?= h((string) ($developerLinks["notifications"] ?? route("developer.panel.notifications"))) ?>">Open review queue</a>
          </div>
        </div>

      </div>
      <div class="navbar-actions project-navbar-actions">
        <div class="dropdown project-operations-dropdown" data-fnlla-dropdown>
          <button class="btn btn-outline btn-sm project-dropdown-toggle" type="button" data-fnlla-dropdown-toggle aria-label="Open developer operations menu">
            <span class="developer-dropdown-avatar" aria-hidden="true">
              <?php if ($developerAvatarIsUrl): ?>
              <img src="<?= h($developerAvatar) ?>" alt="">
              <?php else: ?>
              <?= h($developerAvatarMark) ?>
              <?php endif; ?>
            </span>
            <span><?= h($developerMenuLabel) ?></span>
          </button>
          <div class="dropdown-menu project-dropdown-menu" role="menu">
            <div class="developer-dropdown-profile">
              <a class="developer-dropdown-avatar-link" href="<?= h((string) ($developerLinks["profile"] ?? route("developer.panel.profile"))) ?>#developer-profile-avatar" aria-label="Change developer avatar">
                <span class="developer-dropdown-avatar developer-dropdown-avatar-lg" aria-hidden="true">
                <?php if ($developerAvatarIsUrl): ?>
                  <img src="<?= h($developerAvatar) ?>" alt="">
                <?php else: ?>
                  <?= h($developerAvatarMark) ?>
                <?php endif; ?>
                </span>
              </a>
              <div>
                <strong><?= h((string) ($currentDeveloper["name"] ?? "Developer")) ?></strong>
                <span><?= h((string) ($currentDeveloper["role_label"] ?? "Developer")) ?></span>
                <?php if (($currentDeveloper["email"] ?? "") !== ""): ?>
                <small><?= h((string) $currentDeveloper["email"]) ?></small>
                <?php endif; ?>
              </div>
            </div>
            <div class="developer-dropdown-session" data-fnlla-session-countdown data-fnlla-session-countdown-format="hm" data-fnlla-session-expires-at="<?= h((string) $expiresAt) ?>">
              <span class="developer-dropdown-session-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" focusable="false">
                  <circle cx="12" cy="12" r="9"></circle>
                  <path d="M12 7v5l3 2"></path>
                </svg>
              </span>
              <div class="developer-dropdown-session-copy">
                <span>Session locks</span>
                <strong data-fnlla-session-countdown-output><?= h($initialCountdown) ?></strong>
                <div class="developer-dropdown-session-progress" aria-hidden="true"><i style="width: <?= h((string) $sessionProgress) ?>%"></i></div>
              </div>
              <form action="<?= h(route("developer.extend")) ?>" method="post">
                <?= csrf_field() ?>
                <button class="btn btn-outline btn-sm" type="submit">Extend session</button>
              </form>
            </div>
            <div class="developer-dropdown-group" aria-label="Session actions">
              <a class="dropdown-item" role="menuitem" href="<?= h((string) ($developerLinks["home"] ?? route("home"))) ?>" target="_blank" rel="noopener noreferrer">Open public website</a>
              <a class="dropdown-item" role="menuitem" href="<?= h((string) ($developerLinks["profile"] ?? route("developer.panel.profile"))) ?>">Developer profile</a>
              <a class="dropdown-item" role="menuitem" href="<?= h((string) ($developerLinks["settings"] ?? route("developer.panel.settings"))) ?>">Panel settings</a>
            </div>
            <form class="project-dropdown-form" action="<?= h(route("developer.lock")) ?>" method="post">
              <?= csrf_field() ?>
              <button class="dropdown-item project-dropdown-danger" role="menuitem" type="submit">Lock session</button>
            </form>
          </div>
        </div>
      </div>
    </div>
    <div class="developer-command-palette" id="developer-command-palette" data-developer-command-palette hidden>
      <button class="developer-command-backdrop" type="button" data-developer-command-close aria-label="Close command palette"></button>
      <div class="developer-command-dialog" role="dialog" aria-modal="true" aria-labelledby="developer-command-title" aria-describedby="developer-command-help">
        <div class="developer-command-search">
          <svg viewBox="0 0 24 24" focusable="false" aria-hidden="true">
            <circle cx="11" cy="11" r="7"></circle>
            <path d="m20 20-3.5-3.5"></path>
          </svg>
          <label class="visually-hidden" for="developer-command-input">Search developer panel</label>
          <input id="developer-command-input" type="search" placeholder="Launch action, section or setting..." autocomplete="off" role="combobox" aria-autocomplete="list" aria-controls="developer-command-results" aria-expanded="true" data-developer-command-input>
          <span class="developer-command-shortcuts" aria-hidden="true"><kbd>Ctrl K</kbd><kbd>/</kbd></span>
        </div>
        <div class="developer-command-list" id="developer-command-results" role="listbox" aria-labelledby="developer-command-title" data-developer-command-list>
          <div class="developer-command-list-head">
            <h2 id="developer-command-title">Command launcher</h2>
            <small id="developer-command-help">Use arrows and Enter, or type a decision, route, setting or export action.</small>
          </div>
          <?php foreach ($developerCommandItems as $item): ?>
          <?php $commandItemId = "developer-command-item-" . substr(hash("sha256", (string) ($item["label"] ?? "") . "|" . (string) ($item["href"] ?? "") . "|" . (string) ($item["kind"] ?? "")), 0, 12); ?>
          <a class="developer-command-item" id="<?= h($commandItemId) ?>" href="<?= h((string) $item["href"]) ?>" role="option" aria-selected="false" data-developer-command-item data-developer-command-search="<?= h((string) $item["search"]) ?>" data-developer-command-kind="<?= h((string) ($item["kind"] ?? "Open")) ?>" data-developer-command-capability="<?= h((string) ($item["capability"] ?? "")) ?>" data-developer-command-policy="<?= h((string) ($item["environment_policy"] ?? "all")) ?>" data-developer-command-destructive="<?= ($item["destructive"] ?? false) ? "true" : "false" ?>"<?= ($item["external"] ?? false) ? ' target="_blank" rel="noopener noreferrer" data-developer-command-target="_blank"' : "" ?>>
            <span>
              <strong><?= h((string) $item["label"]) ?></strong>
              <em><?= h((string) $item["description"]) ?></em>
            </span>
            <small><b><?= h((string) $item["group"]) ?></b><em><?= h((string) ($item["kind"] ?? "Open")) ?></em></small>
          </a>
          <?php endforeach; ?>
          <p class="developer-command-empty" data-developer-command-empty hidden>No matching panel destinations.</p>
        </div>
      </div>
    </div>
  </header>

  <div class="developer-workspace-body">
    <aside id="developer-panel-navigation" class="developer-panel-sidebar" aria-label="Developer panel sections">
      <div class="developer-panel-sidebar-top">
        <nav class="developer-panel-sidebar-nav" aria-label="Developer panel sections">
          <a class="developer-panel-sidebar-link developer-panel-sidebar-link-standalone <?= $developerPanelActive === "overview" ? "is-active" : "" ?>" href="<?= h((string) $dashboardNavigationItem["href"]) ?>" <?= $developerPanelActive === "overview" ? 'aria-current="page"' : "" ?>>
            <span><?= h((string) $dashboardNavigationItem["label"]) ?></span>
            <?php if ($developerPanelActive === "overview"): ?>
            <span class="developer-panel-sidebar-state">NOW</span>
            <?php endif; ?>
          </a>
          <?php foreach ($panelNavigationGroups as $groupLabel => $groupItems): ?>
          <div class="developer-panel-sidebar-group">
            <p><?= h((string) $groupLabel) ?></p>
            <?php foreach ($groupItems as $key => $item): ?>
            <?php
                $sidebarActiveAliases = array_values(array_filter((array) ($item["active_aliases"] ?? []), static fn (mixed $alias): bool => is_string($alias) && trim($alias) !== ""));
                $sidebarChildren = array_values(array_filter((array) ($item["children"] ?? []), static fn (mixed $child): bool => is_array($child)));
                $sidebarChildActive = false;
                foreach ($sidebarChildren as $sidebarChild) {
                    $sidebarChildKey = (string) ($sidebarChild["key"] ?? "");
                    $sidebarChildAliases = array_values(array_filter((array) ($sidebarChild["active_aliases"] ?? []), static fn (mixed $alias): bool => is_string($alias) && trim($alias) !== ""));
                    if ($developerPanelActive === $sidebarChildKey || in_array($developerPanelActive, $sidebarChildAliases, true)) {
                        $sidebarChildActive = true;
                        break;
                    }
                }
                $sidebarItemActive = $developerPanelActive === $key || in_array($developerPanelActive, $sidebarActiveAliases, true) || $sidebarChildActive;
                $sidebarBranchId = "developer-panel-sidebar-subnav-" . (string) preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) $key);
                $sidebarBranchExpanded = $sidebarItemActive;
            ?>
            <?php if ($sidebarChildren !== []): ?>
            <div class="developer-panel-sidebar-branch <?= $sidebarItemActive ? "is-active" : "" ?>">
              <button class="developer-panel-sidebar-link developer-panel-sidebar-link-parent <?= $sidebarItemActive ? "is-active" : "" ?>" type="button" data-developer-sidebar-toggle data-developer-sidebar-key="<?= h((string) $key) ?>" aria-expanded="<?= $sidebarBranchExpanded ? "true" : "false" ?>" aria-controls="<?= h($sidebarBranchId) ?>">
                <span><?= h((string) $item["label"]) ?></span>
                <span class="developer-panel-sidebar-parent-meta">
                <?php if ($sidebarItemActive): ?>
                  <span class="developer-panel-sidebar-state">NOW</span>
                <?php endif; ?>
                  <svg class="developer-panel-sidebar-chevron" viewBox="0 0 24 24" focusable="false" aria-hidden="true"><use href="<?= h(asset("vendor/fnlla-runtime/assets/icons/sprite.svg")) ?>#chevron-down"></use></svg>
                </span>
              </button>
              <div class="developer-panel-sidebar-subnav" id="<?= h($sidebarBranchId) ?>" aria-label="<?= h((string) $item["label"]) ?> sections" <?= $sidebarBranchExpanded ? "" : "hidden" ?>>
                <?php foreach ($sidebarChildren as $sidebarChild): ?>
                <?php
                    $sidebarChildKey = (string) ($sidebarChild["key"] ?? "");
                    $sidebarChildAliases = array_values(array_filter((array) ($sidebarChild["active_aliases"] ?? []), static fn (mixed $alias): bool => is_string($alias) && trim($alias) !== ""));
                    $sidebarChildIsActive = $developerPanelActive === $sidebarChildKey || in_array($developerPanelActive, $sidebarChildAliases, true);
                ?>
                <a class="developer-panel-sidebar-sublink <?= $sidebarChildIsActive ? "is-active" : "" ?>" href="<?= h((string) ($sidebarChild["href"] ?? "#")) ?>" <?= $sidebarChildIsActive ? 'aria-current="page"' : "" ?>>
                  <span><?= h((string) ($sidebarChild["label"] ?? "")) ?></span>
                </a>
                <?php endforeach; ?>
              </div>
            </div>
            <?php else: ?>
            <a class="developer-panel-sidebar-link <?= $sidebarItemActive ? "is-active" : "" ?>" href="<?= h((string) $item["href"]) ?>" <?= $sidebarItemActive ? 'aria-current="page"' : "" ?>>
              <span><?= h((string) $item["label"]) ?></span>
              <?php if ($sidebarItemActive): ?>
              <span class="developer-panel-sidebar-state">NOW</span>
              <?php endif; ?>
            </a>
            <?php endif; ?>
            <?php endforeach; ?>
          </div>
          <?php endforeach; ?>
        </nav>
      </div>
      <div class="developer-panel-sidebar-bottom">
        <small><a class="developer-workspace-brand-link" href="<?= h($frameworkOfficialUrl) ?>" target="_blank" rel="noopener noreferrer">FNLLA <span class="fnlla-literal"><?= h($developerFrameworkVersion) ?></span></a> by <a class="developer-workspace-brand-link" href="<?= h($frameworkMaintainerUrl) ?>" target="_blank" rel="noopener noreferrer">TechAyo</a></small>
      </div>

    </aside>

    <main class="developer-panel-main developer-panel-frame" data-developer-panel-frame>
      <?php if (is_array($developerNotice) && isset($developerNotice["title"], $developerNotice["text"])): ?>
      <section class="developer-dashboard-section" aria-label="Developer path notice">
        <article class="developer-panel-fieldset-card">
          <p class="feature-kicker">Developer session</p>
          <h2 class="section-title mb-0"><?= h((string) $developerNotice["title"]) ?></h2>
          <p class="content-text"><?= h((string) $developerNotice["text"]) ?></p>
        </article>
      </section>
      <?php endif; ?>

      <header class="developer-panel-page-head">
        <div>
          <p class="feature-kicker">Developer panel</p>
          <h1 class="section-title mb-0"><?= h((string) $developerPanelTitle) ?></h1>
          <p class="content-text"><?= h((string) $developerPanelLead) ?></p>
        </div>
      </header>
