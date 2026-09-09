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
        "role_label" => "Administrator",
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
$projectBrandLogo = project_brand_logo_asset();
$developerWorkspaceModuleEnabled = \Fnlla\Php\Support\DeveloperModules::enabled("workspace");
$developerMyTodoHref = (string) ($developerLinks["private_todo"] ?? route("developer.panel.private_todo"));
$notificationHref = static function (array $item) use ($developerLinks): string {
    return \Fnlla\Php\Support\DeveloperNotificationCenter::routeFor($item, $developerLinks);
};
$headerRuntime = is_array($developerDashboard["runtime_environment"] ?? null) ? (array) $developerDashboard["runtime_environment"] : [];
$headerRuntimeMode = (string) ($headerRuntime["mode"] ?? (app_environment() === "production" ? "production" : "development"));
$headerRuntimeReady = (bool) ($headerRuntime["production_ready"] ?? false);
$headerDebugEnabled = (bool) ($headerRuntime["debug_enabled"] ?? app_debug());
$headerRuntimeState = $headerRuntimeMode === "production"
    ? ($headerRuntimeReady ? "Ready" : "Review")
    : ($headerDebugEnabled ? "Debug on" : "Debug off");
$developerCommandItems = [];
$addCommandItem = static function (string $label, string $href, string $group, string $description = "", array $keywords = []) use (&$developerCommandItems): void {
    if ($href === "") {
        return;
    }
    $search = strtolower(trim(implode(" ", array_merge([$label, $group, $description, $href], $keywords))));
    $developerCommandItems[$href] = [
        "label" => $label,
        "href" => $href,
        "group" => $group,
        "description" => $description !== "" ? $description : $group,
        "search" => $search,
    ];
};
$addCommandItem((string) $dashboardNavigationItem["label"], (string) $dashboardNavigationItem["href"], "Developer Panel", "Operational snapshot, alerts and project status.", ["home", "overview"]);
$addCommandItem("Public website", (string) ($developerLinks["home"] ?? route("home")), "Project", "Open the public application in a new tab.", ["preview", "site"]);
if ($developerWorkspaceModuleEnabled) {
    $addCommandItem("My to-do", $developerMyTodoHref, "Workspace", "Personal developer checklist outside the shared Kanban.", ["todo", "private", "tasks", "checklist"]);
}
foreach ($panelNavigationGroups as $groupLabel => $items) {
    foreach ($items as $item) {
        $addCommandItem((string) ($item["label"] ?? ""), (string) ($item["href"] ?? ""), (string) $groupLabel, "Open this Developer Panel section.");
    }
}
$addCommandItem("Runtime environment", (string) ($developerLinks["identity"] ?? route("developer.panel.project_identity")) . "#runtime-environment", "Project setup", "Switch development or production runtime posture.", ["app_env", "app_debug", "trusted hosts"]);
$addCommandItem("Client preview", (string) ($developerLinks["project_settings"] ?? route("developer.panel.project_settings")), "Project setup", "Maintenance password and public service control.", ["maintenance", "preview"]);
$addCommandItem("Notifications", (string) ($developerLinks["notifications"] ?? route("developer.panel.notifications")), "Developer Panel", "Review actionable panel alerts.", ["alerts", "bell"]);
$addCommandItem("Developer profile", (string) ($developerLinks["profile"] ?? route("developer.panel.profile")), "Developer Panel", "Avatar, profile and account settings.", ["account", "password", "totp"]);
?>
<section class="developer-workspace" aria-label="Developer workspace">
  <script src="<?= h(asset("assets/developer-panel.js")) ?>" defer></script>
  <header class="developer-workspace-header">
    <button class="developer-mobile-menu" type="button" data-panel-menu aria-controls="developer-panel-navigation" aria-expanded="false" aria-label="Navigation" title="Navigation">
      <svg width="20" height="20" aria-hidden="true"><use href="<?= h(asset("vendor/fnlla-runtime/assets/icons/sprite.svg")) ?>#menu"></use></svg>
    </button>
    <div class="navbar-brand project-brand developer-workspace-brand">
      <a class="developer-workspace-brand-home" href="<?= h((string) ($developerLinks["overview"] ?? route("developer.panel"))) ?>">
        <span class="project-brand-mark <?= $projectBrandLogo !== null ? "is-logo" : "is-initials" ?>" aria-hidden="true">
          <?php if ($projectBrandLogo !== null): ?>
          <img src="<?= h($projectBrandLogo) ?>" alt="" width="1205" height="1176" decoding="async">
          <?php else: ?>
          <?= h(project_brand_mark()) ?>
          <?php endif; ?>
        </span>
        <span class="project-brand-name"><?= h((string) config("app.name")) ?></span>
      </a>
    </div>
    <a class="btn btn-outline btn-sm developer-workspace-public-link" href="<?= h((string) ($developerLinks["home"] ?? route("home"))) ?>" target="_blank" rel="noopener noreferrer">Go to public website</a>
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
        <?php if ($developerWorkspaceModuleEnabled): ?>
        <a class="developer-header-tool-button <?= $developerPanelActive === "private-todo" ? "is-active" : "" ?>" href="<?= h($developerMyTodoHref) ?>" aria-label="Open developer to-do list" data-fnlla-tooltip="My to-do" data-fnlla-tooltip-position="bottom">
          <span class="developer-header-tool-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" focusable="false">
              <path d="M9 11l2 2 4-4"></path>
              <path d="M5 6h14"></path>
              <path d="M5 18h14"></path>
              <path d="M5 12h1"></path>
            </svg>
          </span>
        </a>
        <?php endif; ?>
        <div class="dropdown developer-header-tool-dropdown" data-fnlla-dropdown>
          <button class="developer-header-tool-button" type="button" data-fnlla-dropdown-toggle aria-label="Open notification center">
            <span class="developer-header-tool-icon" aria-hidden="true">
              <svg viewBox="0 0 24 24" focusable="false">
                <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"></path>
                <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
              </svg>
            </span>
            <?php if ($headerNotificationCount > 0): ?>
            <span class="developer-header-tool-badge"><?= h((string) min(99, $headerNotificationCount)) ?></span>
            <?php endif; ?>
          </button>
          <div class="dropdown-menu developer-header-tool-menu" role="menu">
            <div class="developer-header-tool-menu-head">
              <p class="project-dropdown-heading">Notifications</p>
              <strong><?= h((string) $headerNotificationCount) ?></strong>
            </div>
            <div class="developer-header-tool-list">
              <?php if ($headerNotificationItems === []): ?>
              <p class="developer-header-tool-empty">No notifications right now.</p>
              <?php endif; ?>
              <?php foreach (array_slice($headerNotificationItems, 0, 5) as $item): ?>
              <a class="developer-header-tool-item" role="menuitem" href="<?= h($notificationHref((array) $item)) ?>">
                <span>
                  <strong><?= h((string) ($item["title"] ?? "Notification")) ?></strong>
                  <small><?= h((string) ($item["text"] ?? "")) ?></small>
                </span>
                <em><?= h((string) ($item["severity"] ?? "info")) ?></em>
              </a>
              <?php endforeach; ?>
            </div>
            <a class="developer-header-tool-footer" role="menuitem" href="<?= h((string) ($developerLinks["notifications"] ?? route("developer.panel.notifications"))) ?>">Open notification center</a>
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
              <a class="dropdown-item" role="menuitem" href="<?= h((string) ($developerLinks["profile"] ?? route("developer.panel.profile"))) ?>">Developer profile</a>
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
          <input id="developer-command-input" type="search" placeholder="Jump to section, setting or tool..." autocomplete="off" role="combobox" aria-autocomplete="list" aria-controls="developer-command-results" aria-expanded="true" data-developer-command-input>
          <span class="developer-command-shortcuts" aria-hidden="true"><kbd>Ctrl K</kbd><kbd>/</kbd></span>
        </div>
        <div class="developer-command-list" id="developer-command-results" role="listbox" aria-labelledby="developer-command-title" data-developer-command-list>
          <div class="developer-command-list-head">
            <h2 id="developer-command-title">Jump search</h2>
            <small id="developer-command-help">Use arrows and Enter, or type a route, setting or tool name.</small>
          </div>
          <?php foreach ($developerCommandItems as $item): ?>
          <?php $commandItemId = "developer-command-item-" . substr(hash("sha256", (string) $item["href"]), 0, 12); ?>
          <a class="developer-command-item" id="<?= h($commandItemId) ?>" href="<?= h((string) $item["href"]) ?>" role="option" aria-selected="false" data-developer-command-item data-developer-command-search="<?= h((string) $item["search"]) ?>">
            <span>
              <strong><?= h((string) $item["label"]) ?></strong>
              <em><?= h((string) $item["description"]) ?></em>
            </span>
            <small><?= h((string) $item["group"]) ?></small>
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
          <?php foreach ($panelNavigationGroups as $groupLabel => $items): ?>
          <div class="developer-panel-sidebar-group">
            <p><?= h((string) $groupLabel) ?></p>
            <?php foreach ($items as $key => $item): ?>
            <a class="developer-panel-sidebar-link <?= $developerPanelActive === $key ? "is-active" : "" ?>" href="<?= h((string) $item["href"]) ?>" <?= $developerPanelActive === $key ? 'aria-current="page"' : "" ?>>
              <span><?= h((string) $item["label"]) ?></span>
              <?php if ($developerPanelActive === $key): ?>
              <span class="developer-panel-sidebar-state">NOW</span>
              <?php endif; ?>
            </a>
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
