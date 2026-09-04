<?php

declare(strict_types=1);

$developerPanelTitle = "Dashboard";
$developerPanelLead = "Operational snapshot for identity, access, preview mode and framework readiness.";
$dashboard = is_array($developerDashboard ?? null) ? $developerDashboard : [];
$maintenanceEnabled = (bool) ($dashboard["maintenance_enabled"] ?? false);
$maintenanceConfigured = (bool) ($dashboard["maintenance_configured"] ?? false);
$frameworkLockReady = (bool) ($dashboard["framework_lock"] ?? false);
$storageReady = (bool) ($dashboard["storage_writable"] ?? false);
$sessionStorageReady = (bool) ($dashboard["session_storage_writable"] ?? false);
$queueStorageReady = (bool) ($dashboard["queue_storage_writable"] ?? false);
$allStorageReady = $storageReady && $sessionStorageReady && $queueStorageReady;
$observabilityEnabled = (bool) ($dashboard["observability_enabled"] ?? false);
$environment = ucfirst((string) ($dashboard["environment"] ?? app_environment()));
$sessionMinutes = (int) ($dashboard["developer_session_minutes"] ?? 120);
$developerCount = (int) ($developerAccess["users_count"] ?? 1);
$developerControl ??= ["disabled" => false, "source" => "none", "remote_enabled" => false];
$developerActivity = is_array($developerActivity ?? null) ? $developerActivity : [];
$projectLeadership = is_array($dashboard["project_leadership"] ?? null) ? (array) $dashboard["project_leadership"] : project_leadership("admin");
$projectLeadershipLabel = (bool) ($projectLeadership["configured"] ?? false)
    ? (string) ($projectLeadership["person_name"] ?? "Named lead")
    : "Not set";
$projectLeadershipState = (string) ($projectLeadership["status"] ?? "pending");
$remainingSeconds = max(0, (int) ($developerAccess["seconds_remaining"] ?? 0));
$remainingLabel = $remainingSeconds >= 3600
    ? sprintf("%dh %02dm %02ds", intdiv($remainingSeconds, 3600), intdiv($remainingSeconds % 3600, 60), $remainingSeconds % 60)
    : sprintf("%02dm %02ds", intdiv($remainingSeconds, 60), $remainingSeconds % 60);
