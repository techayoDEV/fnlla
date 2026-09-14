<?php

declare(strict_types=1);

$developerPanelTitle = "Project identity: Access & preview";
$developerPanelLead = "Control private client preview and emergency public-service state from one place.";
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
require __DIR__ . "/panel-header.php";
?>

        <section class="developer-dashboard-section" id="developer-project-settings" aria-label="Project settings">
          <div class="developer-dashboard-section-head">
            <h2 class="dashboard-section-title">Preview and service state</h2>
            <span class="developer-dashboard-refresh">Client preview and public-service status</span>
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

          <div class="developer-panel-form-grid">

            <article class="developer-panel-fieldset-card">
              <p class="feature-kicker">Service control</p>
              <h2 class="content-title">Save service control</h2>
              <p class="content-text"><?= $serviceRemoteSuspended
                  ? "A remote provider suspension is active. Local controls can clear only the project-owned lock; the provider state must be changed in the external control plane."
                  : "Use this to open the website, pause it with a public notice, suspend it, or enable maintenance mode with password access from one save action." ?></p>
              <div class="developer-panel-status-note">
                <strong>Developer session note</strong>
                <span>Developer sessions bypass maintenance mode so you can keep managing the project. Use a signed-out browser to check what visitors see.</span>
              </div>
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

<?php require __DIR__ . "/panel-footer.php"; ?>
