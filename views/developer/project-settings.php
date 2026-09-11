<?php

declare(strict_types=1);

$developerPanelTitle = "Project Settings";
$developerPanelLead = "Client preview and maintenance controls for this environment.";
$developerControl ??= [
    "disabled" => false,
    "local_disabled" => false,
    "remote_disabled" => false,
    "message" => (string) config("developer_control.disabled_message", ""),
    "contact" => (string) config("developer_control.disabled_contact", ""),
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
$serviceContactPhone = (string) ($developerControl["contact_phone"] ?? config("developer_control.disabled_contact_phone", ""));
$serviceRemoteSuspended = $serviceDisabled && ($developerControl["source"] ?? "") === "remote" && $serviceStatus === "suspended";
$serviceLocalScenario = "open";
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
$serviceControlScenarios = [
    "open" => ["label" => "Open public service", "text" => "Public routes are available."],
    "disabled" => ["label" => "Paused by developer", "text" => "Developer-owned pause with a public support message."],
    "maintenance" => ["label" => "Maintenance window", "text" => "Planned service pause for maintenance work."],
    "suspended_billing" => ["label" => "Suspended - payment overdue", "text" => "Payment or billing suspension notice."],
    "suspended_contract" => ["label" => "Suspended - contract issue", "text" => "Contract or service agreement suspension notice."],
    "security_review" => ["label" => "Paused - security review", "text" => "Security-review pause while access is checked."],
];
$serviceStatusTone = $serviceStatus === "suspended" ? "suspended" : ($serviceDisabled ? "stopped" : "open");
$previewStatusTone = $maintenanceEnabled ? "locked" : "open";
require __DIR__ . "/panel-header.php";
?>

        <section class="developer-dashboard-section" id="developer-project-settings" aria-label="Project settings">
          <div class="developer-panel-intro">
            <div class="developer-panel-intro-copy">
              <p class="feature-kicker">Access and preview</p>
              <h2 class="developer-dashboard-section-title">Control private client preview and emergency public-service state from one place.</h2>
              <p class="content-text mb-0"><?= $maintenanceEnabled
                  ? "Maintenance mode is currently active. Save settings here to keep the lock enabled or turn it off while preserving a prepared password."
                  : "Maintenance mode is currently off. Save a password here when you want to prepare a private preview lock, then choose whether it should stay off or be enabled immediately." ?></p>
            </div>
            <div class="developer-panel-intro-actions">
              <a class="btn btn-outline btn-sm" href="<?= h((string) ($developerLinks["integrations"] ?? route("developer.panel.integrations"))) ?>">Remote control</a>
              <a class="btn btn-outline btn-sm" href="<?= h((string) ($developerLinks["overview"] ?? route("developer.panel"))) ?>">Dashboard</a>
            </div>
          </div>

          <div class="developer-dashboard-status-grid">
            <article class="developer-dashboard-status-card">
              <div class="developer-dashboard-card-head"><strong>Preview mode</strong><span class="developer-dashboard-ok is-<?= h($previewStatusTone) ?>"><?= $maintenanceEnabled ? "LOCKED" : "OPEN" ?></span></div>
              <h3><?= $maintenanceEnabled ? "Password required" : "Public routes open" ?></h3>
              <p><?= $maintenanceConfigured ? "A preview password is configured." : "No preview password is configured yet." ?></p>
            </article>
            <article class="developer-dashboard-status-card">
              <div class="developer-dashboard-card-head"><strong>Password state</strong><span class="developer-dashboard-ok <?= $maintenanceConfigured ? "is-ready" : "is-review" ?>"><?= $maintenanceConfigured ? "READY" : "MISSING" ?></span></div>
              <h3><?= $maintenanceConfigured ? "Prepared" : "Not prepared" ?></h3>
              <p>Rotate it here before sharing a private build.</p>
            </article>
            <article class="developer-dashboard-status-card">
              <div class="developer-dashboard-card-head"><strong>Service control</strong><span class="developer-dashboard-ok is-<?= h($serviceStatusTone) ?>"><?= $serviceStatus === "suspended" ? "SUSPENDED" : ($serviceDisabled ? "STOPPED" : "OPEN") ?></span></div>
              <h3><?= $serviceRemoteSuspended ? "Suspended by service provider" : ($serviceDisabled ? "Public service disabled" : "Public service available") ?></h3>
              <p>Source: <?= h((string) ($developerControl["source"] ?? "none")) ?><?= $serviceReason !== "" ? ". Reason: " . h($serviceReason) : "" ?><?= $serviceProvider !== "" ? ". Provider: " . h($serviceProvider) : "" ?>.</p>
            </article>
            <article class="developer-dashboard-status-card">
              <div class="developer-dashboard-card-head"><strong>Remote contract</strong><span class="developer-dashboard-ok <?= $remoteEnabled ? "is-ready" : "is-neutral" ?>"><?= $remoteEnabled ? "ON" : "OFF" ?></span></div>
              <h3><?= $remoteEnabled ? "Remote control enabled" : "Local control only" ?></h3>
              <p>Configure the adapter from Integrations.</p>
            </article>
          </div>

          <div class="developer-panel-form-grid">
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

            <article class="developer-panel-fieldset-card">
              <p class="feature-kicker">Service control</p>
              <h2 class="content-title">Save service control</h2>
              <p class="content-text"><?= $serviceRemoteSuspended
                  ? "A remote provider suspension is active. Local controls can clear only the project-owned lock; the provider state must be changed in the external control plane."
                  : "Use this when the website should be stopped immediately with a developer-owned message while the private developer panel remains available." ?></p>
              <form class="form stack gap-md" action="<?= h(route("developer.settings.service_control")) ?>" method="post" novalidate>
                <?= csrf_field() ?>
                <div class="form-group">
                  <label class="label" for="developer-control-status">Public-service scenario</label>
                  <select class="select" id="developer-control-status" name="developer_control_status">
                    <?php foreach ($serviceControlScenarios as $scenarioValue => $scenario): ?>
                    <option value="<?= h((string) $scenarioValue) ?>" <?= $serviceControlScenario === $scenarioValue ? "selected" : "" ?>><?= h((string) $scenario["label"]) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <p class="help-text">Current source: <?= h((string) ($developerControl["source"] ?? "none")) ?>. Status: <?= h($serviceStatus) ?><?= $serviceReason !== "" ? ", reason: " . h($serviceReason) : "" ?>. Remote control is <?= $remoteEnabled ? "enabled" : "disabled" ?>.</p>
                </div>
                <div class="developer-service-scenario-grid">
                  <?php foreach ($serviceControlScenarios as $scenarioValue => $scenario): ?>
                  <span class="<?= $serviceControlScenario === $scenarioValue ? "is-active" : "" ?>" data-developer-service-scenario="<?= h((string) $scenarioValue) ?>">
                    <strong><?= h((string) $scenario["label"]) ?></strong>
                    <small><?= h((string) $scenario["text"]) ?></small>
                  </span>
                  <?php endforeach; ?>
                </div>
                <div class="form-group">
                  <label class="label" for="developer-control-message">Public message override <span class="content-text">(optional)</span></label>
                  <textarea class="textarea" id="developer-control-message" name="developer_control_message" rows="3" placeholder="Leave blank to use the selected scenario message."><?= h((string) old("developer_control_message", (string) ($developerControl["message"] ?? ""))) ?></textarea>
                </div>
                <div class="form-group">
                  <label class="label" for="developer-control-contact">Developer contact email</label>
                  <input class="input" id="developer-control-contact" name="developer_control_contact" type="email" value="<?= h((string) old("developer_control_contact", (string) ($developerControl["contact"] ?? config("developer_control.disabled_contact", "")))) ?>" placeholder="developer@example.com">
                </div>
                <div class="form-group">
                  <label class="label" for="developer-control-contact-phone">Developer contact phone <span class="content-text">(optional)</span></label>
                  <input class="input" id="developer-control-contact-phone" name="developer_control_contact_phone" type="tel" value="<?= h((string) old("developer_control_contact_phone", $serviceContactPhone)) ?>" placeholder="+44 20 0000 0000">
                </div>
                <button class="btn btn-primary" type="submit">Save service control</button>
              </form>
            </article>
          </div>
        </section>

<?php require __DIR__ . "/panel-footer.php"; ?>