$atAGlance = [
    ["label" => "Project", "value" => (string) ($dashboard["project_name"] ?? "FNLLA Project")],
    ["label" => "Environment", "value" => $environment],
    ["label" => "Framework", "value" => "FNLLA " . (string) ($dashboard["framework_version"] ?? "unknown")],
    ["label" => "Framework lock", "value" => $frameworkLockReady ? "Present" : "Missing"],
    ["label" => "Preview lock", "value" => $maintenanceEnabled ? "Enabled" : "Disabled"],
    ["label" => "Developers", "value" => (string) $developerCount],
    ["label" => "Service control", "value" => ($developerControl["disabled"] ?? false) ? "Disabled" : "Open"],
    ["label" => "Writable storage", "value" => $allStorageReady ? "OK" : "Needs attention"],
    ["label" => "Observability", "value" => $observabilityEnabled ? "Enabled" : "Disabled"],
    ["label" => "Leadership", "value" => $projectLeadershipLabel . " (" . $projectLeadershipState . ")"],
    ["label" => "Operations", "value" => "Ready"],
    ["label" => "Developer session", "value" => $remainingLabel . " of " . (string) $sessionMinutes . "m"],
];
$dashboardNotifications = is_array($developerHeaderNotifications ?? null) ? (array) $developerHeaderNotifications : [];
$dashboardNotificationItems = array_values((array) ($dashboardNotifications["items"] ?? []));
$dashboardNotificationCount = max(0, (int) ($dashboardNotifications["unread_count"] ?? 0));
$dashboardNotificationSourceFor = static function (array $item): string {
    $key = strtolower((string) ($item["key"] ?? ""));

    if (str_contains($key, "framework") || str_contains($key, "update")) {
        return "Framework updates";
    }

    if (str_contains($key, "totp") || str_contains($key, "access") || str_contains($key, "security")) {
        return "Access & security";
    }

    if (str_contains($key, "readiness") || str_contains($key, "backup") || str_contains($key, "audit")) {
        return "Readiness & health";
    }

    if (str_contains($key, "analytics") || str_contains($key, "heatmap") || str_contains($key, "metric")) {
        return "Analytics";
    }

    if (str_contains($key, "preview") || str_contains($key, "service")) {
        return "Project setup";
    }

    if (str_contains($key, "leadership") || str_contains($key, "identity")) {
        return "Project setup";
    }

    return "Developer Panel";
};
require __DIR__ . "/panel-header.php";
?>

        <section class="developer-dashboard-section" aria-label="Project overview">
          <h2 class="developer-dashboard-section-title">Project overview</h2>
          <div class="developer-dashboard-overview-grid">
            <article class="developer-dashboard-card">
              <p class="feature-kicker">Project identity</p>
              <h3><?= h((string) ($dashboard["project_name"] ?? "FNLLA Project")) ?></h3>
              <p class="developer-dashboard-value"><?= h((string) (($dashboard["project_url"] ?? "") !== "" ? $dashboard["project_url"] : "Local project URL not set")) ?></p>
              <?php if (($dashboard["project_tagline"] ?? "") !== ""): ?>
              <p class="content-text">Slogan: <?= h((string) $dashboard["project_tagline"]) ?></p>
              <?php else: ?>
              <p class="content-text">No public browser-title slogan is configured yet.</p>
              <?php endif; ?>
              <a class="btn btn-outline btn-sm" href="<?= h((string) ($developerLinks["identity"] ?? route("developer.panel.project_identity"))) ?>">Open setup</a>
            </article>

            <article class="developer-dashboard-card">
              <p class="feature-kicker">Client preview</p>
              <h3><?= $maintenanceEnabled ? "Password-protected preview active" : "Public routes open" ?></h3>
              <p class="developer-dashboard-status <?= $maintenanceEnabled ? "is-active" : "is-neutral" ?>"><?= $maintenanceEnabled ? "Maintenance lock enabled" : "Maintenance lock disabled" ?></p>
              <p class="content-text"><?= $maintenanceConfigured
                  ? "A preview password is configured and can be rotated before client handoff."
                  : "No preview password is configured yet. Set one before sharing a private build." ?></p>
              <a class="btn btn-outline btn-sm" href="<?= h((string) ($developerLinks["project_settings"] ?? route("developer.panel.project_settings"))) ?>">Open preview</a>
            </article>

            <article class="developer-dashboard-card">
              <p class="feature-kicker">Developer access</p>
              <h3><?= h((string) $developerCount) ?> named <?= $developerCount === 1 ? "developer" : "developers" ?></h3>
              <p class="content-text">Each developer can use a unique email and password while project settings stay global.</p>
              <a class="btn btn-outline btn-sm" href="<?= h((string) ($developerLinks["access"] ?? route("developer.panel.access"))) ?>">Manage access</a>
            </article>

            <article class="developer-dashboard-card">
              <p class="feature-kicker">System information</p>
              <h3><?= h($projectLeadershipLabel) ?></h3>
              <p class="developer-dashboard-status <?= $projectLeadershipState === "confirmed" ? "is-active" : "is-neutral" ?>"><?= h(ucfirst($projectLeadershipState)) ?></p>
              <p class="content-text"><?= ($projectLeadership["visibility"] ?? "disabled") === "public" ? "Can appear publicly after the named person confirms it." : "Kept private for the Developer Panel and documentation." ?></p>
              <a class="btn btn-outline btn-sm" href="<?= h((string) ($developerLinks["identity"] ?? route("developer.panel.project_identity"))) ?>#project-leadership">Open leadership</a>
            </article>
          </div>
        </section>

        <section class="developer-dashboard-section" aria-label="Dashboard notifications">
          <details class="developer-dashboard-notification-drawer">
            <summary>
              <span>
                <strong>Notifications</strong>
                <small><?= $dashboardNotificationCount > 0 ? h((string) $dashboardNotificationCount) . " active items need review" : "No open action items" ?></small>
              </span>
              <em><?= h((string) $dashboardNotificationCount) ?></em>
            </summary>
            <div class="developer-dashboard-notification-list">
              <?php foreach (array_slice($dashboardNotificationItems, 0, 5) as $item): ?>
              <?php
                  $severity = strtoupper((string) ($item["severity"] ?? "info"));
                  $title = trim((string) ($item["title"] ?? "Notification"));
                  $text = trim((string) ($item["text"] ?? ""));
              ?>
              <article class="developer-dashboard-notification-item">
                <span class="developer-notification-marker" aria-hidden="true"></span>
                <div>
                  <strong><?= h($title) ?></strong>
                  <p><?= h($text !== "" ? $text : "No extra detail was provided by this alert source.") ?></p>
                  <small><?= h($severity) ?> / <?= h($dashboardNotificationSourceFor((array) $item)) ?></small>
                </div>
              </article>
              <?php endforeach; ?>
              <?php if ($dashboardNotificationItems === []): ?>
              <p class="content-text mb-0">The Developer Panel did not detect any action items for this project.</p>
              <?php endif; ?>
              <a class="btn btn-outline btn-sm" href="<?= h((string) ($developerLinks["notifications"] ?? route("developer.panel.notifications"))) ?>">Open notification center</a>
            </div>
          </details>
        </section>

        <section class="developer-dashboard-section" aria-label="Environment status">
          <div class="developer-dashboard-section-head">
            <h2 class="developer-dashboard-section-title">Environment status</h2>
            <span class="developer-dashboard-refresh">Last checked: just now</span>
          </div>
          <div class="developer-dashboard-status-grid">
            <article class="developer-dashboard-status-card">
              <div class="developer-dashboard-card-head">
                <strong>Framework runtime</strong>
                <span class="developer-dashboard-ok">OK</span>
              </div>
              <h3>FNLLA <?= h((string) ($dashboard["framework_version"] ?? "unknown")) ?></h3>
              <p>Runtime <?= h((string) ($dashboard["runtime_version"] ?? "unknown")) ?></p>
              <p class="developer-dashboard-status is-active"><?= $frameworkLockReady ? "Framework lock present" : "Framework lock missing" ?></p>
            </article>

            <article class="developer-dashboard-status-card">
              <div class="developer-dashboard-card-head">
                <strong>Writable storage</strong>
                <span class="developer-dashboard-ok"><?= $allStorageReady ? "OK" : "Check" ?></span>
              </div>
              <ul class="developer-dashboard-check-list">
                <li>Storage: <?= $storageReady ? "OK" : "Needs attention" ?></li>
                <li>Sessions: <?= $sessionStorageReady ? "OK" : "Needs attention" ?></li>
                <li>Queue: <?= $queueStorageReady ? "OK" : "Needs attention" ?></li>
              </ul>
            </article>

            <article class="developer-dashboard-status-card">
              <div class="developer-dashboard-card-head">
                <strong>Observability</strong>
                <span class="developer-dashboard-ok">OK</span>
              </div>
              <ul class="developer-dashboard-check-list">
                <li><?= $observabilityEnabled ? "Metrics enabled" : "Metrics disabled" ?></li>
                <li>Health and acceptance checks active</li>
              </ul>
            </article>

            <article class="developer-dashboard-status-card">
              <div class="developer-dashboard-card-head">
                <strong>Service control</strong>
                <span class="developer-dashboard-ok"><?= ($developerControl["disabled"] ?? false) ? "Locked" : "OK" ?></span>
              </div>
              <ul class="developer-dashboard-check-list">
                <li><?= ($developerControl["disabled"] ?? false) ? "Public service disabled" : "Public service open" ?></li>
                <li><?= ($developerControl["remote_enabled"] ?? false) ? "Remote control contract enabled" : "Remote control contract disabled" ?></li>
              </ul>
            </article>
          </div>
        </section>

        <section class="developer-dashboard-section" aria-label="Management actions">
          <h2 class="developer-dashboard-section-title">Management</h2>
          <div class="developer-dashboard-management-list">
            <article class="developer-dashboard-management-row">
              <div>
                <strong>Maintenance access, preview lock and service disable</strong>
                <p>Prepare client preview access or disable the public service with a developer contact message.</p>
              </div>
              <a class="btn btn-ghost btn-sm" href="<?= h((string) ($developerLinks["project_settings"] ?? route("developer.panel.project_settings"))) ?>">Open preview</a>
              <span aria-hidden="true">-&gt;</span>
            </article>
            <article class="developer-dashboard-management-row">
              <div>
                <strong>Open the project in a clean browser tab</strong>
                <p>Use this after changing preview or identity settings.</p>
              </div>
              <a class="btn btn-ghost btn-sm" href="<?= h((string) ($developerLinks["home"] ?? route("home"))) ?>" target="_blank" rel="noopener noreferrer">Open public site</a>
              <span aria-hidden="true">-&gt;</span>
            </article>
            <article class="developer-dashboard-management-row">
              <div>
                <strong>Check runtime status</strong>
                <p>Health stays behind developer operations access once the project is configured.</p>
              </div>
              <a class="btn btn-ghost btn-sm" href="<?= h((string) ($developerLinks["health"] ?? route("developer.panel.health"))) ?>">Open health</a>
              <span aria-hidden="true">-&gt;</span>
            </article>
            <article class="developer-dashboard-management-row">
              <div>
                <strong>Review framework-managed drift</strong>
                <p>Use the update surface before pulling a newer FNLLA base into this application.</p>
              </div>
              <a class="btn btn-ghost btn-sm" href="<?= h((string) ($developerLinks["framework_updates"] ?? route("developer.panel.framework_updates"))) ?>">Open update</a>
              <span aria-hidden="true">-&gt;</span>
            </article>
            <article class="developer-dashboard-management-row">
              <div>
                <strong>Check notifications and analytics</strong>
                <p>Review actionable panel alerts and privacy-light traffic trends before release work.</p>
              </div>
              <a class="btn btn-ghost btn-sm" href="<?= h((string) ($developerLinks["notifications"] ?? route("developer.panel.notifications"))) ?>">Open alerts</a>
              <span aria-hidden="true">-&gt;</span>
            </article>
          </div>
        </section>

        <section class="developer-dashboard-section" aria-label="Recent developer activity">
          <div class="developer-dashboard-section-head">
            <h2 class="developer-dashboard-section-title">Developer activity</h2>
            <span class="developer-dashboard-refresh">Shared across all developer sessions</span>
          </div>
          <?php if ($developerActivity === []): ?>
          <p class="content-text">No developer-panel changes have been recorded yet.</p>
          <?php else: ?>
          <div class="developer-dashboard-activity-list">
            <?php foreach ($developerActivity as $event): ?>
            <article class="developer-dashboard-activity-row">
              <div>
                <strong><?= h((string) ($event["title"] ?? "Developer change")) ?></strong>
                <p><?= h((string) ($event["text"] ?? "")) ?></p>
              </div>
              <span><?= h((string) (($event["developer"]["email"] ?? "") ?: ($event["developer"]["name"] ?? "Developer"))) ?></span>
            </article>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </section>

        <section class="developer-dashboard-section" aria-label="At a glance">
          <h2 class="developer-dashboard-section-title">At a glance</h2>
          <div class="developer-dashboard-glance-table">
            <?php foreach ($atAGlance as $row): ?>
            <div class="developer-dashboard-glance-row">
              <strong><?= h((string) $row["label"]) ?></strong>
              <span><?= h((string) $row["value"]) ?></span>
            </div>
            <?php endforeach; ?>
          </div>
        </section>

<?php require __DIR__ . "/panel-footer.php"; ?>
