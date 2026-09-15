<?php

declare(strict_types=1);

$projectIdentitySection = (string) ($projectIdentitySection ?? "overview");
$projectIdentitySection = in_array($projectIdentitySection, ["overview", "identity", "runtime", "leadership", "access"], true) ? $projectIdentitySection : "overview";
$projectIdentityMeta = [
    "overview" => ["title" => "Project identity: Overview", "lead" => "Project setup status, handover checklist and identity workflow overview."],
    "identity" => ["title" => "Project identity: Identity", "lead" => "Public name, browser-title slogan and generated project URL metadata."],
    "runtime" => ["title" => "Project identity: Runtime", "lead" => "Development or production posture, debug switches and trusted host boundary."],
    "leadership" => ["title" => "Project identity: Leadership", "lead" => "Optional responsibility record, confirmation state and public visibility."],
    "access" => ["title" => "Project identity: Access & preview", "lead" => "Control private client preview and emergency public-service state from one place."],
];
$developerPanelTitle = (string) $projectIdentityMeta[$projectIdentitySection]["title"];
$developerPanelLead = (string) $projectIdentityMeta[$projectIdentitySection]["lead"];
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
$runtimeProductionSelected = $runtimeMode === "production";
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
    "contact_url" => (string) config("developer_control.disabled_contact_url", ""),
    "contact_phone" => (string) config("developer_control.disabled_contact_phone", ""),
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
$serviceContactUrl = (string) ($developerControl["contact_url"] ?? config("developer_control.disabled_contact_url", ""));
$serviceContactPhone = (string) ($developerControl["contact_phone"] ?? config("developer_control.disabled_contact_phone", ""));
$serviceMaintenanceActive = $maintenanceEnabled && !$serviceDisabled;
if ($serviceMaintenanceActive) {
    $serviceStatus = "paused";
    $serviceReason = "maintenance";
}
$serviceControlActive = $serviceDisabled || $serviceMaintenanceActive;
$serviceRemoteSuspended = $serviceDisabled && ($developerControl["source"] ?? "") === "remote" && $serviceStatus === "suspended";
$serviceLocalScenario = $serviceMaintenanceActive ? "maintenance" : "open";
if ($serviceLocalDisabled) {
    $serviceLocalScenario = match ($serviceReason) {
        "maintenance" => "maintenance",
        "payment_overdue", "billing" => "suspended_billing",
        "contract_review" => "suspended_contract",
        "security_review" => "security_review",
        default => $serviceStatus === "suspended" ? "suspended_billing" : "disabled",
    };
}
$serviceControlScenario = (string) old("developer_control_status", $serviceLocalScenario);
$serviceStatusBadge = $serviceStatus === "suspended" ? "SUSPENDED" : ($serviceControlActive ? strtoupper($serviceStatus) : "OPEN");
$serviceSourceLabel = $serviceMaintenanceActive ? "maintenance" : (string) ($developerControl["source"] ?? "none");
$serviceControlScenarios = [
    "open" => [
        "label" => "Open public service",
        "text" => "Public routes are available.",
        "fields" => "open",
        "default_message" => "",
        "message_label" => "Public message override",
        "message_placeholder" => "No public notice is shown while the service is open.",
        "contact_heading" => "Support contact",
        "contact_help" => "Contact fields are not shown while the public service is open.",
    ],
    "disabled" => [
        "label" => "Paused by developer",
        "text" => "Developer-owned pause with a public support message.",
        "fields" => "notice",
        "default_message" => "This service is temporarily paused by the developer team. Please contact the project developer for assistance.",
        "message_label" => "Pause message",
        "message_placeholder" => "Explain why the public service is paused.",
        "contact_heading" => "Pause support contact",
        "contact_help" => "Shown on the public pause notice below the message.",
    ],
    "maintenance" => [
        "label" => "Maintenance mode",
        "text" => "Planned service pause for maintenance work.",
        "fields" => "maintenance",
        "default_message" => "",
        "message_label" => "Maintenance message",
        "message_placeholder" => "Maintenance uses the password access screen instead of the public service-control notice.",
        "contact_heading" => "Maintenance access",
        "contact_help" => "Set or keep the password used by public visitors during maintenance mode.",
    ],
    "suspended_billing" => [
        "label" => "Suspended - payment overdue",
        "text" => "Payment or billing suspension notice.",
        "fields" => "notice",
        "default_message" => "This service has been suspended because payment is overdue. Please contact the service provider to restore access.",
        "message_label" => "Billing suspension message",
        "message_placeholder" => "Explain the payment-overdue suspension.",
        "contact_heading" => "Billing support contact",
        "contact_help" => "Shown on the public suspension notice before the email address.",
    ],
    "suspended_contract" => [
        "label" => "Suspended - contract issue",
        "text" => "Contract or service agreement suspension notice.",
        "fields" => "notice",
        "default_message" => "This service has been suspended while the service contract is reviewed. Please contact the service provider.",
        "message_label" => "Contract suspension message",
        "message_placeholder" => "Explain the contract-review suspension.",
        "contact_heading" => "Contract support contact",
        "contact_help" => "Shown on the public suspension notice before the email address.",
    ],
    "security_review" => [
        "label" => "Paused - security review",
        "text" => "Security-review pause while access is checked.",
        "fields" => "notice",
        "default_message" => "This service is temporarily paused while a security review is completed. Please contact the project developer for assistance.",
        "message_label" => "Security review message",
        "message_placeholder" => "Explain the temporary security-review pause.",
        "contact_heading" => "Security support contact",
        "contact_help" => "Shown on the public security-review notice below the message.",
    ],
];
$serviceSelectedScenario = (array) ($serviceControlScenarios[$serviceControlScenario] ?? $serviceControlScenarios["open"]);
$serviceSelectedFields = (string) ($serviceSelectedScenario["fields"] ?? "notice");
$serviceStatusTone = $serviceStatus === "suspended" ? "suspended" : ($serviceControlActive ? "stopped" : "open");
$previewStatusTone = $maintenanceEnabled ? "locked" : "open";
$serviceControlPublicTest = (array) flash("service_control_public_test", []);
require __DIR__ . "/panel-header.php";
?>

        <?php if (in_array($projectIdentitySection, ["overview", "identity"], true)): ?>
        <section class="developer-dashboard-section" id="developer-project-identity" aria-label="Project identity settings">
          <?php if ($projectIdentitySection === "overview"): ?>
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

          <?php endif; ?>
          <?php if ($projectIdentitySection === "identity"): ?>
          <div class="developer-dashboard-section-head mt-3">
            <h2 class="dashboard-section-title">Project identity</h2>
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
          <?php endif; ?>
        </section>
        <?php endif; ?>

        <?php if ($projectIdentitySection === "runtime"): ?>
        <section class="developer-dashboard-section" id="runtime-environment" aria-label="Runtime environment">
          <div class="developer-dashboard-section-head">
            <h2 class="dashboard-section-title">Runtime environment</h2>
            <span class="developer-dashboard-refresh">APP_ENV and diagnostics</span>
          </div>

          <div class="developer-dashboard-status-grid">
            <article class="developer-dashboard-status-card">
              <div class="developer-dashboard-card-head"><strong>Mode</strong><span class="developer-dashboard-ok is-<?= $runtimeMode === "production" ? "production" : "development" ?>"><?= h(strtoupper((string) ($runtimeEnvironment["mode"] ?? $runtimeMode))) ?></span></div>
              <h3><?= h((string) ($runtimeEnvironment["label"] ?? ucfirst($runtimeMode))) ?></h3>
              <p><?= ((string) ($runtimeEnvironment["mode"] ?? $runtimeMode)) === "production" ? "Setup UI is closed and production guards apply." : "Local setup and developer diagnostics can stay available." ?></p>
            </article>
            <article class="developer-dashboard-status-card">
              <div class="developer-dashboard-card-head"><strong>Debug exposure</strong><span class="developer-dashboard-ok <?= (bool) ($runtimeEnvironment["diagnostics_safe"] ?? false) ? "is-debug-off" : "is-debug-on" ?>"><?= (bool) ($runtimeEnvironment["diagnostics_safe"] ?? false) ? "OFF" : "ON" ?></span></div>
              <h3><?= (bool) ($runtimeEnvironment["debug_enabled"] ?? false) ? "APP_DEBUG on" : "APP_DEBUG off" ?></h3>
              <p>Production save forces APP_DEBUG, toolbar and request history off.</p>
            </article>
            <article class="developer-dashboard-status-card">
              <div class="developer-dashboard-card-head"><strong>Trusted hosts</strong><span class="developer-dashboard-ok <?= ((array) ($runtimeEnvironment["trusted_hosts"] ?? [])) !== [] ? "is-ready" : "is-review" ?>"><?= h((string) count((array) ($runtimeEnvironment["trusted_hosts"] ?? []))) ?></span></div>
              <h3><?= ((array) ($runtimeEnvironment["trusted_hosts"] ?? [])) !== [] ? "Pinned host boundary" : "Not configured" ?></h3>
              <p><?= ((array) ($runtimeEnvironment["trusted_hosts"] ?? [])) !== [] ? h(implode(", ", (array) ($runtimeEnvironment["trusted_hosts"] ?? []))) : "Set this before a real production deployment." ?></p>
            </article>
            <article class="developer-dashboard-status-card">
              <div class="developer-dashboard-card-head"><strong>Production readiness</strong><span class="developer-dashboard-ok <?= $runtimeProductionReady ? "is-ready" : "is-review" ?>"><?= $runtimeProductionReady ? "READY" : "REVIEW" ?></span></div>
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
              <form class="form stack gap-md" action="<?= h(route("developer.settings.runtime_environment")) ?>" method="post" novalidate data-developer-runtime-form>
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
                  <label class="developer-analytics-toggle <?= $runtimeProductionSelected ? "is-forced-off" : "" ?>" data-developer-runtime-diagnostic>
                    <input type="checkbox" name="runtime_debug_enabled" value="1" <?= !$runtimeProductionSelected && $runtimeDebugEnabled ? "checked" : "" ?> <?= $runtimeProductionSelected ? "disabled" : "" ?>>
                    <span><strong>APP_DEBUG</strong><small><?= $runtimeProductionSelected ? "Forced off while Production is selected." : "Detailed error output for development only." ?></small></span>
                  </label>
                  <label class="developer-analytics-toggle <?= $runtimeProductionSelected ? "is-forced-off" : "" ?>" data-developer-runtime-diagnostic>
                    <input type="checkbox" name="runtime_debug_toolbar_enabled" value="1" <?= !$runtimeProductionSelected && $runtimeDebugToolbarEnabled ? "checked" : "" ?> <?= $runtimeProductionSelected ? "disabled" : "" ?>>
                    <span><strong>Debug toolbar</strong><small><?= $runtimeProductionSelected ? "Forced off while Production is selected." : "Developer-only toolbar when debug mode is available." ?></small></span>
                  </label>
                  <label class="developer-analytics-toggle <?= $runtimeProductionSelected ? "is-forced-off" : "" ?>" data-developer-runtime-diagnostic>
                    <input type="checkbox" name="runtime_request_history_enabled" value="1" <?= !$runtimeProductionSelected && $runtimeRequestHistoryEnabled ? "checked" : "" ?> <?= $runtimeProductionSelected ? "disabled" : "" ?>>
                    <span><strong>Request history</strong><small><?= $runtimeProductionSelected ? "Forced off while Production is selected." : "Private aggregate request timing during local diagnostics." ?></small></span>
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
        <?php endif; ?>

        <?php if ($projectIdentitySection === "leadership"): ?>
        <section class="developer-dashboard-section" id="project-leadership" aria-label="Project leadership">
          <div class="developer-dashboard-section-head">
            <h2 class="dashboard-section-title">Project leadership</h2>
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
                  <input class="input" id="project-leadership-organization" name="project_leadership_organization" type="text" value="<?= h((string) ($projectLeadership["organization"] ?? "")) ?>" maxlength="120" placeholder="e.g. FNLLA Company">
                </div>
                <div class="form-group">
                  <label class="label" for="project-leadership-person-name">Responsible person</label>
                  <input class="input" id="project-leadership-person-name" name="project_leadership_person_name" type="text" value="<?= h((string) ($projectLeadership["person_name"] ?? "")) ?>" maxlength="120" autocomplete="name" placeholder="e.g. Lead Developer Name">
                </div>
                <div class="form-group">
                  <label class="label" for="project-leadership-person-email">Confirmation email</label>
                  <input class="input" id="project-leadership-person-email" name="project_leadership_person_email" type="email" value="<?= h((string) ($projectLeadership["person_email"] ?? "")) ?>" maxlength="160" autocomplete="email" placeholder="e.g. leader@example.com">
                  <p class="help-text">The named person can confirm with this developer email. Lead developers can also approve the responsibility record.</p>
                </div>
                <div class="form-group">
                  <label class="label" for="project-leadership-person-role">Role or position <span class="content-text">(optional)</span></label>
                  <input class="input" id="project-leadership-person-role" name="project_leadership_person_role" type="text" value="<?= h((string) ($projectLeadership["person_role"] ?? "")) ?>" maxlength="120" placeholder="e.g. Project Manager">
                </div>
                <div class="form-group">
                  <label class="label" for="project-leadership-responsibility">Responsibility scope <span class="content-text">(optional)</span></label>
                  <input class="input" id="project-leadership-responsibility" name="project_leadership_responsibility" type="text" value="<?= h((string) ($projectLeadership["responsibility"] ?? "")) ?>" maxlength="240" placeholder="e.g. product direction, roadmap and technical delivery">
                </div>
                <div class="form-group">
                  <label class="label" for="project-leadership-profile-url">Profile or contact URL <span class="content-text">(optional)</span></label>
                  <input class="input" id="project-leadership-profile-url" name="project_leadership_profile_url" type="url" value="<?= h((string) ($projectLeadership["profile_url"] ?? "")) ?>" maxlength="2048" inputmode="url" placeholder="e.g. https://example.com/contact">
                </div>
                <div class="form-group">
                  <label class="label" for="project-leadership-visibility">Visibility</label>
                  <select class="select" id="project-leadership-visibility" name="project_leadership_visibility">
                    <?php foreach (["admin" => "Developer panel and documentation", "public" => "Public after confirmation", "disabled" => "Disabled"] as $value => $label): ?>
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
        <?php endif; ?>

        <?php if ($projectIdentitySection === "access"): ?>
        <section class="developer-dashboard-section" id="developer-access-preview" aria-label="Preview and service settings">
          <div class="developer-dashboard-section-head">
            <h2 class="dashboard-section-title">Preview and service state</h2>
            <span class="developer-dashboard-refresh">Client preview and public-service status</span>
          </div>

          <div class="developer-dashboard-status-grid">
            <article class="developer-dashboard-status-card">
              <div class="developer-dashboard-card-head"><strong>Preview mode</strong><span class="developer-dashboard-ok is-<?= h($previewStatusTone) ?>"><?= $maintenanceEnabled ? "LOCKED" : "OPEN" ?></span></div>
              <h3><?= $maintenanceEnabled ? "Password required" : "Public routes open" ?></h3>
              <p><?= $maintenanceEnabled
                  ? "Maintenance mode is currently on. Public routes require the preview password."
                  : "Maintenance mode is currently off. Save a password here when you want to prepare a private preview lock." ?></p>
            </article>
            <article class="developer-dashboard-status-card">
              <div class="developer-dashboard-card-head"><strong>Password state</strong><span class="developer-dashboard-ok <?= $maintenanceConfigured ? "is-ready" : "is-review" ?>"><?= $maintenanceConfigured ? "READY" : "MISSING" ?></span></div>
              <h3><?= $maintenanceConfigured ? "Prepared" : "Not prepared" ?></h3>
              <p>Rotate it here before sharing a private build.</p>
            </article>
            <article class="developer-dashboard-status-card">
              <div class="developer-dashboard-card-head"><strong>Service control</strong><span class="developer-dashboard-ok is-<?= h($serviceStatusTone) ?>"><?= h($serviceStatusBadge) ?></span></div>
              <h3><?= $serviceRemoteSuspended ? "Suspended by service provider" : ($serviceMaintenanceActive ? "Maintenance access active" : ($serviceDisabled ? "Public service paused" : "Public service available")) ?></h3>
              <p>Source: <?= h($serviceSourceLabel) ?><?= $serviceReason !== "" ? ". Reason: " . h($serviceReason) : "" ?><?= $serviceProvider !== "" ? ". Provider: " . h($serviceProvider) : "" ?>.</p>
            </article>
            <article class="developer-dashboard-status-card">
              <div class="developer-dashboard-card-head"><strong>Remote contract</strong><span class="developer-dashboard-ok <?= $remoteEnabled ? "is-ready" : "is-neutral" ?>"><?= $remoteEnabled ? "ON" : "OFF" ?></span></div>
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
                  : "Use this to open the website, pause it with a public notice, suspend it, or enable maintenance mode with password access from one save action." ?></p>
              <div class="developer-panel-status-note">
                <strong>Developer session note</strong>
                <span>Developer sessions bypass maintenance mode so you can keep managing the project. Use the public-view test or a signed-out browser to check what visitors see.</span>
              </div>
              <form id="service-control-public-view-test" class="d-flex flex-wrap gap-md" action="<?= h((string) ($developerLinks["service_control_public_view_test"] ?? route("developer.settings.service_control.public_view_test"))) ?>" method="post">
                <?= csrf_field() ?>
                <button class="btn btn-outline btn-sm" type="submit">Test public view</button>
              </form>
              <?php if ($serviceControlPublicTest !== []): ?>
              <div class="developer-dashboard-glance-table" aria-label="Latest public-view test result">
                <div class="developer-dashboard-glance-row"><strong>Last test</strong><span><?= h((string) ($serviceControlPublicTest["generated_at"] ?? "")) ?></span></div>
                <?php foreach ((array) ($serviceControlPublicTest["checks"] ?? []) as $check): ?>
                <div class="developer-dashboard-glance-row"><strong><?= h((string) ($check["label"] ?? "Check")) ?></strong><span><?= h((string) ($check["value"] ?? "")) ?>. <?= h((string) ($check["detail"] ?? "")) ?></span></div>
                <?php endforeach; ?>
              </div>
              <?php endif; ?>
              <form class="form stack gap-md" action="<?= h(route("developer.settings.service_control")) ?>" method="post" novalidate>
                <?= csrf_field() ?>
                <div class="form-group">
                  <label class="label" for="developer-control-status">Public-service scenario</label>
                  <select class="select" id="developer-control-status" name="developer_control_status">
                    <?php foreach ($serviceControlScenarios as $scenarioValue => $scenario): ?>
                    <option value="<?= h((string) $scenarioValue) ?>" <?= $serviceControlScenario === $scenarioValue ? "selected" : "" ?> data-developer-service-fields="<?= h((string) ($scenario["fields"] ?? "notice")) ?>" data-developer-service-message-label="<?= h((string) ($scenario["message_label"] ?? "Public message override")) ?>" data-developer-service-message-placeholder="<?= h((string) ($scenario["message_placeholder"] ?? "Leave blank to use the selected scenario message.")) ?>" data-developer-service-default-message="<?= h((string) ($scenario["default_message"] ?? "")) ?>" data-developer-service-contact-heading="<?= h((string) ($scenario["contact_heading"] ?? "Support contact")) ?>" data-developer-service-contact-help="<?= h((string) ($scenario["contact_help"] ?? "Shown on the public service notice.")) ?>"><?= h((string) $scenario["label"]) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <p class="help-text">Current source: <?= h($serviceSourceLabel) ?>. Status: <?= h($serviceStatus) ?><?= $serviceReason !== "" ? ", reason: " . h($serviceReason) : "" ?>. Remote control is <?= $remoteEnabled ? "enabled" : "disabled" ?>.</p>
                </div>
                <div class="developer-service-scenario-grid">
                  <?php foreach ($serviceControlScenarios as $scenarioValue => $scenario): ?>
                  <span class="<?= $serviceControlScenario === $scenarioValue ? "is-active" : "" ?>" data-developer-service-scenario="<?= h((string) $scenarioValue) ?>" role="button" tabindex="0">
                    <strong><?= h((string) $scenario["label"]) ?></strong>
                    <small><?= h((string) $scenario["text"]) ?></small>
                  </span>
                  <?php endforeach; ?>
                </div>

                <div class="developer-service-maintenance-fields" data-developer-service-fields-panel="maintenance" <?= $serviceSelectedFields === "maintenance" ? "" : "hidden" ?>>
                  <div class="developer-panel-status-note">
                    <strong>Maintenance access</strong>
                    <span><?= $maintenanceConfigured ? "Leave the password fields blank to keep the current maintenance password." : "Set a password before enabling maintenance mode." ?></span>
                  </div>
                  <div class="form-group">
                    <label class="label" for="maintenance-access-password">Maintenance password</label>
                    <div class="password-field">
                      <input class="input" id="maintenance-access-password" name="maintenance_access_password" type="password" autocomplete="new-password" placeholder="<?= $maintenanceConfigured ? "Keep existing password" : "Create maintenance password" ?>">
                      <button class="password-toggle" type="button" data-fnlla-password-toggle data-fnlla-password-target="#maintenance-access-password" aria-label="Toggle password visibility">Show</button>
                    </div>
                    <p class="help-text">Required when no maintenance password exists yet. Minimum 8 characters.</p>
                  </div>
                  <div class="form-group">
                    <label class="label" for="maintenance-access-password-confirmation">Confirm maintenance password</label>
                    <div class="password-field">
                      <input class="input" id="maintenance-access-password-confirmation" name="maintenance_access_password_confirmation" type="password" autocomplete="new-password" placeholder="Repeat maintenance password">
                      <button class="password-toggle" type="button" data-fnlla-password-toggle data-fnlla-password-target="#maintenance-access-password-confirmation" aria-label="Toggle password visibility">Show</button>
                    </div>
                  </div>
                </div>
                <div class="developer-service-open-fields" data-developer-service-fields-panel="open" <?= $serviceSelectedFields === "open" ? "" : "hidden" ?>>
                  <div class="developer-panel-status-note">
                    <strong>Public service open</strong>
                    <span>No public service-control notice is shown. Saving this scenario clears the local service lock and turns maintenance access off.</span>
                  </div>
                </div>
                <div class="developer-service-notice-fields" data-developer-service-fields-panel="notice" <?= $serviceSelectedFields === "notice" ? "" : "hidden" ?>>
                  <div class="form-group">
                    <label class="label" for="developer-control-message"><span data-developer-service-message-label><?= h((string) ($serviceSelectedScenario["message_label"] ?? "Public message override")) ?></span> <span class="content-text">(optional)</span></label>
                    <textarea class="textarea" id="developer-control-message" name="developer_control_message" rows="3" placeholder="<?= h((string) ($serviceSelectedScenario["message_placeholder"] ?? "Leave blank to use the selected scenario message.")) ?>"><?= h((string) old("developer_control_message", (string) ($developerControl["message"] ?? ""))) ?></textarea>
                  </div>
                  <div class="developer-panel-status-note">
                    <strong data-developer-service-contact-heading><?= h((string) ($serviceSelectedScenario["contact_heading"] ?? "Support contact")) ?></strong>
                    <span data-developer-service-contact-help><?= h((string) ($serviceSelectedScenario["contact_help"] ?? "Shown on the public service notice.")) ?></span>
                  </div>
                  <div class="form-group">
                    <label class="label" for="developer-control-contact-url">Support website <span class="content-text">(optional)</span></label>
                    <input class="input" id="developer-control-contact-url" name="developer_control_contact_url" type="url" value="<?= h((string) old("developer_control_contact_url", $serviceContactUrl)) ?>" placeholder="https://example.com/support">
                  </div>
                  <div class="form-group">
                    <label class="label" for="developer-control-contact-phone">Support phone <span class="content-text">(optional)</span></label>
                    <input class="input" id="developer-control-contact-phone" name="developer_control_contact_phone" type="tel" value="<?= h((string) old("developer_control_contact_phone", $serviceContactPhone)) ?>" placeholder="+44 20 0000 0000">
                  </div>
                  <div class="form-group">
                    <label class="label" for="developer-control-contact">Support email</label>
                    <input class="input" id="developer-control-contact" name="developer_control_contact" type="email" value="<?= h((string) old("developer_control_contact", (string) ($developerControl["contact"] ?? config("developer_control.disabled_contact", "")))) ?>" placeholder="developer@example.com">
                  </div>
                </div>
                <button class="btn btn-primary" type="submit">Save service control</button>
              </form>
            </article>
          </div>
        </section>
        <?php endif; ?>

<?php require __DIR__ . "/panel-footer.php"; ?>
