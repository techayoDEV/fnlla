<?php

declare(strict_types=1);

$developerPanelTitle = "Project Identity";
$developerPanelLead = "Project name, runtime posture, ownership and preview controls in one focused place.";
$projectName = (string) ($projectSettings["name"] ?? config("app.name", "FNLLA Project"));
$projectTagline = (string) ($projectSettings["tagline"] ?? "");
$projectUrl = (string) ($projectSettings["url"] ?? "");
$runtimeEnvironment = is_array($projectSettings["runtime_environment"] ?? null) ? (array) $projectSettings["runtime_environment"] : [];
$runtimeMode = (string) old("runtime_environment", (string) ($runtimeEnvironment["mode"] ?? (app_environment() === "production" ? "production" : "development")));
$runtimeMode = in_array($runtimeMode, ["development", "production"], true) ? $runtimeMode : "development";
$runtimeTrustedHostsValue = (string) old("runtime_trusted_hosts", (string) ($runtimeEnvironment["trusted_hosts_value"] ?? ""));
$runtimeCheckbox = static fn (string $key, bool $current): bool => (string) old($key, $current ? "1" : "0") === "1";
$runtimeDebugEnabled = $runtimeCheckbox("runtime_debug_enabled", (bool) ($runtimeEnvironment["debug_enabled"] ?? app_debug()));
$runtimeDebugToolbarEnabled = $runtimeCheckbox("runtime_debug_toolbar_enabled", (bool) ($runtimeEnvironment["debug_toolbar_enabled"] ?? config("debug.toolbar", false)));
$runtimeRequestHistoryEnabled = $runtimeCheckbox("runtime_request_history_enabled", (bool) ($runtimeEnvironment["request_history_enabled"] ?? config("debug.history.enabled", false)));
$runtimeProductionReady = (bool) ($runtimeEnvironment["production_ready"] ?? false);
$runtimeChecks = array_values((array) ($runtimeEnvironment["checks"] ?? []));
$projectLeadership = is_array($projectSettings["leadership"] ?? null) ? (array) $projectSettings["leadership"] : project_leadership("admin");
$projectLeadershipStatus = (string) ($projectLeadership["status"] ?? "pending");
$projectLeadershipVisibility = (string) ($projectLeadership["visibility"] ?? "disabled");
$projectLeadershipConfigured = (bool) ($projectLeadership["configured"] ?? false);
$projectLeadershipPublic = (bool) ($projectLeadership["public_visible"] ?? false);
$projectLeadershipManager = new \Fnlla\Php\Support\ProjectLeadership();
$projectLeadershipCanConfirm = $projectLeadershipManager->canConfirm($projectLeadership, (array) ($developerAccess["current_developer"] ?? []), (array) ($developerAccess["current_capabilities"] ?? []));
$projectLeadershipVisibilityPreview = match ($projectLeadershipVisibility) {
    "public" => $projectLeadershipPublic ? "Public visitors can see the confirmed responsibility record." : "Public visitors will not see this until the named person confirms it.",
    "admin" => "Only developer-panel users can see the responsibility record.",
    default => "Public visitors and developer-panel summaries treat leadership as disabled.",
};
$titlePreview = "Contact | " . $projectName . ($projectTagline !== "" ? " - " . $projectTagline : "");
$checklist = is_array($projectSetupChecklist ?? null) ? (array) $projectSetupChecklist : [];
$checklistItems = array_values((array) ($checklist["items"] ?? []));
$summary = (array) ($checklist["summary"] ?? []);
$readyCount = (int) ($checklist["ready_count"] ?? 0);
$totalCount = max(1, (int) ($checklist["total_count"] ?? count($checklistItems)));
$readyPercent = max(0, min(100, (int) round(($readyCount / $totalCount) * 100)));
$developerControl ??= [
    "disabled" => false,
    "local_disabled" => false,
    "remote_disabled" => false,
    "message" => (string) config("developer_control.disabled_message", ""),
    "contact" => (string) config("developer_control.disabled_contact", ""),
    "source" => "none",
    "remote_enabled" => false,
];
$maintenanceEnabled = (bool) ($maintenanceAccess["enabled"] ?? false);
$maintenanceConfigured = (bool) ($maintenanceAccess["configured"] ?? false);
$serviceDisabled = (bool) ($developerControl["disabled"] ?? false);
$serviceLocalDisabled = (bool) ($developerControl["local_disabled"] ?? (($developerControl["source"] ?? "") === "local" && $serviceDisabled));
$remoteEnabled = (bool) ($developerControl["remote_enabled"] ?? false);
$serviceStatus = (string) ($developerControl["status"] ?? ($serviceDisabled ? "disabled" : "open"));
$serviceReason = (string) ($developerControl["reason"] ?? "");
$serviceProvider = (string) ($developerControl["provider"] ?? "");
$serviceRemoteSuspended = $serviceDisabled && ($developerControl["source"] ?? "") === "remote" && $serviceStatus === "suspended";
$identitySections = [
    ["href" => "#developer-project-identity", "label" => "Identity", "text" => "Name, slogan and URL"],
    ["href" => "#runtime-environment", "label" => "Runtime", "text" => "Environment, debug and hosts"],
    ["href" => "#project-leadership", "label" => "Ownership", "text" => "Lead record and visibility"],
    ["href" => "#developer-access-preview", "label" => "Preview", "text" => "Client lock and service state"],
];
require __DIR__ . "/panel-header.php";
?>

        <section class="developer-dashboard-section" id="developer-project-identity" aria-label="Project identity settings">
          <div class="developer-panel-intro">
            <div class="developer-panel-intro-copy">
              <p class="feature-kicker">Workspace</p>
              <h2 class="developer-dashboard-section-title">Set the public identity, keep the build private, then hand it over cleanly.</h2>
              <p class="content-text mb-0">Start with the name and runtime posture. Add ownership and preview controls only when the project needs them.</p>
            </div>
          </div>

          <nav class="developer-project-identity-nav" aria-label="Project identity sections">
            <?php foreach ($identitySections as $section): ?>
            <a href="<?= h((string) $section["href"]) ?>">
              <strong><?= h((string) $section["label"]) ?></strong>
              <span><?= h((string) $section["text"]) ?></span>
            </a>
            <?php endforeach; ?>
          </nav>

          <div class="developer-dashboard-status-grid" id="developer-setup-checklist">
            <article class="developer-dashboard-status-card developer-setup-progress-card">
              <div class="developer-dashboard-card-head"><strong>Setup progress</strong><span class="developer-dashboard-ok"><?= h((string) $readyPercent) ?>%</span></div>
              <h3><?= h((string) $readyCount) ?> of <?= h((string) $totalCount) ?> ready</h3>
              <div class="developer-setup-progress" aria-label="Setup progress">
                <span style="width: <?= h((string) $readyPercent) ?>%;"></span>
              </div>
              <p>Review items can ship locally, but should be checked before client handover.</p>
            </article>
            <article class="developer-dashboard-status-card">
              <div class="developer-dashboard-card-head"><strong>Ready</strong><span class="developer-dashboard-ok"><?= h((string) ($summary["ready"] ?? 0)) ?></span></div>
              <h3>Configured</h3>
              <p>Core setup items already have usable values.</p>
            </article>
            <article class="developer-dashboard-status-card">
              <div class="developer-dashboard-card-head"><strong>Review</strong><span class="developer-dashboard-ok"><?= h((string) (($summary["review"] ?? 0) + ($summary["attention"] ?? 0))) ?></span></div>
              <h3>Needs a decision</h3>
              <p>These are usually security, preview or visibility choices.</p>
            </article>
            <article class="developer-dashboard-status-card">
              <div class="developer-dashboard-card-head"><strong>Optional</strong><span class="developer-dashboard-ok"><?= h((string) ($summary["optional"] ?? 0)) ?></span></div>
              <h3>Can stay empty</h3>
              <p>Optional project details should stay blank unless they help this client build.</p>
            </article>
          </div>

          <details class="developer-project-identity-drawer developer-project-identity-checklist-callout" open>
            <summary>
              <span>
                <strong>Release identity checklist</strong>
                <small><?= h((string) $readyCount) ?> of <?= h((string) $totalCount) ?> setup checks are ready before handover.</small>
              </span>
              <em>Review checklist</em>
            </summary>
          <div class="developer-setup-checklist-grid">
            <?php foreach ($checklistItems as $item): ?>
            <?php
            $status = (string) ($item["status"] ?? "review");
            $statusClass = in_array($status, ["ready", "review", "attention", "optional"], true) ? $status : "review";
            ?>
            <article class="developer-setup-check-card is-<?= h($statusClass) ?>">
              <div class="developer-dashboard-card-head">
                <strong><?= h((string) ($item["label"] ?? "Setup item")) ?></strong>
                <span class="developer-dashboard-ok"><?= h((string) ($item["status_label"] ?? ucfirst($statusClass))) ?></span>
              </div>
              <p><?= h((string) ($item["text"] ?? "")) ?></p>
              <?php if ((string) ($item["href"] ?? "") !== ""): ?>
              <a class="btn btn-outline btn-sm" href="<?= h((string) $item["href"]) ?>">Open setting</a>
              <?php endif; ?>
            </article>
            <?php endforeach; ?>
          </div>
          </details>

          <div class="developer-dashboard-section-head mt-3">
            <h2 class="developer-dashboard-section-title">Project identity</h2>
            <span class="developer-dashboard-refresh">Environment metadata</span>
          </div>

          <div class="developer-dashboard-status-grid">
            <article class="developer-dashboard-status-card">
              <div class="developer-dashboard-card-head"><strong>Project name</strong><span class="developer-dashboard-ok">ENV</span></div>
              <h3><?= h($projectName) ?></h3>
              <p>Main name used across operational screens.</p>
            </article>
            <article class="developer-dashboard-status-card">
              <div class="developer-dashboard-card-head"><strong>Project slogan</strong><span class="developer-dashboard-ok"><?= $projectTagline !== "" ? "SET" : "EMPTY" ?></span></div>
              <h3><?= h($projectTagline !== "" ? $projectTagline : "Not configured") ?></h3>
              <p>Optional browser-title suffix for public pages.</p>
            </article>
            <article class="developer-dashboard-status-card">
              <div class="developer-dashboard-card-head"><strong>Public URL</strong><span class="developer-dashboard-ok"><?= $projectUrl !== "" ? "SET" : "LOCAL" ?></span></div>
              <h3><?= h($projectUrl !== "" ? $projectUrl : "Local build") ?></h3>
              <p>Used for generated links once staging or production exists.</p>
            </article>
            <article class="developer-dashboard-status-card">
              <div class="developer-dashboard-card-head"><strong>Leadership</strong><span class="developer-dashboard-ok"><?= h(strtoupper($projectLeadershipStatus)) ?></span></div>
              <h3><?= $projectLeadershipConfigured ? h((string) ($projectLeadership["person_name"] ?? "Named lead")) : "Not configured" ?></h3>
              <p><?= $projectLeadershipPublic ? "Confirmed and available for public display." : "Private, pending or disabled by project visibility." ?></p>
            </article>
          </div>

          <div class="developer-panel-workbench-grid is-stacked">
            <article class="developer-panel-fieldset-card">
              <p class="feature-kicker">Live preview</p>
              <h2 class="content-title">How this will read</h2>
              <div class="developer-dashboard-glance-table">
                <div class="developer-dashboard-glance-row"><strong>Browser title</strong><span><?= h($titlePreview) ?></span></div>
                <div class="developer-dashboard-glance-row"><strong>Public base URL</strong><span><?= h($projectUrl !== "" ? $projectUrl : "Not set for local build") ?></span></div>
                <div class="developer-dashboard-glance-row"><strong>Panel label</strong><span><?= h($projectName) ?></span></div>
                <div class="developer-dashboard-glance-row"><strong>Leadership visibility</strong><span><?= h($projectLeadershipVisibilityPreview) ?></span></div>
              </div>
              <div class="developer-panel-status-note">
                <strong>Scope</strong>
                <span>This changes runtime identity and generated metadata only. It does not rename routes, database tables or project-owned copy.</span>
              </div>
            </article>

            <article class="developer-panel-fieldset-card">
              <p class="feature-kicker">Identity form</p>
              <h2 class="content-title">Save project identity</h2>
              <form class="form stack gap-md" action="<?= h(route("developer.settings.project")) ?>" method="post" novalidate>
                <?= csrf_field() ?>
                <div class="form-group">
                  <label class="label" for="developer-project-name">Project name</label>
                  <input class="input" id="developer-project-name" name="project_name" type="text" value="<?= h($projectName) ?>" autocomplete="organization" required maxlength="80">
                </div>
                <div class="form-group">
                  <label class="label" for="developer-project-tagline">Project slogan <span class="content-text">(optional)</span></label>
                  <input class="input" id="developer-project-tagline" name="project_tagline" type="text" value="<?= h($projectTagline) ?>" maxlength="120" placeholder="Business systems delivered clearly">
                  <p class="help-text">Example title: <?= h($titlePreview) ?>.</p>
                </div>
                <div class="form-group">
                  <label class="label" for="developer-project-url">Public URL <span class="content-text">(optional)</span></label>
                  <input class="input" id="developer-project-url" name="project_url" type="url" value="<?= h($projectUrl) ?>" inputmode="url" autocomplete="url" placeholder="https://example.com">
                  <p class="help-text">Leave blank on a purely local build. Use HTTPS once staging or production exists.</p>
                </div>
                <div class="d-flex flex-wrap gap-md">
                  <button class="btn btn-primary" type="submit">Save project identity</button>
                </div>
              </form>
            </article>
          </div>
        </section>

        <section class="developer-dashboard-section" id="runtime-environment" aria-label="Runtime environment">
          <div class="developer-dashboard-section-head">
            <h2 class="developer-dashboard-section-title">Runtime environment</h2>
            <span class="developer-dashboard-refresh">APP_ENV and diagnostics</span>
          </div>

          <div class="developer-dashboard-status-grid">
            <article class="developer-dashboard-status-card">
              <div class="developer-dashboard-card-head"><strong>Mode</strong><span class="developer-dashboard-ok"><?= h(strtoupper((string) ($runtimeEnvironment["mode"] ?? $runtimeMode))) ?></span></div>
              <h3><?= h((string) ($runtimeEnvironment["label"] ?? ucfirst($runtimeMode))) ?></h3>
              <p><?= ((string) ($runtimeEnvironment["mode"] ?? $runtimeMode)) === "production" ? "Setup UI is closed and production guards apply." : "Local setup and developer diagnostics can stay available." ?></p>
            </article>
            <article class="developer-dashboard-status-card">
              <div class="developer-dashboard-card-head"><strong>Debug exposure</strong><span class="developer-dashboard-ok"><?= (bool) ($runtimeEnvironment["diagnostics_safe"] ?? false) ? "OFF" : "ON" ?></span></div>
              <h3><?= (bool) ($runtimeEnvironment["debug_enabled"] ?? false) ? "APP_DEBUG on" : "APP_DEBUG off" ?></h3>
              <p>Production save forces APP_DEBUG, toolbar and request history off.</p>
            </article>
            <article class="developer-dashboard-status-card">
              <div class="developer-dashboard-card-head"><strong>Trusted hosts</strong><span class="developer-dashboard-ok"><?= h((string) count((array) ($runtimeEnvironment["trusted_hosts"] ?? []))) ?></span></div>
              <h3><?= ((array) ($runtimeEnvironment["trusted_hosts"] ?? [])) !== [] ? "Pinned host boundary" : "Not configured" ?></h3>
              <p><?= ((array) ($runtimeEnvironment["trusted_hosts"] ?? [])) !== [] ? h(implode(", ", (array) ($runtimeEnvironment["trusted_hosts"] ?? []))) : "Set this before a real production deployment." ?></p>
            </article>
            <article class="developer-dashboard-status-card">
              <div class="developer-dashboard-card-head"><strong>Production readiness</strong><span class="developer-dashboard-ok"><?= $runtimeProductionReady ? "READY" : "REVIEW" ?></span></div>
              <h3><?= $runtimeProductionReady ? "Runtime switches align" : "Review required" ?></h3>
              <ul class="developer-dashboard-check-list">
                <?php foreach ($runtimeChecks as $check): ?>
                <li><?= ($check["ready"] ?? false) ? "OK" : "Review" ?>: <?= h((string) ($check["label"] ?? "Check")) ?> - <?= h((string) ($check["value"] ?? "")) ?></li>
                <?php endforeach; ?>
              </ul>
            </article>
          </div>

          <div class="developer-panel-form-grid developer-runtime-environment-grid is-stacked">
            <article class="developer-panel-fieldset-card">
              <p class="feature-kicker">Runtime mode</p>
              <h2 class="content-title">Switch environment</h2>
              <form class="form stack gap-md" action="<?= h(route("developer.settings.runtime_environment")) ?>" method="post" novalidate>
                <?= csrf_field() ?>
                <div class="developer-runtime-mode-options" role="radiogroup" aria-label="Runtime environment mode">
                  <label class="developer-runtime-mode-option">
                    <input type="radio" name="runtime_environment" value="development" <?= $runtimeMode === "development" ? "checked" : "" ?>>
                    <strong>Development</strong>
                    <span>Local setup, detailed errors and optional debug tools.</span>
                  </label>
                  <label class="developer-runtime-mode-option">
                    <input type="radio" name="runtime_environment" value="production" <?= $runtimeMode === "production" ? "checked" : "" ?>>
                    <strong>Production</strong>
                    <span>Public runtime posture with diagnostics forced off.</span>
                  </label>
                </div>
                <div class="form-group">
                  <label class="label" for="runtime-trusted-hosts">Trusted hosts <span class="content-text">(comma separated)</span></label>
                  <input class="input" id="runtime-trusted-hosts" name="runtime_trusted_hosts" type="text" value="<?= h($runtimeTrustedHostsValue) ?>" maxlength="512" placeholder="fnlla.com,www.fnlla.com">
                  <p class="help-text">Use host names without paths. Wildcard subdomains such as <code>*.example.com</code> are supported.</p>
                </div>
                <div class="developer-runtime-switch-grid">
                  <label class="developer-analytics-toggle">
                    <input type="checkbox" name="runtime_debug_enabled" value="1" <?= $runtimeDebugEnabled ? "checked" : "" ?>>
                    <span><strong>APP_DEBUG</strong><small>Detailed error output for development only.</small></span>
                  </label>
                  <label class="developer-analytics-toggle">
                    <input type="checkbox" name="runtime_debug_toolbar_enabled" value="1" <?= $runtimeDebugToolbarEnabled ? "checked" : "" ?>>
                    <span><strong>Debug toolbar</strong><small>Developer-only toolbar when debug mode is available.</small></span>
                  </label>
                  <label class="developer-analytics-toggle">
                    <input type="checkbox" name="runtime_request_history_enabled" value="1" <?= $runtimeRequestHistoryEnabled ? "checked" : "" ?>>
                    <span><strong>Request history</strong><small>Private aggregate request timing during local diagnostics.</small></span>
                  </label>
                </div>
                <button class="btn btn-primary" type="submit">Save runtime environment</button>
              </form>
            </article>

            <article class="developer-panel-fieldset-card">
              <p class="feature-kicker">What changes</p>
              <h2 class="content-title">Environment file writes</h2>
              <div class="developer-dashboard-glance-table">
                <div class="developer-dashboard-glance-row"><strong>APP_ENV</strong><span><?= h((string) ($runtimeEnvironment["environment"] ?? app_environment())) ?></span></div>
                <div class="developer-dashboard-glance-row"><strong>APP_DEBUG</strong><span><?= (bool) ($runtimeEnvironment["debug_enabled"] ?? false) ? "true" : "false" ?></span></div>
                <div class="developer-dashboard-glance-row"><strong>DEBUG_TOOLBAR</strong><span><?= (bool) ($runtimeEnvironment["debug_toolbar_enabled"] ?? false) ? "true" : "false" ?></span></div>
                <div class="developer-dashboard-glance-row"><strong>DEBUG_REQUEST_HISTORY</strong><span><?= (bool) ($runtimeEnvironment["request_history_enabled"] ?? false) ? "true" : "false" ?></span></div>
                <div class="developer-dashboard-glance-row"><strong>TRUSTED_HOSTS</strong><span><?= h((string) ($runtimeEnvironment["trusted_hosts_value"] ?? "")) ?></span></div>
              </div>
              <div class="developer-panel-status-note">
                <strong>Production note</strong>
                <span>Switching to production is real: setup screens are no longer available to public visitors, debug output is off and production readiness checks become stricter.</span>
              </div>
            </article>
          </div>
        </section>

        <section class="developer-dashboard-section" id="project-leadership" aria-label="Project leadership">
          <div class="developer-dashboard-section-head">
            <h2 class="developer-dashboard-section-title">Project leadership</h2>
            <span class="developer-dashboard-refresh">Optional responsibility block</span>
          </div>
          <div class="developer-panel-workbench-grid is-stacked">
            <article class="developer-panel-fieldset-card">
              <p class="feature-kicker">Leadership form</p>
              <h2 class="content-title">Save responsibility details</h2>
              <div class="developer-panel-status-note">
                <strong>Visibility preview</strong>
                <span><?= h($projectLeadershipVisibilityPreview) ?></span>
              </div>
              <form class="form stack gap-md" action="<?= h((string) ($developerLinks["project_leadership"] ?? route("developer.settings.project_leadership"))) ?>" method="post" novalidate>
                <?= csrf_field() ?>
                <div class="form-group">
                  <label class="label" for="project-leadership-organization">Delivery organisation</label>
                  <input class="input" id="project-leadership-organization" name="project_leadership_organization" type="text" value="<?= h((string) ($projectLeadership["organization"] ?? "")) ?>" maxlength="120" placeholder="TechAyo Limited">
                </div>
                <div class="form-group">
                  <label class="label" for="project-leadership-person-name">Responsible person</label>
                  <input class="input" id="project-leadership-person-name" name="project_leadership_person_name" type="text" value="<?= h((string) ($projectLeadership["person_name"] ?? "")) ?>" maxlength="120" autocomplete="name" placeholder="Name Surname">
                </div>
                <div class="form-group">
                  <label class="label" for="project-leadership-person-email">Confirmation email</label>
                  <input class="input" id="project-leadership-person-email" name="project_leadership_person_email" type="email" value="<?= h((string) ($projectLeadership["person_email"] ?? "")) ?>" maxlength="160" autocomplete="email" placeholder="lead@example.com">
                  <p class="help-text">The named person can confirm with this developer email. Lead developers can also approve the responsibility record.</p>
                </div>
                <div class="form-group">
                  <label class="label" for="project-leadership-person-role">Role or position</label>
                  <input class="input" id="project-leadership-person-role" name="project_leadership_person_role" type="text" value="<?= h((string) ($projectLeadership["person_role"] ?? "")) ?>" maxlength="120" placeholder="Director of TechAyo">
                </div>
                <div class="form-group">
                  <label class="label" for="project-leadership-responsibility">Responsibility scope</label>
                  <input class="input" id="project-leadership-responsibility" name="project_leadership_responsibility" type="text" value="<?= h((string) ($projectLeadership["responsibility"] ?? "")) ?>" maxlength="240" placeholder="product direction, roadmap and technical delivery">
                </div>
                <div class="form-group">
                  <label class="label" for="project-leadership-profile-url">Profile or contact URL <span class="content-text">(optional)</span></label>
                  <input class="input" id="project-leadership-profile-url" name="project_leadership_profile_url" type="url" value="<?= h((string) ($projectLeadership["profile_url"] ?? "")) ?>" maxlength="2048" inputmode="url" placeholder="https://example.com/contact">
                </div>
                <div class="form-group">
                  <label class="label" for="project-leadership-visibility">Visibility</label>
                  <select class="select" id="project-leadership-visibility" name="project_leadership_visibility">
                    <?php foreach (["admin" => "Private panel and documentation", "public" => "Public after confirmation", "disabled" => "Disabled"] as $value => $label): ?>
                    <option value="<?= h($value) ?>" <?= $projectLeadershipVisibility === $value ? "selected" : "" ?>><?= h($label) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <p class="help-text">Client systems can keep this private without showing TechAyo or named-lead information on the public website.</p>
                </div>
                <div class="d-flex flex-wrap gap-md">
                  <button class="btn btn-primary" type="submit">Save leadership details</button>
                </div>
              </form>
            </article>

            <article class="developer-panel-fieldset-card">
              <p class="feature-kicker">System information</p>
              <h2 class="content-title">Responsibility record</h2>
              <p class="content-text">Use this for the real person responsible for product direction, roadmap or technical delivery. Public display requires confirmation by the named person.</p>
              <?php
              $projectLeadershipContext = "admin";
              $projectLeadershipActions = true;
              $projectLeadershipConfirmationRoute = (string) ($developerLinks["project_leadership_confirmation"] ?? route("developer.settings.project_leadership.confirmation"));
              require VIEW_ROOT . "/partials/project-leadership.php";
              ?>
              <?php if (!$projectLeadershipConfigured): ?>
              <div class="developer-panel-status-note">
                <strong>No named lead yet</strong>
                <span>Add the responsible person below. Client projects can keep this admin-only or disabled for the public site.</span>
              </div>
              <?php endif; ?>
            </article>
          </div>
        </section>

        <section class="developer-dashboard-section" id="developer-access-preview" aria-label="Access and preview settings">
          <div class="developer-dashboard-section-head">
            <h2 class="developer-dashboard-section-title">Access & preview</h2>
            <span class="developer-dashboard-refresh">Client preview and public-service controls</span>
          </div>

          <div class="developer-dashboard-status-grid">
            <article class="developer-dashboard-status-card">
              <div class="developer-dashboard-card-head"><strong>Preview mode</strong><span class="developer-dashboard-ok"><?= $maintenanceEnabled ? "LOCKED" : "OPEN" ?></span></div>
              <h3><?= $maintenanceEnabled ? "Password required" : "Public routes open" ?></h3>
              <p><?= $maintenanceEnabled
                  ? "Maintenance mode is currently on. Public routes require the preview password."
                  : "Maintenance mode is currently off. Save a password here when you want to prepare a private preview lock." ?></p>
            </article>
            <article class="developer-dashboard-status-card">
              <div class="developer-dashboard-card-head"><strong>Password state</strong><span class="developer-dashboard-ok"><?= $maintenanceConfigured ? "READY" : "MISSING" ?></span></div>
              <h3><?= $maintenanceConfigured ? "Prepared" : "Not prepared" ?></h3>
              <p>Rotate it here before sharing a private build.</p>
            </article>
            <article class="developer-dashboard-status-card">
              <div class="developer-dashboard-card-head"><strong>Service control</strong><span class="developer-dashboard-ok"><?= $serviceDisabled ? "STOPPED" : "OPEN" ?></span></div>
              <h3><?= $serviceRemoteSuspended ? "Suspended by service provider" : ($serviceDisabled ? "Public service disabled" : "Public service available") ?></h3>
              <p>Source: <?= h((string) ($developerControl["source"] ?? "none")) ?><?= $serviceReason !== "" ? ". Reason: " . h($serviceReason) : "" ?><?= $serviceProvider !== "" ? ". Provider: " . h($serviceProvider) : "" ?>.</p>
            </article>
            <article class="developer-dashboard-status-card">
              <div class="developer-dashboard-card-head"><strong>Remote contract</strong><span class="developer-dashboard-ok"><?= $remoteEnabled ? "ON" : "OFF" ?></span></div>
              <h3><?= $remoteEnabled ? "Remote control enabled" : "Local control only" ?></h3>
              <p>Configure the adapter from Integrations.</p>
            </article>
          </div>

          <div class="developer-panel-form-grid is-stacked">
            <article class="developer-panel-fieldset-card">
              <p class="feature-kicker">Service control</p>
              <h2 class="content-title">Save service control</h2>
              <p class="content-text"><?= $serviceRemoteSuspended
                  ? "A remote provider suspension is active. Local controls can clear only the project-owned lock; the provider state must be changed in the external control plane."
                  : "Use this when the website should be stopped immediately with a developer-owned message while the private developer panel remains available." ?></p>
              <form class="form stack gap-md" action="<?= h(route("developer.settings.service_control")) ?>" method="post" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="developer_control_disabled" value="0">
                <div class="form-group">
                  <label class="label" for="developer-control-disabled">
                    <input id="developer-control-disabled" name="developer_control_disabled" type="checkbox" value="1" <?= $serviceLocalDisabled ? "checked" : "" ?>>
                    <?= $serviceLocalDisabled ? "Keep local public-service lock enabled" : "Enable local public-service lock after saving" ?>
                  </label>
                  <p class="help-text">Current source: <?= h((string) ($developerControl["source"] ?? "none")) ?>. Status: <?= h($serviceStatus) ?><?= $serviceReason !== "" ? ", reason: " . h($serviceReason) : "" ?>. Remote control is <?= $remoteEnabled ? "enabled" : "disabled" ?>.</p>
                </div>
                <div class="form-group">
                  <label class="label" for="developer-control-message">Public message</label>
                  <textarea class="textarea" id="developer-control-message" name="developer_control_message" rows="3"><?= h((string) ($developerControl["message"] ?? config("developer_control.disabled_message", ""))) ?></textarea>
                </div>
                <div class="form-group">
                  <label class="label" for="developer-control-contact">Developer contact</label>
                  <input class="input" id="developer-control-contact" name="developer_control_contact" type="text" value="<?= h((string) ($developerControl["contact"] ?? config("developer_control.disabled_contact", ""))) ?>">
                </div>
                <button class="btn btn-primary" type="submit">Save service control</button>
              </form>
            </article>

            <article class="developer-panel-fieldset-card">
              <p class="feature-kicker">Maintenance access</p>
              <h2 class="content-title">Save maintenance settings</h2>
              <form class="form stack gap-md" action="<?= h(route("developer.settings.maintenance")) ?>" method="post" novalidate>
                <?= csrf_field() ?>
                <div class="form-group">
                  <label class="label" for="maintenance-access-password">Password</label>
                  <div class="password-field">
                    <input class="input" id="maintenance-access-password" name="maintenance_access_password" type="password" autocomplete="new-password" required>
                    <button class="password-toggle" type="button" data-fnlla-password-toggle data-fnlla-password-target="#maintenance-access-password" aria-label="Toggle password visibility">Show</button>
                  </div>
                </div>
                <div class="form-group">
                  <label class="label" for="maintenance-access-password-confirmation">Confirm password</label>
                  <div class="password-field">
                    <input class="input" id="maintenance-access-password-confirmation" name="maintenance_access_password_confirmation" type="password" autocomplete="new-password" required>
                    <button class="password-toggle" type="button" data-fnlla-password-toggle data-fnlla-password-target="#maintenance-access-password-confirmation" aria-label="Toggle password visibility">Show</button>
                  </div>
                </div>
                <input type="hidden" name="maintenance_access_enabled" value="0">
                <div class="form-group">
                  <label class="label" for="maintenance-access-enabled">
                    <input id="maintenance-access-enabled" name="maintenance_access_enabled" type="checkbox" value="1" <?= $maintenanceEnabled ? "checked" : "" ?>>
                    <?= $maintenanceEnabled ? "Keep maintenance mode enabled after saving" : "Enable maintenance mode after saving" ?>
                  </label>
                  <p class="help-text">Leave this unchecked to store or rotate the maintenance password without locking the public routes yet.</p>
                </div>
                <button class="btn btn-primary" type="submit">Save maintenance settings</button>
              </form>
            </article>
          </div>
        </section>

<?php require __DIR__ . "/panel-footer.php"; ?>
