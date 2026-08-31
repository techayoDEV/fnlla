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

$panelNavigationGroups = [
    "Workspace" => [
        "overview" => ["label" => "Dashboard", "href" => (string) ($developerLinks["overview"] ?? route("developer.panel"))],
        "workspace" => ["label" => "Project workspace", "href" => (string) ($developerLinks["workspace"] ?? route("developer.panel.workspace"))],
        "notifications" => ["label" => "Notifications", "href" => (string) ($developerLinks["notifications"] ?? route("developer.panel.notifications"))],
    ],
    "Project setup" => [
        "identity" => ["label" => "Project identity", "href" => (string) ($developerLinks["identity"] ?? route("developer.panel.project_identity"))],
        "analytics" => ["label" => "Analytics", "href" => (string) ($developerLinks["analytics"] ?? route("developer.panel.analytics"))],
        "heatmap" => ["label" => "Heatmap", "href" => (string) ($developerLinks["heatmap"] ?? route("developer.panel.heatmap"))],
    ],
    "Operations" => [
        "release-readiness" => ["label" => "Readiness & health", "href" => (string) ($developerLinks["release_readiness"] ?? route("developer.panel.release_readiness"))],
        "operations" => ["label" => "Operations hub", "href" => (string) ($developerLinks["operations"] ?? route("developer.panel.operations"))],
        "framework-updates" => ["label" => "Framework updates", "href" => (string) ($developerLinks["framework_updates"] ?? route("developer.panel.framework_updates"))],
        "integrations" => ["label" => "Integrations", "href" => (string) ($developerLinks["integrations"] ?? route("developer.panel.integrations"))],
    ],
    "Security & policy" => [
        "project-settings" => ["label" => "Access & preview", "href" => (string) ($developerLinks["project_settings"] ?? route("developer.panel.project_settings"))],
        "access" => ["label" => "Access & security", "href" => (string) ($developerLinks["access"] ?? route("developer.panel.access"))],
        "settings" => ["label" => "Runtime & storage", "href" => (string) ($developerLinks["settings"] ?? route("developer.panel.settings"))],
        "policy" => ["label" => "Policy boundary", "href" => (string) ($developerLinks["policy"] ?? route("developer.panel.policy"))],
        "documentation" => ["label" => "Documentation", "href" => (string) ($developerLinks["documentation"] ?? route("developer.panel.documentation"))],
        "about" => ["label" => "About", "href" => (string) ($developerLinks["about"] ?? route("developer.panel.about"))],
    ],
];
$headerNotifications = is_array($developerHeaderNotifications ?? null) ? (array) $developerHeaderNotifications : [];
$headerNotificationItems = array_values((array) ($headerNotifications["items"] ?? []));
$headerNotificationCount = max(0, (int) ($headerNotifications["unread_count"] ?? 0));
$notificationHref = static function (array $item) use ($developerLinks): string {
    $key = (string) ($item["key"] ?? "");
    $action = strtolower((string) ($item["action"] ?? ""));

    if (str_contains($key, "totp") || str_contains($key, "security")) {
        return (string) ($developerLinks["security"] ?? route("developer.panel.security"));
    }

    if (str_contains($key, "framework")) {
        return (string) ($developerLinks["framework_updates"] ?? route("developer.panel.framework_updates"));
    }

    if (str_contains($key, "backup") || str_contains($key, "audit") || str_contains($action, "readiness")) {
        return (string) ($developerLinks["release_readiness"] ?? route("developer.panel.release_readiness"));
    }

    if (str_contains($key, "metrics") || str_contains($action, "analytics")) {
        return (string) ($developerLinks["analytics"] ?? route("developer.panel.analytics"));
    }

    if (str_contains($key, "service")) {
        return (string) ($developerLinks["project_settings"] ?? route("developer.panel.project_settings"));
    }

    if (str_contains($key, "leadership")) {
        return (string) ($developerLinks["identity"] ?? route("developer.panel.project_identity")) . "#project-leadership";
    }

    return (string) ($developerLinks["notifications"] ?? route("developer.panel.notifications"));
};
?>
<section class="developer-workspace" aria-label="Developer workspace">
  <header class="developer-workspace-header">
    <div class="navbar-brand project-brand developer-workspace-brand">
      <a class="developer-workspace-brand-home" href="<?= h((string) ($developerLinks["overview"] ?? route("developer.panel"))) ?>">
        <span class="project-brand-mark" aria-hidden="true"><?= h(project_brand_mark()) ?></span>
        <span class="project-brand-name"><?= h((string) config("app.name")) ?></span>
      </a>
      <span class="developer-workspace-brand-copy">
        <small>FNLLA by <a class="developer-workspace-brand-link" href="https://techayo.co.uk" target="_blank" rel="noopener noreferrer">TechAyo</a> Limited</small>
      </span>
    </div>
    <a class="btn btn-outline btn-sm developer-workspace-public-link" href="<?= h((string) ($developerLinks["home"] ?? route("home"))) ?>" target="_blank" rel="noopener noreferrer">Go to public website</a>
    <div class="developer-workspace-actions">
      <div class="developer-header-tools" aria-label="Developer quick tools">
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
              <span class="developer-dropdown-avatar developer-dropdown-avatar-lg" aria-hidden="true">
                <?php if ($developerAvatarIsUrl): ?>
                <img src="<?= h($developerAvatar) ?>" alt="">
                <?php else: ?>
                <?= h($developerAvatarMark) ?>
                <?php endif; ?>
              </span>
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
  </header>

  <div class="developer-workspace-body">
    <aside class="developer-panel-sidebar" aria-label="Developer panel sections">
      <div class="developer-panel-sidebar-top">
        <nav class="developer-panel-sidebar-nav" aria-label="Developer panel sections">
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
